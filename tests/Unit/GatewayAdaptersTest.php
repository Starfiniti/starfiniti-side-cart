<?php
/**
 * Official gateway adapter boundary tests.
 *
 * @package StarfinitiCart
 */

declare(strict_types=1);

namespace Starfiniti\Cart\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Starfiniti\Cart\Gateway\ExpressButtons;
use Starfiniti\Cart\Gateway\PayPalPaymentsAdapter;
use Starfiniti\Cart\Gateway\StripeAdapter;

require_once dirname( __DIR__ ) . '/Fixtures/PayPalPPCP.php';
require_once dirname( __DIR__ ) . '/Fixtures/StripeExpressCheckoutElement.php';

if ( ! defined( 'WC_STRIPE_VERSION' ) ) {
	define( 'WC_STRIPE_VERSION', '10.8.4-test' );
}

/** Verify that adapters expose only gateway-owned controls in supported contexts. */
final class GatewayAdaptersTest extends TestCase {

	/** Reset isolated gateway and hook state. */
	protected function setUp(): void {
		$GLOBALS['sfcart_test_hooks']   = array();
		$GLOBALS['sfcart_test_is_cart'] = false;
		$GLOBALS['sfcart_test_blocks']  = array();
	}

	/** PayPal uses its public mini-cart renderer-hook filter. */
	public function test_paypal_uses_the_official_mini_cart_renderer_filter(): void {
		PayPalPaymentsAdapter::register();

		self::assertSame(
			ExpressButtons::RENDER_ACTION,
			apply_filters( 'woocommerce_paypal_payments_mini_cart_button_renderer_hook', 'woocommerce_widget_shopping_cart_after_buttons' )
		);
	}

	/** Stripe's official callback moves only on the supported classic cart. */
	public function test_stripe_classic_cart_callback_is_relocated_into_drawer(): void {
		$GLOBALS['sfcart_test_is_cart'] = true;
		$element                        = \WC_Stripe_Express_Checkout_Element::instance();
		$callback                       = array( $element, 'display_express_checkout_button_html' );
		add_action( 'woocommerce_proceed_to_checkout', $callback, 20 );

		StripeAdapter::register();
		do_action( 'wp' );

		self::assertFalse( has_action( 'woocommerce_proceed_to_checkout', $callback ) );
		self::assertSame( 20, has_action( ExpressButtons::RENDER_ACTION, $callback ) );
		ob_start();
		do_action( ExpressButtons::RENDER_ACTION );
		self::assertSame( '<div id="wc-stripe-express-checkout-element"></div>', ob_get_clean() );
	}

	/** Stripe stays attached to its native hook outside the classic cart. */
	public function test_stripe_stays_native_outside_supported_classic_cart(): void {
		$element  = \WC_Stripe_Express_Checkout_Element::instance();
		$callback = array( $element, 'display_express_checkout_button_html' );
		add_action( 'woocommerce_proceed_to_checkout', $callback, 20 );

		StripeAdapter::register();
		do_action( 'wp' );

		self::assertSame( 20, has_action( 'woocommerce_proceed_to_checkout', $callback ) );
		self::assertFalse( has_action( ExpressButtons::RENDER_ACTION, $callback ) );
	}
}
