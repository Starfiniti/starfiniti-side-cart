<?php
/**
 * Settings and migration tests.
 *
 * @package StarfinitiCart
 */

declare(strict_types=1);

namespace Starfiniti\Cart\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Starfiniti\Cart\Lifecycle\Installer;
use Starfiniti\Cart\Settings;

/**
 * Verify retain-by-default settings and idempotent migrations.
 */
final class SettingsTest extends TestCase {

	/**
	 * Reset the in-memory WordPress option stubs.
	 */
	protected function setUp(): void {
		$GLOBALS['sfcart_test_options'] = array();
		$GLOBALS['sfcart_test_actions'] = array();
	}

	/**
	 * Destructive uninstall must be disabled by default.
	 */
	public function test_delete_data_is_disabled_by_default(): void {
		self::assertFalse( Settings::defaults()[ Settings::DELETE_DATA_KEY ] );
		self::assertFalse( Settings::sanitize( array() )[ Settings::DELETE_DATA_KEY ] );
	}

	/** Analytics retention is finite and constrained to the supported range. */
	public function test_analytics_retention_is_bounded(): void {
		self::assertSame( 365, Settings::sanitize( array() )[ Settings::ANALYTICS_RETENTION_KEY ] );
		self::assertSame( 30, Settings::sanitize( array( Settings::ANALYTICS_RETENTION_KEY => 1 ) )[ Settings::ANALYTICS_RETENTION_KEY ] );
		self::assertSame( 3650, Settings::sanitize( array( Settings::ANALYTICS_RETENTION_KEY => 9999 ) )[ Settings::ANALYTICS_RETENTION_KEY ] );
	}

	/** Page cache is purged only when the effective settings document changes. */
	public function test_settings_update_emits_change_action_only_for_changes(): void {
		$settings = Settings::defaults();
		$GLOBALS['sfcart_test_options'][ Settings::OPTION_NAME ] = $settings;
		Settings::update( $settings );
		self::assertNotContains( 'sfcart_settings_updated', array_column( $GLOBALS['sfcart_test_actions'], 0 ) );

		$settings['cart']['width'] = 480;
		Settings::update( $settings );
		self::assertContains( 'sfcart_settings_updated', array_column( $GLOBALS['sfcart_test_actions'], 0 ) );
	}

	/**
	 * Unknown settings and untrusted derived media URLs are never persisted.
	 */
	public function test_settings_document_is_strictly_normalized(): void {
		$settings = Settings::sanitize(
			array(
				'cart'         => array(
					'position' => 'sideways',
					'width'    => 9999,
				),
				'design'       => array(
					'accent'          => 'red',
					'empty_image_id'  => -20,
					'empty_image_url' => 'https://attacker.invalid/image.jpg',
				),
				'language'     => array( 'title' => '<b>Basket</b>' ),
				'unknown_data' => 'discard me',
			)
		);

		self::assertSame( Settings::SCHEMA_VERSION, $settings['settings_version'] );
		self::assertSame( 'right', $settings['cart']['position'] );
		self::assertSame( 640, $settings['cart']['width'] );
		self::assertSame( '#1d4ed8', $settings['design']['accent'] );
		self::assertSame( 0, $settings['design']['empty_image_id'] );
		self::assertSame( '', $settings['design']['empty_image_url'] );
		self::assertTrue( $settings['cart']['header_cart'] );
		self::assertTrue( $settings['cart']['show_continue_shopping'] );
		self::assertSame( 'shopping-cart', $settings['design']['floating_icon'] );
		self::assertSame( 'shopping-cart', $settings['design']['shortcode_icon'] );
		self::assertSame( 0, $settings['design']['floating_icon_id'] );
		self::assertSame( '', $settings['design']['floating_icon_url'] );
		self::assertSame( '#ffffff', $settings['design']['floating_background'] );
		self::assertSame( '#111827', $settings['design']['floating_icon_color'] );
		self::assertSame( 56, $settings['design']['floating_size'] );
		self::assertSame( '#ffffff', $settings['design']['shortcode_background'] );
		self::assertSame( 1, $settings['design']['shortcode_border_width'] );
		self::assertSame( 8, $settings['design']['shortcode_border_radius'] );
		self::assertSame( 'Basket', $settings['language']['title'] );
		self::assertArrayNotHasKey( 'unknown_data', $settings );

		$with_media = Settings::sanitize(
			array( 'design' => array( 'empty_image_id' => 42 ) )
		);
		self::assertSame(
			'https://example.test/media/42.jpg',
			$with_media['design']['empty_image_url']
		);

		$with_custom_icon = Settings::sanitize(
			array(
				'design' => array(
					'floating_icon'     => 'custom',
					'floating_icon_id'  => 17,
					'shortcode_icon'    => 'custom',
					'shortcode_icon_id' => 18,
				),
			)
		);
		self::assertSame( 'custom', $with_custom_icon['design']['floating_icon'] );
		self::assertSame( 'https://example.test/media/17.jpg', $with_custom_icon['design']['floating_icon_url'] );
		self::assertSame( 'https://example.test/media/18.jpg', $with_custom_icon['design']['shortcode_icon_url'] );

		$legacy_shared_icon = Settings::sanitize(
			array( 'design' => array( 'cart_icon' => 'shopping-basket' ) )
		);
		self::assertSame( 'shopping-basket', $legacy_shared_icon['design']['floating_icon'] );
		self::assertSame( 'shopping-basket', $legacy_shared_icon['design']['shortcode_icon'] );

		$with_transparency = Settings::sanitize(
			array(
				'design' => array(
					'floating_background'  => '#ffffff00',
					'shortcode_background' => '#12345680',
				),
			)
		);
		self::assertSame( '#ffffff00', $with_transparency['design']['floating_background'] );
		self::assertSame( '#12345680', $with_transparency['design']['shortcode_background'] );
	}

