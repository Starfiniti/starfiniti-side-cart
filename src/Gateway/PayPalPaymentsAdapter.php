<?php
/**
 * Official WooCommerce PayPal Payments mini-cart adapter.
 *
 * @package StarfinitiCart
 */

namespace Starfiniti\Cart\Gateway;

/**
 * Uses the gateway's public renderer-hook filter so PayPal owns all controls.
 */
final class PayPalPaymentsAdapter {

	/** Register only when the official plugin has completed its bootstrap. */
	public static function register(): void {
		if ( ! class_exists( '\\WooCommerce\\PayPalCommerce\\PPCP' ) ) {
			return;
		}

		add_filter( 'woocommerce_paypal_payments_mini_cart_button_renderer_hook', array( self::class, 'renderer_hook' ) );
	}

	/** Point the official mini-cart renderer at the drawer-owned target. */
	public static function renderer_hook(): string {
		return ExpressButtons::RENDER_ACTION;
	}

	/** Prevent construction. */
	private function __construct() {
	}
}
