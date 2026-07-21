<?php
/**
 * WordPress privacy and analytics-retention integration.
 *
 * @package StarfinitiCart
 */

namespace Starfiniti\Cart\Analytics;

use Starfiniti\Cart\Settings;
use Starfiniti\Cart\Support\Logger;
use Throwable;
use WC_Order;

/**
 * Owns privacy disclosures, data requests, and bounded analytics retention.
 */
final class Privacy {

	/** Daily analytics cleanup event. */
	public const CRON_HOOK = 'sfcart_cleanup_analytics';

	/** Number of orders processed by one privacy request page. */
	private const ORDERS_PER_PAGE = 50;

	/** Register WordPress privacy and retention hooks. */
	public static function register(): void {
		add_action( 'admin_init', array( self::class, 'add_policy_content' ) );
		add_action( 'init', array( self::class, 'schedule_cleanup' ) );
		add_action( self::CRON_HOOK, array( self::class, 'cleanup_expired' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( self::class, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( self::class, 'register_eraser' ) );
	}

	/** Add suggested text to the WordPress privacy-policy guide. */
	public static function add_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = '<p>' . esc_html__( 'Starfiniti Cart records pseudonymous side-cart interaction, recommendation, reward, add-on, order-attribution, and refund analytics in tables owned by this site. It does not store customer names, email addresses, raw IP addresses, or payment credentials in those analytics tables. When a cart session becomes an order, its pseudonymous events are linked to that order and can be exported or erased through the WordPress personal-data tools. Unconverted anonymous cart events cannot be searched by email. Analytics is automatically deleted after the retention period configured in WooCommerce > Starfiniti Cart > Tools.', 'starfiniti-cart' ) . '</p>';
		wp_add_privacy_policy_content( 'Starfiniti Cart for WooCommerce', wp_kses_post( $content ) );
	}

	/** Ensure the daily cleanup event exists. */
	public static function schedule_cleanup(): void {
		if ( false === wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/** Delete analytics older than the configured retention period. */
	public static function cleanup_expired(): void {
		try {
			$cutoff          = gmdate( 'Y-m-d H:i:s', time() - ( Settings::analytics_retention_days() * DAY_IN_SECONDS ) );
			$maximum_batches = max( 1, min( 50, absint( apply_filters( 'sfcart_analytics_cleanup_batches', 20 ) ) ) );
			for ( $batch = 0; $batch < $maximum_batches; $batch++ ) {
				if ( Repository::delete_before( $cutoff, 1000 ) < 1000 ) {
					break;
				}
			}
		} catch ( Throwable $error ) {
			Logger::exception( 'Starfiniti Cart analytics cleanup failed.', $error );
		}
	}

	/**
	 * Register the plugin's WordPress personal-data exporter.
	 *
	 * @param array<string, mixed> $exporters Registered exporters.
	 *
	 * @return array<string, mixed>
	 */
	public static function register_exporter( array $exporters ): array {
		$exporters['starfiniti-cart'] = array(
			'exporter_friendly_name' => __( 'Starfiniti Cart analytics', 'starfiniti-cart' ),
			'callback'               => array( self::class, 'export_personal_data' ),
		);

		return $exporters;
	}

	/**
	 * Register the plugin's WordPress personal-data eraser.
	 *
	 * @param array<string, mixed> $erasers Registered erasers.
	 *
	 * @return array<string, mixed>
	 */
	public static function register_eraser( array $erasers ): array {
		$erasers['starfiniti-cart'] = array(
			'eraser_friendly_name' => __( 'Starfiniti Cart analytics', 'starfiniti-cart' ),
			'callback'             => array( self::class, 'erase_personal_data' ),
		);

		return $erasers;
	}

	/**
	 * Export analytics connected to WooCommerce orders for one email address.
	 *
	 * @param string $email_address Customer email address.
	 * @param int    $page          Export page number.
	 *
	 * @return array{data: list<array<string, mixed>>, done: bool}
	 */
	public static function export_personal_data( string $email_address, int $page = 1 ): array {
		$order_page = self::orders_for_email( $email_address, $page );
		$data       = array();

		foreach ( Repository::privacy_records( $order_page['ids'], $order_page['session_ids'] ) as $record ) {
			$data[] = array(
				'group_id'    => 'starfiniti-cart-analytics',
				'group_label' => __( 'Starfiniti Cart analytics', 'starfiniti-cart' ),
				'item_id'     => 'sfcart-' . sanitize_key( (string) $record['kind'] ) . '-' . absint( $record['id'] ),
				'data'        => self::export_fields( $record ),
			);
		}

		return array(
			'data' => $data,
			'done' => $order_page['done'],
		);
	}

	/**
	 * Erase analytics connected to WooCommerce orders for one email address.
	 *
	 * @param string $email_address Customer email address.
	 * @param int    $page          Erasure page number.
	 *
	 * @return array{items_removed: bool, items_retained: bool, messages: list<string>, done: bool}
	 */
	public static function erase_personal_data( string $email_address, int $page = 1 ): array {
		$order_page = self::orders_for_email( $email_address, $page );
		$removed    = Repository::delete_for_orders( $order_page['ids'], $order_page['session_ids'] );

		foreach ( $order_page['ids'] as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order instanceof WC_Order && '' !== (string) $order->get_meta( '_sfcart_session_id', true ) ) {
				$order->delete_meta_data( '_sfcart_session_id' );
				$order->save();
				$removed = true;
			}
		}

		return array(
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => $order_page['done'],
		);
	}

	/**
	 * Resolve one bounded page of WooCommerce orders for an email address.
	 *
	 * @param string $email_address Customer email address.
	 * @param int    $page          Request page number.
	 *
	 * @return array{ids: list<int>, session_ids: list<string>, done: bool}
	 */
	private static function orders_for_email( string $email_address, int $page ): array {
		$email_address = sanitize_email( $email_address );
		if ( '' === $email_address || ! is_email( $email_address ) ) {
			return array(
				'ids'         => array(),
				'session_ids' => array(),
				'done'        => true,
			);
		}

		$result      = wc_get_orders(
			array(
				'billing_email' => $email_address,
				'limit'         => self::ORDERS_PER_PAGE,
				'page'          => max( 1, $page ),
				'paginate'      => true,
				'return'        => 'ids',
			)
		);
		$ids         = is_object( $result ) && isset( $result->orders ) && is_array( $result->orders )
			? array_values( array_filter( array_map( 'absint', $result->orders ) ) )
			: array();
		$pages       = is_object( $result ) && isset( $result->max_num_pages ) ? max( 1, absint( $result->max_num_pages ) ) : 1;
		$session_ids = array();
		foreach ( $ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order instanceof WC_Order ) {
				continue;
			}
			$session_id = strtolower( (string) $order->get_meta( '_sfcart_session_id', true ) );
			if ( 1 === preg_match( '/^[a-f0-9]{16}$/', $session_id ) ) {
				$session_ids[] = $session_id;
			}
		}

		return array(
			'ids'         => $ids,
			'session_ids' => array_values( array_unique( $session_ids ) ),
			'done'        => max( 1, $page ) >= $pages,
		);
	}

	/**
	 * Convert one database record to WordPress exporter name/value rows.
	 *
	 * @param array<string, mixed> $record Analytics database record.
	 *
	 * @return list<array{name: string, value: string}>
	 */
	private static function export_fields( array $record ): array {
		$fields = array();
		foreach ( $record as $name => $value ) {
			if ( in_array( $name, array( 'id', 'kind' ), true ) ) {
				continue;
			}
			$fields[] = array(
				'name'  => ucwords( str_replace( '_', ' ', sanitize_key( (string) $name ) ) ),
				'value' => is_scalar( $value ) ? (string) $value : '',
			);
		}

		return $fields;
	}

	/** Prevent construction. */
	private function __construct() {
	}
}
