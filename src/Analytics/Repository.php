<?php
/**
 * Low-level analytics persistence helpers.
 *
 * @package StarfinitiCart
 */

namespace Starfiniti\Cart\Analytics;

/**
 * Writes idempotent conversion ledger rows.
 */
final class Repository {

	/**
	 * Return bounded analytics records related to WooCommerce orders.
	 *
	 * @param array $order_ids   Order identifiers.
	 * @param array $session_ids Pseudonymous session identifiers stored on those orders.
	 * @phpstan-param list<int> $order_ids
	 * @phpstan-param list<string> $session_ids
	 * @return list<array<string, mixed>>
	 */
	public static function privacy_records( array $order_ids, array $session_ids = array() ): array {
		global $wpdb;

		$order_ids   = array_values( array_unique( array_filter( array_map( 'absint', $order_ids ) ) ) );
		$session_ids = self::session_ids( $session_ids );
		if ( array() === $order_ids && array() === $session_ids ) {
			return array();
		}

		$events       = Tables::conversions();
		$items        = Tables::items();
		$event_filter = self::privacy_event_filter( $order_ids, $session_ids );
		$item_filter  = self::privacy_item_filter( $order_ids, $session_ids, $events );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$event_sql  = $wpdb->prepare(
			"SELECT id, session_id, order_id, event_date, type, status, currency, total, revenue, refunded, coupon_code FROM {$events} WHERE {$event_filter['sql']} ORDER BY id ASC",
			$event_filter['values']
		);
		$item_sql   = $wpdb->prepare(
			"SELECT id, order_id, product_id, variation_id, type, quantity, total, refunded, currency, source, source_product_id FROM {$items} WHERE {$item_filter['sql']} ORDER BY id ASC",
			$item_filter['values']
		);
		$event_rows = is_string( $event_sql ) ? $wpdb->get_results( $event_sql, ARRAY_A ) : array();
		$item_rows  = is_string( $item_sql ) ? $wpdb->get_results( $item_sql, ARRAY_A ) : array();
		// phpcs:enable

		$records = array();
		foreach ( is_array( $event_rows ) ? $event_rows : array() as $row ) {
			if ( is_array( $row ) ) {
				$records[] = array_merge( array( 'kind' => 'event' ), $row );
			}
		}
		foreach ( is_array( $item_rows ) ? $item_rows : array() as $row ) {
			if ( is_array( $row ) ) {
				$records[] = array_merge( array( 'kind' => 'item' ), $row );
			}
		}

		return $records;
	}

	/**
	 * Delete analytics linked to a list of WooCommerce orders.
	 *
	 * @param array $order_ids   Order identifiers.
	 * @param array $session_ids Pseudonymous session identifiers stored on those orders.
	 * @phpstan-param list<int> $order_ids
	 * @phpstan-param list<string> $session_ids
	 */
	public static function delete_for_orders( array $order_ids, array $session_ids = array() ): bool {
		global $wpdb;

		$order_ids   = array_values( array_unique( array_filter( array_map( 'absint', $order_ids ) ) ) );
		$session_ids = self::session_ids( $session_ids );
		if ( array() === $order_ids && array() === $session_ids ) {
			return false;
		}

		$events       = Tables::conversions();
		$items        = Tables::items();
		$event_filter = self::privacy_event_filter( $order_ids, $session_ids );
		$item_filter  = self::privacy_item_filter( $order_ids, $session_ids, $events );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$item_sql     = $wpdb->prepare( "DELETE FROM {$items} WHERE {$item_filter['sql']}", $item_filter['values'] );
		$event_sql    = $wpdb->prepare( "DELETE FROM {$events} WHERE {$event_filter['sql']}", $event_filter['values'] );
		$item_result  = is_string( $item_sql ) ? $wpdb->query( $item_sql ) : false;
		$event_result = is_string( $event_sql ) ? $wpdb->query( $event_sql ) : false;
		// phpcs:enable

		return ( is_int( $item_result ) && $item_result > 0 ) || ( is_int( $event_result ) && $event_result > 0 );
	}

