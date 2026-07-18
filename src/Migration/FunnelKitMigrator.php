<?php
/**
 * One-time FunnelKit Cart data importer.
 *
 * @package StarfinitiCart
 */

namespace Starfiniti\Cart\Migration;

use Starfiniti\Cart\Analytics\Repository;
use Starfiniti\Cart\Analytics\Tables;
use Starfiniti\Cart\Analytics\Time;
use Starfiniti\Cart\Settings;
use Starfiniti\Cart\Support\Logger;

/**
 * Previews and imports legacy FunnelKit Cart data into Starfiniti-owned storage.
 *
 * This is intentionally the only runtime class that knows legacy identifiers.
 * It reads legacy data, writes Starfiniti-owned options/tables, and never
 * registers aliases or ongoing compatibility shims.
 */
final class FunnelKitMigrator {

	/** Maximum legacy order rows handled by one REST request. */
	private const BATCH_SIZE = 200;

	/** Legacy settings option. */
	private const LEGACY_SETTINGS_OPTION = 'fkcart_settings';

	/** Legacy conversion table suffix. */
	private const LEGACY_CART_TABLE = 'fk_cart';

	/** Legacy conversion item table suffix. */
	private const LEGACY_PRODUCTS_TABLE = 'fk_cart_products';

	/** Starfiniti audit option. */
	public const AUDIT_OPTION = 'sfcart_funnelkit_migration_audit';

	/** Starfiniti state option. */
	public const STATE_OPTION = 'sfcart_funnelkit_migration_state';

	/**
	 * Return migration status without changing data.
	 *
	 * @return array<string, mixed>
	 */
	public static function status(): array {
		$state = get_option( self::STATE_OPTION, array() );
		$audit = get_option( self::AUDIT_OPTION, array() );

		return array(
			'state' => is_array( $state ) ? $state : array(),
			'audit' => self::bounded_audit( is_array( $audit ) ? $audit : array() ),
		);
	}

