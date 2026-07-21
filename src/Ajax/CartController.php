<?php
/**
 * Public WooCommerce AJAX cart endpoints.
 *
 * @package StarfinitiCart
 */

namespace Starfiniti\Cart\Ajax;

use DomainException;
use Starfiniti\Cart\AddOn\SpecialAddOn;
use Starfiniti\Cart\Analytics\PublicEventPolicy;
use Starfiniti\Cart\Analytics\PublicRateLimiter;
use Starfiniti\Cart\Analytics\Recorder;
use Starfiniti\Cart\Cart\CartState;
use Starfiniti\Cart\Cart\Notices;
use Starfiniti\Cart\Cart\Quantity;
use Starfiniti\Cart\Cart\SessionToken;
use Starfiniti\Cart\Recommendations\Attribution;
use Starfiniti\Cart\Recommendations\RecommendationEngine;
use Starfiniti\Cart\Rewards\RewardEngine;
use Starfiniti\Cart\Support\Logger;
use Throwable;
use WC_Cart;
use WC_Product;

/**
 * Owns the drawer's cart state and mutation endpoints.
 */
final class CartController {

	/** WooCommerce session key for the public analytics quota. */
	private const ANALYTICS_QUOTA_KEY = 'sfcart_analytics_quota';

	/**
	 * Register public WooCommerce AJAX actions.
	 */
	public static function register(): void {
		$actions = array(
			'sfcart_state'                 => 'state',
			'sfcart_update_item'           => 'update_item',
			'sfcart_remove_item'           => 'remove_item',
			'sfcart_apply_coupon'          => 'apply_coupon',
			'sfcart_remove_coupon'         => 'remove_coupon',
			'sfcart_add_recommendation'    => 'add_recommendation',
			'sfcart_track_recommendations' => 'track_recommendations',
			'sfcart_track_event'           => 'track_event',
			'sfcart_set_special_addon'     => 'set_special_addon',
		);

		foreach ( $actions as $action => $method ) {
			add_action( 'wc_ajax_' . $action, array( self::class, $method ) );
		}
	}

	/**
	 * Return the current cart state.
	 */
	public static function state(): void {
		self::send( true );
	}

	/**
	 * Update a cart item's quantity while preserving its metadata.
	 */
	public static function update_item(): void {
		try {
			self::verify_nonce();
			wc_clear_notices();

			$cart     = self::cart();
			$cart_key = self::post_string( 'cart_key' );
			$raw      = self::post_string( 'quantity' );

			if ( '' === $cart_key || '' === $raw || ! is_numeric( $raw ) ) {
				self::fail( __( 'Choose a valid cart item quantity.', 'starfiniti-cart' ) );
			}

			$cart_item = $cart->get_cart_item( $cart_key );

			if ( empty( $cart_item ) ) {
				self::fail( __( 'That cart item could not be found.', 'starfiniti-cart' ), 404 );
			}

			$product = $cart_item['data'] ?? null;

			if ( ! $product instanceof WC_Product ) {
				self::fail( __( 'That product is unavailable.', 'starfiniti-cart' ), 404 );
			}

			if ( RewardEngine::is_gift( $cart_item ) || SpecialAddOn::is_addon( $cart_item ) ) {
				self::fail( __( 'This cart item has a fixed quantity.', 'starfiniti-cart' ) );
			}

			$parsed_quantity = filter_var( $raw, FILTER_VALIDATE_INT );

			if ( false === $parsed_quantity ) {
				self::fail( __( 'Choose a whole-number cart item quantity.', 'starfiniti-cart' ) );
			}

			$quantity = (int) wc_stock_amount( $parsed_quantity );

			if ( $quantity <= 0 ) {
				self::remove_cart_item( $cart, $cart_key, $product );
			}

			self::validate_quantity( $cart, $cart_key, $cart_item, $product, $quantity );

			if ( ! $cart->set_quantity( $cart_key, $quantity, true ) ) {
				self::fail( __( 'The cart item quantity could not be updated.', 'starfiniti-cart' ) );
			}

			/**
			 * Fires after the owned side cart updates an item quantity.
			 *
			 * @param string $cart_key Cart item key.
			 * @param int    $quantity New cart item quantity.
			 */
			do_action( 'sfcart_cart_item_updated', $cart_key, $quantity );

			/* translators: %s: product name. */
			wc_add_notice( sprintf( __( '“%s” was updated.', 'starfiniti-cart' ), $product->get_name() ), 'success' );
			self::send( true );
		} catch ( Throwable $error ) {
			self::handle_exception( 'Cart item update failed.', $error );
		}
	}

