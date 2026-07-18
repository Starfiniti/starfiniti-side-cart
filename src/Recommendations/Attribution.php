<?php
/**
 * Recommendation impression, acceptance, order, and refund attribution.
 *
 * @package StarfinitiCart
 */

namespace Starfiniti\Cart\Recommendations;

use WC_Order;
use WC_Order_Item_Product;
use WC_Order_Refund;

/**
 * Stores owned attribution metadata and exposes events for the Batch 7 ledger.
 */
final class Attribution {

	/**
	 * WooCommerce session key for de-duplicated viewed recommendations.
	 */
	public const SESSION_KEY = 'sfcart_recommendation_impressions';

	/**
	 * Cart item data key used by the owned add endpoint.
	 */
	public const CART_ITEM_KEY = 'sfcart_recommendation';

	/**
	 * Register WooCommerce order lifecycle hooks.
	 */
	public static function register(): void {
		add_action( 'woocommerce_checkout_create_order_line_item', array( self::class, 'copy_line_item_attribution' ), 10, 4 );
		add_action( 'woocommerce_checkout_create_order', array( self::class, 'copy_order_impressions' ), 10, 1 );
		add_action( 'woocommerce_payment_complete', array( self::class, 'record_revenue' ), 10, 1 );
		add_action( 'woocommerce_order_status_processing', array( self::class, 'record_revenue' ), 10, 1 );
		add_action( 'woocommerce_order_status_completed', array( self::class, 'record_revenue' ), 10, 1 );
		add_action( 'woocommerce_refund_created', array( self::class, 'record_refund' ), 10, 2 );
	}

	/**
	 * Record newly visible recommendations once per WooCommerce session.
	 *
	 * @param list<array<string, mixed>> $recommendations Server-approved recommendation rows.
	 * @param int[]                      $requested_ids Product identifiers reported by the client.
	 * @return int Number of newly recorded impressions.
	 */
	public static function record_impressions( array $recommendations, array $requested_ids ): int {
		$session = WC()->session;
		if ( null === $session ) {
			return 0;
		}

		$requested = array_fill_keys( array_map( 'absint', $requested_ids ), true );
		$stored    = $session->get( self::SESSION_KEY, array() );
		$stored    = is_array( $stored ) ? $stored : array();
		$recorded  = 0;

		foreach ( $recommendations as $recommendation ) {
			$product_id = absint( $recommendation['id'] ?? 0 );
			if ( 0 === $product_id || ! isset( $requested[ $product_id ] ) || isset( $stored[ $product_id ] ) ) {
				continue;
			}

			$stored[ $product_id ] = array(
				'product_id'        => $product_id,
				'source'            => sanitize_key( (string) ( $recommendation['source'] ?? '' ) ),
				'source_product_id' => absint( $recommendation['source_product_id'] ?? 0 ),
				'viewed_at'         => time(),
			);
			++$recorded;

			/**
			 * Fires once when a recommendation is first viewed in a cart session.
			 *
			 * @param array<string, mixed> $impression Normalized impression data.
			 */
			do_action( 'sfcart_recommendation_impression', $stored[ $product_id ] );
		}

		$session->set( self::SESSION_KEY, $stored );

		return $recorded;
	}

	/**
	 * Copy accepted recommendation data from a cart item to hidden order-item meta.
	 *
	 * @param WC_Order_Item_Product $item          New order item.
	 * @param string                $cart_item_key Cart item key.
	 * @param array<string, mixed>  $values        Cart item data.
	 * @param WC_Order              $order         Pending order.
	 */
	public static function copy_line_item_attribution( WC_Order_Item_Product $item, string $cart_item_key, array $values, WC_Order $order ): void {
		unset( $cart_item_key, $order );
		$attribution = $values[ self::CART_ITEM_KEY ] ?? null;
		if ( ! is_array( $attribution ) ) {
			return;
		}

		$item->add_meta_data( '_sfcart_recommendation', 'yes', true );
		$item->add_meta_data( '_sfcart_recommendation_product_id', (string) absint( $attribution['product_id'] ?? $item->get_product_id() ), true );
		$item->add_meta_data( '_sfcart_recommendation_source', sanitize_key( (string) ( $attribution['source'] ?? '' ) ), true );
		$item->add_meta_data( '_sfcart_recommendation_source_product_id', (string) absint( $attribution['source_product_id'] ?? 0 ), true );
	}

