<?php
/**
 * Owned analytics table names and schema.
 *
 * @package StarfinitiCart
 */

namespace Starfiniti\Cart\Analytics;

/**
 * Defines the Starfiniti-owned analytics storage.
 */
final class Tables {

	/** Conversion event table suffix. */
	private const CONVERSIONS = 'sfcart_conversions';

	/** Conversion item table suffix. */
	private const ITEMS = 'sfcart_conversion_items';

	/** Return the conversion table name for the current site. */
	public static function conversions(): string {
		global $wpdb;

		return $wpdb->prefix . self::CONVERSIONS;
	}

	/** Return the conversion item table name for the current site. */
	public static function items(): string {
		global $wpdb;

		return $wpdb->prefix . self::ITEMS;
	}

	/**
	 * Create or update analytics tables.
	 */
	public static function install(): void {
		global $wpdb;

		if ( ! defined( 'ABSPATH' ) || ! is_object( $wpdb ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$events  = self::conversions();
		$items   = self::items();

		dbDelta(
			"CREATE TABLE {$events} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				event_key varchar(191) NOT NULL,
				session_id varchar(64) NOT NULL DEFAULT '',
				order_id bigint(20) unsigned NOT NULL DEFAULT 0,
				refund_id bigint(20) unsigned NOT NULL DEFAULT 0,
				event_date datetime NOT NULL,
				type varchar(32) NOT NULL DEFAULT '',
				status varchar(32) NOT NULL DEFAULT '',
				currency varchar(10) NOT NULL DEFAULT '',
				total decimal(26,8) NOT NULL DEFAULT 0,
				revenue decimal(26,8) NOT NULL DEFAULT 0,
				refunded decimal(26,8) NOT NULL DEFAULT 0,
				coupon_code varchar(191) NOT NULL DEFAULT '',
				metadata longtext NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY event_key (event_key),
				KEY session_id (session_id),
				KEY order_id (order_id),
				KEY refund_id (refund_id),
				KEY event_date (event_date),
				KEY type (type),
				KEY currency (currency)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$items} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				conversion_id bigint(20) unsigned NOT NULL DEFAULT 0,
				event_key varchar(191) NOT NULL,
				order_id bigint(20) unsigned NOT NULL DEFAULT 0,
				order_item_id bigint(20) unsigned NOT NULL DEFAULT 0,
				product_id bigint(20) unsigned NOT NULL DEFAULT 0,
				variation_id bigint(20) unsigned NOT NULL DEFAULT 0,
				type varchar(32) NOT NULL DEFAULT '',
				quantity decimal(26,8) NOT NULL DEFAULT 0,
				total decimal(26,8) NOT NULL DEFAULT 0,
				refunded decimal(26,8) NOT NULL DEFAULT 0,
				currency varchar(10) NOT NULL DEFAULT '',
				source varchar(32) NOT NULL DEFAULT '',
				source_product_id bigint(20) unsigned NOT NULL DEFAULT 0,
				metadata longtext NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY event_key (event_key),
				KEY conversion_id (conversion_id),
				KEY order_id (order_id),
				KEY order_item_id (order_item_id),
				KEY product_id (product_id),
				KEY type (type),
				KEY currency (currency)
			) {$charset};"
		);
	}

	/**
	 * Drop analytics tables for explicit uninstall cleanup.
	 */
	public static function drop(): void {
		global $wpdb;

		if ( ! is_object( $wpdb ) ) {
			return;
		}

		$items  = self::items();
		$events = self::conversions();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "DROP TABLE IF EXISTS {$items}" );
		$wpdb->query( "DROP TABLE IF EXISTS {$events}" );
		// phpcs:enable
	}

	/** Prevent construction. */
	private function __construct() {
	}
}
