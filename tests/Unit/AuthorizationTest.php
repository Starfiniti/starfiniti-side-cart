<?php
/**
 * Administration authorization boundary tests.
 *
 * @package StarfinitiCart
 */

declare(strict_types=1);

namespace Starfiniti\Cart\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Starfiniti\Cart\Rest\AdminController;
use WP_REST_Request;

/** Verify object mutations require both broad and object-level capabilities. */
final class AuthorizationTest extends TestCase {

	/** Reset isolated capabilities. */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['sfcart_test_caps'] = array();
	}

	/** Product relationships cannot be changed with manage_woocommerce alone. */
	public function test_relationship_permission_requires_product_edit_capability(): void {
		$request = new WP_REST_Request( array( 'id' => 42 ) );
		$GLOBALS['sfcart_test_caps']['manage_woocommerce'] = true;

		self::assertFalse( AdminController::can_manage_relationships( $request ) );

		$GLOBALS['sfcart_test_caps']['edit_post:42'] = true;
		self::assertTrue( AdminController::can_manage_relationships( $request ) );
	}
}
