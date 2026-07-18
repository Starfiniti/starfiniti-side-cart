<?php
/**
 * Idempotent threshold rewards for the WooCommerce cart.
 *
 * @package StarfinitiCart
 */

namespace Starfiniti\Cart\Rewards;

use Starfiniti\Cart\Settings;
use WC_Cart;
use WC_Coupon;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;
use WC_Product_Variable;
use WC_Product_Variation;
use WC_Shipping_Rate;

/**
 * Reconciles coupons, free gifts, and a zero-cost shipping rate from owned settings.
 */
final class RewardEngine {

	/** Cart-item marker used only for owned free gifts. */
	public const GIFT_KEY = 'sfcart_reward_gift';

	/** Session key for coupons applied by this engine. */
	private const COUPON_SESSION_KEY = 'sfcart_reward_coupons';

	/** Session key for gifts explicitly removed by a shopper. */
	private const REMOVED_GIFT_SESSION_KEY = 'sfcart_reward_removed_gifts';

	/** Session key for the achieved free-shipping milestone. */
	private const SHIPPING_SESSION_KEY = 'sfcart_reward_free_shipping';

	/** Owned shipping method and rate identifier. */
	public const SHIPPING_RATE_ID = 'sfcart_reward_free_shipping';

	/**
	 * Prevent recursive total calculation while owned mutations settle.
	 *
	 * @var bool
	 */
	private static bool $reconciling = false;

	/**
	 * Distinguish engine cleanup from shopper gift removal.
	 *
	 * @var bool
	 */
	private static bool $internal_gift_removal = false;

	/** Register reward lifecycle hooks. */
	public static function register(): void {
		add_action( 'woocommerce_before_calculate_totals', array( self::class, 'normalize_gifts' ), 5 );
		add_action( 'woocommerce_after_calculate_totals', array( self::class, 'reconcile' ), 50 );
		add_filter( 'woocommerce_package_rates', array( self::class, 'shipping_rates' ), 50, 2 );
		add_filter( 'woocommerce_shipping_chosen_method', array( self::class, 'chosen_shipping_method' ), 50, 3 );
		add_filter( 'woocommerce_cart_item_remove_link', array( self::class, 'gift_remove_link' ), 50, 2 );
		add_action( 'woocommerce_remove_cart_item', array( self::class, 'record_gift_removal' ), 10, 2 );
		add_action( 'woocommerce_cart_emptied', array( self::class, 'clear_session_state' ) );
		add_action( 'woocommerce_checkout_order_processed', array( self::class, 'clear_session_state' ) );
		add_action( 'woocommerce_checkout_create_order_line_item', array( self::class, 'add_order_item_metadata' ), 10, 4 );
		add_action( 'woocommerce_checkout_create_order', array( self::class, 'add_order_metadata' ), 10, 2 );
	}

	/**
	 * Return the public progress state for the active cart.
	 *
	 * @param WC_Cart|null $cart Cart to evaluate.
	 * @return array<string, mixed>
	 */
	public static function snapshot( ?WC_Cart $cart = null ): array {
		$settings   = self::settings();
		$milestones = self::active_milestones( $settings );
		$amount     = $cart instanceof WC_Cart ? self::qualifying_amount( $cart, $settings ) : 0.0;
		$maximum    = empty( $milestones ) ? 0.0 : (float) max( array_column( $milestones, 'threshold' ) );
		$progress   = $maximum > 0 ? min( 100.0, ( $amount / $maximum ) * 100 ) : ( empty( $milestones ) ? 0.0 : 100.0 );
		$items      = array();
		$next       = null;

		foreach ( $milestones as $milestone ) {
			$threshold = (float) $milestone['threshold'];
			$achieved  = $amount >= $threshold;
			$remaining = max( 0.0, $threshold - $amount );
			$label     = self::milestone_label( $milestone );
			$message   = self::message( $achieved ? (string) $milestone['achieved_message'] : (string) $milestone['pending_message'], $label, $remaining );

			$items[] = array(
				'id'        => (string) $milestone['id'],
				'type'      => (string) $milestone['type'],
				'label'     => $label,
				'threshold' => $threshold,
				'position'  => $maximum > 0 ? min( 100.0, ( $threshold / $maximum ) * 100 ) : 100.0,
				'achieved'  => $achieved,
				'remaining' => $remaining,
				'message'   => $message,
			);

			if ( ! $achieved && null === $next ) {
				$next = $message;
			}
		}

		$complete = ! empty( $milestones ) && null === $next;

		return array(
			'enabled'    => true === $settings['enabled'] && ! empty( $milestones ),
			'design'     => (string) $settings['progress_design'],
			'amount'     => $amount,
			'progress'   => round( $progress, 2 ),
			'complete'   => $complete,
			'message'    => $complete ? (string) $settings['complete_message'] : ( $next ?? '' ),
			'milestones' => $items,
		);
	}

