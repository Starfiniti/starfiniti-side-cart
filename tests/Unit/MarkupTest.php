<?php
/**
 * Cart trigger markup tests.
 *
 * @package StarfinitiCart
 */

declare(strict_types=1);

namespace Starfiniti\Cart\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Starfiniti\Cart\Frontend\Markup;
use Starfiniti\Cart\Settings;

/**
 * Verify the shared live cart-count badge attributes.
 */
final class MarkupTest extends TestCase {

	/** Empty counts stay visible unless the store opts in. */
	public function test_empty_count_is_marked_but_visible_by_default(): void {
		$settings = Settings::defaults();

		self::assertSame(
			'class="sfcart-toggle__count is-empty" data-sfcart-count',
			Markup::count_attributes( 'sfcart-toggle__count', 0, $settings )
		);
		self::assertSame(
			'class="sfcart-menu-count" data-sfcart-count',
			Markup::count_attributes( 'sfcart-menu-count', 2, $settings )
		);
	}

	/** Every trigger count carries the visitor-neutral hide flag when enabled. */
	public function test_hide_empty_count_setting_flags_every_badge(): void {
		$settings = Settings::defaults();

		$settings['design']['hide_empty_count'] = true;

		self::assertSame(
			'class="sfcart-toggle__count is-empty" data-sfcart-count data-sfcart-hide-empty',
			Markup::count_attributes( 'sfcart-toggle__count', 0, $settings )
		);
		self::assertSame(
			'class="sfcart-menu-count" data-sfcart-count data-sfcart-hide-empty',
			Markup::count_attributes( 'sfcart-menu-count', 3, $settings )
		);
	}
}
