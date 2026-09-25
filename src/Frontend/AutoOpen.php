<?php
/**
 * One-shot drawer opening after classic add-to-cart requests.
 *
 * @package StarfinitiCart
 */

namespace Starfiniti\Cart\Frontend;

use Starfiniti\Cart\Settings;

/**
 * Carries a successful non-AJAX add to cart to the next storefront page load.
 *
 * Classic single-product forms submit and reload the page, so no
 * added_to_cart event ever reaches the drawer. A short-lived browser cookie
 * marks the add instead. It never enters page HTML, so full-page caches keep
 * serving identical markup to every visitor, and the frontend deletes it on
 * first read. The drawer state endpoint is not used because the frontend only
 * requests cart state lazily; polling it on every page view would add an
 * uncached request for every visitor.
 */
final class AutoOpen {

	/** Cookie asking the next storefront page to open the drawer once. */
	public const COOKIE_NAME = 'sfcart_auto_open';

	/** Seconds the flag survives a post-add redirect or a slow reload. */
	private const LIFETIME = 60;

	/**
	 * Whether this request already flagged an add.
	 *
	 * @var bool
	 */
	private static bool $flagged = false;

	/** Register classic add-to-cart and cart/checkout hooks. */
	public static function register(): void {
		add_action( 'woocommerce_add_to_cart', array( self::class, 'flag_classic_add' ), 20 );
		add_action( 'template_redirect', array( self::class, 'clear_on_cart_or_checkout' ) );
	}

	/** Flag a successful classic add so the next page load opens the drawer once. */
	public static function flag_classic_add(): void {
		if ( self::$flagged || ! self::is_classic_add_request() ) {
			return;
		}

		self::$flagged = true;

		// WooCommerce already sends no-cache headers for add-to-cart requests; keep LiteSpeed aligned.
		do_action( 'litespeed_control_set_nocache', 'Starfiniti Cart classic add to cart' );
		self::set_cookie( '1', time() + self::LIFETIME );
	}

	/** Drop a pending flag on cart and checkout pages, which never auto-open the drawer. */
	public static function clear_on_cart_or_checkout(): void {
		if ( ! self::$flagged && ! isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return;
		}

		if ( ( function_exists( 'is_cart' ) && is_cart() ) || ( function_exists( 'is_checkout' ) && is_checkout() ) ) {
			self::set_cookie( '', time() - HOUR_IN_SECONDS );
		}
	}

	/**
	 * Report whether the current request is a classic WooCommerce form add.
	 *
	 * AJAX, WooCommerce AJAX, REST/Store API, and administration requests are
	 * excluded because their callers already refresh or open the drawer.
	 * Requiring WooCommerce's own add-to-cart parameter also ignores internal
	 * cart additions such as reward gifts reconciled during a page view.
	 *
	 * @internal Public for isolated regression tests.
	 */
	public static function is_classic_add_request(): bool {
		$settings = Settings::get();
		if ( true !== $settings['cart']['auto_open'] ) {
			return false;
		}

		if ( is_admin() || wp_doing_ajax() || wp_is_serving_rest_request() ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Presence check only; no request data is used.
		if ( isset( $_GET['wc-ajax'] ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Presence check of WooCommerce's own form-handler parameter.
		return isset( $_REQUEST['add-to-cart'] );
	}

	/**
	 * Describe the cookie for the frontend, which reads and deletes it once.
	 *
	 * Path and domain are site constants, so the description is identical for
	 * every visitor and safe inside cached page HTML.
	 *
	 * @return array{name: string, path: string, domain: string}
	 */
	public static function cookie_config(): array {
		return array(
			'name'   => self::COOKIE_NAME,
			'path'   => self::cookie_path(),
			'domain' => self::cookie_domain(),
		);
	}

	/**
	 * Send the flag cookie when response headers are still writable.
	 *
	 * The value is only "1" and carries no visitor data. It is readable by the
	 * frontend script so the drawer can delete it immediately after use.
	 *
	 * @param string $value   Cookie value.
	 * @param int    $expires Expiry timestamp.
	 */
	private static function set_cookie( string $value, int $expires ): void {
		if ( headers_sent() ) {
			return;
		}

		setcookie(
			self::COOKIE_NAME,
			$value,
			array(
				'expires'  => $expires,
				'path'     => self::cookie_path(),
				'domain'   => self::cookie_domain(),
				'secure'   => is_ssl(),
				'httponly' => false,
				'samesite' => 'Lax',
			)
		);
	}

	/** Return the WordPress front-end cookie path. */
	private static function cookie_path(): string {
		$path = defined( 'COOKIEPATH' ) ? constant( 'COOKIEPATH' ) : '';

		return is_string( $path ) && '' !== $path ? $path : '/';
	}

	/** Return the configured WordPress cookie domain, if any. */
	private static function cookie_domain(): string {
		$domain = defined( 'COOKIE_DOMAIN' ) ? constant( 'COOKIE_DOMAIN' ) : '';

		return is_string( $domain ) ? $domain : '';
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
