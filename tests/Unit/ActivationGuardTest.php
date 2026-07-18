<?php
/**
 * Activation conflict guard tests.
 *
 * @package StarfinitiCart
 */

declare(strict_types=1);

namespace Starfiniti\Cart\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Starfiniti\Cart\Lifecycle\Activator;
use Starfiniti\Cart\Requirements;

/**
 * Verify conflict detection refuses activation without changing plugin state.
 */
final class ActivationGuardTest extends TestCase {

	/**
	 * Establish a compatible WordPress runtime with the conflicting cart active.
	 */
	protected function setUp(): void {
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Isolated version test stub.
		$GLOBALS['wp_version']          = '6.6';
		$GLOBALS['sfcart_test_options'] = array(
			'active_plugins' => array( 'cart-for-woocommerce/plugin.php' ),
		);
	}

	/**
	 * Conflict detection must win before the missing WooCommerce message.
	 */
	public function test_conflicting_cart_is_detected(): void {
		$failure = Requirements::activation_failure( false );

		self::assertNotNull( $failure );
		self::assertSame( 'cart_conflict', $failure->code() );
	}

	/**
	 * WooCommerce remains the only required runtime plugin.
	 */
	public function test_missing_woocommerce_is_reported_without_a_conflict(): void {
		$GLOBALS['sfcart_test_options']['active_plugins'] = array();
		$failure = Requirements::activation_failure( false );

		self::assertNotNull( $failure );
		self::assertSame( 'woocommerce_missing', $failure->code() );
	}

	/**
	 * Refused activation leaves the conflicting plugin active.
	 */
	public function test_refused_activation_never_deactivates_another_plugin(): void {
		try {
			Activator::activate( false );
			self::fail( 'Activation should have been refused.' );
		} catch ( RuntimeException $error ) {
			self::assertStringContainsString( 'FunnelKit Cart', $error->getMessage() );
		}

		self::assertSame(
			array( 'cart-for-woocommerce/plugin.php' ),
			$GLOBALS['sfcart_test_options']['active_plugins']
		);
	}
}