	/**
	 * Preview detected legacy source data and transformed settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function preview(): array {
		$legacy_settings = self::legacy_settings();
		$table_counts    = self::legacy_table_counts();
		$transformed     = self::transform_settings( $legacy_settings );
		$warnings        = array();

		if ( array() === $legacy_settings ) {
			$warnings[] = __( 'No FunnelKit Cart settings option was found.', 'starfiniti-cart' );
		}
		if ( 0 === $table_counts['cart_rows'] && 0 === $table_counts['product_rows'] ) {
			$warnings[] = __( 'No legacy FunnelKit Cart analytics rows were found.', 'starfiniti-cart' );
		}

		return array(
			'available'            => array() !== $legacy_settings || $table_counts['cart_rows'] > 0 || $table_counts['product_rows'] > 0,
			'source_hash'          => self::source_hash( $legacy_settings, $table_counts ),
			'legacy_settings_keys' => array_keys( $legacy_settings ),
			'table_counts'         => $table_counts,
			'transformed_settings' => $transformed,
			'warnings'             => $warnings,
			'status'               => self::status(),
		);
	}

	/**
	 * Run a confirmed, idempotent migration.
	 *
	 * @return array<string, mixed>
	 */
	public static function run(): array {
		$preview  = self::preview();
		$previous = get_option( self::STATE_OPTION, array() );
		$previous = is_array( $previous ) ? $previous : array();
		$resuming = 'running' === (string) ( $previous['status'] ?? '' )
			&& (string) ( $previous['source_hash'] ?? '' ) === (string) $preview['source_hash'];
		$started  = $resuming ? (string) ( $previous['started_at'] ?? self::now() ) : self::now();
		$steps    = $resuming && is_array( $previous['steps'] ?? null ) ? $previous['steps'] : array(
			'preview'   => 'complete',
			'settings'  => 'pending',
			'analytics' => 'pending',
			'audit'     => 'pending',
		);
		$progress = $resuming && is_array( $previous['progress'] ?? null ) ? $previous['progress'] : array(
			'cart_offset'          => 0,
			'cart_total'           => absint( $preview['table_counts']['cart_rows'] ?? 0 ),
			'conversions_imported' => 0,
			'items_imported'       => 0,
			'settings_changed'     => false,
		);

		self::update_state( 'running', $started, (string) $preview['source_hash'], $steps, $progress );

		$settings_changed = false;
		try {
			if ( 'complete' !== (string) ( $steps['settings'] ?? '' ) && is_array( $preview['transformed_settings'] ) && array() !== self::legacy_settings() ) {
				$current                      = Settings::get();
				$next                         = array_replace_recursive( $current, $preview['transformed_settings'] );
				$settings_changed             = Settings::update( $next ) !== $current;
				$progress['settings_changed'] = $settings_changed;
			}
			$steps['settings'] = 'complete';
			self::update_state( 'running', $started, (string) $preview['source_hash'], $steps, $progress );

			$analytics                        = self::import_analytics( absint( $progress['cart_offset'] ?? 0 ) );
			$progress['cart_offset']          = absint( $progress['cart_offset'] ?? 0 ) + $analytics['processed'];
			$progress['conversions_imported'] = absint( $progress['conversions_imported'] ?? 0 ) + $analytics['conversions_imported'];
			$progress['items_imported']       = absint( $progress['items_imported'] ?? 0 ) + $analytics['items_imported'];
			$steps['analytics']               = absint( $progress['cart_offset'] ) >= absint( $progress['cart_total'] ) ? 'complete' : 'running';
			self::update_state( 'running', $started, (string) $preview['source_hash'], $steps, $progress );

			if ( 'complete' !== $steps['analytics'] ) {
				return array(
					'preview' => self::preview(),
					'result'  => array(
						'status'               => 'running',
						'started_at'           => $started,
						'source_hash'          => $preview['source_hash'],
						'conversions_imported' => absint( $progress['conversions_imported'] ),
						'items_imported'       => absint( $progress['items_imported'] ),
						'progress'             => $progress,
					),
				);
			}

			$entry = array(
				'id'                   => 'fkcart-' . gmdate( 'YmdHis' ),
				'started_at'           => $started,
				'completed_at'         => self::now(),
				'status'               => 'complete',
				'source_hash'          => $preview['source_hash'],
				'settings_changed'     => (bool) ( $progress['settings_changed'] ?? $settings_changed ),
				'conversions_imported' => absint( $progress['conversions_imported'] ),
				'items_imported'       => absint( $progress['items_imported'] ),
				'warnings'             => $preview['warnings'],
			);
			self::append_audit( $entry );
			$steps['audit'] = 'complete';
			self::update_state( 'complete', $started, (string) $preview['source_hash'], $steps, $progress );

			return array(
				'preview' => self::preview(),
				'result'  => $entry,
			);
		} catch ( \Throwable $error ) {
			Logger::exception( 'FunnelKit Cart migration failed.', $error );
			$entry = array(
				'id'           => 'fkcart-' . gmdate( 'YmdHis' ),
				'started_at'   => $started,
				'completed_at' => self::now(),
				'status'       => 'failed',
				'source_hash'  => $preview['source_hash'],
				'message'      => $error->getMessage(),
			);
			self::append_audit( $entry );
			self::update_state( 'failed', $started, (string) $preview['source_hash'], $steps, $progress );

			return array(
				'preview' => $preview,
				'result'  => $entry,
			);
		}
	}

