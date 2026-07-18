<?php
/**
 * Native WordPress updates backed by public GitHub Releases.
 *
 * @package StarfinitiCart
 */

namespace Starfiniti\Cart\Updates;

/**
 * Discovers stable releases and verifies packages before WordPress installs them.
 */
final class GitHubUpdater {

	/** Unique WordPress update identifier. */
	public const UPDATE_URI = 'https://github.com/Starfiniti/starfiniti-side-cart';

	/** Public latest-release endpoint. */
	private const RELEASE_API = 'https://api.github.com/repos/Starfiniti/starfiniti-side-cart/releases/latest';

	/** Repository page shown in WordPress. */
	private const REPOSITORY_URL = 'https://github.com/Starfiniti/starfiniti-side-cart';

	/** Installed plugin basename. */
	private const PLUGIN_FILE = 'starfiniti-cart/starfiniti-cart.php';

	/** WordPress plugin slug. */
	private const SLUG = 'starfiniti-cart';

	/** Network-wide release metadata cache. */
	private const CACHE_KEY = 'sfcart_github_release_v1';

	/** Maximum accepted API response size. */
	private const MAX_RESPONSE_BYTES = 262144;

	/** Maximum accepted update package size. */
	private const MAX_PACKAGE_BYTES = 52428800;

	/** Register update discovery and package verification. */
	public static function register(): void {
		add_filter( 'update_plugins_github.com', array( self::class, 'filter_update' ), 10, 4 );
		add_filter( 'plugins_api', array( self::class, 'plugin_information' ), 10, 3 );
		add_filter( 'upgrader_pre_download', array( self::class, 'verify_download' ), 10, 4 );
		add_action( 'upgrader_process_complete', array( self::class, 'clear_cache_after_update' ), 10, 2 );
	}

	/**
	 * Supply a native WordPress update response for this plugin only.
	 *
	 * @param array<string, mixed>|false $update      Existing update response.
	 * @param array<string, mixed>       $plugin_data Parsed plugin headers.
	 * @param string                     $plugin_file Plugin basename.
	 * @param string[]                   $locales     Requested locales.
	 * @return array<string, mixed>|false
	 */
	public static function filter_update( array|false $update, array $plugin_data, string $plugin_file, array $locales ): array|false {
		unset( $locales );

		if ( self::PLUGIN_FILE !== $plugin_file || self::UPDATE_URI !== (string) ( $plugin_data['UpdateURI'] ?? '' ) ) {
			return $update;
		}

		$release = self::release();
		if ( false === $release || version_compare( $release['version'], SFCART_VERSION, '<=' ) ) {
			return false;
		}

		return array(
			'id'           => self::UPDATE_URI,
			'slug'         => self::SLUG,
			'plugin'       => self::PLUGIN_FILE,
			'version'      => $release['version'],
			'url'          => $release['html_url'],
			'package'      => $release['package'],
			'requires_php' => '8.1',
		);
	}

	/**
	 * Populate the standard WordPress plugin-information dialog.
	 *
	 * @param mixed  $result Existing API result.
	 * @param string $action Plugin API action.
	 * @param object $args   Plugin API arguments.
	 * @return mixed
	 */
	public static function plugin_information( mixed $result, string $action, object $args ): mixed {
		$slug = property_exists( $args, 'slug' ) ? (string) $args->slug : '';
		if ( 'plugin_information' !== $action || self::SLUG !== $slug ) {
			return $result;
		}

		$release = self::release();
		if ( false === $release ) {
			return $result;
		}

		$changelog = '' !== $release['body']
			? nl2br( esc_html( $release['body'] ) )
			: esc_html__( 'See the GitHub release for details.', 'starfiniti-cart' );

		return (object) array(
			'name'          => 'Starfiniti Cart for WooCommerce',
			'slug'          => self::SLUG,
			'version'       => $release['version'],
			'author'        => '<a href="https://starfiniti.com/">Starfiniti</a>',
			'homepage'      => self::REPOSITORY_URL,
			'requires'      => '6.6',
			'requires_php'  => '8.1',
			'download_link' => $release['package'],
			'sections'      => array(
				'description' => esc_html__( 'A free, production-focused side cart for WooCommerce.', 'starfiniti-cart' ),
				'changelog'   => $changelog,
			),
		);
	}

