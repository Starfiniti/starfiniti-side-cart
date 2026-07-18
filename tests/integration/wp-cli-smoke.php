<?php
/**
 * Real WordPress, WooCommerce, and MySQL integration assertions for CI.
 *
 * @package StarfinitiCart
 */

use Starfiniti\Cart\Analytics\CsvExporter;
use Starfiniti\Cart\Analytics\Recorder;
use Starfiniti\Cart\Analytics\Reports;
use Starfiniti\Cart\Analytics\Repository;
use Starfiniti\Cart\Analytics\Tables;
use Starfiniti\Cart\Analytics\Time;
use Starfiniti\Cart\Cart\ProductSnapshot;
use Starfiniti\Cart\Plugin;
use Starfiniti\Cart\Settings;

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
};

$assert( class_exists( Plugin::class ) && Plugin::instance()->is_ready(), 'Plugin runtime is not ready.' );
$assert( 1 === did_action( 'sfcart_loaded' ), 'Plugin loaded action did not fire exactly once.' );
$assert( Settings::SCHEMA_VERSION === (int) Settings::get()['settings_version'], 'Settings schema was not installed.' );

global $wpdb;
$events = Tables::conversions();
$items  = Tables::items();
$assert( $events === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $events ) ) ), 'Conversions table is missing.' );
$assert( $items === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $items ) ) ), 'Conversion items table is missing.' );

$product = new WC_Product_Simple();
$product->set_name( 'Starfiniti integration product' );
$product->set_status( 'publish' );
$product->set_regular_price( '19.99' );
$product->set_manage_stock( true );
$product->set_stock_quantity( 10 );
$product_id = $product->save();
$assert( $product_id > 0, 'WooCommerce product could not be created.' );
$stored_product = wc_get_product( $product_id );
$assert( $stored_product instanceof WC_Product && ProductSnapshot::is_supported( $stored_product ), 'Simple WooCommerce product is not drawer-compatible.' );

$event_id = Repository::upsert_conversion(
	array(
		'event_key'  => 'integration:cart-open',
		'session_id' => 'abcdef0123456789',
		'event_date' => time(),
		'type'       => 'cart_open',
		'status'     => 'non_empty',
		'currency'   => get_woocommerce_currency(),
	)
);
$assert( $event_id > 0, 'Analytics event could not be written.' );

$updated_event_id = Repository::upsert_conversion(
	array(
		'event_key'  => 'integration:cart-open',
		'session_id' => 'abcdef0123456789',
		'event_date' => time(),
		'type'       => 'cart_open',
		'status'     => 'non_empty',
		'currency'   => get_woocommerce_currency(),
	)
);
$assert( $updated_event_id === $event_id, 'Atomic analytics upsert did not return the existing identifier.' );

$filters  = array(
	'from'       => Time::site_date(),
	'to'         => Time::site_date(),
	'type'       => '',
	'product_id' => 0,
	'coupon'     => '',
	'currency'   => '',
);
$overview = Reports::overview( $filters );
$assert( (int) $overview['cart_opens'] >= 1, 'Analytics report did not reconcile the persisted cart event.' );

for ( $index = 0; $index <= 100; $index++ ) {
	Repository::upsert_conversion(
		array(
			'event_key'  => sprintf( 'integration:export:%03d', $index ),
			'session_id' => 'abcdef0123456789',
			'event_date' => time(),
			'type'       => 'cart_interaction',
			'status'     => sprintf( 'export-%03d', $index ),
			'currency'   => get_woocommerce_currency(),
		)
	);
}
$csv = CsvExporter::conversions( $filters );
$assert( str_contains( $csv, 'export-000' ) && str_contains( $csv, 'export-100' ), 'CSV export truncated matching events after 100 rows.' );

Recorder::record_recommendation_impression(
	array(
		'product_id'        => $product_id,
		'source'            => 'default',
		'source_product_id' => 0,
		'viewed_at'         => time(),
	)
);
$impression_like = $wpdb->esc_like( 'impression-item:' ) . '%';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$impression = $wpdb->get_row( $wpdb->prepare( "SELECT id, conversion_id FROM {$items} WHERE product_id = %d AND event_key LIKE %s ORDER BY id DESC LIMIT 1", $product_id, $impression_like ), ARRAY_A );
$assert( is_array( $impression ) && absint( $impression['conversion_id'] ?? 0 ) > 0, 'Recommendation impression item was not linked to its conversion.' );
$impression_event_id = absint( $impression['conversion_id'] ?? 0 );
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$impression_session = (string) $wpdb->get_var( $wpdb->prepare( "SELECT session_id FROM {$events} WHERE id = %d", $impression_event_id ) );
$assert( '' !== $impression_session, 'Recommendation impression conversion did not retain its analytics session.' );

$wpdb->delete( $events, array( 'event_key' => 'integration:cart-open' ) );
$export_like = $wpdb->esc_like( 'integration:export:' ) . '%';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
$wpdb->query( $wpdb->prepare( "DELETE FROM {$events} WHERE event_key LIKE %s", $export_like ) );
$wpdb->delete( $items, array( 'id' => absint( $impression['id'] ?? 0 ) ) );
$wpdb->delete( $events, array( 'id' => $impression_event_id ) );
wp_delete_post( $product_id, true );

WP_CLI::success( 'Starfiniti Cart WordPress/WooCommerce/MySQL integration assertions passed.' );
