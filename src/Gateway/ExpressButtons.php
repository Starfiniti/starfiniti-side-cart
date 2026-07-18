<?php
/**
 * Official gateway express-button adapters.
 *
 * @package StarfinitiCart
 */

namespace Starfiniti\Cart\Gateway;

/**
 * Connects gateway-owned mini-cart controls to the owned drawer target.
 */
final class ExpressButtons {

	/** Drawer-only rendering action used by supported official gateways. */
	public const RENDER_ACTION = 'sfcart_express_buttons';

	/** Register version-guarded adapters without taking over payment behavior. */
	public static function register(): void {
		PayPalPaymentsAdapter::register();
		StripeAdapter::register();
	}

	/** Prevent construction. */
	private function __construct() {
	}
}