	/**
	 * Transform legacy settings into the owned Starfiniti settings subset.
	 *
	 * @param array<string, mixed> $legacy Legacy option value.
	 * @return array<string, mixed>
	 */
	public static function transform_settings( array $legacy ): array {
		if ( array() === $legacy ) {
			return array();
		}

		$defaults            = Settings::defaults();
		$flat                = self::flatten( $legacy );
		$legacy_upsell_style = self::choice(
			$flat,
			array( 'upsell_style', 'upsell_layout', 'layout' ),
			array( 'style1', 'style2', 'style3', 'style4', 'style5' ),
			(string) $defaults['upsells']['layout']
		);
		$upsell_layout       = in_array( $legacy_upsell_style, array( 'style4', 'style5' ), true )
			? ( 'style4' === $legacy_upsell_style ? 'style1' : 'style2' )
			: $legacy_upsell_style;
		$upsell_placement    = in_array( $legacy_upsell_style, array( 'style4', 'style5' ), true )
			? 'after_checkout'
			: 'after_items';
		$settings            = array(
			'cart'          => array(
				'position'        => self::cart_position( $flat, (string) $defaults['cart']['position'] ),
				'width'           => self::integer( $flat, array( 'cart_width', 'drawer_width', 'width' ), (int) $defaults['cart']['width'] ),
				'auto_open'       => self::boolean( $flat, array( 'open_cart', 'auto_open', 'open_side_cart', 'ajax_add_to_cart' ), (bool) $defaults['cart']['auto_open'] ),
				'floating_button' => self::boolean( $flat, array( 'enable_cart_icon', 'floating_button', 'show_floating_icon', 'cart_icon' ), (bool) $defaults['cart']['floating_button'] ),
				'header_cart'     => self::boolean( $flat, array( 'enable_menu', 'header_cart' ), (bool) $defaults['cart']['header_cart'] ),
				'coupons'         => self::boolean( $flat, array( 'enable_coupon', 'coupon', 'coupons' ), (bool) $defaults['cart']['coupons'] ),
				'show_shipping'   => self::boolean( $flat, array( 'show_shipping', 'enable_shipping', 'shipping' ), (bool) $defaults['cart']['show_shipping'] ),
				'show_tax'        => self::boolean( $flat, array( 'show_tax', 'enable_tax', 'tax' ), (bool) $defaults['cart']['show_tax'] ),
				'show_cart_link'  => self::boolean( $flat, array( 'show_view_cart', 'show_cart_link', 'view_cart' ), (bool) $defaults['cart']['show_cart_link'] ),
			),
			'design'        => array(
				'floating_icon'             => self::cart_icon( $flat, array( 'floating_icon' ), (string) $defaults['design']['floating_icon'] ),
				'floating_background'       => self::color( $flat, array( 'css_icon_bg_color', 'floating_background' ), (string) $defaults['design']['floating_background'] ),
				'floating_icon_color'       => self::color( $flat, array( 'css_icon_color', 'floating_icon_color' ), (string) $defaults['design']['floating_icon_color'] ),
				'floating_badge_background' => self::color( $flat, array( 'css_icon_count_bg_color', 'floating_badge_background' ), (string) $defaults['design']['floating_badge_background'] ),
				'floating_badge_color'      => self::color( $flat, array( 'css_icon_count_color', 'floating_badge_color' ), (string) $defaults['design']['floating_badge_color'] ),
				'floating_size'             => self::integer( $flat, array( 'floating_icon_size', 'floating_size' ), (int) $defaults['design']['floating_size'] ),
				'floating_border_radius'    => self::integer( $flat, array( 'css_floating_icon_border_radius', 'floating_border_radius' ), (int) $defaults['design']['floating_border_radius'] ),
				'shortcode_icon'            => self::cart_icon( $flat, array( 'cart_icon' ), (string) $defaults['design']['shortcode_icon'] ),
				'shortcode_show_count'      => self::boolean( $flat, array( 'display_menu_product_count', 'shortcode_show_count' ), (bool) $defaults['design']['shortcode_show_count'] ),
				'shortcode_show_total'      => self::boolean( $flat, array( 'display_menu_total', 'shortcode_show_total' ), (bool) $defaults['design']['shortcode_show_total'] ),
				'shortcode_icon_size'       => self::integer( $flat, array( 'cart_menu_icon_size', 'shortcode_icon_size' ), (int) $defaults['design']['shortcode_icon_size'] ),
				'shortcode_text_size'       => self::integer( $flat, array( 'cart_menu_text_size', 'shortcode_text_size' ), (int) $defaults['design']['shortcode_text_size'] ),
				'accent'                    => self::color( $flat, array( 'accent', 'primary_color', 'button_bg_color', 'cart_primary_color' ), (string) $defaults['design']['accent'] ),
				'accent_hover'              => self::color( $flat, array( 'accent_hover', 'button_hover_color' ), (string) $defaults['design']['accent_hover'] ),
				'background'                => self::color( $flat, array( 'background', 'cart_background', 'drawer_background' ), (string) $defaults['design']['background'] ),
				'text'                      => self::color( $flat, array( 'text', 'text_color', 'cart_text_color' ), (string) $defaults['design']['text'] ),
				'muted'                     => self::color( $flat, array( 'muted', 'sub_text_color', 'secondary_text_color' ), (string) $defaults['design']['muted'] ),
				'border'                    => self::color( $flat, array( 'border', 'border_color' ), (string) $defaults['design']['border'] ),
				'border_radius'             => self::integer( $flat, array( 'border_radius', 'button_border_radius', 'cart_border_radius' ), (int) $defaults['design']['border_radius'] ),
				'overlay_opacity'           => self::integer( $flat, array( 'overlay_opacity', 'overlay' ), (int) $defaults['design']['overlay_opacity'] ),
			),
			'language'      => array(
				'title'             => self::text( $flat, array( 'cart_heading', 'cart_title', 'heading', 'title' ), '' ),
				'close'             => self::text( $flat, array( 'close_text', 'close', 'close_cart' ), '' ),
				'empty_title'       => self::text( $flat, array( 'empty_cart_heading', 'empty_title' ), '' ),
				'empty_message'     => self::text( $flat, array( 'empty_cart_message', 'empty_message' ), '' ),
				'continue_shopping' => self::text( $flat, array( 'continue_shopping_text', 'continue_shopping' ), '' ),
				'coupon_code'       => self::text( $flat, array( 'coupon_placeholder', 'coupon_code' ), '' ),
				'apply_coupon'      => self::text( $flat, array( 'apply_coupon_text', 'apply_coupon' ), '' ),
				'checkout'          => self::text( $flat, array( 'checkout_button_text', 'checkout' ), '' ),
				'view_cart'         => self::text( $flat, array( 'view_cart_text', 'view_cart' ), '' ),
				'open_cart'         => self::text( $flat, array( 'cart_icon_text', 'open_cart' ), '' ),
			),
			'upsells'       => array(
				'enabled'              => self::boolean( $flat, array( 'enable_upsell', 'upsell_enable', 'upsells_enabled' ), (bool) $defaults['upsells']['enabled'] ),
				'mode'                 => self::mode( $flat, (string) $defaults['upsells']['mode'] ),
				'layout'               => $upsell_layout,
				'placement'            => $upsell_placement,
				'heading'              => self::text( $flat, array( 'upsell_heading', 'recommendation_heading' ), (string) $defaults['upsells']['heading'] ),
				'default_product_ids'  => self::identifiers( $flat, array( 'default_upsell_products', 'default_products', 'upsell_products' ) ),
				'excluded_product_ids' => self::identifiers( $flat, array( 'exclude_products', 'excluded_products' ) ),
				'display_limit'        => self::integer( $flat, array( 'upsell_count', 'upsell_limit', 'display_limit' ), (int) $defaults['upsells']['display_limit'] ),
				'always_show_defaults' => self::boolean( $flat, array( 'always_show_upsells', 'always_show_defaults' ), (bool) $defaults['upsells']['always_show_defaults'] ),
			),
			'special_addon' => array(
				'enabled'        => self::boolean( $flat, array( 'enable_special_addon', 'special_addon_enable' ), (bool) $defaults['special_addon']['enabled'] ),
				'product_id'     => self::integer( $flat, array( 'special_addon_product', 'special_addon_product_id', 'addon_product_id' ), 0 ),
				'preselected'    => self::boolean( $flat, array( 'special_addon_preselect', 'special_addon_preselected' ), (bool) $defaults['special_addon']['preselected'] ),
				'selection_type' => self::choice( $flat, array( 'special_addon_selection_type', 'addon_selection_type' ), array( 'checkbox', 'toggle' ), (string) $defaults['special_addon']['selection_type'] ),
				'heading'        => self::text( $flat, array( 'special_addon_heading', 'addon_heading' ), (string) $defaults['special_addon']['heading'] ),
				'description'    => self::text( $flat, array( 'special_addon_description', 'addon_description' ), '' ),
			),
		);

		return Settings::sanitize( $settings );
	}

