<?php
/**
 * Analytics report queries.
 *
 * @package StarfinitiCart
 */

namespace Starfiniti\Cart\Analytics;

use WP_REST_Request;

/**
 * Builds bounded dashboard datasets from owned analytics tables.
 */
final class Reports {

	/**
	 * Parse and normalize report filters from a REST request.
	 *
	 * @param WP_REST_Request $request Report request.
	 * @return array{from: string, to: string, type: string, product_id: int, coupon: string, currency: string}
	 */
	public static function filters( WP_REST_Request $request ): array {
		$to   = self::date( (string) $request->get_param( 'to' ), Time::site_date() );
		$from = self::date( (string) $request->get_param( 'from' ), Time::site_date( '-29 days' ) );

		if ( strtotime( $from ) > strtotime( $to ) ) {
			$from = $to;
		}

		return array(
			'from'       => $from,
			'to'         => $to,
			'type'       => sanitize_key( (string) $request->get_param( 'type' ) ),
			'product_id' => absint( $request->get_param( 'product_id' ) ),
			'coupon'     => wc_format_coupon_code( (string) $request->get_param( 'coupon' ) ),
			'currency'   => substr( strtoupper( preg_replace( '/[^A-Z]/i', '', (string) $request->get_param( 'currency' ) ) ?? '' ), 0, 10 ),
		);
	}