	/** Add, change, or remove the server-approved special add-on. */
	public static function set_special_addon(): void {
		try {
			self::verify_nonce();
			wc_clear_notices();

			$selected     = in_array( self::post_string( 'selected' ), array( '1', 'yes', 'true', 'on' ), true );
			$variation_id = absint( self::post_string( 'variation_id' ) );
			try {
				SpecialAddOn::set_selected( self::cart(), $selected, $variation_id, self::posted_attributes() );
			} catch ( DomainException $error ) {
				self::fail( $error->getMessage() );
			}

			wc_add_notice(
				$selected
					? __( 'Special add-on selected.', 'starfiniti-cart' )
					: __( 'Special add-on removed.', 'starfiniti-cart' ),
				'success'
			);
			self::send( true );
		} catch ( Throwable $error ) {
			self::handle_exception( 'Special add-on update failed.', $error );
		}
	}

	/**
	 * Remove a cart item.
	 */
	public static function remove_item(): void {
		try {
			self::verify_nonce();
			wc_clear_notices();

			$cart      = self::cart();
			$cart_key  = self::post_string( 'cart_key' );
			$cart_item = $cart->get_cart_item( $cart_key );
			$product   = $cart_item['data'] ?? null;

			if ( '' === $cart_key || empty( $cart_item ) || ! $product instanceof WC_Product ) {
				self::fail( __( 'That cart item could not be found.', 'starfiniti-cart' ), 404 );
			}

			if ( RewardEngine::is_gift( $cart_item ) && ! RewardEngine::gift_removal_allowed() ) {
				self::fail( __( 'This reward gift cannot be removed.', 'starfiniti-cart' ) );
			}

			self::remove_cart_item( $cart, $cart_key, $product );
		} catch ( Throwable $error ) {
			self::handle_exception( 'Cart item removal failed.', $error );
		}
	}

	/**
	 * Apply a WooCommerce coupon.
	 */
	public static function apply_coupon(): void {
		try {
			self::verify_nonce();
			wc_clear_notices();

			if ( ! wc_coupons_enabled() ) {
				self::fail( __( 'Coupons are not enabled for this store.', 'starfiniti-cart' ) );
			}

			$code = wc_format_coupon_code( self::post_string( 'coupon_code' ) );

			if ( '' === $code ) {
				self::fail( __( 'Enter a coupon code.', 'starfiniti-cart' ) );
			}

			if ( ! self::cart()->apply_coupon( $code ) ) {
				if ( empty( wc_get_notices( 'error' ) ) ) {
					self::fail( __( 'That coupon could not be applied.', 'starfiniti-cart' ) );
				}

				self::send( false, 400 );
			}

			self::send( true );
		} catch ( Throwable $error ) {
			self::handle_exception( 'Coupon application failed.', $error );
		}
	}

	/**
	 * Remove an applied WooCommerce coupon.
	 */
	public static function remove_coupon(): void {
		try {
			self::verify_nonce();
			wc_clear_notices();

			$code = wc_format_coupon_code( self::post_string( 'coupon_code' ) );

			if ( '' === $code || ! self::cart()->remove_coupon( $code ) ) {
				self::fail( __( 'That coupon is not applied to the cart.', 'starfiniti-cart' ), 404 );
			}

			wc_add_notice( __( 'Coupon removed.', 'starfiniti-cart' ), 'success' );
			self::send( true );
		} catch ( Throwable $error ) {
			self::handle_exception( 'Coupon removal failed.', $error );
		}
	}