	/**
	 * Invalid administration values return precise field paths.
	 */
	public function test_validation_reports_invalid_fields(): void {
		$errors = Settings::validation_errors(
			array(
				'cart'   => array(
					'position' => 'top',
					'width'    => 200,
				),
				'design' => array(
					'accent'                  => '#12345z',
					'border_radius'           => 50,
					'overlay_opacity'         => 100,
					'floating_size'           => 20,
					'floating_border_radius'  => 80,
					'shortcode_icon_size'     => 4,
					'shortcode_text_size'     => 100,
					'shortcode_border_width'  => 10,
					'shortcode_border_radius' => 80,
				),
			)
		);

		self::assertArrayHasKey( 'cart.position', $errors );
		self::assertArrayHasKey( 'cart.width', $errors );
		self::assertArrayHasKey( 'design.accent', $errors );
		self::assertArrayHasKey( 'design.border_radius', $errors );
		self::assertArrayHasKey( 'design.overlay_opacity', $errors );
		self::assertArrayHasKey( 'design.floating_size', $errors );
		self::assertArrayHasKey( 'design.floating_border_radius', $errors );
		self::assertArrayHasKey( 'design.shortcode_icon_size', $errors );
		self::assertArrayHasKey( 'design.shortcode_text_size', $errors );
		self::assertArrayHasKey( 'design.shortcode_border_width', $errors );
		self::assertArrayHasKey( 'design.shortcode_border_radius', $errors );

		$icon_errors = Settings::validation_errors(
			array(
				'design' => array(
					'floating_icon'     => 'custom',
					'floating_icon_id'  => 0,
					'shortcode_icon'    => 'custom',
					'shortcode_icon_id' => 0,
				),
			)
		);
		self::assertArrayHasKey( 'design.floating_icon_id', $icon_errors );
		self::assertArrayHasKey( 'design.shortcode_icon_id', $icon_errors );

		$recommendation_errors = Settings::validation_errors(
			array(
				'upsells' => array(
					'layout'    => 'style9',
					'placement' => 'somewhere',
					'mode'      => 'unknown',
					'ordering'  => 'random',
				),
			)
		);
		self::assertArrayHasKey( 'upsells.layout', $recommendation_errors );
		self::assertArrayHasKey( 'upsells.placement', $recommendation_errors );
		self::assertArrayHasKey( 'upsells.mode', $recommendation_errors );
		self::assertArrayHasKey( 'upsells.ordering', $recommendation_errors );
	}

	/**
	 * Recommendation settings retain only supported layouts, orders, and IDs.
	 */
	public function test_recommendation_settings_are_strictly_normalized(): void {
		$settings = Settings::sanitize(
			array(
				'upsells' => array(
					'enabled'              => 'yes',
					'layout'               => 'carousel',
					'placement'            => 'before_totals',
					'heading'              => '<strong>Complete it</strong>',
					'ordering'             => 'price_desc',
					'default_product_ids'  => array( 4, '4', -1, 7 ),
					'excluded_product_ids' => array( 8, 0, 8, 9 ),
				),
			)
		);

		self::assertTrue( $settings['upsells']['enabled'] );
		self::assertSame( 'carousel', $settings['upsells']['layout'] );
		self::assertSame( 'before_totals', $settings['upsells']['placement'] );
		self::assertSame( 'Complete it', $settings['upsells']['heading'] );
		self::assertSame( 'price_desc', $settings['upsells']['ordering'] );
		self::assertSame( array( 4, 7 ), $settings['upsells']['default_product_ids'] );
		self::assertSame( array( 8, 9 ), $settings['upsells']['excluded_product_ids'] );
	}