	/**
	 * Delete events and related item rows older than a UTC cutoff.
	 *
	 * @param string $cutoff UTC MySQL datetime cutoff.
	 * @param int    $limit  Maximum event rows deleted in one transaction-sized batch.
	 */
	public static function delete_before( string $cutoff, int $limit = 1000 ): int {
		global $wpdb;

		$cutoff = Time::utc_mysql( $cutoff );
		$limit  = max( 1, min( 5000, $limit ) );
		$events = Tables::conversions();
		$items  = Tables::items();
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$id_sql = $wpdb->prepare( "SELECT id FROM {$events} WHERE event_date < %s ORDER BY id ASC LIMIT %d", $cutoff, $limit );
		$ids    = is_string( $id_sql ) ? array_values( array_filter( array_map( 'absint', (array) $wpdb->get_col( $id_sql ) ) ) ) : array();
		if ( array() === $ids ) {
			return 0;
		}
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		$item_sql     = $wpdb->prepare( "DELETE FROM {$items} WHERE conversion_id IN ({$placeholders})", $ids );
		$event_sql    = $wpdb->prepare( "DELETE FROM {$events} WHERE id IN ({$placeholders})", $ids );
		if ( is_string( $item_sql ) ) {
			$wpdb->query( $item_sql );
		}
		$deleted = is_string( $event_sql ) ? $wpdb->query( $event_sql ) : 0;
		// phpcs:enable

		return is_int( $deleted ) ? max( 0, $deleted ) : 0;
	}

	/**
	 * Normalize session identifiers accepted by privacy tools.
	 *
	 * @param array $session_ids Candidate session identifiers.
	 * @phpstan-param list<string> $session_ids
	 * @return list<string>
	 */
	private static function session_ids( array $session_ids ): array {
		return array_values(
			array_unique(
				array_filter(
					array_map( 'strtolower', array_map( 'strval', $session_ids ) ),
					static fn( string $session_id ): bool => 1 === preg_match( '/^[a-f0-9]{16}$/', $session_id )
				)
			)
		);
	}

	/**
	 * Build the order/session predicate for conversion events.
	 *
	 * @param array $order_ids Order identifiers.
	 * @param array $session_ids Session identifiers.
	 * @phpstan-param list<int> $order_ids
	 * @phpstan-param list<string> $session_ids
	 * @return array{sql: string, values: list<int|string>}
	 */
	private static function privacy_event_filter( array $order_ids, array $session_ids ): array {
		$clauses = array();
		$values  = array();
		if ( array() !== $order_ids ) {
			$clauses[] = 'order_id IN (' . implode( ', ', array_fill( 0, count( $order_ids ), '%d' ) ) . ')';
			$values    = array_merge( $values, $order_ids );
		}
		if ( array() !== $session_ids ) {
			$clauses[] = 'session_id IN (' . implode( ', ', array_fill( 0, count( $session_ids ), '%s' ) ) . ')';
			$values    = array_merge( $values, $session_ids );
		}
		return array(
			'sql'    => '(' . implode( ' OR ', $clauses ) . ')',
			'values' => $values,
		);
	}

	/**
	 * Build the order/session predicate for conversion items.
	 *
	 * @param array  $order_ids Order identifiers.
	 * @param array  $session_ids Session identifiers.
	 * @param string $events Event table name.
	 * @phpstan-param list<int> $order_ids
	 * @phpstan-param list<string> $session_ids
	 * @return array{sql: string, values: list<int|string>}
	 */
	private static function privacy_item_filter( array $order_ids, array $session_ids, string $events ): array {
		$clauses = array();
		$values  = array();
		if ( array() !== $order_ids ) {
			$clauses[] = 'order_id IN (' . implode( ', ', array_fill( 0, count( $order_ids ), '%d' ) ) . ')';
			$values    = array_merge( $values, $order_ids );
		}
		if ( array() !== $session_ids ) {
			$clauses[] = "conversion_id IN (SELECT id FROM {$events} WHERE session_id IN (" . implode( ', ', array_fill( 0, count( $session_ids ), '%s' ) ) . '))';
			$values    = array_merge( $values, $session_ids );
		}
		return array(
			'sql'    => '(' . implode( ' OR ', $clauses ) . ')',
			'values' => $values,
		);
	}