	/**
	 * Import legacy order analytics into Starfiniti tables idempotently.
	 *
	 * @param int $offset Legacy order-row cursor.
	 * @return array{processed: int, conversions_imported: int, items_imported: int}
	 */
	private static function import_analytics( int $offset ): array {
		global $wpdb;

		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_results' ) ) {
			return array(
				'processed'            => 0,
				'conversions_imported' => 0,
				'items_imported'       => 0,
			);
		}

		$cart_table     = self::legacy_table( self::LEGACY_CART_TABLE );
		$products_table = self::legacy_table( self::LEGACY_PRODUCTS_TABLE );
		$cart_rows      = self::table_exists( $cart_table ) ? (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$cart_table} ORDER BY oid ASC LIMIT %d OFFSET %d", self::BATCH_SIZE, absint( $offset ) ), ARRAY_A ) : array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$order_ids      = array_values( array_unique( array_filter( array_map( static fn( array $row ): int => absint( $row['oid'] ?? 0 ), $cart_rows ) ) ) );
		$product_rows   = array();
		if ( self::table_exists( $products_table ) && array() !== $order_ids ) {
			$placeholders = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );
			$product_rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$products_table} WHERE oid IN ({$placeholders}) ORDER BY oid ASC", $order_ids ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}
		$products = array();
		$imported = array(
			'processed'            => count( $cart_rows ),
			'conversions_imported' => 0,
			'items_imported'       => 0,
		);

		foreach ( $product_rows as $row ) {
			$order_id                = absint( $row['oid'] ?? 0 );
			$products[ $order_id ][] = $row;
		}

		foreach ( $cart_rows as $row ) {
			$order_id = absint( $row['oid'] ?? 0 );
			if ( 0 === $order_id ) {
				continue;
			}
			$type          = self::legacy_conversion_type( $row, $products[ $order_id ] ?? array() );
			$conversion_id = Repository::upsert_conversion(
				array(
					'event_key'   => 'legacy-fkcart-order-' . $order_id,
					'order_id'    => $order_id,
					'event_date'  => Time::local_mysql_to_utc( (string) ( $row['date_created'] ?? self::now() ) ),
					'type'        => $type,
					'status'      => 'imported',
					'currency'    => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
					'coupon_code' => (string) ( $row['discount'] ?? '' ),
					'metadata'    => array(
						'legacy_order_number'  => (string) ( $row['onumber'] ?? '' ),
						'legacy_free_shipping' => absint( $row['free_shipping'] ?? 0 ),
					),
				)
			);
			if ( $conversion_id > 0 ) {
				++$imported['conversions_imported'];
			}

			foreach ( $products[ $order_id ] ?? array() as $product ) {
				$product_signature = substr( hash( 'sha256', (string) wp_json_encode( $product ) ), 0, 16 );
				$item_id           = Repository::upsert_item(
					array(
						'conversion_id' => $conversion_id,
						'event_key'     => 'legacy-fkcart-order-' . $order_id . '-item-' . $product_signature,
						'order_id'      => $order_id,
						'product_id'    => absint( $product['product_id'] ?? 0 ),
						'type'          => self::legacy_product_type( absint( $product['type'] ?? 0 ) ),
						'quantity'      => 1,
						'total'         => (float) ( $product['price'] ?? 0 ),
						'currency'      => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
						'source'        => 'legacy_migration',
					)
				);
				if ( $item_id > 0 ) {
					++$imported['items_imported'];
				}
			}
		}

		return $imported;
	}

	/**
	 * Read legacy settings.
	 *
	 * @return array<string, mixed>
	 */
	private static function legacy_settings(): array {
		$settings = get_option( self::LEGACY_SETTINGS_OPTION, array() );

		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * Count legacy tables if present.
	 *
	 * @return array{cart_rows: int, product_rows: int}
	 */
	private static function legacy_table_counts(): array {
		global $wpdb;

		$counts = array(
			'cart_rows'    => 0,
			'product_rows' => 0,
		);

		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_var' ) ) {
			return $counts;
		}

		$cart_table = self::legacy_table( self::LEGACY_CART_TABLE );
		if ( self::table_exists( $cart_table ) ) {
			$counts['cart_rows'] = absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$cart_table}" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		$products_table = self::legacy_table( self::LEGACY_PRODUCTS_TABLE );
		if ( self::table_exists( $products_table ) ) {
			$counts['product_rows'] = absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$products_table}" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		return $counts;
	}

	/**
	 * Build a prefixed legacy table name.
	 *
	 * @param string $suffix Legacy table suffix.
	 */
	private static function legacy_table( string $suffix ): string {
		global $wpdb;

		return is_object( $wpdb ) && isset( $wpdb->prefix ) ? $wpdb->prefix . $suffix : $suffix;
	}

	/**
	 * Check whether a table exists.
	 *
	 * @param string $table Table name.
	 */
	private static function table_exists( string $table ): bool {
		global $wpdb;

		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_var' ) || ! method_exists( $wpdb, 'esc_like' ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $table === (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	}

	/**
	 * Update resumable migration state.
	 *
	 * @param string               $status      Migration status.
	 * @param string               $started     Start timestamp.
	 * @param string               $source_hash Source hash.
	 * @param array                $steps       Step state map.
	 * @param array<string, mixed> $progress Persistent batch cursor and counters.
	 * @phpstan-param array<string, string> $steps
	 */
	private static function update_state( string $status, string $started, string $source_hash, array $steps, array $progress ): void {
		update_option(
			self::STATE_OPTION,
			array(
				'status'      => $status,
				'started_at'  => $started,
				'updated_at'  => self::now(),
				'source_hash' => $source_hash,
				'steps'       => $steps,
				'progress'    => $progress,
			),
			false
		);
	}

	/**
	 * Append a bounded audit entry.
	 *
	 * @param array<string, mixed> $entry Audit event.
	 */
	private static function append_audit( array $entry ): void {
		$audit   = get_option( self::AUDIT_OPTION, array() );
		$audit   = is_array( $audit ) ? $audit : array();
		$audit[] = $entry;

		update_option( self::AUDIT_OPTION, self::bounded_audit( $audit ), false );
	}

	/**
	 * Keep only recent audit entries.
	 *
	 * @param array<int, mixed> $audit Raw audit entries.
	 * @return list<array<string, mixed>>
	 */
	private static function bounded_audit( array $audit ): array {
		return array_values(
			array_filter(
				array_slice( $audit, -25 ),
				static fn( mixed $entry ): bool => is_array( $entry )
			)
		);
	}

	/**
	 * Stable hash for change detection.
	 *
	 * @param array<string, mixed> $settings Legacy settings.
	 * @param array<string, int>   $counts Legacy table counts.
	 */
	private static function source_hash( array $settings, array $counts ): string {
		$encoded = wp_json_encode(
			array(
				'settings' => $settings,
				'counts'   => $counts,
			)
		);

		return hash( 'sha256', is_string( $encoded ) ? $encoded : '' );
	}

	/**
	 * Flatten nested settings to lower-case lookup keys.
	 *
	 * @param array<string, mixed> $source Source data.
	 * @return array<string, mixed>
	 */
	private static function flatten( array $source ): array {
		$flat = array();
		foreach ( $source as $key => $value ) {
			$key          = strtolower( (string) $key );
			$flat[ $key ] = $value;
			if ( is_array( $value ) ) {
				foreach ( self::flatten( $value ) as $child_key => $child_value ) {
					$flat[ $child_key ]              = $child_value;
					$flat[ $key . '_' . $child_key ] = $child_value;
				}
				continue;
			}
		}

		return $flat;
	}

	/**
	 * Read a scalar legacy value by possible keys.
	 *
	 * @param array<string, mixed> $flat Legacy lookup map.
	 * @param array                $keys Candidate keys.
	 * @phpstan-param list<string> $keys
	 */
	private static function scalar( array $flat, array $keys ): mixed {
		foreach ( $keys as $key ) {
			$lookup = strtolower( $key );
			if ( array_key_exists( $lookup, $flat ) && is_scalar( $flat[ $lookup ] ) ) {
				return $flat[ $lookup ];
			}
		}

		return null;
	}

	/**
	 * Read text.
	 *
	 * @param array<string, mixed> $flat Legacy lookup map.
	 * @param array                $keys Candidate keys.
	 * @param string               $fallback Fallback text.
	 * @phpstan-param list<string> $keys
	 */
	private static function text( array $flat, array $keys, string $fallback ): string {
		$value = self::scalar( $flat, $keys );

		return null === $value ? $fallback : trim( wp_strip_all_tags( (string) $value ) );
	}

	/**
	 * Read a boolean.
	 *
	 * @param array<string, mixed> $flat Legacy lookup map.
	 * @param array                $keys Candidate keys.
	 * @param bool                 $fallback Fallback value.
	 * @phpstan-param list<string> $keys
	 */
	private static function boolean( array $flat, array $keys, bool $fallback ): bool {
		$value = self::scalar( $flat, $keys );
		if ( null === $value ) {
			return $fallback;
		}

		return true === $value || 1 === $value || in_array( strtolower( (string) $value ), array( '1', 'yes', 'true', 'on', 'enable', 'enabled' ), true );
	}

	/**
	 * Read an integer.
	 *
	 * @param array<string, mixed> $flat Legacy lookup map.
	 * @param array                $keys Candidate keys.
	 * @param int                  $fallback Fallback value.
	 * @phpstan-param list<string> $keys
	 */
	private static function integer( array $flat, array $keys, int $fallback ): int {
		$value = self::scalar( $flat, $keys );

		return is_numeric( $value ) ? (int) $value : $fallback;
	}

	/**
	 * Read a known choice.
	 *
	 * @param array<string, mixed> $flat Legacy lookup map.
	 * @param array                $keys Candidate keys.
	 * @param array                $choices Allowed choices.
	 * @param string               $fallback Fallback value.
	 * @phpstan-param list<string> $keys
	 * @phpstan-param list<string> $choices
	 */
	private static function choice( array $flat, array $keys, array $choices, string $fallback ): string {
		$value = self::scalar( $flat, $keys );
		$value = is_scalar( $value ) ? strtolower( (string) $value ) : '';

		return in_array( $value, $choices, true ) ? $value : $fallback;
	}

	/**
	 * Map FunnelKit bottom positions to the owned drawer side.
	 *
	 * @param array<string, mixed> $flat Legacy lookup map.
	 * @param string               $fallback Fallback position.
	 */
	private static function cart_position( array $flat, string $fallback ): string {
		$value = self::scalar( $flat, array( 'cart_icon_position', 'cart_position', 'drawer_position', 'side_cart_position', 'position' ) );
		$value = is_scalar( $value ) ? strtolower( (string) $value ) : '';

		return match ( $value ) {
			'bottom-left', 'left' => 'left',
			'bottom-right', 'right' => 'right',
			default => $fallback,
		};
	}

	/**
	 * Map FunnelKit's four cart icon identifiers to the owned Lucide set.
	 *
	 * @param array<string, mixed> $flat Legacy lookup map.
	 * @param array                $keys Candidate keys.
	 * @param string               $fallback Fallback icon.
	 * @phpstan-param list<string> $keys
	 */
	private static function cart_icon( array $flat, array $keys, string $fallback ): string {
		$value = self::scalar( $flat, $keys );
		$value = is_scalar( $value ) ? strtolower( (string) $value ) : '';

		return match ( $value ) {
			'cart_1', 'shopping-cart' => 'shopping-cart',
			'cart_2', 'shopping-bag' => 'shopping-bag',
			'cart_3', 'shopping-basket' => 'shopping-basket',
			'cart_4', 'baggage-claim' => 'baggage-claim',
			default => $fallback,
		};
	}

	/**
	 * Read the recommendation mode.
	 *
	 * @param array<string, mixed> $flat Legacy lookup map.
	 * @param string               $fallback Fallback mode.
	 */
	private static function mode( array $flat, string $fallback ): string {
		$value = self::choice( $flat, array( 'upsell_type', 'recommendation_source', 'mode' ), array( 'upsells', 'cross_sells', 'both' ), $fallback );
		if ( 'crosssell' === $value || 'cross_sell' === $value ) {
			return 'cross_sells';
		}

		return $value;
	}

	/**
	 * Read a hex color.
	 *
	 * @param array<string, mixed> $flat Legacy lookup map.
	 * @param array                $keys Candidate keys.
	 * @param string               $fallback Fallback color.
	 * @phpstan-param list<string> $keys
	 */
	private static function color( array $flat, array $keys, string $fallback ): string {
		$value = self::scalar( $flat, $keys );
		$value = is_scalar( $value ) ? (string) $value : '';

		return 1 === preg_match( '/^#[0-9a-fA-F]{6}$/', $value ) ? strtolower( $value ) : $fallback;
	}

	/**
	 * Read product identifiers from array or delimited scalar values.
	 *
	 * @param array<string, mixed> $flat Legacy lookup map.
	 * @param array                $keys Candidate keys.
	 * @return array
	 * @phpstan-param list<string> $keys
	 * @phpstan-return list<int>
	 */
	private static function identifiers( array $flat, array $keys ): array {
		foreach ( $keys as $key ) {
			$lookup = strtolower( $key );
			if ( ! array_key_exists( $lookup, $flat ) ) {
				continue;
			}
			$value = $flat[ $lookup ];
			if ( is_scalar( $value ) ) {
				$value = preg_split( '/[,|]/', (string) $value );
			}
			if ( is_array( $value ) ) {
				return array_values( array_unique( array_filter( array_map( 'absint', $value ) ) ) );
			}
		}

		return array();
	}

	/**
	 * Classify one legacy conversion row.
	 *
	 * @param array<string, mixed>       $row Order row.
	 * @param list<array<string, mixed>> $products Legacy product rows.
	 */
	private static function legacy_conversion_type( array $row, array $products ): string {
		foreach ( $products as $product ) {
			if ( 1 === absint( $product['type'] ?? 0 ) ) {
				return 'upsell';
			}
			if ( 3 === absint( $product['type'] ?? 0 ) ) {
				return 'special_addon';
			}
		}
		if ( 1 === absint( $row['free_shipping'] ?? 0 ) || '' !== (string) ( $row['discount'] ?? '' ) ) {
			return 'reward';
		}

		return 'legacy_migration';
	}

	/**
	 * Map a legacy product type integer to owned analytics type.
	 *
	 * @param int $type Legacy product type.
	 */
	private static function legacy_product_type( int $type ): string {
		return match ( $type ) {
			1 => 'upsell',
			2 => 'reward_gift',
			3 => 'special_addon',
			default => 'legacy_migration',
		};
	}

	/**
	 * Current MySQL UTC timestamp.
	 */
	private static function now(): string {
		return function_exists( 'current_time' ) ? current_time( 'mysql', true ) : gmdate( 'Y-m-d H:i:s' );
	}

	/** Prevent construction. */
	private function __construct() {
	}
}
