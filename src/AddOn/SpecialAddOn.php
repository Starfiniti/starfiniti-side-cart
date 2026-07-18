<?php
/**
 * Owned cart-only special add-on offer.
 *
 * @package StarfinitiCart
 */

namespace Starfiniti\Cart\AddOn;

use DomainException;
use Starfiniti\Cart\Cart\ProductSnapshot;
use Starfiniti\Cart\Rewards\RewardEngine;
use Starfiniti\Cart\Settings;
use Starfiniti\Cart\Support\Logger;
use WC_Cart;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;
use WC_Product_Variable;
use WC_Product_Variation;

/**
 * Reconciles one configured optional product with a normal WooCommerce cart.
 */
final class SpecialAddOn {

	/** Cart-item marker used only for the owned special add-on. */
	public const CART_ITEM_KEY = 'sfcart_special_addon';

	/** Session key remembering an explicit shopper opt-out. */
	private const REMOVED_SESSION_KEY = 'sfcart_special_addon_removed';

	/**
	 * Prevent nested WooCommerce cart hooks during owned mutations.
	 *
	 * @var bool
	 */
	private static bool $mutating = false;

	/** Register cart lifecycle and order attribution hooks. */
	public static function register(): void {
		add_action( 'woocommerce_add_to_cart', array( self::class, 'maybe_preselect' ), 60, 6 );
		add_action( 'woocommerce_cart_loaded_from_session', array( self::class, 'reconcile' ), 60 );
		add_action( 'woocommerce_remove_cart_item', array( self::class, 'record_removal' ), 10, 2 );
		add_action( 'woocommerce_cart_item_removed', array( self::class, 'remove_orphan' ), 60, 2 );
		add_action( 'woocommerce_cart_emptied', array( self::class, 'clear_session' ) );
		add_action( 'woocommerce_checkout_create_order_line_item', array( self::class, 'add_order_item_metadata' ), 10, 4 );
	}

	/**
	 * Return the public add-on offer and current selection state.
	 *
	 * @param WC_Cart|null              $cart     Active cart.
	 * @param array<string, mixed>|null $settings Normalized settings override.
	 * @return array<string, mixed>
	 */
	public static function snapshot( ?WC_Cart $cart = null, ?array $settings = null ): array {
		$settings = is_array( $settings ) ? Settings::sanitize( $settings ) : Settings::get();
		$options  = is_array( $settings['special_addon'] ?? null ) ? $settings['special_addon'] : Settings::defaults()['special_addon'];
		$result   = array(
			'enabled'           => false,
			'selected'          => false,
			'cart_key'          => '',
			'selection_type'    => (string) $options['selection_type'],
			'heading'           => (string) $options['heading'],
			'description'       => (string) $options['description'],
			'image_enabled'     => (bool) $options['image_enabled'],
			'image_source'      => (string) $options['image_source'],
			'image_size'        => (int) $options['image_size'],
			'background'        => (string) $options['background'],
			'accent'            => (string) $options['accent'],
			'heading_color'     => (string) $options['heading_color'],
			'description_color' => (string) $options['description_color'],
			'product'           => null,
		);

		$cart = $cart ?? WC()->cart;
		if ( ! $cart instanceof WC_Cart || ! self::offer_is_eligible( $cart, $options ) ) {
			return $result;
		}

		$product = ProductSnapshot::resolve( (int) $options['product_id'] );
		if ( ! $product instanceof WC_Product ) {
			return $result;
		}
		$snapshot = ProductSnapshot::serialize( $product );
		if ( null === $snapshot ) {
			return $result;
		}

		$selected = self::selected_item( $cart );
		if ( null !== $selected ) {
			$selected_product = $selected['item']['data'] ?? null;
			if ( $selected_product instanceof WC_Product ) {
				$snapshot['price'] = self::plain_text( $selected_product->get_price_html() );
				$snapshot['image'] = self::image_url( $selected_product );
			}
			$snapshot['selected_variation_id'] = (int) ( $selected['item']['variation_id'] ?? 0 );
			$snapshot['selected_attributes']   = is_array( $selected['item']['variation'] ?? null ) ? $selected['item']['variation'] : array();
		}

		if ( (bool) $options['image_enabled'] && 'custom' === $options['image_source'] && '' !== (string) $options['image_url'] ) {
			$snapshot['image'] = (string) $options['image_url'];
		}

		$result['enabled']  = true;
		$result['selected'] = null !== $selected;
		$result['cart_key'] = null !== $selected ? $selected['key'] : '';
		$result['product']  = $snapshot;

		return $result;
	}