	/**
	 * Attach the session's viewed products to the order through the CRUD API.
	 *
	 * @param WC_Order $order Pending checkout order.
	 */
	public static function copy_order_impressions( WC_Order $order ): void {
		$session = WC()->session;
		$views   = null !== $session ? $session->get( self::SESSION_KEY, array() ) : array();

		if ( is_array( $views ) && array() !== $views ) {
			$order->update_meta_data( '_sfcart_recommendation_impressions', array_values( $views ) );
		}
	}

	/**
	 * Record accepted recommendation revenue once after an order becomes paid.
	 *
	 * Revenue is the WooCommerce line total after discounts and before tax.
	 *
	 * @param int $order_id WooCommerce order identifier.
	 */
	public static function record_revenue( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order || 'yes' === $order->get_meta( '_sfcart_recommendation_revenue_recorded', true ) ) {
			return;
		}

		$revenue = 0.0;
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( $item instanceof WC_Order_Item_Product && 'yes' === $item->get_meta( '_sfcart_recommendation', true ) ) {
				$revenue += (float) $item->get_total();
			}
		}

		$order->update_meta_data( '_sfcart_recommendation_revenue', wc_format_decimal( $revenue, wc_get_price_decimals() ) );
		$order->update_meta_data( '_sfcart_recommendation_revenue_recorded', 'yes' );
		$order->save();

		/**
		 * Fires after recommendation revenue is recorded idempotently for an order.
		 *
		 * @param int   $order_id Order identifier.
		 * @param float $revenue Recommendation line revenue before tax.
		 */
		do_action( 'sfcart_recommendation_revenue_recorded', $order_id, $revenue );
	}

	/**
	 * Attribute recommendation refund lines and update the parent order total once.
	 *
	 * @param int                  $refund_id Refund identifier.
	 * @param array<string, mixed> $arguments WooCommerce refund arguments.
	 */
	public static function record_refund( int $refund_id, array $arguments = array() ): void {
		unset( $arguments );
		$refund = wc_get_order( $refund_id );
		if ( ! $refund instanceof WC_Order_Refund || 'yes' === $refund->get_meta( '_sfcart_recommendation_attributed', true ) ) {
			return;
		}

		$order = wc_get_order( $refund->get_parent_id() );
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$amount = 0.0;
		foreach ( $refund->get_items( 'line_item' ) as $refund_item ) {
			if ( ! $refund_item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$original_id   = absint( $refund_item->get_meta( '_refunded_item_id', true ) );
			$original_item = $order->get_item( $original_id );
			if ( $original_item instanceof WC_Order_Item_Product && 'yes' === $original_item->get_meta( '_sfcart_recommendation', true ) ) {
				$amount += abs( (float) $refund_item->get_total() );
			}
		}

		$refund->update_meta_data( '_sfcart_recommendation_refund', wc_format_decimal( $amount, wc_get_price_decimals() ) );
		$refund->update_meta_data( '_sfcart_recommendation_attributed', 'yes' );
		$refund->save();

		$current = (float) $order->get_meta( '_sfcart_recommendation_refunded', true );
		$order->update_meta_data( '_sfcart_recommendation_refunded', wc_format_decimal( $current + $amount, wc_get_price_decimals() ) );
		$order->save();

		/**
		 * Fires after a recommendation refund is attributed idempotently.
		 *
		 * @param int   $order_id  Parent order identifier.
		 * @param int   $refund_id Refund identifier.
		 * @param float $amount    Recommendation refund amount before tax.
		 */
		do_action( 'sfcart_recommendation_refund_recorded', $order->get_id(), $refund_id, $amount );
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
