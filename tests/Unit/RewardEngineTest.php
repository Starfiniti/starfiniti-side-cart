<?php
/**
 * Free-shipping reward rate tests.
 *
 * @package StarfinitiCart
 */

declare(strict_types=1);

namespace Starfiniti\Cart\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Starfiniti\Cart\Rewards\RewardEngine;
use WC_Shipping_Rate;

/**
 * Verify that free-shipping milestones never duplicate WooCommerce's own rate.
 */
final class RewardEngineTest extends TestCase {

	/** WooCommerce session key recording an achieved free-shipping milestone. */
	private const ACHIEVED_KEY = 'sfcart_reward_free_shipping';

	/** Use an isolated WooCommerce session. */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['sfcart_test_woocommerce']->session = $this->session();
	}

	/** Remove the isolated WooCommerce session. */
	protected function tearDown(): void {
		$GLOBALS['sfcart_test_woocommerce']->session = null;
		parent::tearDown();
	}

	/** An available native free_shipping rate is reused and preselected. */
	public function test_native_free_shipping_rate_is_reused_instead_of_duplicated(): void {
		WC()->session->set( self::ACHIEVED_KEY, true );
		$rates = $this->rates( true );

		$filtered = RewardEngine::shipping_rates( $rates, array() );

		self::assertSame( array( 'flat_rate:1', 'free_shipping:2' ), array_keys( $filtered ) );
		self::assertArrayNotHasKey( RewardEngine::SHIPPING_RATE_ID, $filtered );
		self::assertSame( 'free_shipping:2', RewardEngine::chosen_shipping_method( 'flat_rate:1', $filtered, '' ) );
	}

	/** Packages without a native free rate keep receiving the owned reward rate. */
	public function test_reward_rate_is_added_without_native_free_shipping(): void {
		WC()->session->set( self::ACHIEVED_KEY, true );

		$filtered = RewardEngine::shipping_rates( $this->rates( false ), array() );

		self::assertSame( array( 'flat_rate:1', RewardEngine::SHIPPING_RATE_ID ), array_keys( $filtered ) );
		self::assertSame( '0', $filtered[ RewardEngine::SHIPPING_RATE_ID ]->get_cost() );
		self::assertSame( RewardEngine::SHIPPING_RATE_ID, RewardEngine::chosen_shipping_method( 'flat_rate:1', $filtered, '' ) );
	}

	/** Without an achieved milestone, WooCommerce's rates and default stay untouched. */
	public function test_rates_are_unchanged_before_the_milestone_is_achieved(): void {
		$rates = $this->rates( true );

		self::assertSame( $rates, RewardEngine::shipping_rates( $rates, array() ) );
		self::assertSame( 'flat_rate:1', RewardEngine::chosen_shipping_method( 'flat_rate:1', $rates, '' ) );
	}

	/**
	 * Build calculated package rates.
	 *
	 * @param bool $with_free_shipping Whether WooCommerce's free_shipping method is available.
	 * @return array<string, WC_Shipping_Rate>
	 */
	private function rates( bool $with_free_shipping ): array {
		$rates = array(
			'flat_rate:1' => new WC_Shipping_Rate( 'flat_rate:1', 'Flat rate', '6', array(), 'flat_rate', 1 ),
		);
		if ( $with_free_shipping ) {
			$rates['free_shipping:2'] = new WC_Shipping_Rate( 'free_shipping:2', 'Free shipping', '0', array(), 'free_shipping', 2 );
		}

		return $rates;
	}

	/** Return a minimal WooCommerce session double. */
	private function session(): object {
		return new class() {
			/**
			 * Stored values.
			 *
			 * @var array<string, mixed>
			 */
			private array $values = array();

			/**
			 * Read one stored value.
			 *
			 * @param string $key      Storage key.
			 * @param mixed  $fallback Fallback value.
			 */
			public function get( string $key, mixed $fallback = null ): mixed {
				return $this->values[ $key ] ?? $fallback;
			}

			/**
			 * Store one value.
			 *
			 * @param string $key   Storage key.
			 * @param mixed  $value Stored value.
			 */
			public function set( string $key, mixed $value ): void {
				$this->values[ $key ] = $value;
			}
		};
	}
}