	/**
	 * Add, change, or remove the configured add-on selection.
	 *
	 * @param WC_Cart               $cart         Active cart.
	 * @param bool                  $selected     Requested selection state.
	 * @param int                   $variation_id Requested child product.
	 * @param array<string, string> $attributes   Requested variation attributes.
	 * @throws DomainException When the offer or variation is unavailable.
	 */
	public static function set_selected( WC_Cart $cart, bool $selected, int $variation_id = 0, array $attributes = array() ): void {
		$settings = Settings::get();
		$options  = $settings['special_addon'];
		$product  = ProductSnapshot::resolve( (int) $options['product_id'] );

		if ( ! $selected ) {
			self::session_set( self::REMOVED_SESSION_KEY, (int) $options['product_id'] );
			self::remove_all( $cart );
			return;
		}

		if ( ! self::offer_is_eligible( $cart, $options ) || ! $product instanceof WC_Product ) {
			throw new DomainException( esc_html__( 'That special add-on is no longer available.', 'starfiniti-cart' ) );
		}

		$selection = self::selection( $product, $variation_id, $attributes );
		$current   = self::selected_item( $cart );
		if ( null !== $current
			&& (int) ( $current['item']['variation_id'] ?? 0 ) === $selection['variation_id']
			&& (array) ( $current['item']['variation'] ?? array() ) === $selection['attributes']
		) {
			self::session_set( self::REMOVED_SESSION_KEY, 0 );
			return;
		}

		self::remove_all( $cart );
		self::add_selection( $cart, $selection );
		self::session_set( self::REMOVED_SESSION_KEY, 0 );
	}

