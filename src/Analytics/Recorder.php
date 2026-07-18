<?php
/**
 * WooCommerce analytics reconciliation.
 *
 * @package StarfinitiCart
 */

namespace Starfiniti\Cart\Analytics;

use WC_Order;
use WC_Order_Item_Product;
use WC_Order_Refund;

/**
 * Records paid orders, attributed line items, impressions, rewards, add-ons, and refunds.
 */
final class Recorder {

	/** Register WooCommerce event hooks. */
	public static function register(): void {
		add_action( 'sfcart_recommendation_impression', array( self::class, 'record_recommendation_impression' ), 10, 1 );
		add_action( 'sfcart_recommendation_accepted', array( self::class, 'record_recommendation_acceptance' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order', array( self::class, 'attach_session_to_order' ), 20, 1 );
		add_action( 'woocommerce_store_api_checkout_update_order_meta', array( self::class, 'attach_session_to_order' ), 20, 1 );
		add_action( 'woocommerce_payment_complete', array( self::class, 'record_paid_order' ), 20, 1 );
		add_action( 'woocommerce_order_status_changed', array( self::class, 'record_status_change' ), 20, 4 );
		add_action( 'woocommerce_refund_created', array( self::class, 'record_refund' ), 20, 2 );
	}

	/**
	 * Record one anonymous cart UI event.
	 *
	 * @param string               $type Client event type.
	 * @param string               $event_id Client-generated idempotency key.
	 * @param string               $status Event action or cart state.
	 * @param array<string, mixed> $metadata Bounded non-personal context.
	 */
	public static function record_cart_event( string $type, string $event_id, string $status, array $metadata = array() ): void {
		$session_id = self::session_identifier();
		if ( '' === $session_id ) {
			return;
		}

		Repository::upsert_conversion(
			array(
				'event_key'  => 'cart:' . $session_id . ':' . $event_id,
				'session_id' => $session_id,
				'type'       => $type,
				'status'     => $status,
				'metadata'   => $metadata,
			)
		);

		if ( 'checkout_click' === $type && WC()->session ) {
			WC()->session->set( 'sfcart_checkout_clicked', true );
		}
	}

	/**
	 * Attach an anonymous side-cart session to an order created after its checkout click.
	 *
	 * @param WC_Order $order Checkout order.
	 */
	public static function attach_session_to_order( WC_Order $order ): void {
		if ( ! WC()->session || ! WC()->session->get( 'sfcart_checkout_clicked', false ) ) {
			return;
		}

		$order->update_meta_data( '_sfcart_session_id', self::session_identifier() );
	}

	/**
	 * Record a viewed recommendation for session-level funnel reporting.
	 *
	 * @param array<string, mixed> $impression Normalized impression data.
	 */
	public static function record_recommendation_impression( array $impression ): void {
		$product_id = absint( $impression['product_id'] ?? 0 );
		if ( 0 === $product_id ) {
			return;
		}

		$session_id = self::session_identifier();
		$viewed_at  = absint( $impression['viewed_at'] ?? time() );

		$event_id = Repository::upsert_conversion(
			array(
				'event_key'  => 'impression:' . $session_id . ':' . $product_id,
				'session_id' => $session_id,
				'event_date' => $viewed_at,
				'type'       => 'impression',
				'metadata'   => $impression,
			)
		);

		Repository::upsert_item(
			array(
				'conversion_id'     => $event_id,
				'event_key'         => 'impression-item:' . $session_id . ':' . $product_id,
				'product_id'        => $product_id,
				'type'              => 'recommendation',
				'source'            => sanitize_key( (string) ( $impression['source'] ?? '' ) ),
				'source_product_id' => absint( $impression['source_product_id'] ?? 0 ),
				'metadata'          => $impression,
			)
		);
	}

	/**
	 * Record an accepted recommendation before checkout exists.
	 *
	 * @param array<string, mixed> $attribution Attribution payload.
	 * @param string               $cart_item_key Cart item key.
	 */
	public static function record_recommendation_acceptance( array $attribution, string $cart_item_key ): void {
		$product_id = absint( $attribution['product_id'] ?? 0 );
		if ( 0 === $product_id ) {
			return;
		}

		$session_id = self::session_identifier();
		$key        = 'accepted:' . $session_id . ':' . md5( $cart_item_key . ':' . $product_id );
		$event_id   = Repository::upsert_conversion(
			array(
				'event_key'  => $key,
				'session_id' => $session_id,
				'type'       => 'accepted',
				'metadata'   => $attribution,
			)
		);

		Repository::upsert_item(
			array(
				'conversion_id'     => $event_id,
				'event_key'         => $key . ':item',
				'product_id'        => $product_id,
				'type'              => 'recommendation',
				'source'            => sanitize_key( (string) ( $attribution['source'] ?? '' ) ),
				'source_product_id' => absint( $attribution['source_product_id'] ?? 0 ),
				'metadata'          => $attribution,
			)
		);
	}

	/**
	 * Reconcile paid-order rows after payment completion.
	 *
	 * @param int $order_id Order identifier.
	 */
	public static function record_paid_order( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( $order instanceof WC_Order ) {
			self::record_order( $order );
		}
	}

	/**
	 * Reconcile delayed gateways when the order reaches a paid status.
	 *
	 * @param int    $order_id Order identifier.
	 * @param string $from Previous status.
	 * @param string $to New status.
	 * @param mixed  $order Order object.
	 */
	public static function record_status_change( int $order_id, string $from, string $to, mixed $order ): void {
		unset( $from, $to );
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );
		if ( $order instanceof WC_Order ) {
			self::record_order( $order );
		}
	}

	/**
	 * Reconcile one paid order.
	 *
	 * @param WC_Order $order Paid order.
	 */
	public static function record_order( WC_Order $order ): void {
		if ( ! $order->is_paid() ) {
			return;
		}

		$order_id = $order->get_id();
		$event_id = Repository::upsert_conversion(
			array(
				'event_key'  => 'order:' . $order_id,
				'session_id' => (string) $order->get_meta( '_sfcart_session_id', true ),
				'order_id'   => $order_id,
				'event_date' => $order->get_date_paid()?->getTimestamp() ?? $order->get_date_created()?->getTimestamp(),
				'type'       => 'order',
				'status'     => $order->get_status(),
				'currency'   => $order->get_currency(),
				'total'      => $order->get_total(),
				'revenue'    => self::attributed_total( $order ),
				'metadata'   => array(
					'payment_method' => $order->get_payment_method(),
				),
			)
		);

		self::record_order_impressions( $order, $event_id );
		self::record_order_items( $order, $event_id );
		self::record_rewards( $order, $event_id );
	}

	/**
	 * Record refund rows once and update them idempotently if the hook repeats.
	 *
	 * @param int                  $refund_id Refund identifier.
	 * @param array<string, mixed> $arguments WooCommerce refund arguments.
	 */
	public static function record_refund( int $refund_id, array $arguments = array() ): void {
		unset( $arguments );
		$refund = wc_get_order( $refund_id );
		if ( ! $refund instanceof WC_Order_Refund ) {
			return;
		}

		$order = wc_get_order( $refund->get_parent_id() );
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$amount   = self::attributed_refund_total( $refund, $order );
		$event_id = Repository::upsert_conversion(
			array(
				'event_key'  => 'refund:' . $refund->get_id(),
				'order_id'   => $order->get_id(),
				'refund_id'  => $refund->get_id(),
				'event_date' => $refund->get_date_created()?->getTimestamp(),
				'type'       => 'refund',
				'status'     => $order->get_status(),
				'currency'   => $order->get_currency(),
				'refunded'   => $amount,
				'metadata'   => array(
					'reason'             => $refund->get_reason(),
					'order_refund_total' => abs( (float) $refund->get_amount() ),
				),
			)
		);

		foreach ( $refund->get_items( 'line_item' ) as $refund_item ) {
			if ( ! $refund_item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$original_id   = absint( $refund_item->get_meta( '_refunded_item_id', true ) );
			$original_item = $order->get_item( $original_id );
			$type          = $original_item instanceof WC_Order_Item_Product ? self::item_type( $original_item ) : 'product';

			Repository::upsert_item(
				array(
					'conversion_id' => $event_id,
					'event_key'     => 'refund:' . $refund->get_id() . ':item:' . $refund_item->get_id(),
					'order_id'      => $order->get_id(),
					'order_item_id' => $refund_item->get_id(),
					'product_id'    => $refund_item->get_product_id(),
					'variation_id'  => $refund_item->get_variation_id(),
					'type'          => $type,
					'quantity'      => abs( (float) $refund_item->get_quantity() ),
					'refunded'      => abs( (float) $refund_item->get_total() ),
					'currency'      => $order->get_currency(),
					'metadata'      => array(
						'refunded_item_id' => $original_id,
					),
				)
			);
		}
	}

	/**
	 * Persist recommendation impressions that were copied to the paid order.
	 *
	 * @param WC_Order $order    Paid order.
	 * @param int      $event_id Conversion event identifier.
	 */
	private static function record_order_impressions( WC_Order $order, int $event_id ): void {
		$impressions = $order->get_meta( '_sfcart_recommendation_impressions', true );
		if ( ! is_array( $impressions ) ) {
			return;
		}

		foreach ( $impressions as $index => $impression ) {
			if ( ! is_array( $impression ) ) {
				continue;
			}
			$product_id = absint( $impression['product_id'] ?? 0 );
			if ( 0 === $product_id ) {
				continue;
			}
			Repository::upsert_item(
				array(
					'conversion_id'     => $event_id,
					'event_key'         => 'order:' . $order->get_id() . ':impression:' . $index . ':' . $product_id,
					'order_id'          => $order->get_id(),
					'product_id'        => $product_id,
					'type'              => 'impression',
					'currency'          => $order->get_currency(),
					'source'            => sanitize_key( (string) ( $impression['source'] ?? '' ) ),
					'source_product_id' => absint( $impression['source_product_id'] ?? 0 ),
					'metadata'          => $impression,
				)
			);
		}
	}

	/**
	 * Persist paid order line attribution.
	 *
	 * @param WC_Order $order    Paid order.
	 * @param int      $event_id Conversion event identifier.
	 */
	private static function record_order_items( WC_Order $order, int $event_id ): void {
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$type = self::item_type( $item );
			Repository::upsert_item(
				array(
					'conversion_id'     => $event_id,
					'event_key'         => 'order:' . $order->get_id() . ':item:' . $item->get_id(),
					'order_id'          => $order->get_id(),
					'order_item_id'     => $item->get_id(),
					'product_id'        => $item->get_product_id(),
					'variation_id'      => $item->get_variation_id(),
					'type'              => $type,
					'quantity'          => $item->get_quantity(),
					'total'             => $item->get_total(),
					'currency'          => $order->get_currency(),
					'source'            => sanitize_key( (string) $item->get_meta( '_sfcart_recommendation_source', true ) ),
					'source_product_id' => absint( $item->get_meta( '_sfcart_recommendation_source_product_id', true ) ),
				)
			);
		}
	}

	/**
	 * Persist achieved reward milestones from the order-level metadata.
	 *
	 * @param WC_Order $order    Paid order.
	 * @param int      $event_id Conversion event identifier.
	 */
	private static function record_rewards( WC_Order $order, int $event_id ): void {
		$achieved = $order->get_meta( '_sfcart_rewards_achieved', true );
		if ( ! is_array( $achieved ) ) {
			return;
		}

		foreach ( array_values( $achieved ) as $index => $milestone_id ) {
			$milestone_id = sanitize_key( (string) $milestone_id );
			if ( '' === $milestone_id ) {
				continue;
			}
			Repository::upsert_item(
				array(
					'conversion_id' => $event_id,
					'event_key'     => 'order:' . $order->get_id() . ':reward:' . $index . ':' . $milestone_id,
					'order_id'      => $order->get_id(),
					'type'          => 'reward',
					'currency'      => $order->get_currency(),
					'metadata'      => array( 'milestone_id' => $milestone_id ),
				)
			);
		}
	}

	/**
	 * Resolve the owned attribution type for an order item.
	 *
	 * @param WC_Order_Item_Product $item Order line item.
	 */
	private static function item_type( WC_Order_Item_Product $item ): string {
		if ( 'yes' === $item->get_meta( '_sfcart_recommendation', true ) ) {
			return 'recommendation';
		}
		if ( 'yes' === $item->get_meta( '_sfcart_reward_gift', true ) ) {
			return 'reward_gift';
		}
		if ( 'yes' === $item->get_meta( '_sfcart_special_addon', true ) ) {
			return 'special_addon';
		}
		return 'product';
	}

	/**
	 * Sum side-cart-attributed order line revenue.
	 *
	 * @param WC_Order $order Paid order.
	 */
	private static function attributed_total( WC_Order $order ): float {
		$total = 0.0;
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( $item instanceof WC_Order_Item_Product && 'product' !== self::item_type( $item ) ) {
				$total += (float) $item->get_total();
			}
		}

		return $total;
	}

