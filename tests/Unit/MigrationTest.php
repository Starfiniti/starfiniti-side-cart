<?php
/**
 * FunnelKit migration tests.
 *
 * @package StarfinitiCart
 */

declare(strict_types=1);

namespace Starfiniti\Cart\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Starfiniti\Cart\Analytics\Tables;
use Starfiniti\Cart\Migration\FunnelKitMigrator;
use Starfiniti\Cart\Settings;
use Starfiniti\Cart\Tests\Fixtures\FakeWpdb;

/**
 * Verifies legacy cart data is copied into owned storage idempotently.
 */
final class MigrationTest extends TestCase {

	/**
	 * Fake database adapter.
	 *
	 * @var FakeWpdb
	 */
	private FakeWpdb $wpdb;

	/**
	 * Reset isolated WordPress state.
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['sfcart_test_options'] = array();
		$this->wpdb                     = new FakeWpdb();
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Isolated migration unit test.
		$GLOBALS['wpdb'] = $this->wpdb;
	}

	/**
	 * Legacy option values are normalized into Starfiniti settings.
	 */
	public function test_preview_transforms_legacy_settings_without_writing(): void {
		update_option(
			'fkcart_settings',
			array(
				'cart_position'           => 'left',
				'cart_width'              => 520,
				'enable_coupon'           => 'no',
				'primary_color'           => '#123456',
				'upsell_type'             => 'upsells',
				'upsell_style'            => 'style2',
				'default_upsell_products' => array( 10, 10, 11 ),
				'special_addon_product'   => 99,
			)
		);

		$preview = FunnelKitMigrator::preview();

		self::assertTrue( $preview['available'] );
		self::assertSame( 'left', $preview['transformed_settings']['cart']['position'] );
		self::assertSame( 520, $preview['transformed_settings']['cart']['width'] );
		self::assertFalse( $preview['transformed_settings']['cart']['coupons'] );
		self::assertSame( '#123456', $preview['transformed_settings']['design']['accent'] );
		self::assertSame( array( 10, 11 ), $preview['transformed_settings']['upsells']['default_product_ids'] );
		self::assertSame( 'style2', $preview['transformed_settings']['upsells']['layout'] );
		self::assertSame( 'after_items', $preview['transformed_settings']['upsells']['placement'] );
		self::assertSame( 99, $preview['transformed_settings']['special_addon']['product_id'] );
		self::assertFalse( get_option( Settings::OPTION_NAME, false ) );
	}

	/** FunnelKit footer styles retain their layout and migrate below checkout. */
	public function test_footer_upsell_style_migrates_to_independent_placement(): void {
		$settings = FunnelKitMigrator::transform_settings(
			array( 'upsell_style' => 'style5' )
		);

		self::assertSame( 'style2', $settings['upsells']['layout'] );
		self::assertSame( 'after_checkout', $settings['upsells']['placement'] );
	}

	/**
	 * Running the importer twice updates owned rows without deleting legacy rows.
	 */
	public function test_run_copies_legacy_rows_idempotently_and_keeps_legacy_data(): void {
		update_option( 'fkcart_settings', array( 'cart_position' => 'left' ) );
		$this->wpdb->tables['wp_fk_cart']          = array(
			array(
				'oid'           => 123,
				'onumber'       => '100123',
				'discount'      => 'SAVE10',
				'free_shipping' => 1,
				'date_created'  => '2026-07-16 10:00:00',
			),
		);
		$this->wpdb->tables['wp_fk_cart_products'] = array(
			array(
				'oid'        => 123,
				'product_id' => 55,
				'price'      => 19.99,
				'type'       => 1,
			),
			array(
				'oid'        => 123,
				'product_id' => 77,
				'price'      => 0,
				'type'       => 2,
			),
		);

		$first  = FunnelKitMigrator::run();
		$second = FunnelKitMigrator::run();

		self::assertSame( 'complete', $first['result']['status'] );
		self::assertSame( 'complete', $second['result']['status'] );
		self::assertCount( 1, $this->wpdb->tables[ Tables::conversions() ] );
		self::assertCount( 2, $this->wpdb->tables[ Tables::items() ] );
		self::assertCount( 1, $this->wpdb->tables['wp_fk_cart'] );
		self::assertCount( 2, $this->wpdb->tables['wp_fk_cart_products'] );
		self::assertSame( 'left', Settings::get()['cart']['position'] );
		self::assertCount( 2, get_option( FunnelKitMigrator::AUDIT_OPTION, array() ) );
	}

	/** Large imports persist their cursor and complete across bounded requests. */
	public function test_large_migration_resumes_from_persisted_cursor(): void {
		$rows = array();
		for ( $order_id = 1; $order_id <= 201; ++$order_id ) {
			$rows[] = array(
				'oid'          => $order_id,
				'date_created' => '2026-07-16 10:00:00',
			);
		}
		$this->wpdb->tables['wp_fk_cart'] = $rows;

		$first = FunnelKitMigrator::run();

		self::assertSame( 'running', $first['result']['status'] );
		self::assertSame( 200, $first['result']['progress']['cart_offset'] );
		self::assertSame( 201, $first['result']['progress']['cart_total'] );
		self::assertCount( 200, $this->wpdb->tables[ Tables::conversions() ] );

		$second = FunnelKitMigrator::run();

		self::assertSame( 'complete', $second['result']['status'] );
		self::assertCount( 201, $this->wpdb->tables[ Tables::conversions() ] );
		self::assertSame( 201, get_option( FunnelKitMigrator::STATE_OPTION, array() )['progress']['cart_offset'] );
	}
}
