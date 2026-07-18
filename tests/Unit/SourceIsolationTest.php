<?php
/**
 * Executable source isolation tests.
 *
 * @package StarfinitiCart
 */

declare(strict_types=1);

namespace Starfiniti\Cart\Tests\Unit;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Prevent accidental Funnel Builder or legacy public identifiers in runtime code.
 */
final class SourceIsolationTest extends TestCase {

	/**
	 * Runtime source may mention upstream only in the private conflict guard files.
	 */
	public function test_runtime_has_no_legacy_public_identifiers(): void {
		$project_root = dirname( __DIR__, 2 );
		$files        = array(
			$project_root . '/starfiniti-cart.php',
			$project_root . '/uninstall.php',
		);
		$iterator     = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $project_root . '/src', FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( ! $file instanceof SplFileInfo || 'php' !== $file->getExtension() ) {
				continue;
			}

			if ( in_array( $file->getFilename(), array( 'Requirements.php', 'RequirementFailure.php', 'FunnelKitMigrator.php', 'MigrationController.php' ), true ) ) {
				continue;
			}

			$files[] = $file->getPathname();
		}

		foreach ( $files as $file ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local source audit.
			$contents = (string) file_get_contents( $file );

			foreach ( array( 'FKCart', 'fkcart_', 'WFFN', 'WooFunnels', 'FunnelKit' ) as $identifier ) {
				self::assertStringNotContainsString( $identifier, $contents, $file );
			}

			self::assertStringNotContainsString( 'deactivate_plugins', $contents, $file );
			self::assertStringNotContainsString( 'wc-cart-fragments', $contents, $file );
			self::assertStringNotContainsString( 'wc_fragment_refresh', $contents, $file );
		}
	}
}