	/**
	 * Sum only refunded line revenue attributed to side-cart features.
	 *
	 * A refund can contain ordinary catalog products and attributed
	 * recommendations, gifts, or add-ons. Dashboard net revenue must only
	 * subtract the latter from its attributed-revenue numerator.
	 *
	 * @param WC_Order_Refund $refund Refund being recorded.
	 * @param WC_Order        $order  Parent order.
	 */
	private static function attributed_refund_total( WC_Order_Refund $refund, WC_Order $order ): float {
		$total = 0.0;
		foreach ( $refund->get_items( 'line_item' ) as $refund_item ) {
			if ( ! $refund_item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$original_id   = absint( $refund_item->get_meta( '_refunded_item_id', true ) );
			$original_item = $order->get_item( $original_id );
			if ( $original_item instanceof WC_Order_Item_Product && 'product' !== self::item_type( $original_item ) ) {
				$total += abs( (float) $refund_item->get_total() );
			}
		}

		return $total;
	}

	/**
	 * Return a non-personal stable session identifier for anonymous events.
	 */
	public static function session_identifier(): string {
		$session = WC()->session;
		if ( $session ) {
			$stored = $session->get( 'sfcart_analytics_id', '' );
			if ( is_string( $stored ) && preg_match( '/^[a-f0-9]{16}$/', $stored ) ) {
				return $stored;
			}

			$customer_id = $session->get_customer_id();
			$basis       = is_string( $customer_id ) && '' !== $customer_id ? $customer_id : wp_generate_uuid4();
			$stored      = substr( hash_hmac( 'sha256', $basis, wp_salt( 'nonce' ) ), 0, 16 );
			$session->set( 'sfcart_analytics_id', $stored );
			return $stored;
		}

		return substr( hash_hmac( 'sha256', wp_generate_uuid4(), wp_salt( 'nonce' ) ), 0, 16 );
	}

	/** Prevent construction. */
	private function __construct() {
	}
}
