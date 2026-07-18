<?php
/**
 * Frontend integration coordinator.
 *
 * @package StarfinitiCart
 */

namespace Starfiniti\Cart\Frontend;

use Starfiniti\Cart\Compatibility\CompatibilityManager;
use Starfiniti\Cart\Settings;
use WC_AJAX;

/**
 * Registers assets, drawer output, shortcode, and menu toggles.
 */
final class Frontend {

	/**
	 * Register frontend hooks.
	 */
	public static function register(): void {
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( self::class, 'render' ), 20 );
		add_shortcode( 'starfiniti_cart', array( self::class, 'shortcode' ) );
		add_filter( 'nav_menu_link_attributes', array( self::class, 'menu_link_attributes' ), 10, 4 );
		add_filter( 'nav_menu_item_title', array( self::class, 'menu_item_title' ), 10, 4 );
		add_filter( 'walker_nav_menu_start_el', array( self::class, 'menu_item_output' ), 10, 4 );
	}

	/**
	 * Load the owned frontend source build only where the drawer can render.
	 */
	public static function enqueue_assets(): void {
		if ( ! self::should_render() ) {
			return;
		}

		CompatibilityManager::tag_page_cache();

		$asset_path    = SFCART_PLUGIN_DIR . 'build/frontend.asset.php';
		$asset         = is_readable( $asset_path ) ? require $asset_path : array();
		$version       = is_array( $asset ) && isset( $asset['version'] ) ? (string) $asset['version'] : SFCART_VERSION;
		$dependencies  = is_array( $asset ) && isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] )
			? $asset['dependencies']
			: array();
		$style_path    = SFCART_PLUGIN_DIR . 'build/style-frontend.css';
		$style_hash    = is_readable( $style_path ) ? hash_file( 'sha256', $style_path ) : false;
		$style_version = is_string( $style_hash ) ? SFCART_VERSION . '.' . substr( $style_hash, 0, 12 ) : SFCART_VERSION;
		if ( wp_script_is( 'wc-add-to-cart', 'enqueued' ) && ! in_array( 'jquery', $dependencies, true ) ) {
			$dependencies[] = 'jquery';
		}

		// CSS is emitted separately from the JavaScript entry, so the webpack
		// asset hash does not change for CSS-only releases. Include a content hash
		// to invalidate browser and page caches even when package timestamps are reproducible.
		wp_enqueue_style( 'sfcart-frontend', SFCART_PLUGIN_URL . 'build/style-frontend.css', array(), $style_version );
		wp_enqueue_script( 'sfcart-frontend', SFCART_PLUGIN_URL . 'build/frontend.js', $dependencies, $version, true );

		$settings      = Settings::get();
		$configuration = array(
			'behavior'  => array(
				'autoOpen'             => (bool) $settings['cart']['auto_open'],
				'coupons'              => (bool) $settings['cart']['coupons'],
				'showShipping'         => (bool) $settings['cart']['show_shipping'],
				'showTax'              => (bool) $settings['cart']['show_tax'],
				'showCartLink'         => (bool) $settings['cart']['show_cart_link'],
				'showContinueShopping' => (bool) $settings['cart']['show_continue_shopping'],
			),
			'endpoints' => array(
				'state'                => WC_AJAX::get_endpoint( 'sfcart_state' ),
				'updateItem'           => WC_AJAX::get_endpoint( 'sfcart_update_item' ),
				'removeItem'           => WC_AJAX::get_endpoint( 'sfcart_remove_item' ),
				'applyCoupon'          => WC_AJAX::get_endpoint( 'sfcart_apply_coupon' ),
				'removeCoupon'         => WC_AJAX::get_endpoint( 'sfcart_remove_coupon' ),
				'addRecommendation'    => WC_AJAX::get_endpoint( 'sfcart_add_recommendation' ),
				'trackRecommendations' => WC_AJAX::get_endpoint( 'sfcart_track_recommendations' ),
				'trackEvent'           => WC_AJAX::get_endpoint( 'sfcart_track_event' ),
				'setSpecialAddon'      => WC_AJAX::get_endpoint( 'sfcart_set_special_addon' ),
			),
			'nonce'     => wp_create_nonce( 'sfcart_cart' ),
			'labels'    => array(
				'item'                   => __( 'item', 'starfiniti-cart' ),
				'items'                  => __( 'items', 'starfiniti-cart' ),
				/* translators: %s: product name. */
				'decrease'               => __( 'Decrease quantity for %s', 'starfiniti-cart' ),
				/* translators: %s: product name. */
				'increase'               => __( 'Increase quantity for %s', 'starfiniti-cart' ),
				/* translators: %s: product name. */
				'quantity'               => __( 'Quantity for %s', 'starfiniti-cart' ),
				/* translators: %s: product name. */
				'remove'                 => __( 'Remove %s from cart', 'starfiniti-cart' ),
				/* translators: %s: coupon code. */
				'removeCoupon'           => __( 'Remove coupon %s', 'starfiniti-cart' ),
				/* translators: %1$s: saved amount, %2$d: saved percentage. */
				'savings'                => __( 'You save %1$s (%2$d%%)', 'starfiniti-cart' ),
				'backorder'              => __( 'Available on backorder', 'woocommerce' ),
				'requestFailed'          => __( 'The cart could not be refreshed. Please try again.', 'starfiniti-cart' ),
				'addRecommendation'      => __( 'Add to cart', 'starfiniti-cart' ),
				'chooseOptions'          => __( 'Choose options', 'starfiniti-cart' ),
				/* translators: %s: variation attribute label. */
				'chooseOption'           => __( 'Choose %s', 'starfiniti-cart' ),
				'selectAddon'            => __( 'Select add-on', 'starfiniti-cart' ),
				'recommendationPrevious' => __( 'Previous recommendation', 'starfiniti-cart' ),
				'recommendationNext'     => __( 'Next recommendation', 'starfiniti-cart' ),
				'sessionExpired'         => __( 'Your cart session expired. Refreshing the cart…', 'starfiniti-cart' ),
			),
		);

		wp_add_inline_script(
			'sfcart-frontend',
			'window.sfcartConfig = Object.freeze(' . wp_json_encode( $configuration ) . ');',
			'before'
		);
	}

	/**
	 * Output the single drawer and fixed cart toggle.
	 */
	public static function render(): void {
		if ( ! self::should_render() ) {
			return;
		}

		$settings = Settings::get();

		echo Markup::drawer( $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup escapes dynamic values internally.
		if ( true === $settings['cart']['floating_button'] ) {
			echo Markup::floating_toggle( $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup escapes dynamic values internally.
		}
	}

	/**
	 * Render [starfiniti_cart].
	 *
	 * @param array<string, mixed>|string $attributes Shortcode attributes.
	 */
	public static function shortcode( array|string $attributes = array() ): string {
		$settings = Settings::get();
		if ( false === $settings['cart']['header_cart'] ) {
			return '';
		}

		$attributes = shortcode_atts(
			array(
				'label' => '',
			),
			is_array( $attributes ) ? $attributes : array(),
			'starfiniti_cart'
		);

		return Markup::toggle( 'sfcart-shortcode-toggle', sanitize_text_field( (string) $attributes['label'] ), $settings );
	}

	/**
	 * Convert a custom menu link with URL #starfiniti-cart into a drawer toggle.
	 *
	 * @param array<string, mixed> $attributes Link attributes.
	 * @param object               $menu_item Menu item object.
	 * @param mixed                $menu_args Menu rendering arguments.
	 * @param int                  $depth     Menu depth.
	 * @return array<string, mixed>
	 */
	public static function menu_link_attributes( array $attributes, object $menu_item, mixed $menu_args = null, int $depth = 0 ): array {
		unset( $menu_args, $depth );

		if ( ! self::is_cart_menu_item( $menu_item ) ) {
			return $attributes;
		}

		$attributes['href']               = '#starfiniti-cart';
		$attributes['data-sfcart-toggle'] = '';
		$attributes['aria-controls']      = 'sfcart-drawer';
		$attributes['aria-haspopup']      = 'dialog';

		return $attributes;
	}

	/**
	 * Append the live cart count to the special custom menu link.
	 *
	 * @param string $title     Menu item title.
	 * @param object $menu_item Menu item object.
	 * @param mixed  $menu_args Menu rendering arguments.
	 * @param int    $depth     Menu depth.
	 */
	public static function menu_item_title( string $title, object $menu_item, mixed $menu_args = null, int $depth = 0 ): string {
		unset( $menu_args, $depth );

		if ( ! self::is_cart_menu_item( $menu_item ) ) {
			return $title;
		}

		return $title . sprintf(
			' <span class="sfcart-menu-count" data-sfcart-count aria-hidden="true">%d</span>',
			Markup::cart_count()
		);
	}

	/**
	 * Ensure the documented custom-link target is enhanced after menu walkers render it.
	 *
	 * @param string $item_output Rendered menu item output.
	 * @param object $menu_item   Menu item object.
	 * @param int    $depth       Menu depth.
	 * @param mixed  $menu_args   Menu rendering arguments.
	 */
	public static function menu_item_output( string $item_output, object $menu_item, int $depth = 0, mixed $menu_args = null ): string {
		unset( $depth, $menu_args );

		if ( ! self::is_cart_menu_item( $menu_item ) && ! str_contains( $item_output, '#starfiniti-cart' ) ) {
			return $item_output;
		}

		$processor = new \WP_HTML_Tag_Processor( $item_output );

		if ( ! $processor->next_tag( array( 'tag_name' => 'a' ) ) ) {
			return $item_output;
		}

		$processor->set_attribute( 'data-sfcart-toggle', '' );
		$processor->set_attribute( 'aria-controls', 'sfcart-drawer' );
		$processor->set_attribute( 'aria-haspopup', 'dialog' );
		$item_output = $processor->get_updated_html();

		if ( ! str_contains( $item_output, 'data-sfcart-count' ) ) {
			$item_output = preg_replace(
				'/<\/a>/',
				sprintf( ' <span class="sfcart-menu-count" data-sfcart-count aria-hidden="true">%d</span></a>', Markup::cart_count() ),
				$item_output,
				1
			) ?? $item_output;
		}

		return $item_output;
	}

	/**
	 * Report whether the drawer may alter this request's frontend presentation.
	 */
	private static function should_render(): bool {
		if ( is_admin() || wp_doing_ajax() ) {
			return false;
		}

		return ! function_exists( 'is_checkout' ) || ! is_checkout();
	}

	/**
	 * Identify the documented custom menu-link target.
	 *
	 * @param object $menu_item Menu item object.
	 */
	private static function is_cart_menu_item( object $menu_item ): bool {
		if ( ! isset( $menu_item->url ) || ! is_string( $menu_item->url ) ) {
			return false;
		}

		return '#starfiniti-cart' === $menu_item->url
			|| 'starfiniti-cart' === wp_parse_url( $menu_item->url, PHP_URL_FRAGMENT );
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
