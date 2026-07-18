<?php
/**
 * GitHub release updater unit tests.
 *
 * @package StarfinitiCart
 */

declare(strict_types=1);

namespace Starfiniti\Cart\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Starfiniti\Cart\Updates\GitHubUpdater;
use WP_Error;

/** Verify safe discovery, caching, and package verification. */
final class GitHubUpdaterTest extends TestCase {

	private const RELEASE_API = 'https://api.github.com/repos/Starfiniti/starfiniti-side-cart/releases/latest';

	/** Reset isolated WordPress state before every test. */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['sfcart_test_hooks']      = array();
		$GLOBALS['sfcart_test_transients'] = array();
		$GLOBALS['sfcart_test_remote']     = array();
		$GLOBALS['sfcart_test_requests']   = array();
		$GLOBALS['sfcart_test_downloads']  = array();
	}

	/** Register all native WordPress update integration points. */
	public function test_registers_native_update_hooks(): void {
		GitHubUpdater::register();

		self::assertSame( 10, has_action( 'update_plugins_github.com', array( GitHubUpdater::class, 'filter_update' ) ) );
		self::assertSame( 10, has_action( 'plugins_api', array( GitHubUpdater::class, 'plugin_information' ) ) );
		self::assertSame( 10, has_action( 'upgrader_pre_download', array( GitHubUpdater::class, 'verify_download' ) ) );
		self::assertSame( 10, has_action( 'upgrader_process_complete', array( GitHubUpdater::class, 'clear_cache_after_update' ) ) );
	}

	/** A newer stable release becomes a native update and is cached. */
	public function test_returns_and_caches_valid_update(): void {
		$this->configure_release( $this->release() );

		$first  = $this->discover();
		$second = $this->discover();

		self::assertIsArray( $first );
		self::assertSame( '1.4.0', $first['version'] );
		self::assertSame( $this->package_url(), $first['package'] );
		self::assertSame( GitHubUpdater::UPDATE_URI, $first['id'] );
		self::assertSame( $first, $second );
		self::assertCount( 1, $GLOBALS['sfcart_test_requests'] );
		self::assertSame( 'Starfiniti-Cart/' . SFCART_VERSION, $GLOBALS['sfcart_test_requests'][0]['args']['headers']['User-Agent'] );
	}

	/** The hostname hook must never alter another plugin's result. */
	public function test_ignores_other_plugins(): void {
		$existing = array( 'version' => '9.9.9' );
		$result   = GitHubUpdater::filter_update(
			$existing,
			array( 'UpdateURI' => GitHubUpdater::UPDATE_URI ),
			'other-plugin/other.php',
			array()
		);

		self::assertSame( $existing, $result );
		self::assertSame( array(), $GLOBALS['sfcart_test_requests'] );
	}

	/** Drafts, prereleases, invalid assets, and missing checksums fail closed. */
	public function test_rejects_untrusted_release_metadata_and_caches_failure(): void {
		$release             = $this->release();
		$release['prerelease'] = true;
		$this->configure_release( $release );

		self::assertFalse( $this->discover() );
		self::assertFalse( $this->discover() );
		self::assertCount( 1, $GLOBALS['sfcart_test_requests'] );
		self::assertSame( 3600, $GLOBALS['sfcart_test_transients']['sfcart_github_release_v1']['expiration'] );

		GitHubUpdater::clear_cache();
		$release                         = $this->release();
		$release['assets'][0]['digest']   = '';
		$release['assets'][1]['browser_download_url'] = 'https://example.test/checksum';
		$this->configure_release( $release );
		self::assertFalse( $this->discover() );
	}

	/** The standard details dialog is populated from validated release data. */
	public function test_returns_plugin_information(): void {
		$this->configure_release( $this->release() );

		$result = GitHubUpdater::plugin_information( false, 'plugin_information', (object) array( 'slug' => 'starfiniti-cart' ) );

		self::assertIsObject( $result );
		self::assertSame( '1.4.0', $result->version );
		self::assertSame( $this->package_url(), $result->download_link );
		self::assertStringContainsString( 'Security fixes', $result->sections['changelog'] );
	}

	/** A package matching GitHub's digest is accepted. */
	public function test_accepts_package_with_matching_digest(): void {
		$content  = 'verified-plugin-package';
		$checksum = hash( 'sha256', $content );
		$release  = $this->release( $checksum );
		$file     = $this->temporary_package( $content );
		$this->configure_release( $release );
		$GLOBALS['sfcart_test_downloads'][ $this->package_url() ] = $file;

		$result = GitHubUpdater::verify_download( false, $this->package_url(), null, array() );

		self::assertSame( $file, $result );
		self::assertFileExists( $file );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_unlink -- Isolated temporary test fixture.
		unlink( $file );
	}

	/** A mismatched package is deleted and the install is stopped. */
	public function test_rejects_and_deletes_package_with_wrong_digest(): void {
		$file = $this->temporary_package( 'tampered-package' );
		$this->configure_release( $this->release( str_repeat( 'a', 64 ) ) );
		$GLOBALS['sfcart_test_downloads'][ $this->package_url() ] = $file;

		$result = GitHubUpdater::verify_download( false, $this->package_url(), null, array() );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'sfcart_update_checksum_mismatch', $result->get_error_code() );
		self::assertFileDoesNotExist( $file );
	}

	/** Older GitHub releases can use the separately published checksum asset. */
	public function test_accepts_checksum_asset_fallback(): void {
		$content                  = 'fallback-checksum-package';
		$checksum                 = hash( 'sha256', $content );
		$release                  = $this->release( '' );
		$file                     = $this->temporary_package( $content );
		$checksum_url             = $this->checksum_url();
		$this->configure_release( $release );
		$GLOBALS['sfcart_test_remote'][ $checksum_url ] = $this->http_response( $checksum . '  starfiniti-cart-1.4.0.zip' );
		$GLOBALS['sfcart_test_downloads'][ $this->package_url() ] = $file;

		$result = GitHubUpdater::verify_download( false, $this->package_url(), null, array() );

		self::assertSame( $file, $result );
		self::assertCount( 2, $GLOBALS['sfcart_test_requests'] );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_unlink -- Isolated temporary test fixture.
		unlink( $file );
	}

	/** Cache invalidation is scoped to this plugin's completed update. */
	public function test_clears_cache_only_after_own_update(): void {
		$this->configure_release( $this->release() );
		self::assertIsArray( $this->discover() );

		GitHubUpdater::clear_cache_after_update( null, array( 'type' => 'plugin', 'action' => 'update', 'plugin' => 'other/other.php' ) );
		self::assertNotEmpty( $GLOBALS['sfcart_test_transients'] );

		GitHubUpdater::clear_cache_after_update( null, array( 'type' => 'plugin', 'action' => 'update', 'plugins' => array( 'starfiniti-cart/starfiniti-cart.php' ) ) );
		self::assertSame( array(), $GLOBALS['sfcart_test_transients'] );
	}

	/** Discover the isolated plugin update. */
	private function discover(): array|false {
		return GitHubUpdater::filter_update(
			false,
			array( 'UpdateURI' => GitHubUpdater::UPDATE_URI ),
			'starfiniti-cart/starfiniti-cart.php',
			array( 'en_US' )
		);
	}

	/** Configure the latest release endpoint. @param array<string, mixed> $release Release data. */
	private function configure_release( array $release ): void {
		$GLOBALS['sfcart_test_remote'][ self::RELEASE_API ] = $this->http_response( (string) json_encode( $release ) );
	}

	/** @return array<string, mixed> */
	private function release( string $checksum = '' ): array {
		$digest = '' !== $checksum ? 'sha256:' . $checksum : '';
		return array(
			'tag_name'   => 'v1.4.0',
			'draft'      => false,
			'prerelease' => false,
			'html_url'   => 'https://github.com/Starfiniti/starfiniti-side-cart/releases/tag/v1.4.0',
			'body'       => 'Security fixes and update support.',
			'assets'     => array(
				array(
					'name'                 => 'starfiniti-cart-1.4.0.zip',
					'browser_download_url' => $this->package_url(),
					'size'                 => 2048,
					'digest'               => $digest,
				),
				array(
					'name'                 => 'starfiniti-cart-1.4.0.zip.sha256',
					'browser_download_url' => $this->checksum_url(),
					'size'                 => 96,
				),
			),
		);
	}

	/** @return array{response:array{code:int},body:string} */
	private function http_response( string $body ): array {
		return array( 'response' => array( 'code' => 200 ), 'body' => $body );
	}

	/** Create an isolated temporary archive fixture. */
	private function temporary_package( string $content ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_tempnam -- Isolated temporary test fixture.
		$file = tempnam( sys_get_temp_dir(), 'sfcart-' );
		self::assertIsString( $file );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Isolated temporary test fixture.
		file_put_contents( $file, $content );
		return $file;
	}

	/** Return the exact owned release package URL. */
	private function package_url(): string {
		return 'https://github.com/Starfiniti/starfiniti-side-cart/releases/download/v1.4.0/starfiniti-cart-1.4.0.zip';
	}

	/** Return the exact owned release checksum URL. */
	private function checksum_url(): string {
		return $this->package_url() . '.sha256';
	}
}
