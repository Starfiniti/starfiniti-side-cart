<?php
/**
 * Cart quantity constraints.
 *
 * @package StarfinitiCart
 */

namespace Starfiniti\Cart\Cart;

use WC_Product;

/**
 * Normalizes WooCommerce quantity constraints for PHP and JavaScript consumers.
 */
final class Quantity {

	/**
	 * Return normalized quantity limits for a product.
	 *
	 * @param WC_Product $product Product represented by the cart item.
	 * @return array{editable: bool, min: int, max: int|null, step: int}
	 */
	public static function limits( WC_Product $product ): array {
		$arguments = wc_get_quantity_input_args( array(), $product );
		$minimum   = max( 0, (int) ( $arguments['min_value'] ?? 1 ) );
		$maximum   = (int) ( $arguments['max_value'] ?? 0 );
		$step      = (int) ( $arguments['step'] ?? 1 );

		return array(
			'editable' => ! $product->is_sold_individually(),
			'min'      => $minimum,
			'max'      => $maximum > 0 ? $maximum : null,
			'step'     => $step > 0 ? $step : 1,
		);
	}

	/**
	 * Report whether a quantity aligns with the product's configured step.
	 *
	 * @param int $quantity Requested quantity.
	 * @param int $minimum  Minimum quantity.
	 * @param int $step     Quantity increment.
	 */
	public static function is_step_aligned( int $quantity, int $minimum, int $step ): bool {
		$steps = ( $quantity - $minimum ) / $step;

		return abs( $steps - round( $steps ) ) < 0.000001;
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