	/**
	 * Upsert one conversion event by stable event key.
	 *
	 * @param array<string, mixed> $row Event fields.
	 */
	public static function upsert_conversion( array $row ): int {
		$event_key = sanitize_text_field( (string) ( $row['event_key'] ?? '' ) );
		if ( '' === $event_key ) {
			return 0;
		}

		$table = Tables::conversions();
		$now   = current_time( 'mysql', true );
		$data  = array(
			'event_key'   => $event_key,
			'session_id'  => self::session_id( (string) ( $row['session_id'] ?? '' ) ),
			'order_id'    => absint( $row['order_id'] ?? 0 ),
			'refund_id'   => absint( $row['refund_id'] ?? 0 ),
			'event_date'  => self::date_to_mysql( $row['event_date'] ?? $now ),
			'type'        => sanitize_key( (string) ( $row['type'] ?? '' ) ),
			'status'      => sanitize_key( (string) ( $row['status'] ?? '' ) ),
			'currency'    => self::currency( (string) ( $row['currency'] ?? '' ) ),
			'total'       => self::decimal( $row['total'] ?? 0 ),
			'revenue'     => self::decimal( $row['revenue'] ?? 0 ),
			'refunded'    => self::decimal( $row['refunded'] ?? 0 ),
			'coupon_code' => wc_format_coupon_code( (string) ( $row['coupon_code'] ?? '' ) ),
			'metadata'    => self::json( $row['metadata'] ?? array() ),
			'updated_at'  => $now,
		);

		$data['created_at'] = $now;
		return self::atomic_upsert( $table, $data );
	}

	/**
	 * Upsert one conversion item by stable event key.
	 *
	 * @param array<string, mixed> $row Item fields.
	 */
	public static function upsert_item( array $row ): int {
		$event_key = sanitize_text_field( (string) ( $row['event_key'] ?? '' ) );
		if ( '' === $event_key ) {
			return 0;
		}

		$table = Tables::items();
		$now   = current_time( 'mysql', true );
		$data  = array(
			'conversion_id'     => absint( $row['conversion_id'] ?? 0 ),
			'event_key'         => $event_key,
			'order_id'          => absint( $row['order_id'] ?? 0 ),
			'order_item_id'     => absint( $row['order_item_id'] ?? 0 ),
			'product_id'        => absint( $row['product_id'] ?? 0 ),
			'variation_id'      => absint( $row['variation_id'] ?? 0 ),
			'type'              => sanitize_key( (string) ( $row['type'] ?? '' ) ),
			'quantity'          => self::decimal( $row['quantity'] ?? 0 ),
			'total'             => self::decimal( $row['total'] ?? 0 ),
			'refunded'          => self::decimal( $row['refunded'] ?? 0 ),
			'currency'          => self::currency( (string) ( $row['currency'] ?? '' ) ),
			'source'            => sanitize_key( (string) ( $row['source'] ?? '' ) ),
			'source_product_id' => absint( $row['source_product_id'] ?? 0 ),
			'metadata'          => self::json( $row['metadata'] ?? array() ),
			'updated_at'        => $now,
		);

		$data['created_at'] = $now;
		return self::atomic_upsert( $table, $data );
	}