	/**
	 * Reward milestones are bounded, sorted, and stripped to the owned shape.
	 */
	public function test_reward_milestones_are_strictly_normalized(): void {
		$settings = Settings::sanitize(
			array(
				'rewards' => array(
					'enabled'            => 'yes',
					'calculation_mode'   => 'total',
					'progress_design'    => 'steps',
					'allow_gift_removal' => true,
					'complete_message'   => '<b>Everything unlocked</b>',
					'milestones'         => array(
						array(
							'id'              => 'Gift @ 30',
							'type'            => 'gift',
							'threshold'       => 30.5,
							'gift_product_id' => 42,
							'unknown'         => 'discard',
						),
						array(
							'id'        => 'coupon-10',
							'type'      => 'coupon',
							'threshold' => 10,
							'coupon_id' => 7,
						),
					),
				),
			)
		);

		self::assertTrue( $settings['rewards']['enabled'] );
		self::assertSame( 'total', $settings['rewards']['calculation_mode'] );
		self::assertSame( 'steps', $settings['rewards']['progress_design'] );
		self::assertSame( 'Everything unlocked', $settings['rewards']['complete_message'] );
		self::assertSame( 'coupon-10', $settings['rewards']['milestones'][0]['id'] );
		self::assertSame( 'gift30', $settings['rewards']['milestones'][1]['id'] );
		self::assertSame( 42, $settings['rewards']['milestones'][1]['gift_product_id'] );
		self::assertArrayNotHasKey( 'unknown', $settings['rewards']['milestones'][1] );
	}

	/**
	 * Invalid reward definitions return exact field paths.
	 */
	public function test_reward_validation_reports_invalid_milestones(): void {
		$errors = Settings::validation_errors(
			array(
				'rewards' => array(
					'progress_design' => 'circle',
					'milestones'      => array(
						array(
							'id'        => 'duplicate',
							'type'      => 'coupon',
							'threshold' => -1,
						),
						array(
							'id'        => 'duplicate',
							'type'      => 'gift',
							'threshold' => 20,
						),
					),
				),
			)
		);

		self::assertArrayHasKey( 'rewards.progress_design', $errors );
		self::assertArrayHasKey( 'rewards.milestones.0.threshold', $errors );
		self::assertArrayHasKey( 'rewards.milestones.0.coupon_id', $errors );
		self::assertArrayHasKey( 'rewards.milestones.1.id', $errors );
		self::assertArrayHasKey( 'rewards.milestones.1.gift_product_id', $errors );
	}

	/** Special add-on settings retain only the owned presentation shape. */
	public function test_special_addon_settings_are_strictly_normalized(): void {
		$settings = Settings::sanitize(
			array(
				'special_addon' => array(
					'enabled'           => 'yes',
					'product_id'        => 42,
					'preselected'       => true,
					'selection_type'    => 'toggle',
					'heading'           => '<b>Protect this order</b>',
					'description'       => '<script>bad</script>Optional protection',
					'image_enabled'     => true,
					'image_source'      => 'custom',
					'image_id'          => 17,
					'image_url'         => 'https://attacker.invalid/addon.jpg',
					'image_size'        => 200,
					'background'        => '#ABCDEF',
					'accent'            => 'red',
					'heading_color'     => '#112233',
					'description_color' => '#445566',
					'unknown'           => 'discard',
				),
			)
		);

		self::assertTrue( $settings['special_addon']['enabled'] );
		self::assertSame( 42, $settings['special_addon']['product_id'] );
		self::assertSame( 'toggle', $settings['special_addon']['selection_type'] );
		self::assertSame( 'Protect this order', $settings['special_addon']['heading'] );
		self::assertSame( 'badOptional protection', $settings['special_addon']['description'] );
		self::assertSame( 96, $settings['special_addon']['image_size'] );
		self::assertSame( '#abcdef', $settings['special_addon']['background'] );
		self::assertSame( '#1d4ed8', $settings['special_addon']['accent'] );
		self::assertSame( 'https://example.test/media/17.jpg', $settings['special_addon']['image_url'] );
		self::assertArrayNotHasKey( 'unknown', $settings['special_addon'] );
	}

