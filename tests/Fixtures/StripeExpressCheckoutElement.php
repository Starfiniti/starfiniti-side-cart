<?php
/**
 * Stripe express checkout fixture.
 *
 * @package StarfinitiCart
 */

declare(strict_types=1);

/** Test double for the official classic-cart express element. */
final class WC_Stripe_Express_Checkout_Element {

	/** Return the shared test instance. */
	public static function instance(): self {
		static $instance;
		$instance ??= new self();
		return $instance;
	}

	/** Render the gateway-owned marker used by assertions. */
	public function display_express_checkout_button_html(): void {
		echo '<div id="wc-stripe-express-checkout-element"></div>';
	}
}
