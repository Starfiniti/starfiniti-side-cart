<?php
/**
 * PHPUnit bootstrap.
 *
 * @package StarfinitiCart
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once dirname( __DIR__ ) . '/phpunit-stubs/woocommerce.php';
require_once dirname( __DIR__ ) . '/phpunit-stubs/wp-error.php';
require_once dirname( __DIR__ ) . '/phpunit-stubs/wp-rest-request.php';

if ( ! defined( 'SFCART_VERSION' ) ) {
	define( 'SFCART_VERSION', '1.5.0' );
}

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Isolated WordPress version test stub.
$GLOBALS['wp_version']                   = '6.6';
$GLOBALS['sfcart_test_options']          = array();
$GLOBALS['sfcart_test_sitewide']         = array();
$GLOBALS['sfcart_test_actions']          = array();
$GLOBALS['sfcart_test_hooks']            = array();
$GLOBALS['sfcart_test_quantity_args']    = array(
	'max_value' => 0,
	'min_value' => 1,
	'step'      => 1,
);
$GLOBALS['sfcart_test_products']         = array();
$GLOBALS['sfcart_test_timezone']         = 'UTC';
$GLOBALS['sfcart_test_transients']       = array();
$GLOBALS['sfcart_test_local_transients'] = array();
$GLOBALS['sfcart_test_caps']             = array();
$GLOBALS['sfcart_test_remote']           = array();
$GLOBALS['sfcart_test_requests']         = array();
$GLOBALS['sfcart_test_downloads']        = array();
$GLOBALS['sfcart_test_woocommerce']      = (object) array( 'session' => null );

if ( ! function_exists( 'WC' ) ) {
	/** Return the isolated WooCommerce container. */
	function WC(): object { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid -- Matches WooCommerce's public API.
		return $GLOBALS['sfcart_test_woocommerce'];
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * Determine whether a value is a WordPress error.
	 *
	 * @param mixed $thing Value to inspect.
	 */
	function is_wp_error( mixed $thing ): bool {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * Return isolated scalar input unchanged.
	 *
	 * @param mixed $value Input value.
	 */
	function wp_unslash( mixed $value ): mixed {
		return $value;
	}
}

if ( ! function_exists( 'wp_salt' ) ) {
	/**
	 * Return a deterministic non-secret test salt.
	 *
	 * @param string $scheme Salt scheme.
	 */
	function wp_salt( string $scheme = 'auth' ): string {
		return 'test-salt-' . $scheme;
	}
}

if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	/** Return a deterministic unique UUID-shaped test value. */
	function wp_generate_uuid4(): string {
		static $counter = 0;
		++$counter;
		return sprintf( '00000000-0000-4000-8000-%012d', $counter );
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	/**
	 * Read an isolated site transient.
	 *
	 * @param string $key Transient key.
	 */
	function get_transient( string $key ): mixed {
		return $GLOBALS['sfcart_test_local_transients'][ $key ]['value'] ?? false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	/**
	 * Store an isolated site transient.
	 *
	 * @param string $key        Transient key.
	 * @param mixed  $value      Transient value.
	 * @param int    $expiration Requested lifetime.
	 */
	function set_transient( string $key, mixed $value, int $expiration = 0 ): bool {
		$GLOBALS['sfcart_test_local_transients'][ $key ] = array(
			'value'      => $value,
			'expiration' => $expiration,
		);
		return true;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * Resolve deterministic primitive and object-level capabilities.
	 *
	 * @param string $capability Capability name.
	 * @param mixed  ...$args    Optional object identifiers.
	 */
	function current_user_can( string $capability, mixed ...$args ): bool {
		$object_key = array() !== $args ? $capability . ':' . (string) $args[0] : '';
		if ( '' !== $object_key && array_key_exists( $object_key, $GLOBALS['sfcart_test_caps'] ) ) {
			return true === $GLOBALS['sfcart_test_caps'][ $object_key ];
		}
		return true === ( $GLOBALS['sfcart_test_caps'][ $capability ] ?? false );
	}
}

if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
	/**
	 * Record removal of an isolated scheduled hook.
	 *
	 * @param string $hook Scheduled hook name.
	 */
	function wp_clear_scheduled_hook( string $hook ): int|false {
		$GLOBALS['sfcart_test_actions'][] = array( 'wp_clear_scheduled_hook', array( $hook ) );
		return 1;
	}
}

if ( ! function_exists( 'get_site_transient' ) ) {
	/**
	 * Read an isolated network transient.
	 *
	 * @param string $key Transient key.
	 */
	function get_site_transient( string $key ): mixed {
		return $GLOBALS['sfcart_test_transients'][ $key ]['value'] ?? false;
	}
}

if ( ! function_exists( 'set_site_transient' ) ) {
	/**
	 * Store an isolated network transient and its requested lifetime.
	 *
	 * @param string $key        Transient key.
	 * @param mixed  $value      Transient value.
	 * @param int    $expiration Requested lifetime.
	 */
	function set_site_transient( string $key, mixed $value, int $expiration = 0 ): bool {
		$GLOBALS['sfcart_test_transients'][ $key ] = array(
			'value'      => $value,
			'expiration' => $expiration,
		);
		return true;
	}
}

if ( ! function_exists( 'delete_site_transient' ) ) {
	/**
	 * Delete an isolated network transient.
	 *
	 * @param string $key Transient key.
	 */
	function delete_site_transient( string $key ): bool {
		unset( $GLOBALS['sfcart_test_transients'][ $key ] );
		return true;
	}
}

if ( ! function_exists( 'wp_safe_remote_get' ) ) {
	/**
	 * Return a configured isolated HTTP response.
	 *
	 * @param string               $url  Requested URL.
	 * @param array<string, mixed> $args Request arguments.
	 */
	function wp_safe_remote_get( string $url, array $args = array() ): array|WP_Error {
		$GLOBALS['sfcart_test_requests'][] = array(
			'url'  => $url,
			'args' => $args,
		);
		return $GLOBALS['sfcart_test_remote'][ $url ] ?? new WP_Error( 'http_request_failed', 'No response configured.' );
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	/**
	 * Return an isolated response status.
	 *
	 * @param array<string, mixed>|WP_Error $response HTTP response.
	 */
	function wp_remote_retrieve_response_code( array|WP_Error $response ): int {
		return $response instanceof WP_Error ? 0 : (int) ( $response['response']['code'] ?? 0 );
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	/**
	 * Return an isolated response body.
	 *
	 * @param array<string, mixed>|WP_Error $response HTTP response.
	 */
	function wp_remote_retrieve_body( array|WP_Error $response ): string {
		return $response instanceof WP_Error ? '' : (string) ( $response['body'] ?? '' );
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	/**
	 * Parse a URL using the native test runtime.
	 *
	 * @param string $url       URL to parse.
	 * @param int    $component Optional URL component.
	 */
	function wp_parse_url( string $url, int $component = -1 ): array|string|int|false|null {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Isolated WordPress test stub.
		return -1 === $component ? parse_url( $url ) : parse_url( $url, $component );
	}
}

if ( ! function_exists( 'download_url' ) ) {
	/**
	 * Return a configured temporary update package.
	 *
	 * @param string $url     Package URL.
	 * @param int    $timeout Download timeout.
	 */
	function download_url( string $url, int $timeout = 300 ): string|WP_Error {
		unset( $timeout );
		return $GLOBALS['sfcart_test_downloads'][ $url ] ?? new WP_Error( 'download_failed', 'No download configured.' );
	}
}

if ( ! function_exists( 'wp_delete_file' ) ) {
	/**
	 * Delete an isolated temporary package.
	 *
	 * @param string $file Temporary file path.
	 */
	function wp_delete_file( string $file ): void {
		if ( is_file( $file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Isolated temporary test fixture.
			unlink( $file );
		}
	}
}

if ( ! function_exists( 'wp_timezone' ) ) {
	/** Return the configured isolated site timezone. */
	function wp_timezone(): DateTimeZone {
		return new DateTimeZone( (string) $GLOBALS['sfcart_test_timezone'] );
	}
}

if ( ! function_exists( 'wc_get_product' ) ) {
	/**
	 * Resolve an isolated WooCommerce product.
	 *
	 * @param int $product_id Product identifier.
	 */
	function wc_get_product( int $product_id ): WC_Product|false {
		$product = $GLOBALS['sfcart_test_products'][ $product_id ] ?? false;
		return $product instanceof WC_Product ? $product : false;
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Minimal WordPress translation stub.
	 *
	 * @param string $text Text to translate.
	 */
	function __( string $text ): string {
		return $text;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * Minimal escaping stub.
	 *
	 * @param string $text Text to escape.
	 */
	function esc_html( string $text ): string {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * Minimal translated escaping stub.
	 *
	 * @param string $text Text to translate and escape.
	 */
	function esc_html__( string $text ): string {
		return $text;
	}
}

if ( ! function_exists( 'absint' ) ) {
	/**
	 * Normalize a positive integer.
	 *
	 * @param mixed $value Value to normalize.
	 */
	function absint( mixed $value ): int {
		return max( 0, (int) $value );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Minimal text field sanitizer.
	 *
	 * @param mixed $value Value to sanitize.
	 */
	function sanitize_text_field( mixed $value ): string {
		return trim( wp_strip_all_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * Minimal JSON encoder.
	 *
	 * @param mixed $value Value to encode.
	 */
	function wp_json_encode( mixed $value ): string|false {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Isolated WordPress test stub.
		return json_encode( $value );
	}
}

if ( ! function_exists( 'current_time' ) ) {
	/**
	 * Minimal current time helper.
	 *
	 * @param string $type Time format type.
	 * @param bool   $gmt  Whether to return GMT.
	 */
	function current_time( string $type, bool $gmt = false ): string {
		unset( $gmt );
		return 'mysql' === $type ? '2026-07-17 12:00:00' : gmdate( 'Y-m-d H:i:s' );
	}
}

if ( ! function_exists( 'wc_format_coupon_code' ) ) {
	/**
	 * Minimal coupon code formatter.
	 *
	 * @param string $code Coupon code.
	 */
	function wc_format_coupon_code( string $code ): string {
		return strtolower( trim( $code ) );
	}
}

if ( ! function_exists( 'wc_format_decimal' ) ) {
	/**
	 * Minimal decimal formatter.
	 *
	 * @param float $value Decimal value.
	 * @param int   $decimals Decimal places.
	 */
	function wc_format_decimal( float $value, int $decimals = 2 ): string {
		return number_format( $value, $decimals, '.', '' );
	}
}

if ( ! function_exists( 'wc_get_price_decimals' ) ) {
	/** Return test price precision. */
	function wc_get_price_decimals(): int {
		return 2;
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	/**
	 * Minimal text sanitization stub.
	 *
	 * @param string $text Text to sanitize.
	 */
	function wp_strip_all_tags( string $text ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Isolated WordPress test stub.
		return strip_tags( $text );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * Normalize a deterministic test key.
	 *
	 * @param string $key Candidate key.
	 */
	function sanitize_key( string $key ): string {
		return strtolower( (string) preg_replace( '/[^a-z0-9_\-]/i', '', $key ) );
	}
}

if ( ! function_exists( 'wp_get_attachment_image_url' ) ) {
	/**
	 * Resolve deterministic test attachment URLs.
	 *
	 * @param int $attachment_id Test attachment identifier.
	 */
	function wp_get_attachment_image_url( int $attachment_id ): string|false {
		return $attachment_id > 0
			? 'https://example.test/media/' . $attachment_id . '.jpg'
			: false;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Read a test option.
	 *
	 * @param string $option  Option name.
	 * @param mixed  $fallback Default value.
	 * @return mixed
	 */
	function get_option( string $option, mixed $fallback = false ): mixed {
		return $GLOBALS['sfcart_test_options'][ $option ] ?? $fallback;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * Update a test option.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Option value.
	 */
	function update_option( string $option, mixed $value ): bool {
		$GLOBALS['sfcart_test_options'][ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'add_option' ) ) {
	/**
	 * Add a test option.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Option value.
	 */
	function add_option( string $option, mixed $value ): bool {
		if ( array_key_exists( $option, $GLOBALS['sfcart_test_options'] ) ) {
			return false;
		}

		$GLOBALS['sfcart_test_options'][ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	/**
	 * Delete a test option.
	 *
	 * @param string $option Option name.
	 */
	function delete_option( string $option ): bool {
		unset( $GLOBALS['sfcart_test_options'][ $option ] );
		return true;
	}
}

if ( ! function_exists( 'get_site_option' ) ) {
	/**
	 * Read a test network option.
	 *
	 * @param string $option  Option name.
	 * @param mixed  $fallback Default value.
	 * @return mixed
	 */
	function get_site_option( string $option, mixed $fallback = false ): mixed {
		return $GLOBALS['sfcart_test_sitewide'][ $option ] ?? $fallback;
	}
}

if ( ! function_exists( 'is_multisite' ) ) {
	/**
	 * Unit tests use a single-site runtime unless explicitly expanded later.
	 */
	function is_multisite(): bool {
		return false;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Register an isolated test action.
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Test callback.
	 * @param int      $priority Hook priority.
	 */
	function add_action( string $hook, callable $callback, int $priority = 10 ): bool {
		$GLOBALS['sfcart_test_hooks'][ $hook ][ $priority ][] = $callback;
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Register an isolated test filter.
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Test callback.
	 * @param int      $priority Hook priority.
	 */
	function add_filter( string $hook, callable $callback, int $priority = 10 ): bool {
		return add_action( $hook, $callback, $priority );
	}
}

if ( ! function_exists( 'has_action' ) ) {
	/**
	 * Find an isolated callback priority.
	 *
	 * @param string         $hook     Hook name.
	 * @param callable|false $callback Optional callback.
	 */
	function has_action( string $hook, callable|false $callback = false ): int|bool {
		$priorities = $GLOBALS['sfcart_test_hooks'][ $hook ] ?? array();
		if ( false === $callback ) {
			return ! empty( $priorities );
		}
		foreach ( $priorities as $priority => $callbacks ) {
			foreach ( $callbacks as $registered ) {
				if ( $registered === $callback ) {
					return (int) $priority;
				}
			}
		}
		return false;
	}
}

if ( ! function_exists( 'remove_action' ) ) {
	/**
	 * Remove an isolated callback.
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Test callback.
	 * @param int      $priority Hook priority.
	 */
	function remove_action( string $hook, callable $callback, int $priority = 10 ): bool {
		$callbacks = $GLOBALS['sfcart_test_hooks'][ $hook ][ $priority ] ?? array();
		foreach ( $callbacks as $index => $registered ) {
			if ( $registered === $callback ) {
				unset( $GLOBALS['sfcart_test_hooks'][ $hook ][ $priority ][ $index ] );
				return true;
			}
		}
		return false;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Apply isolated filters in priority order.
	 *
	 * @param string $hook    Hook name.
	 * @param mixed  $value   Initial value.
	 * @param mixed  ...$args Additional callback arguments.
	 */
	function apply_filters( string $hook, mixed $value, mixed ...$args ): mixed {
		$priorities = $GLOBALS['sfcart_test_hooks'][ $hook ] ?? array();
		ksort( $priorities );
		foreach ( $priorities as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$value = $callback( $value, ...$args );
			}
		}
		return $value;
	}
}

if ( ! function_exists( 'is_cart' ) ) {
	/** Report the isolated classic-cart query context. */
	function is_cart(): bool {
		return true === ( $GLOBALS['sfcart_test_is_cart'] ?? false );
	}
}

if ( ! function_exists( 'has_block' ) ) {
	/**
	 * Report whether an isolated test block is present.
	 *
	 * @param string $block_name Block name.
	 */
	function has_block( string $block_name ): bool {
		return in_array( $block_name, $GLOBALS['sfcart_test_blocks'] ?? array(), true );
	}
}

if ( ! function_exists( 'do_action' ) ) {
	/**
	 * Record a test action.
	 *
	 * @param string $hook Hook name.
	 * @param mixed  ...$args Hook arguments.
	 */
	function do_action( string $hook, mixed ...$args ): void {
		$GLOBALS['sfcart_test_actions'][] = array( $hook, $args );
		$priorities                       = $GLOBALS['sfcart_test_hooks'][ $hook ] ?? array();
		ksort( $priorities );
		foreach ( $priorities as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$callback( ...$args );
			}
		}
	}
}

if ( ! function_exists( 'wp_die' ) ) {
	/**
	 * Turn activation termination into a test exception.
	 *
	 * @param string $message Failure message.
	 * @throws RuntimeException Always.
	 */
	function wp_die( string $message ): never {
		throw new RuntimeException( esc_html( $message ) );
	}
}