	/** Invalid add-on values return exact field paths. */
	public function test_special_addon_validation_reports_invalid_fields(): void {
		$errors = Settings::validation_errors(
			array(
				'special_addon' => array(
					'enabled'        => true,
					'product_id'     => 0,
					'selection_type' => 'radio',
					'image_source'   => 'remote',
					'image_size'     => 10,
					'background'     => '#bad',
				),
			)
		);

		self::assertArrayHasKey( 'special_addon.product_id', $errors );
		self::assertArrayHasKey( 'special_addon.selection_type', $errors );
		self::assertArrayHasKey( 'special_addon.image_source', $errors );
		self::assertArrayHasKey( 'special_addon.image_size', $errors );
		self::assertArrayHasKey( 'special_addon.background', $errors );
	}

	/** WCAG AA text pairs are enforced while accent controls adapt automatically. */
	public function test_low_contrast_colors_are_rejected(): void {
		$settings = Settings::defaults();
		self::assertSame( array(), Settings::validation_errors( $settings ) );

		$settings['design']['accent_hover']             = '#f3658f';
		$settings['design']['muted']                    = '#f7f5f3';
		$settings['design']['shortcode_background']     = '#ffffff00';
		$settings['design']['shortcode_icon_color']     = '#ffffff';
		$settings['special_addon']['description_color'] = '#f8fafc';

		$errors = Settings::validation_errors( $settings );

		self::assertArrayNotHasKey( 'design.accent_hover', $errors );
		self::assertArrayHasKey( 'design.muted', $errors );
		self::assertArrayHasKey( 'design.shortcode_icon_color', $errors );
		self::assertArrayHasKey( 'special_addon.description_color', $errors );
	}

	/** Primary controls automatically choose the stronger black/white contrast. */
	public function test_readable_accent_text_color_is_selected(): void {
		self::assertSame( '#ffffff', Settings::readable_text_color( '#1d4ed8' ) );
		self::assertSame( '#000000', Settings::readable_text_color( '#f3658f' ) );
		self::assertSame( '#000000', Settings::readable_text_color( '#ffffff00', '#ffffff' ) );
	}

	/** Badge colors remain customizable and never prevent saving. */
	public function test_low_contrast_badge_colors_are_allowed(): void {
		$settings = Settings::defaults();

		$settings['design']['floating_badge_background']  = '#f3658f';
		$settings['design']['floating_badge_color']       = '#ffffff';
		$settings['design']['shortcode_badge_background'] = '#f3658f';
		$settings['design']['shortcode_badge_color']      = '#ffffff';

		$errors = Settings::validation_errors( $settings );

		self::assertArrayNotHasKey( 'design.floating_badge_color', $errors );
		self::assertArrayNotHasKey( 'design.shortcode_badge_color', $errors );
	}

	/**
	 * Only explicit truthy settings enable destructive cleanup.
	 */
	public function test_delete_data_requires_explicit_truthy_value(): void {
		foreach ( array( true, 1, '1', 'yes', 'true', 'on' ) as $value ) {
			$settings = Settings::sanitize( array( Settings::DELETE_DATA_KEY => $value ) );
			self::assertTrue( $settings[ Settings::DELETE_DATA_KEY ] );
		}

		foreach ( array( false, 0, '0', 'no', array(), new \stdClass() ) as $value ) {
			$settings = Settings::sanitize( array( Settings::DELETE_DATA_KEY => $value ) );
			self::assertFalse( $settings[ Settings::DELETE_DATA_KEY ] );
		}
	}

	/**
	 * The first migration writes only Starfiniti-owned prefixed options.
	 */
	public function test_first_migration_is_idempotent(): void {
		Installer::install_or_upgrade();
		Installer::install_or_upgrade();

		self::assertSame( Installer::CURRENT_SCHEMA_VERSION, $GLOBALS['sfcart_test_options'][ Installer::SCHEMA_VERSION_OPTION ] );
		self::assertSame( SFCART_VERSION, $GLOBALS['sfcart_test_options'][ Installer::PLUGIN_VERSION_OPTION ] );
		self::assertFalse(
			$GLOBALS['sfcart_test_options'][ Settings::OPTION_NAME ][ Settings::DELETE_DATA_KEY ]
		);

		$migrations = array_filter(
			$GLOBALS['sfcart_test_actions'],
			static fn ( array $action ): bool => 'sfcart_migration_completed' === $action[0]
		);

		self::assertCount( 12, $migrations );
	}
}