	/**
	 * Add a server-approved simple or variable recommendation to the cart.
	 */
	public static function add_recommendation(): void {
		try {
			self::verify_nonce();
			wc_clear_notices();

			$cart           = self::cart();
			$product_id     = absint( self::post_string( 'product_id' ) );
			$variation_id   = absint( self::post_string( 'variation_id' ) );
			$recommendation = self::find_recommendation( $cart, $product_id );

			if ( null === $recommendation ) {
				self::fail( __( 'That recommendation is no longer available.', 'starfiniti-cart' ), 404 );
			}

			$product    = wc_get_product( $product_id );
			$attributes = array();
			if ( ! $product instanceof WC_Product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
				self::fail( __( 'That product is unavailable.', 'starfiniti-cart' ), 404 );
			}

			if ( $product->is_type( 'variable' ) ) {
				$attributes = self::posted_attributes();
				self::validate_variation( $product_id, $variation_id, $attributes );
			} else {
				$variation_id = 0;
			}

			$attribution   = array(
				'product_id'        => $product_id,
				'source'            => sanitize_key( (string) $recommendation['source'] ),
				'source_product_id' => absint( $recommendation['source_product_id'] ),
			);
			$cart_item_key = $cart->add_to_cart(
				$product_id,
				1,
				$variation_id,
				$attributes,
				array( Attribution::CART_ITEM_KEY => $attribution )
			);

			if ( ! is_string( $cart_item_key ) || '' === $cart_item_key ) {
				if ( empty( wc_get_notices( 'error' ) ) ) {
					self::fail( __( 'That product could not be added to your cart.', 'starfiniti-cart' ) );
				}
				self::send( false, 400 );
			}

			/**
			 * Fires after an owned recommendation is accepted into the cart.
			 *
			 * @param array<string, mixed> $attribution Normalized attribution data.
			 * @param string               $cart_item_key Created cart item key.
			 */
			do_action( 'sfcart_recommendation_accepted', $attribution, $cart_item_key );

			/* translators: %s: product name. */
			wc_add_notice( sprintf( __( '“%s” was added to your cart.', 'starfiniti-cart' ), $product->get_name() ), 'success' );
			self::send( true );
		} catch ( Throwable $error ) {
			self::handle_exception( 'Recommendation add failed.', $error );
		}
	}

	/**
	 * Record visible, server-approved recommendations once per cart session.
	 */
	public static function track_recommendations(): void {
		try {
			self::verify_nonce();
			if ( ! self::consume_analytics_quota() ) {
				self::fail_tracking( __( 'Too many cart events were received. Try again later.', 'starfiniti-cart' ), 429 );
			}
			$requested = array_values( array_unique( array_filter( array_map( 'absint', explode( ',', self::post_string( 'product_ids' ) ) ) ) ) );
			$snapshot  = RecommendationEngine::snapshot( self::cart() );
			Attribution::record_impressions( $snapshot['items'], $requested );
			self::send( true );
		} catch ( Throwable $error ) {
			self::handle_exception( 'Recommendation impression tracking failed.', $error );
		}
	}

	/** Record one bounded, anonymous side-cart interaction event. */
	public static function track_event(): void {
		try {
			self::verify_nonce();
			$cart  = self::cart();
			$event = PublicEventPolicy::normalize(
				self::post_string( 'event_type' ),
				self::post_string( 'event_action' ),
				self::post_string( 'outcome' ),
				$cart->is_empty(),
				$cart->get_cart_contents_count(),
				time()
			);
			if ( null === $event ) {
				self::fail_tracking( __( 'That analytics event is not supported.', 'starfiniti-cart' ) );
			}
			if ( ! self::consume_analytics_quota() ) {
				self::fail_tracking( __( 'Too many cart events were received. Try again later.', 'starfiniti-cart' ), 429 );
			}
			Recorder::record_cart_event(
				$event['type'],
				$event['event_id'],
				$event['status'],
				$event['metadata']
			);

			wp_send_json_success(
				array(
					'nonce' => wp_create_nonce( 'sfcart_cart' ),
					'token' => SessionToken::current(),
				)
			);
		} catch ( Throwable $error ) {
			Logger::exception( 'Cart analytics event failed.', $error );
			self::fail_tracking( __( 'The cart event could not be recorded.', 'starfiniti-cart' ), 500 );
		}
	}

	/** Consume one server-owned public analytics quota slot. */
	private static function consume_analytics_quota(): bool {
		$session = WC()->session;
		if ( ! $session ) {
			return false;
		}

		$quota = PublicEventPolicy::consume_quota( $session->get( self::ANALYTICS_QUOTA_KEY, array() ), time() );
		if ( ! $quota['allowed'] ) {
			return false;
		}

		$session->set( self::ANALYTICS_QUOTA_KEY, $quota['state'] );
		return PublicRateLimiter::consume();
	}

	/**
	 * Find one recommendation in the current server-resolved drawer state.
	 *
	 * @param WC_Cart $cart Active cart.
	 * @param int     $product_id Requested product identifier.
	 * @return array<string, mixed>|null
	 */
	private static function find_recommendation( WC_Cart $cart, int $product_id ): ?array {
		$snapshot = RecommendationEngine::snapshot( $cart );
		foreach ( $snapshot['items'] as $recommendation ) {
			if ( absint( $recommendation['id'] ?? 0 ) === $product_id ) {
				return $recommendation;
			}
		}

		return null;
	}