	/**
	 * Insert or update a ledger row without a select-then-insert race.
	 *
	 * The unique event_key index is the source of truth. LAST_INSERT_ID(id)
	 * returns the existing identifier on the update path so related items can
	 * always be linked to the correct conversion row.
	 *
	 * @param string               $table Owned analytics table name.
	 * @param array<string, mixed> $data  Sanitized row data.
	 */
	private static function atomic_upsert( string $table, array $data ): int {
		global $wpdb;

		$columns      = array_keys( $data );
		$column_sql   = implode( ', ', array_map( static fn( string $column ): string => '`' . $column . '`', $columns ) );
		$placeholders = implode( ', ', array_fill( 0, count( $columns ), '%s' ) );
		$updates      = array_values( array_diff( $columns, array( 'event_key', 'created_at' ) ) );
		$update_sql   = implode(
			', ',
			array_map(
				static fn( string $column ): string => '`' . $column . '` = VALUES(`' . $column . '`)',
				$updates
			)
		);

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$sql = $wpdb->prepare(
			"INSERT INTO {$table} ({$column_sql}) VALUES ({$placeholders}) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), {$update_sql}",
			array_values( $data )
		);
		// phpcs:enable
		if ( ! is_string( $sql ) ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->query( $sql );
		if ( false === $result ) {
			return self::compatible_upsert( $table, $data );
		}

		$identifier = absint( $wpdb->insert_id );
		return $identifier > 0 ? $identifier : self::event_identifier( $table, (string) $data['event_key'] );
	}

	/**
	 * Persist through standard wpdb methods when a database compatibility
	 * layer does not understand MySQL's native upsert syntax.
	 *
	 * MySQL and MariaDB never use this path. It keeps WordPress Playground and
	 * other wpdb-compatible development environments functional.
	 *
	 * @param string               $table Owned analytics table name.
	 * @param array<string, mixed> $data  Sanitized row data.
	 */
	private static function compatible_upsert( string $table, array $data ): int {
		global $wpdb;

		$identifier = self::event_identifier( $table, (string) $data['event_key'] );
		if ( $identifier > 0 ) {
			unset( $data['created_at'] );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->update( $table, $data, array( 'id' => $identifier ) );
			return false === $result ? 0 : $identifier;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert( $table, $data );
		if ( false === $result ) {
			return self::event_identifier( $table, (string) $data['event_key'] );
		}

		return absint( $wpdb->insert_id );
	}

	/**
	 * Resolve an existing row identifier by unique event key.
	 *
	 * @param string $table     Table name.
	 * @param string $event_key Stable event key.
	 */
	private static function event_identifier( string $table, string $event_key ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return absint( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE event_key = %s LIMIT 1", $event_key ) ) );
	}

	/**
	 * Normalize a MySQL UTC datetime.
	 *
	 * @param mixed $date Candidate date.
	 */
	private static function date_to_mysql( mixed $date ): string {
		return Time::utc_mysql( $date );
	}

	/**
	 * Normalize currency codes into a compact indexed field.
	 *
	 * @param string $currency Candidate currency code.
	 */
	private static function currency( string $currency ): string {
		return substr( strtoupper( preg_replace( '/[^A-Z]/i', '', $currency ) ?? '' ), 0, 10 );
	}

	/**
	 * Normalize an anonymous analytics session identifier.
	 *
	 * @param string $session_id Candidate identifier.
	 */
	private static function session_id( string $session_id ): string {
		return substr( strtolower( preg_replace( '/[^a-f0-9]/i', '', $session_id ) ?? '' ), 0, 64 );
	}

	/**
	 * Normalize decimal values for SQL storage.
	 *
	 * @param mixed $value Candidate amount.
	 */
	private static function decimal( mixed $value ): string {
		return wc_format_decimal( (float) $value, wc_get_price_decimals() );
	}

	/**
	 * Encode metadata in a predictable bounded form.
	 *
	 * @param mixed $value Metadata value.
	 */
	private static function json( mixed $value ): string {
		$encoded = wp_json_encode( $value );
		return is_string( $encoded ) ? $encoded : '{}';
	}

	/** Prevent construction. */
	private function __construct() {
	}
}