	/**
	 * Dashboard overview metrics.
	 *
	 * @param array<string, mixed> $filters Normalized filters.
	 * @return array<string, mixed>
	 */
	public static function overview( array $filters ): array {
		global $wpdb;

		$events = Tables::conversions();
		$items  = Tables::items();
		$where  = self::where( $filters, 'c' );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(CASE WHEN c.type = 'cart_open' THEN 1 END) AS cart_opens,
					COUNT(DISTINCT CASE WHEN c.type = 'cart_open' AND c.status = 'non_empty' AND c.session_id <> '' THEN c.session_id END) AS cart_sessions,
					COUNT(DISTINCT CASE WHEN c.type = 'cart_interaction' AND c.session_id <> '' THEN c.session_id END) AS interaction_sessions,
					COUNT(CASE WHEN c.type = 'checkout_click' THEN 1 END) AS checkout_clicks,
					COUNT(DISTINCT CASE WHEN c.type = 'checkout_click' AND c.session_id <> '' THEN c.session_id END) AS checkout_sessions,
					COUNT(DISTINCT CASE WHEN c.type = 'order' AND c.session_id <> '' THEN c.session_id END) AS converted_sessions,
					COUNT(DISTINCT CASE WHEN c.type = 'order' THEN c.order_id END) AS orders,
					COALESCE(SUM(CASE WHEN c.type = 'order' THEN c.total ELSE 0 END), 0) AS order_total,
					COALESCE(SUM(CASE WHEN c.type = 'order' THEN c.revenue ELSE 0 END), 0) AS attributed_revenue,
					COALESCE(SUM(CASE WHEN c.type = 'refund' THEN c.refunded ELSE 0 END), 0) AS refunded
				FROM {$events} c
				{$where['sql']}",
				$where['values']
			),
			ARRAY_A
		);

		$counts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT i.type, COUNT(*) AS events, COALESCE(SUM(i.total), 0) AS revenue, COALESCE(SUM(i.refunded), 0) AS refunded
				FROM {$items} i
				INNER JOIN {$events} c ON c.id = i.conversion_id
				{$where['sql']}
				GROUP BY i.type",
				$where['values']
			),
			ARRAY_A
		);

		$actions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.status AS action, COUNT(*) AS events, COUNT(DISTINCT c.session_id) AS sessions
				FROM {$events} c
				{$where['sql']} AND c.type = 'cart_interaction' AND c.status <> ''
				GROUP BY c.status
				ORDER BY events DESC",
				$where['values']
			),
			ARRAY_A
		);
		// phpcs:enable

		$by_type = array();
		foreach ( (array) $counts as $count ) {
			$by_type[ (string) $count['type'] ] = array(
				'events'   => absint( $count['events'] ?? 0 ),
				'revenue'  => (float) ( $count['revenue'] ?? 0 ),
				'refunded' => (float) ( $count['refunded'] ?? 0 ),
			);
		}

		$cart_sessions      = absint( $row['cart_sessions'] ?? 0 );
		$checkout_sessions  = absint( $row['checkout_sessions'] ?? 0 );
		$converted_sessions = absint( $row['converted_sessions'] ?? 0 );
		$by_action          = array();
		foreach ( (array) $actions as $action ) {
			$by_action[] = array(
				'action'   => sanitize_key( (string) ( $action['action'] ?? '' ) ),
				'events'   => absint( $action['events'] ?? 0 ),
				'sessions' => absint( $action['sessions'] ?? 0 ),
			);
		}

		return array(
			'cart_opens'               => absint( $row['cart_opens'] ?? 0 ),
			'cart_sessions'            => $cart_sessions,
			'interaction_sessions'     => absint( $row['interaction_sessions'] ?? 0 ),
			'checkout_clicks'          => absint( $row['checkout_clicks'] ?? 0 ),
			'checkout_sessions'        => $checkout_sessions,
			'converted_sessions'       => $converted_sessions,
			'abandoned_carts'          => max( 0, $cart_sessions - $converted_sessions ),
			'checkout_rate'            => self::rate( $checkout_sessions, $cart_sessions ),
			'cart_conversion_rate'     => self::rate( $converted_sessions, $cart_sessions ),
			'checkout_conversion_rate' => self::rate( $converted_sessions, $checkout_sessions ),
			'orders'                   => absint( $row['orders'] ?? 0 ),
			'order_total'              => (float) ( $row['order_total'] ?? 0 ),
			'attributed_revenue'       => (float) ( $row['attributed_revenue'] ?? 0 ),
			'refunded'                 => (float) ( $row['refunded'] ?? 0 ),
			'net_revenue'              => (float) ( $row['attributed_revenue'] ?? 0 ) - (float) ( $row['refunded'] ?? 0 ),
			'by_type'                  => $by_type,
			'by_action'                => $by_action,
		);
	}

	/**
	 * Recent conversion events.
	 *
	 * @param array<string, mixed> $filters Normalized filters.
	 * @return list<array<string, mixed>>
	 */
	public static function conversions( array $filters ): array {
		return self::conversion_batch( $filters, 100, 0 );
	}

	/**
	 * Return one bounded page of conversion events.
	 *
	 * Dashboard responses remain intentionally small while exports can iterate
	 * every matching row without loading the complete ledger into memory.
	 *
	 * @param array<string, mixed> $filters Normalized filters.
	 * @param int                  $limit Maximum rows to return.
	 * @param int                  $offset Rows to skip.
	 * @return list<array<string, mixed>>
	 */
	public static function conversion_batch( array $filters, int $limit = 100, int $offset = 0 ): array {
		global $wpdb;

		$events = Tables::conversions();
		$where  = self::where( $filters, 'c' );
		$limit  = max( 1, min( 1000, $limit ) );
		$offset = max( 0, $offset );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.id, c.event_date, c.type, c.status, c.order_id, c.refund_id, c.currency, c.total, c.revenue, c.refunded, c.coupon_code
				FROM {$events} c
				{$where['sql']}
				ORDER BY c.event_date DESC, c.id DESC
				LIMIT %d OFFSET %d",
				array_merge( $where['values'], array( $limit, $offset ) )
			),
			ARRAY_A
		);
		// phpcs:enable

		return array_map( array( self::class, 'public_row' ), (array) $rows );
	}

	/**
	 * Product-level performance rows.
	 *
	 * @param array<string, mixed> $filters Normalized filters.
	 * @return list<array<string, mixed>>
	 */
	public static function popular( array $filters ): array {
		global $wpdb;

		$events = Tables::conversions();
		$items  = Tables::items();
		$where  = self::where( $filters, 'c', 'i' );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT i.product_id, i.variation_id, i.type, i.currency, COUNT(*) AS events,
					COALESCE(SUM(i.quantity), 0) AS quantity,
					COALESCE(SUM(i.total), 0) AS revenue,
					COALESCE(SUM(i.refunded), 0) AS refunded
				FROM {$items} i
				INNER JOIN {$events} c ON c.id = i.conversion_id
				{$where['sql']}
				GROUP BY i.product_id, i.variation_id, i.type, i.currency
				ORDER BY revenue DESC, events DESC
				LIMIT 50",
				$where['values']
			),
			ARRAY_A
		);
		// phpcs:enable

		return array_map( array( self::class, 'popular_row' ), (array) $rows );
	}

	/**
	 * Daily performance series.
	 *
	 * @param array<string, mixed> $filters Normalized filters.
	 * @return list<array<string, mixed>>
	 */
	public static function performance( array $filters ): array {
		global $wpdb;

		$events = Tables::conversions();
		$where  = self::where( $filters, 'c' );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE_FORMAT(c.event_date, '%%Y-%%m-%%d %%H:00:00') AS hour,
					COUNT(CASE WHEN c.type = 'cart_open' THEN 1 END) AS opens,
					COUNT(CASE WHEN c.type = 'cart_interaction' THEN 1 END) AS interactions,
					COUNT(CASE WHEN c.type = 'checkout_click' THEN 1 END) AS checkout_clicks,
					COUNT(DISTINCT CASE WHEN c.type = 'order' THEN c.order_id END) AS orders,
					COALESCE(SUM(CASE WHEN c.type = 'order' THEN c.revenue ELSE 0 END), 0) AS revenue,
					COALESCE(SUM(CASE WHEN c.type = 'refund' THEN c.refunded ELSE 0 END), 0) AS refunded
				FROM {$events} c
				{$where['sql']}
				GROUP BY DATE_FORMAT(c.event_date, '%%Y-%%m-%%d %%H:00:00')
				ORDER BY hour ASC",
				$where['values']
			),
			ARRAY_A
		);
		// phpcs:enable

		$days = array();
		foreach ( (array) $rows as $row ) {
			$day = Time::utc_to_site( (string) $row['hour'], 'Y-m-d' );
			if ( ! isset( $days[ $day ] ) ) {
				$days[ $day ] = array(
					'date'            => $day,
					'opens'           => 0,
					'interactions'    => 0,
					'checkout_clicks' => 0,
					'orders'          => 0,
					'revenue'         => 0.0,
					'refunded'        => 0.0,
					'net'             => 0.0,
				);
			}
			$days[ $day ]['opens']           += absint( $row['opens'] ?? 0 );
			$days[ $day ]['interactions']    += absint( $row['interactions'] ?? 0 );
			$days[ $day ]['checkout_clicks'] += absint( $row['checkout_clicks'] ?? 0 );
			$days[ $day ]['orders']          += absint( $row['orders'] ?? 0 );
			$days[ $day ]['revenue']         += (float) ( $row['revenue'] ?? 0 );
			$days[ $day ]['refunded']        += (float) ( $row['refunded'] ?? 0 );
			$days[ $day ]['net']              = $days[ $day ]['revenue'] - $days[ $day ]['refunded'];
		}

		ksort( $days );
		return array_values( $days );
	}

	/**
	 * Available filter values for the dashboard.
	 *
	 * @return array<string, mixed>
	 */
	public static function filter_options(): array {
		global $wpdb;

		$events = Tables::conversions();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$currencies = $wpdb->get_col( "SELECT DISTINCT currency FROM {$events} WHERE currency <> '' ORDER BY currency ASC LIMIT 20" );
		$types      = $wpdb->get_col( "SELECT DISTINCT type FROM {$events} WHERE type <> '' ORDER BY type ASC LIMIT 20" );
		// phpcs:enable

		return array(
			'currencies' => array_values( array_map( 'strval', (array) $currencies ) ),
			'types'      => array_values( array_map( 'strval', (array) $types ) ),
		);
	}

	/**
	 * Build a prepared WHERE clause for event and optional item filters.
	 *
	 * @param array<string, mixed> $filters Normalized filters.
	 * @param string               $event_alias Event table alias.
	 * @param string               $item_alias Optional item table alias.
	 * @return array{sql: string, values: list<mixed>}
	 */
	private static function where( array $filters, string $event_alias, string $item_alias = '' ): array {
		$range   = Time::local_days_to_utc_range( (string) $filters['from'], (string) $filters['to'] );
		$clauses = array(
			"{$event_alias}.event_date >= %s",
			"{$event_alias}.event_date < %s",
		);
		$values  = array( $range['from'], $range['to_exclusive'] );

		if ( '' !== (string) $filters['type'] ) {
			if ( '' !== $item_alias ) {
				$clauses[] = "{$item_alias}.type = %s";
			} else {
				$clauses[] = "{$event_alias}.type = %s";
			}
			$values[] = (string) $filters['type'];
		}
		if ( '' !== (string) $filters['currency'] ) {
			$clauses[] = "{$event_alias}.currency = %s";
			$values[]  = (string) $filters['currency'];
		}
		if ( '' !== (string) $filters['coupon'] ) {
			$clauses[] = "{$event_alias}.coupon_code = %s";
			$values[]  = (string) $filters['coupon'];
		}
		if ( '' !== $item_alias && (int) $filters['product_id'] > 0 ) {
			$clauses[] = "{$item_alias}.product_id = %d";
			$values[]  = (int) $filters['product_id'];
		}

		return array(
			'sql'    => 'WHERE ' . implode( ' AND ', $clauses ),
			'values' => $values,
		);
	}

	/**
	 * Normalize one conversion row for JSON.
	 *
	 * @param array<string, mixed> $row SQL row.
	 * @return array<string, mixed>
	 */
	private static function public_row( array $row ): array {
		return array(
			'id'        => absint( $row['id'] ?? 0 ),
			'date'      => Time::utc_to_site( (string) ( $row['event_date'] ?? '' ) ),
			'type'      => (string) ( $row['type'] ?? '' ),
			'status'    => (string) ( $row['status'] ?? '' ),
			'order_id'  => absint( $row['order_id'] ?? 0 ),
			'refund_id' => absint( $row['refund_id'] ?? 0 ),
			'currency'  => (string) ( $row['currency'] ?? '' ),
			'total'     => (float) ( $row['total'] ?? 0 ),
			'revenue'   => (float) ( $row['revenue'] ?? 0 ),
			'refunded'  => (float) ( $row['refunded'] ?? 0 ),
			'coupon'    => (string) ( $row['coupon_code'] ?? '' ),
		);
	}

	/**
	 * Normalize one product performance row.
	 *
	 * @param array<string, mixed> $row SQL row.
	 * @return array<string, mixed>
	 */
	private static function popular_row( array $row ): array {
		$product_id = absint( $row['variation_id'] ?? 0 ) > 0 ? absint( $row['variation_id'] ) : absint( $row['product_id'] ?? 0 );
		$product    = $product_id > 0 ? wc_get_product( $product_id ) : false;

		return array(
			'product_id'   => absint( $row['product_id'] ?? 0 ),
			'variation_id' => absint( $row['variation_id'] ?? 0 ),
			'name'         => $product ? $product->get_name() : __( 'Unknown product', 'starfiniti-cart' ),
			'type'         => (string) ( $row['type'] ?? '' ),
			'currency'     => (string) ( $row['currency'] ?? '' ),
			'events'       => absint( $row['events'] ?? 0 ),
			'quantity'     => (float) ( $row['quantity'] ?? 0 ),
			'revenue'      => (float) ( $row['revenue'] ?? 0 ),
			'refunded'     => (float) ( $row['refunded'] ?? 0 ),
			'net'          => (float) ( $row['revenue'] ?? 0 ) - (float) ( $row['refunded'] ?? 0 ),
		);
	}

	/**
	 * Validate YYYY-MM-DD dates.
	 *
	 * @param string $value Candidate date.
	 * @param string $fallback Fallback date.
	 */
	private static function date( string $value, string $fallback ): string {
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : $fallback;
	}

	/**
	 * Return a bounded percentage with one decimal place.
	 *
	 * @param int $value Funnel-stage sessions.
	 * @param int $total Funnel-entry sessions.
	 */
	private static function rate( int $value, int $total ): float {
		return $total > 0 ? round( min( 100, max( 0, ( $value / $total ) * 100 ) ), 1 ) : 0.0;
	}

	/** Prevent construction. */
	private function __construct() {
	}
}