	/**
	 * Read a JSON object of variation attributes from the request.
	 *
	 * @return array<string, string>
	 */
	private static function posted_attributes(): array {
		$decoded = json_decode( self::post_string( 'attributes' ), true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}

		$attributes = array();
		foreach ( $decoded as $key => $value ) {
			if ( is_string( $key ) && is_scalar( $value ) ) {
				$attributes[ sanitize_key( $key ) ] = sanitize_text_field( (string) $value );
			}
		}

		return $attributes;
	}

	/**
	 * Verify that a variation belongs to the recommendation and matches selections.
	 *
	 * @param int                   $product_id Parent product identifier.
	 * @param int                   $variation_id Variation identifier.
	 * @param array<string, string> $attributes Selected attributes.
	 */
	private static function validate_variation( int $product_id, int $variation_id, array $attributes ): void {
		$variation = wc_get_product( $variation_id );
		if ( ! $variation instanceof \WC_Product_Variation || $product_id !== $variation->get_parent_id() || ! $variation->is_purchasable() || ! $variation->is_in_stock() ) {
			self::fail( __( 'Choose an available product variation.', 'starfiniti-cart' ) );
		}

		foreach ( $variation->get_variation_attributes() as $key => $expected ) {
			$selected = $attributes[ sanitize_key( $key ) ] ?? '';
			if ( '' === $selected || ( '' !== $expected && (string) $expected !== $selected ) ) {
				self::fail( __( 'Choose options for this product before adding it.', 'starfiniti-cart' ) );
			}
		}
	}

	/**
	 * Validate quantity constraints and stock shared by variations.
	 *
	 * @param WC_Cart              $cart      Active WooCommerce cart.
	 * @param string               $cart_key  Cart item key.
	 * @param array<string, mixed> $cart_item Cart item data.
	 * @param WC_Product           $product   Cart item product.
	 * @param int                  $quantity  Requested quantity.
	 */
	private static function validate_quantity(
		WC_Cart $cart,
		string $cart_key,
		array $cart_item,
		WC_Product $product,
		int $quantity
	): void {
		$limits = Quantity::limits( $product );

		if ( ! $limits['editable'] && 1 !== $quantity ) {
			self::fail( __( 'This product is sold individually.', 'woocommerce' ) );
		}

		if ( $quantity < $limits['min'] ) {
			/* translators: %s: minimum product quantity. */
			self::fail( sprintf( __( 'The minimum allowed quantity is %s.', 'starfiniti-cart' ), wc_format_stock_quantity_for_display( $limits['min'], $product ) ) );
		}

		if ( null !== $limits['max'] && $quantity > $limits['max'] ) {
			/* translators: %s: maximum product quantity. */
			self::fail( sprintf( __( 'The maximum allowed quantity is %s.', 'starfiniti-cart' ), wc_format_stock_quantity_for_display( $limits['max'], $product ) ) );
		}

		if ( ! Quantity::is_step_aligned( $quantity, $limits['min'], $limits['step'] ) ) {
			/* translators: %s: product quantity step. */
			self::fail( sprintf( __( 'Quantity must increase in steps of %s.', 'starfiniti-cart' ), wc_format_stock_quantity_for_display( $limits['step'], $product ) ) );
		}

		$valid = apply_filters( 'woocommerce_update_cart_validation', true, $cart_key, $cart_item, $quantity );

		if ( ! $valid ) {
			if ( empty( wc_get_notices( 'error' ) ) ) {
				wc_add_notice( __( 'That quantity is not available.', 'starfiniti-cart' ), 'error' );
			}

			self::send( false, 400 );
		}

		$managed_id      = $product->get_stock_managed_by_id();
		$quantities      = $cart->get_cart_item_quantities();
		$current         = (int) ( $cart_item['quantity'] ?? 0 );
		$required_stock  = (int) ( $quantities[ $managed_id ] ?? 0 ) - $current + $quantity;
		$awaiting_order  = WC()->session ? absint( WC()->session->get( 'order_awaiting_payment', 0 ) ) : 0;
		$held_stock      = (int) wc_get_held_stock_quantity( $product, $awaiting_order );
		$required_stock += $held_stock;

		if ( ! $product->backorders_allowed() && ! $product->has_enough_stock( $required_stock ) ) {
			$available = max( 0, (int) $product->get_stock_quantity() - $held_stock );
			/* translators: %s: available stock quantity. */
			self::fail( sprintf( __( 'Only %s is currently available.', 'starfiniti-cart' ), wc_format_stock_quantity_for_display( $available, $product ) ) );
		}
	}

