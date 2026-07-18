<?php
/**
 * Product quantity constraint tests.
 *
 * @package StarfinitiCart
 */

declare(strict_types=1);

namespace Starfiniti\Cart\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Starfiniti\Cart\Cart\Quantity;
use WC_Product;

/**
 * Verify normalized WooCommerce quantity constraints.
 */
final class QuantityTest extends TestCase {

	/**
	 * Product input limits are exposed without an artificial maximum.
	 */
	public function test_quantity_limits_are_normalized(): void {
		$GLOBALS['sfcart_test_quantity_args'] = array(
			'max_value' => 8,
			'min_value' => 2,
			'step'      => 2,
		);
		$product                              = new WC_Product();

		self::assertSame(
			array(
				'editable' => true,
				'min'      => 2,
				'max'      => 8,
				'step'     => 2,
			),
			Quantity::limits( $product )
		);
		self::assertTrue( Quantity::is_step_aligned( 6, 2, 2 ) );
		self::assertFalse( Quantity::is_step_aligned( 5, 2, 2 ) );
	}

	/**
	 * Sold-individually products cannot render mutable quantity controls.
	 */
	public function test_sold_individually_product_is_not_editable(): void {
		$GLOBALS['sfcart_test_quantity_args'] = array(
			'max_value' => 0,
			'min_value' => 1,
			'step'      => 1,
		);
		$product                              = new WC_Product();
		$product->sold_individually           = true;
		$limits                               = Quantity::limits( $product );

		self::assertFalse( $limits['editable'] );
		self::assertNull( $limits['max'] );
	}
}