	/**
	 * Download this plugin's package and verify its published SHA-256 digest.
	 *
	 * @param mixed                $reply      Existing pre-download result.
	 * @param string               $package    Package URL.
	 * @param mixed                $upgrader   WordPress upgrader instance.
	 * @param array<string, mixed> $hook_extra Upgrade context.
	 * @return mixed
	 */
	public static function verify_download( mixed $reply, string $package, mixed $upgrader, array $hook_extra ): mixed {
		unset( $upgrader, $hook_extra );

		if ( false !== $reply ) {
			return $reply;
		}

		$release = self::release();
		if ( false === $release || $package !== $release['package'] ) {
			return false;
		}

		$checksum = $release['checksum'];
		if ( '' === $checksum ) {
			$checksum = self::remote_checksum( $release['checksum_url'] );
		}

		if ( false === $checksum ) {
			return new \WP_Error(
				'sfcart_update_checksum_unavailable',
				__( 'Starfiniti Cart could not verify the update checksum.', 'starfiniti-cart' )
			);
		}

		if ( ! function_exists( 'download_url' ) && defined( 'ABSPATH' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! function_exists( 'download_url' ) ) {
			return new \WP_Error(
				'sfcart_update_download_unavailable',
				__( 'WordPress could not initialize the update downloader.', 'starfiniti-cart' )
			);
		}

		$temporary_file = download_url( $package, 300 );
		if ( is_wp_error( $temporary_file ) ) {
			return $temporary_file;
		}
		if ( ! is_string( $temporary_file ) || '' === $temporary_file ) {
			return new \WP_Error(
				'sfcart_update_download_failed',
				__( 'WordPress did not return a valid update package.', 'starfiniti-cart' )
			);
		}

		$size   = filesize( $temporary_file );
		$digest = hash_file( 'sha256', $temporary_file );
		if ( false === $size || $size <= 0 || $size > self::MAX_PACKAGE_BYTES || ! is_string( $digest ) || ! hash_equals( $checksum, strtolower( $digest ) ) ) {
			wp_delete_file( $temporary_file );
			return new \WP_Error(
				'sfcart_update_checksum_mismatch',
				__( 'The Starfiniti Cart update package failed integrity verification.', 'starfiniti-cart' )
			);
		}

		return $temporary_file;
	}

	/**
	 * Clear cached release metadata after this plugin is updated.
	 *
	 * @param mixed                $upgrader   WordPress upgrader instance.
	 * @param array<string, mixed> $hook_extra Upgrade context.
	 */
	public static function clear_cache_after_update( mixed $upgrader, array $hook_extra ): void {
		unset( $upgrader );

		if ( 'plugin' !== (string) ( $hook_extra['type'] ?? '' ) || 'update' !== (string) ( $hook_extra['action'] ?? '' ) ) {
			return;
		}

		$plugins = array_map( 'strval', (array) ( $hook_extra['plugins'] ?? array() ) );
		$plugin  = (string) ( $hook_extra['plugin'] ?? '' );
		if ( self::PLUGIN_FILE === $plugin || in_array( self::PLUGIN_FILE, $plugins, true ) ) {
			self::clear_cache();
		}
	}

	/** Clear cached release metadata. */
	public static function clear_cache(): void {
		delete_site_transient( self::CACHE_KEY );
	}

	/**
	 * Return validated latest-release metadata.
	 *
	 * @return array{version:string,package:string,checksum:string,checksum_url:string,html_url:string,body:string}|false
	 */
	private static function release(): array|false {
		$cached = get_site_transient( self::CACHE_KEY );
		if ( is_array( $cached ) && isset( $cached['status'] ) ) {
			if ( 'ok' !== $cached['status'] || ! isset( $cached['release'] ) || ! is_array( $cached['release'] ) ) {
				return false;
			}

			/**
			 * Validated cached release metadata.
			 *
			 * @var array{version:string,package:string,checksum:string,checksum_url:string,html_url:string,body:string} $release
			 */
			$release = $cached['release'];
			return $release;
		}

		$response = self::request( self::RELEASE_API );
		if ( false === $response ) {
			self::cache_failure();
			return false;
		}

		$data = json_decode( $response, true );
		if ( ! is_array( $data ) ) {
			self::cache_failure();
			return false;
		}

		$release = self::validate_release( $data );
		if ( false === $release ) {
			self::cache_failure();
			return false;
		}

		set_site_transient(
			self::CACHE_KEY,
			array(
				'status'  => 'ok',
				'release' => $release,
			),
			12 * HOUR_IN_SECONDS
		);

		return $release;
	}

	/**
	 * Validate one GitHub release and locate the exact packaged assets.
	 *
	 * @param array<string, mixed> $data GitHub release response.
	 * @return array{version:string,package:string,checksum:string,checksum_url:string,html_url:string,body:string}|false
	 */
	private static function validate_release( array $data ): array|false {
		if ( true === ( $data['draft'] ?? false ) || true === ( $data['prerelease'] ?? false ) ) {
			return false;
		}

		$tag = (string) ( $data['tag_name'] ?? '' );
		if ( 1 !== preg_match( '/^v?(\d+\.\d+\.\d+)$/', $tag, $matches ) ) {
			return false;
		}

		$version       = $matches[1];
		$archive_name  = 'starfiniti-cart-' . $version . '.zip';
		$checksum_name = $archive_name . '.sha256';
		$package       = '';
		$checksum      = '';
		$checksum_url  = '';

		foreach ( (array) ( $data['assets'] ?? array() ) as $asset ) {
			if ( ! is_array( $asset ) ) {
				continue;
			}

			$name = (string) ( $asset['name'] ?? '' );
			$url  = (string) ( $asset['browser_download_url'] ?? '' );
			if ( $archive_name === $name && self::valid_asset_url( $url, $tag, $archive_name ) ) {
				$size = absint( $asset['size'] ?? 0 );
				if ( $size <= 0 || $size > self::MAX_PACKAGE_BYTES ) {
					return false;
				}

				$package = $url;
				$digest  = strtolower( (string) ( $asset['digest'] ?? '' ) );
				if ( 1 === preg_match( '/^sha256:([a-f0-9]{64})$/', $digest, $digest_match ) ) {
					$checksum = $digest_match[1];
				}
			}

			if ( $checksum_name === $name && self::valid_asset_url( $url, $tag, $checksum_name ) ) {
				$checksum_url = $url;
			}
		}

		$html_url = (string) ( $data['html_url'] ?? '' );
		if ( '' === $package || ( '' === $checksum && '' === $checksum_url ) || ! self::valid_release_url( $html_url, $tag ) ) {
			return false;
		}

		return array(
			'version'      => $version,
			'package'      => $package,
			'checksum'     => $checksum,
			'checksum_url' => $checksum_url,
			'html_url'     => $html_url,
			'body'         => substr( (string) ( $data['body'] ?? '' ), 0, 50000 ),
		);
	}

	/**
	 * Validate a release asset URL against the owned repository and tag.
	 *
	 * @param string $url      Candidate asset URL.
	 * @param string $tag      Validated release tag.
	 * @param string $filename Exact expected filename.
	 */
	private static function valid_asset_url( string $url, string $tag, string $filename ): bool {
		$parts = wp_parse_url( $url );
		return is_array( $parts )
			&& 'https' === strtolower( (string) ( $parts['scheme'] ?? '' ) )
			&& 'github.com' === strtolower( (string) ( $parts['host'] ?? '' ) )
			&& 0 === strcmp( '/Starfiniti/starfiniti-side-cart/releases/download/' . $tag . '/' . $filename, (string) ( $parts['path'] ?? '' ) );
	}

	/**
	 * Validate the human-readable release URL.
	 *
	 * @param string $url Candidate release URL.
	 * @param string $tag Validated release tag.
	 */
	private static function valid_release_url( string $url, string $tag ): bool {
		$parts = wp_parse_url( $url );
		return is_array( $parts )
			&& 'https' === strtolower( (string) ( $parts['scheme'] ?? '' ) )
			&& 'github.com' === strtolower( (string) ( $parts['host'] ?? '' ) )
			&& 0 === strcmp( '/Starfiniti/starfiniti-side-cart/releases/tag/' . $tag, (string) ( $parts['path'] ?? '' ) );
	}

	/**
	 * Fetch and parse a published checksum asset.
	 *
	 * @param string $url Validated checksum asset URL.
	 */
	private static function remote_checksum( string $url ): string|false {
		if ( '' === $url ) {
			return false;
		}

		$response = self::request( $url );
		if ( false === $response || 1 !== preg_match( '/^([a-f0-9]{64})(?:\s+\*?[^\r\n]+)?\s*$/i', $response, $matches ) ) {
			return false;
		}

		return strtolower( $matches[1] );
	}

	/**
	 * Fetch a small public GitHub response without transmitting store data.
	 *
	 * @param string $url Public GitHub URL.
	 */
	private static function request( string $url ): string|false {
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'     => 10,
				'redirection' => 3,
				'headers'     => array(
					'Accept'               => 'application/vnd.github+json',
					'User-Agent'           => 'Starfiniti-Cart/' . SFCART_VERSION,
					'X-GitHub-Api-Version' => '2022-11-28',
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		return is_string( $body ) && strlen( $body ) <= self::MAX_RESPONSE_BYTES ? $body : false;
	}

	/** Cache a bounded failure so unavailable GitHub responses are not hammered. */
	private static function cache_failure(): void {
		set_site_transient( self::CACHE_KEY, array( 'status' => 'failed' ), HOUR_IN_SECONDS );
	}

	/** Prevent construction. */
	private function __construct() {
	}
}
