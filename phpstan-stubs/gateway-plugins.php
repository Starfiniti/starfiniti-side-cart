<?php
/**
 * Static-analysis declarations for optional official gateway APIs.
 *
 * This file is never loaded at runtime.
 *
 * @package StarfinitiCart
 */

/** Official WooCommerce Stripe classic express element. */
class WC_Stripe_Express_Checkout_Element {

	/** Return the official singleton instance. */
	public static function instance(): self {
	}

	/** Render gateway-owned express controls. */
	public function display_express_checkout_button_html(): void {
	}
}