	/**
	 * Force every owned gift to remain free and single-quantity.
	 *
	 * @param WC_Cart $cart Active cart.
	 */
	public static function normalize_gifts( WC_Cart $cart ): void {
		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( ! self::is_gift( $cart_item ) ) {
				continue;
			}
			$product = $cart_item['data'] ?? null;
			if ( $product instanceof WC_Product ) {
				$product->set_price( '0' );
			}
			if ( 1 !== (int) ( $cart_item['quantity'] ?? 0 ) ) {
				$cart->set_quantity( (string) $cart_item_key, 1, false );
			}
		}
	}

	/**
	 * Reconcile all engine-owned effects after WooCommerce calculates totals.
	 *
	 * @param WC_Cart $cart Active cart.
	 */
	public static function reconcile( WC_Cart $cart ): void {
		if ( self::$reconciling ) {
			return;
		}

		self::$reconciling = true;
		try {
			$settings   = self::settings();
			$milestones = self::active_milestones( $settings );
			$amount     = self::qualifying_amount( $cart, $settings );
			$achieved   = true === $settings['enabled']
				? array_values( array_filter( $milestones, static fn( array $milestone ): bool => $amount >= (float) $milestone['threshold'] ) )
				: array();

			$changed = self::reconcile_coupons( $cart, $achieved );
			$changed = self::reconcile_gifts( $cart, $achieved, true === $settings['allow_gift_removal'] ) || $changed;
			$changed = self::reconcile_shipping( $cart, $achieved ) || $changed;
			if ( $changed ) {
				self::normalize_gifts( $cart );
				$cart->calculate_totals();
			}
		} finally {
			self::$reconciling = false;
		}
	}

	/**
	 * Add the owned free shipping rate to each current WooCommerce package.
	 *
	 * @param array<string, WC_Shipping_Rate> $rates Existing package rates.
	 * @param array<string, mixed>            $package Shipping package.
	 * @return array<string, WC_Shipping_Rate>
	 */
	public static function shipping_rates( array $rates, array $package ): array {
		unset( $package );
		if ( true !== self::session_get( self::SHIPPING_SESSION_KEY, false ) ) {
			return $rates;
		}

		$rates[ self::SHIPPING_RATE_ID ] = new WC_Shipping_Rate(
			self::SHIPPING_RATE_ID,
			__( 'Reward: Free shipping', 'starfiniti-cart' ),
			0,
			array(),
			self::SHIPPING_RATE_ID
		);

		return $rates;
	}

	/**
	 * Prefer the owned rate when it is present in a calculated package.
	 *
	 * @param string                          $fallback      WooCommerce default rate ID.
	 * @param array<string, WC_Shipping_Rate> $rates         Calculated package rates.
	 * @param string                          $chosen_method Previously chosen rate ID.
	 */
	public static function chosen_shipping_method( string $fallback, array $rates, string $chosen_method ): string {
		unset( $chosen_method );
		return isset( $rates[ self::SHIPPING_RATE_ID ] ) ? self::SHIPPING_RATE_ID : $fallback;
	}

	/**
	 * Hide gift removal unless the store explicitly permits it.
	 *
	 * @param string $link          Existing removal link.
	 * @param string $cart_item_key Cart item key.
	 */
	public static function gift_remove_link( string $link, string $cart_item_key ): string {
		$cart_item = WC()->cart instanceof WC_Cart ? WC()->cart->get_cart_item( $cart_item_key ) : array();
		return self::is_gift( $cart_item ) && ! self::gift_removal_allowed() ? '' : $link;
	}

	/**
	 * Remember an explicitly removed gift so it is not immediately restored.
	 *
	 * @param string  $cart_item_key Cart item key being removed.
	 * @param WC_Cart $cart          Active cart.
	 */
	public static function record_gift_removal( string $cart_item_key, WC_Cart $cart ): void {
		if ( self::$internal_gift_removal || ! self::gift_removal_allowed() ) {
			return;
		}
		$cart_item = $cart->get_cart_item( $cart_item_key );
		if ( ! self::is_gift( $cart_item ) ) {
			return;
		}
		$removed   = self::removed_gifts();
		$removed[] = (string) $cart_item[ self::GIFT_KEY ]['milestone_id'];
		self::session_set( self::REMOVED_GIFT_SESSION_KEY, array_values( array_unique( $removed ) ) );
	}

	/** Clear transient reward ownership after a completed/emptied cart. */
	public static function clear_session_state(): void {
		self::session_set( self::COUPON_SESSION_KEY, array() );
		self::session_set( self::REMOVED_GIFT_SESSION_KEY, array() );
		self::session_set( self::SHIPPING_SESSION_KEY, false );
	}

	/**
	 * Copy hidden reward provenance to gift order lines.
	 *
	 * @param WC_Order_Item_Product $item          New order line.
	 * @param string                $cart_item_key Source cart key.
	 * @param array<string, mixed>  $values        Source cart values.
	 * @param WC_Order              $order         New order.
	 */
	public static function add_order_item_metadata( WC_Order_Item_Product $item, string $cart_item_key, array $values, WC_Order $order ): void {
		unset( $cart_item_key, $order );
		if ( ! self::is_gift( $values ) ) {
			return;
		}
		$marker = $values[ self::GIFT_KEY ];
		$item->add_meta_data( '_sfcart_reward_gift', 'yes', true );
		$item->add_meta_data( '_sfcart_reward_milestone_id', (string) $marker['milestone_id'], true );
		$item->add_meta_data( '_sfcart_reward_type', 'gift', true );
	}

	/**
	 * Store achieved reward IDs on the order for later analytics reconciliation.
	 *
	 * @param WC_Order             $order New order.
	 * @param array<string, mixed> $data  Checkout data.
	 */
	public static function add_order_metadata( WC_Order $order, array $data ): void {
		unset( $data );
		$cart = WC()->cart;
		if ( ! $cart instanceof WC_Cart ) {
			return;
		}
		$achieved = array_values(
			array_map(
				static fn( array $milestone ): string => (string) $milestone['id'],
				array_filter( self::snapshot( $cart )['milestones'], static fn( array $milestone ): bool => true === $milestone['achieved'] )
			)
		);
		if ( ! empty( $achieved ) ) {
			$order->update_meta_data( '_sfcart_rewards_achieved', $achieved );
		}
	}

	/**
	 * Determine whether one cart item is an owned reward gift.
	 *
	 * @param mixed $cart_item Candidate cart item.
	 */
	public static function is_gift( mixed $cart_item ): bool {
		return is_array( $cart_item )
			&& is_array( $cart_item[ self::GIFT_KEY ] ?? null )
			&& '' !== (string) ( $cart_item[ self::GIFT_KEY ]['milestone_id'] ?? '' );
	}

	/** Report whether shopper gift removal is enabled. */
	public static function gift_removal_allowed(): bool {
		return true === self::settings()['allow_gift_removal'];
	}

	/**
	 * Return the normalized owned reward settings.
	 *
	 * @return array<string, mixed>
	 */
	private static function settings(): array {
		$settings = Settings::get();
		return is_array( $settings['rewards'] ?? null ) ? $settings['rewards'] : Settings::defaults()['rewards'];
	}

	/**
	 * Return enabled milestones in normalized threshold order.
	 *
	 * @param array<string, mixed> $settings Reward settings.
	 * @return list<array<string, mixed>>
	 */
	private static function active_milestones( array $settings ): array {
		$milestones = is_array( $settings['milestones'] ?? null ) ? $settings['milestones'] : array();
		$milestones = array_values( array_filter( $milestones, static fn( array $milestone ): bool => true === $milestone['enabled'] ) );

		foreach ( $milestones as &$milestone ) {
			$base_threshold         = (float) $milestone['threshold'];
			$milestone['threshold'] = max(
				0.0,
				(float) apply_filters( 'sfcart_reward_threshold', $base_threshold, $milestone )
			);
		}
		unset( $milestone );

		return $milestones;
	}

	/**
	 * Calculate the qualifying value without shipping and without reward feedback loops.
	 *
	 * @param WC_Cart              $cart     Active cart.
	 * @param array<string, mixed> $settings Reward settings.
	 */
	private static function qualifying_amount( WC_Cart $cart, array $settings ): float {
		if ( 'total' !== $settings['calculation_mode'] ) {
			$amount = (float) $cart->get_subtotal();
		} else {
			$amount    = (float) $cart->get_cart_contents_total();
			$amount   += (float) $cart->get_cart_contents_tax();
			$amount   += (float) $cart->get_fee_total();
			$amount   += (float) $cart->get_fee_tax();
			$discounts = $cart->get_coupon_discount_totals();
			$taxes     = $cart->get_coupon_discount_tax_totals();
			foreach ( self::managed_coupons() as $code ) {
				$amount += (float) ( $discounts[ $code ] ?? 0 );
				$amount += (float) ( $taxes[ $code ] ?? 0 );
			}
		}

		/**
		 * Filters the value used for reward thresholds (for example, by a currency adapter).
		 *
		 * @param float   $amount   Qualifying cart value.
		 * @param string  $mode     subtotal or total.
		 * @param WC_Cart $cart     Active cart.
		 */
		return max( 0.0, (float) apply_filters( 'sfcart_reward_amount', $amount, (string) $settings['calculation_mode'], $cart ) );
	}

	/**
	 * Apply eligible coupons and remove only coupons previously owned by this engine.
	 *
	 * @param WC_Cart                    $cart     Active cart.
	 * @param list<array<string, mixed>> $achieved Achieved milestones.
	 */
	private static function reconcile_coupons( WC_Cart $cart, array $achieved ): bool {
		$desired = array();
		foreach ( $achieved as $milestone ) {
			if ( 'coupon' !== $milestone['type'] || empty( $milestone['coupon_id'] ) ) {
				continue;
			}
			$coupon = new WC_Coupon( (int) $milestone['coupon_id'] );
			$code   = wc_format_coupon_code( $coupon->get_code() );
			if ( $coupon->get_id() > 0 && '' !== $code ) {
				$desired[] = $code;
			}
		}
		$desired = array_values( array_unique( $desired ) );
		$managed = self::managed_coupons();
		$changed = false;
		$notices = wc_get_notices();

		foreach ( array_diff( $managed, $desired ) as $code ) {
			$changed = $cart->remove_coupon( $code ) || $changed;
		}
		foreach ( $desired as $code ) {
			if ( ! $cart->has_discount( $code ) && $cart->apply_coupon( $code ) ) {
				$managed[] = $code;
				$changed   = true;
			}
		}
		wc_clear_notices();
		foreach ( $notices as $type => $messages ) {
			foreach ( $messages as $notice ) {
				wc_add_notice( (string) ( $notice['notice'] ?? '' ), (string) $type, (array) ( $notice['data'] ?? array() ) );
			}
		}

		self::session_set( self::COUPON_SESSION_KEY, array_values( array_intersect( array_unique( $managed ), $desired ) ) );
		return $changed;
	}

	/**
	 * Add each achieved gift once and remove ineligible owned gift lines.
	 *
	 * @param WC_Cart                    $cart       Active cart.
	 * @param list<array<string, mixed>> $achieved  Achieved milestones.
	 * @param bool                       $removable  Whether shopper removal persists.
	 */
	private static function reconcile_gifts( WC_Cart $cart, array $achieved, bool $removable ): bool {
		$desired           = array();
		$removed           = $removable ? self::removed_gifts() : array();
		$eligible_gift_ids = array();
		foreach ( $achieved as $milestone ) {
			if ( 'gift' !== $milestone['type'] ) {
				continue;
			}
			$eligible_gift_ids[] = (string) $milestone['id'];
			if ( ! in_array( (string) $milestone['id'], $removed, true ) ) {
				$desired[ (string) $milestone['id'] ] = $milestone;
			}
		}
		self::session_set( self::REMOVED_GIFT_SESSION_KEY, array_values( array_intersect( $removed, $eligible_gift_ids ) ) );

		$found   = array();
		$changed = false;
		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( ! self::is_gift( $cart_item ) ) {
				continue;
			}
			$id = (string) $cart_item[ self::GIFT_KEY ]['milestone_id'];
			if ( ! isset( $desired[ $id ] ) || isset( $found[ $id ] ) ) {
				self::$internal_gift_removal = true;
				$changed                     = $cart->remove_cart_item( (string) $cart_item_key ) || $changed;
				self::$internal_gift_removal = false;
				continue;
			}
			$found[ $id ] = true;
		}

		foreach ( array_diff_key( $desired, $found ) as $id => $milestone ) {
			$gift = self::gift_product( $milestone );
			if ( null === $gift ) {
				continue;
			}
			$cart_item_key = $cart->add_to_cart(
				$gift['product_id'],
				1,
				$gift['variation_id'],
				$gift['attributes'],
				array(
					self::GIFT_KEY => array(
						'milestone_id' => $id,
						'product_id'   => $gift['product_id'],
						'variation_id' => $gift['variation_id'],
					),
				)
			);
			$changed       = ( is_string( $cart_item_key ) && '' !== $cart_item_key ) || $changed;
		}

		return $changed;
	}

	/**
	 * Toggle the session-backed shipping rate and invalidate prior selections.
	 *
	 * @param WC_Cart                    $cart     Active cart.
	 * @param list<array<string, mixed>> $achieved Achieved milestones.
	 */
	private static function reconcile_shipping( WC_Cart $cart, array $achieved ): bool {
		$desired = ! empty( array_filter( $achieved, static fn( array $milestone ): bool => 'free_shipping' === $milestone['type'] ) );
		$current = true === self::session_get( self::SHIPPING_SESSION_KEY, false );
		if ( $desired === $current ) {
			return false;
		}
		self::session_set( self::SHIPPING_SESSION_KEY, $desired );
		self::session_set( 'chosen_shipping_methods', array() );
		foreach ( array_keys( $cart->get_shipping_packages() ) as $package_index ) {
			self::session_set( 'shipping_for_package_' . $package_index, false );
		}
		return true;
	}

	/**
	 * Resolve a purchasable simple product or concrete variable-product child.
	 *
	 * @param array<string, mixed> $milestone Gift milestone.
	 * @return array{product_id: int, variation_id: int, attributes: array<string, string>}|null
	 */
	private static function gift_product( array $milestone ): ?array {
		$product = \Starfiniti\Cart\Cart\ProductSnapshot::resolve( (int) $milestone['gift_product_id'] );
		if ( ! $product instanceof WC_Product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			return null;
		}

		if ( ! $product instanceof WC_Product_Variable ) {
			return array(
				'product_id'   => $product->get_id(),
				'variation_id' => 0,
				'attributes'   => array(),
			);
		}

		$variation_ids = array_filter( array_merge( array( (int) $milestone['gift_variation_id'] ), $product->get_children() ) );
		foreach ( array_unique( $variation_ids ) as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( $variation instanceof WC_Product_Variation && $product->get_id() === $variation->get_parent_id() && $variation->is_purchasable() && $variation->is_in_stock() ) {
				return array(
					'product_id'   => $product->get_id(),
					'variation_id' => $variation->get_id(),
					'attributes'   => $variation->get_variation_attributes(),
				);
			}
		}

		return null;
	}

	/**
	 * Resolve a translated/default reward label.
	 *
	 * @param array<string, mixed> $milestone Reward milestone.
	 */
	private static function milestone_label( array $milestone ): string {
		if ( '' !== (string) $milestone['label'] ) {
			return (string) $milestone['label'];
		}
		return match ( $milestone['type'] ) {
			'coupon' => __( 'Discount', 'starfiniti-cart' ),
			'gift' => __( 'Free gift', 'starfiniti-cart' ),
			default => __( 'Free shipping', 'starfiniti-cart' ),
		};
	}

	/**
	 * Expand owned message placeholders.
	 *
	 * @param string $template  Message template.
	 * @param string $label     Reward label.
	 * @param float  $remaining Remaining qualifying amount.
	 */
	private static function message( string $template, string $label, float $remaining ): string {
		$amount = trim( html_entity_decode( wp_strip_all_tags( wc_price( $remaining ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		return strtr(
			$template,
			array(
				'{{remaining_amount}}' => $amount,
				'{{reward}}'           => $label,
			)
		);
	}

	/**
	 * Return normalized coupon codes applied by the engine.
	 *
	 * @return list<string>
	 */
	private static function managed_coupons(): array {
		$value = self::session_get( self::COUPON_SESSION_KEY, array() );
		return is_array( $value ) ? array_values( array_filter( array_map( 'wc_format_coupon_code', $value ) ) ) : array();
	}

	/**
	 * Return milestone IDs explicitly removed by the shopper.
	 *
	 * @return list<string>
	 */
	private static function removed_gifts(): array {
		$value = self::session_get( self::REMOVED_GIFT_SESSION_KEY, array() );
		return is_array( $value ) ? array_values( array_filter( array_map( 'sanitize_key', $value ) ) ) : array();
	}

	/**
	 * Read a WooCommerce session value without inventing a parallel session layer.
	 *
	 * @param string $key      Owned session key.
	 * @param mixed  $fallback Value when no session value exists.
	 */
	private static function session_get( string $key, mixed $fallback ): mixed {
		return null !== WC()->session ? WC()->session->get( $key, $fallback ) : $fallback;
	}

	/**
	 * Write a WooCommerce session value when a customer session exists.
	 *
	 * @param string $key   Owned session key.
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
