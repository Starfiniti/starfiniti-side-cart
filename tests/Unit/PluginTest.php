<?php
/**
 * Plugin bootstrap unit tests.
 *
 * @package StarfinitiCart
 */

declare(strict_types=1);

namespace Starfiniti\Cart\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Starfiniti\Cart\Plugin;

/**
 * Verify the dependency floor encoded by the plugin bootstrap.
 */
final class PluginTest extends TestCase {

	/**
	 * The implementation must retain the approved platform floor.
	 */
	public function test_supported_platform_floor(): void {
		self::assertSame( '8.1', Plugin::MINIMUM_PHP );
		self::assertSame( '6.6', Plugin::MINIMUM_WORDPRESS );
		self::assertSame( '9.0', Plugin::MINIMUM_WOOCOMMERCE );
	}

	/**
	 * Plugin is a stable singleton service.
	 */
	public function test_plugin_is_a_singleton(): void {
		self::assertSame( Plugin::instance(), Plugin::instance() );
	}
}