	/**
	 * Reconcile configured ownership, duplicates, and optional preselection.
	 *
	 * @param WC_Cart $cart Active cart.
	 */
	public static function reconcile( WC_Cart $cart ): void {
		if ( self::$mutating ) {
			return;
		}

		$settings = Settings::get();
		$options  = $settings['special_addon'];
		$product  = ProductSnapshot::resolve( (int) $options['product_id'] );
		$valid    = $product instanceof WC_Product && self::offer_is_eligible( $cart, $options );
		$found    = false;

		self::$mutating = true;
		try {
			foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
				if ( ! self::is_addon( $cart_item ) ) {
					continue;
				}
				$marker_matches = $product instanceof WC_Product
					&& (int) ( $cart_item[ self::CART_ITEM_KEY ]['product_id'] ?? 0 ) === $product->get_id();
				if ( ! $valid || ! $marker_matches || $found ) {
					$cart->remove_cart_item( (string) $cart_item_key );
					continue;
				}
				$found = true;
			}

			$removed_product = (int) self::session_get( self::REMOVED_SESSION_KEY, 0 );
			if ( $valid && ! $found && (bool) $options['preselected'] && $removed_product !== (int) $options['product_id'] && $product instanceof WC_Product ) {
				$selection = ProductSnapshot::first_selection( $product );
				if ( null !== $selection ) {
					try {
						self::add_selection( $cart, $selection );
					} catch ( DomainException $error ) {
						Logger::exception( 'Special add-on preselection failed.', $error );
					}
				}
			}
		} finally {
			self::$mutating = false;
		}
	}

	/**
	 * Attempt preselection after a normal WooCommerce add-to-cart event.
	 *
	 * @param string                $cart_item_key Cart item key.
	 * @param int                   $product_id     Added product identifier.
	 * @param int                   $quantity       Added quantity.
	 * @param int                   $variation_id   Added variation identifier.
	 * @param array<string, string> $variation      Added variation attributes.
	 * @param array<string, mixed>  $cart_item_data Added cart item data.
	 */
	public static function maybe_preselect( string $cart_item_key, int $product_id, int $quantity, int $variation_id, array $variation, array $cart_item_data ): void {
		unset( $cart_item_key, $product_id, $quantity, $variation_id, $variation );
		if ( self::$mutating || self::is_addon( $cart_item_data ) || ! WC()->cart instanceof WC_Cart ) {
			return;
		}
		self::reconcile( WC()->cart );
	}

	/**
	 * Remember removal through any normal WooCommerce cart UI.
	 *
	 * @param string  $cart_item_key Removed cart item key.
	 * @param WC_Cart $cart          Active cart.
	 */
	public static function record_removal( string $cart_item_key, WC_Cart $cart ): void {
		if ( self::$mutating ) {
			return;
		}
		$cart_item = $cart->get_cart_item( $cart_item_key );
		if ( self::is_addon( $cart_item ) ) {
			self::session_set( self::REMOVED_SESSION_KEY, (int) $cart_item[ self::CART_ITEM_KEY ]['product_id'] );
		}
	}

	/**
	 * Remove an add-on when its last normal companion line is removed.
	 *
	 * @param string  $cart_item_key Removed cart item key.
	 * @param WC_Cart $cart          Active cart.
	 */
	public static function remove_orphan( string $cart_item_key, WC_Cart $cart ): void {
		unset( $cart_item_key );
		if ( self::$mutating || self::has_regular_item( $cart ) ) {
			return;
		}
		self::remove_all( $cart );
		self::clear_session();
	}

	/** Clear shopper opt-out state when the cart lifecycle ends. */
	public static function clear_session(): void {
		self::session_set( self::REMOVED_SESSION_KEY, 0 );
	}

	/**
	 * Copy hidden ownership metadata to the resulting WooCommerce order line.
	 *
	 * @param WC_Order_Item_Product $item          Order line item.
	 * @param string                $cart_item_key Source cart item key.
	 * @param array<string, mixed>  $values        Source cart item values.
	 * @param WC_Order              $order         Destination order.
	 */
	public static function add_order_item_metadata( WC_Order_Item_Product $item, string $cart_item_key, array $values, WC_Order $order ): void {
		unset( $cart_item_key, $order );
		if ( ! self::is_addon( $values ) ) {
			return;
		}
		$item->add_meta_data( '_sfcart_special_addon', 'yes', true );
		$item->add_meta_data( '_sfcart_special_addon_product_id', (string) (int) $values[ self::CART_ITEM_KEY ]['product_id'], true );
	}

	/**
	 * Determine whether a cart line belongs to the owned add-on offer.
	 *
	 * @param mixed $cart_item Candidate cart item.
	 */
	public static function is_addon( mixed $cart_item ): bool {
		return is_array( $cart_item )
			&& is_array( $cart_item[ self::CART_ITEM_KEY ] ?? null )
			&& (int) ( $cart_item[ self::CART_ITEM_KEY ]['product_id'] ?? 0 ) > 0;
	}

	/**
	 * Check settings, cart contents, and product availability for the offer.
	 *
	 * @param WC_Cart              $cart    Active cart.
	 * @param array<string, mixed> $options Normalized add-on settings.
	 */
	private static function offer_is_eligible( WC_Cart $cart, array $options ): bool {
		$product_id = (int) ( $options['product_id'] ?? 0 );
		$product    = ProductSnapshot::resolve( $product_id );
		return true === (bool) ( $options['enabled'] ?? false )
			&& $product instanceof WC_Product
			&& ProductSnapshot::is_supported( $product )
			&& self::has_regular_item( $cart )
			&& ! self::has_regular_product( $cart, $product->get_id() );
	}

	/**
	 * Resolve and validate a requested simple or variable product selection.
	 *
	 * @param WC_Product            $product      Configured product.
	 * @param int                   $variation_id Requested variation identifier.
	 * @param array<string, string> $attributes   Requested attributes.
	 * @return array{product_id: int, variation_id: int, attributes: array<string, string>}
	 * @throws DomainException When the requested selection cannot be purchased.
	 */
	private static function selection( WC_Product $product, int $variation_id, array $attributes ): array {
		if ( ! ProductSnapshot::is_supported( $product ) ) {
			throw new DomainException( esc_html__( 'That special add-on is unavailable.', 'starfiniti-cart' ) );
		}
		if ( ! $product instanceof WC_Product_Variable ) {
			return array(
				'product_id'   => $product->get_id(),
				'variation_id' => 0,
				'attributes'   => array(),
			);
		}

		$variation = wc_get_product( $variation_id );
		if ( ! $variation instanceof WC_Product_Variation || $product->get_id() !== $variation->get_parent_id() || ! $variation->is_purchasable() || ! $variation->is_in_stock() ) {
			throw new DomainException( esc_html__( 'Choose an available add-on variation.', 'starfiniti-cart' ) );
		}

		foreach ( $product->get_variation_attributes() as $attribute_name => $options ) {
			$key      = 'attribute_' . sanitize_title( $attribute_name );
			$selected = $attributes[ $key ] ?? '';
			if ( '' === $selected || ! in_array( $selected, array_map( 'strval', $options ), true ) ) {
				throw new DomainException( esc_html__( 'Choose all add-on options before selecting it.', 'starfiniti-cart' ) );
			}
		}

		foreach ( $variation->get_variation_attributes() as $key => $expected ) {
			$selected = $attributes[ sanitize_key( $key ) ] ?? '';
			if ( '' === $selected || ( '' !== (string) $expected && (string) $expected !== $selected ) ) {
				throw new DomainException( esc_html__( 'Choose an available add-on variation.', 'starfiniti-cart' ) );
			}
		}

		return array(
			'product_id'   => $product->get_id(),
			'variation_id' => $variation->get_id(),
			'attributes'   => $attributes,
		);
	}

	/**
	 * Add one selected product with owned provenance.
	 *
	 * @param WC_Cart                                                                      $cart      Active cart.
	 * @param array{product_id: int, variation_id: int, attributes: array<string, string>} $selection Validated selection.
	 * @throws DomainException When WooCommerce rejects the cart addition.
	 */
	private static function add_selection( WC_Cart $cart, array $selection ): void {
		$cart_item_key = $cart->add_to_cart(
			$selection['product_id'],
			1,
			$selection['variation_id'],
			$selection['attributes'],
			array(
				self::CART_ITEM_KEY => array(
					'product_id'   => $selection['product_id'],
					'variation_id' => $selection['variation_id'],
				),
			)
		);
		if ( ! is_string( $cart_item_key ) || '' === $cart_item_key ) {
			throw new DomainException( esc_html__( 'That special add-on could not be added.', 'starfiniti-cart' ) );
		}
	}

	/**
	 * Remove every owned add-on line without recording a shopper removal twice.
	 *
	 * @param WC_Cart $cart Active cart.
	 */
	private static function remove_all( WC_Cart $cart ): void {
		$was_mutating   = self::$mutating;
		self::$mutating = true;
		try {
			foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
				if ( self::is_addon( $cart_item ) ) {
					$cart->remove_cart_item( (string) $cart_item_key );
				}
			}
		} finally {
			self::$mutating = $was_mutating;
		}
	}

	/**
	 * Return the selected add-on line, if present.
	 *
	 * @param WC_Cart $cart Active cart.
	 * @return array{key: string, item: array<string, mixed>}|null
	 */
	private static function selected_item( WC_Cart $cart ): ?array {
		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( self::is_addon( $cart_item ) ) {
				return array(
					'key'  => (string) $cart_item_key,
					'item' => $cart_item,
				);
			}
		}
		return null;
	}

	/**
	 * Report whether the cart contains at least one normal merchandise item.
	 *
	 * @param WC_Cart $cart Active cart.
	 */
	private static function has_regular_item( WC_Cart $cart ): bool {
		foreach ( $cart->get_cart() as $cart_item ) {
			if ( ! self::is_addon( $cart_item ) && ! RewardEngine::is_gift( $cart_item ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Avoid offering a duplicate when the product is normal cart merchandise.
	 *
	 * @param WC_Cart $cart       Active cart.
	 * @param int     $product_id Configured add-on product.
	 */
	private static function has_regular_product( WC_Cart $cart, int $product_id ): bool {
		foreach ( $cart->get_cart() as $cart_item ) {
			if ( ! self::is_addon( $cart_item ) && (int) ( $cart_item['product_id'] ?? 0 ) === $product_id ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Resolve a product image or the WooCommerce placeholder.
	 *
	 * @param WC_Product $product Product requiring an image.
	 */
	private static function image_url( WC_Product $product ): string {
		$image = wp_get_attachment_image_url( (int) $product->get_image_id(), 'woocommerce_thumbnail' );
		return is_string( $image ) ? $image : wc_placeholder_img_src( 'woocommerce_thumbnail' );
	}

	/**
	 * Convert price HTML to display text.
	 *
	 * @param string $value Formatted price.
	 */
	private static function plain_text( string $value ): string {
		return trim( html_entity_decode( wp_strip_all_tags( $value ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	/**
	 * Read an owned WooCommerce session value.
	 *
	 * @param string $key      Session key.
	 * @param mixed  $fallback Default value.
	 */
	private static function session_get( string $key, mixed $fallback ): mixed {
		return null !== WC()->session ? WC()->session->get( $key, $fallback ) : $fallback;
	}

	/**
	 * Write an owned WooCommerce session value.
	 *
	 * @param string $key   Session key.
	 * @param mixed  $value Session value.
	 */
	private static function session_set( string $key, mixed $value ): void {
		if ( null !== WC()->session ) {
			WC()->session->set( $key, $value );
		}
	}

	/** Prevent construction. */
	private function __construct() {
	}
}