	/**
	 * Remove an item and return a success response.
	 *
	 * @param WC_Cart    $cart     Active WooCommerce cart.
	 * @param string     $cart_key Cart item key.
	 * @param WC_Product $product  Cart item product.
	 */
	private static function remove_cart_item( WC_Cart $cart, string $cart_key, WC_Product $product ): never {
		if ( ! $cart->remove_cart_item( $cart_key ) ) {
			self::fail( __( 'The cart item could not be removed.', 'starfiniti-cart' ) );
		}

		/**
		 * Fires after the owned side cart removes an item.
		 *
		 * @param string $cart_key Cart item key.
		 */
		do_action( 'sfcart_cart_item_removed', $cart_key );

		/* translators: %s: product name. */
		wc_add_notice( sprintf( __( '“%s” was removed from your cart.', 'starfiniti-cart' ), $product->get_name() ), 'success' );
		self::send( true );
	}

	/**
	 * Return the active WooCommerce cart.
	 */
	private static function cart(): WC_Cart {
		$cart = WC()->cart;

		if ( ! $cart instanceof WC_Cart ) {
			self::fail( __( 'The WooCommerce cart is unavailable.', 'starfiniti-cart' ), 503 );
		}

		return $cart;
	}

	/**
	 * Verify the frontend nonce and WooCommerce-session request token.
	 */
	private static function verify_nonce(): void {
		$nonce = self::post_string( 'nonce' );
		$token = self::post_string( 'token' );

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'sfcart_cart' ) || ! SessionToken::verify( $token ) ) {
			self::fail( __( 'Your cart session expired. Refresh the page and try again.', 'starfiniti-cart' ), 403 );
		}
	}

	/**
	 * Read and sanitize a posted scalar value.
	 *
	 * @param string $key Posted field key.
	 */
	private static function post_string( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is performed by each mutation before reading its payload.
		$value = $_POST[ $key ] ?? '';

		if ( ! is_scalar( $value ) ) {
			return '';
		}

		return sanitize_text_field( wp_unslash( (string) $value ) );
	}

	/**
	 * Add an error notice and send a fresh state snapshot.
	 *
	 * @param string $message Error notice.
	 * @param int    $status  HTTP response status.
	 */
	private static function fail( string $message, int $status = 400 ): never {
		wc_add_notice( $message, 'error' );
		self::send( false, $status );
	}

	/**
	 * Send a compact tracking error without serializing the complete cart.
	 *
	 * @param string $message Error message.
	 * @param int    $status HTTP status.
	 */
	private static function fail_tracking( string $message, int $status = 400 ): never {
		wp_send_json_error(
			array(
				'message' => $message,
				'nonce'   => wp_create_nonce( 'sfcart_cart' ),
				'token'   => SessionToken::current(),
			),
			$status
		);
	}

	/**
	 * Send a success or error state response.
	 *
	 * @param bool $success Whether the operation succeeded.
	 * @param int  $status  HTTP response status.
	 */
	private static function send( bool $success, int $status = 200 ): never {
		try {
			$data = CartState::snapshot( Notices::collect() );
		} catch ( Throwable $error ) {
			Logger::exception( 'Cart state serialization failed.', $error );
			$data    = array(
				'notices' => array(
					array(
						'type'    => 'error',
						'message' => __( 'The cart could not be refreshed.', 'starfiniti-cart' ),
					),
				),
				'nonce'   => wp_create_nonce( 'sfcart_cart' ),
				'token'   => SessionToken::current(),
			);
			$success = false;
			$status  = 500;
		}

		if ( $success ) {
			wp_send_json_success( $data, $status );
		} else {
			wp_send_json_error( $data, $status );
		}
	}

	/**
	 * Log an unexpected mutation failure and return a generic error.
	 *
	 * @param string    $context Log context.
	 * @param Throwable $error   Unexpected error.
	 */
	private static function handle_exception( string $context, Throwable $error ): never {
		Logger::exception( $context, $error );
		wc_add_notice( __( 'The cart could not be updated. Please try again.', 'starfiniti-cart' ), 'error' );
		self::send( false, 500 );
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
