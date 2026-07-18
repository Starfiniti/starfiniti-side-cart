<?php
/**
 * Uninstall retention tests.
 *
 * @package StarfinitiCart
 */

declare(strict_types=1);

namespace Starfiniti\Cart\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Starfiniti\Cart\Lifecycle\Installer;
use Starfiniti\Cart\Lifecycle\Uninstaller;
use Starfiniti\Cart\Settings;

/**
 * Verify uninstall deletion is narrow and explicitly opt-in.
 */
final class UninstallerTest extends TestCase {

	/**
	 * Seed plugin-owned and unrelated options.
	 */
	protected function setUp(): void {
		$GLOBALS['sfcart_test_options'] = array(
			Installer::PLUGIN_VERSION_OPTION => SFCART_VERSION,
			Installer::SCHEMA_VERSION_OPTION => Installer::CURRENT_SCHEMA_VERSION,
			Settings::OPTION_NAME            => Settings::defaults(),
			'unrelated_option'               => 'keep-me',
		);
	}

	/**
	 * Default uninstall retains all plugin-owned data.
	 */
	public function test_uninstall_retains_data_by_default(): void {
		Uninstaller::run();

		self::assertArrayHasKey( Settings::OPTION_NAME, $GLOBALS['sfcart_test_options'] );
		self::assertArrayHasKey( Installer::PLUGIN_VERSION_OPTION, $GLOBALS['sfcart_test_options'] );
		self::assertArrayHasKey( Installer::SCHEMA_VERSION_OPTION, $GLOBALS['sfcart_test_options'] );
	}

	/**
	 * Explicit cleanup deletes only the three currently owned options.
	 */
	public function test_explicit_cleanup_deletes_only_plugin_owned_data(): void {
		$GLOBALS['sfcart_test_options'][ Settings::OPTION_NAME ][ Settings::DELETE_DATA_KEY ] = true;

		Uninstaller::run();

		self::assertArrayNotHasKey( Settings::OPTION_NAME, $GLOBALS['sfcart_test_options'] );
		self::assertArrayNotHasKey( Installer::PLUGIN_VERSION_OPTION, $GLOBALS['sfcart_test_options'] );
		self::assertArrayNotHasKey( Installer::SCHEMA_VERSION_OPTION, $GLOBALS['sfcart_test_options'] );
		self::assertSame( 'keep-me', $GLOBALS['sfcart_test_options']['unrelated_option'] );
	}
}
