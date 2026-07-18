<?php
/**
 * Official WooCommerce Stripe classic-cart adapter.
 *
 * @package StarfinitiCart
 */

namespace Starfiniti\Cart\Gateway;

/**
 * Relocates Stripe's own classic-cart callback without reproducing payment UI.
 */
final class StripeAdapter {

	/** Register late enough for WordPress query context and Stripe initialization. */
	public static function register(): void {
		if ( ! defined( 'WC_STRIPE_VERSION' ) || ! class_exists( '\\WC_Stripe_Express_Checkout_Element' ) ) {
			return;
		}

		add_action( 'wp', array( self::class, 'relocate_classic_cart_button' ), 90 );
	}

	/**
	 * Move the official callback from a classic cart page into the drawer.
	 *
	 * Cart and Checkout blocks retain their native gateway integrations.
	 */
	public static function relocate_classic_cart_button(): void {
		if ( ! function_exists( 'is_cart' ) || ! is_cart() || has_block( 'woocommerce/cart' ) ) {
			return;
		}

		$element  = \WC_Stripe_Express_Checkout_Element::instance();
		$callback = array( $element, 'display_express_checkout_button_html' );
		if ( false === has_action( 'woocommerce_proceed_to_checkout', $callback ) ) {
			return;
		}

		remove_action( 'woocommerce_proceed_to_checkout', $callback, 20 );
		add_action( ExpressButtons::RENDER_ACTION, $callback, 20 );
	}

	/** Prevent construction. */
	private function __construct() {
	}
}
