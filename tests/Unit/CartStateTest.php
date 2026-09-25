<?php
/**
 * Drawer cart-state serialization tests.
 *
 * @package StarfinitiCart
 */

declare(strict_types=1);

namespace Starfiniti\Cart\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Starfiniti\Cart\Cart\CartState;
use WC_Cart;

/**
 * Verify the drawer summary stays consistent with WooCommerce totals.
 */
final class CartStateTest extends TestCase {

	/**
	 * Original isolated WooCommerce container.
	 *
	 * @var object
	 */
	private object $woocommerce;

	/** Preserve the shared WooCommerce container. */
	protected function setUp(): void {
		parent::setUp();
		$this->woocommerce = $GLOBALS['sfcart_test_woocommerce'];
	}

	/** Restore the shared WooCommerce container. */
	protected function tearDown(): void {
		$GLOBALS['sfcart_test_woocommerce'] = $this->woocommerce;
		parent::tearDown();
	}

	/** Carts without shippable items show no shipping row. */
	public function test_shipping_is_hidden_when_cart_does_not_need_shipping(): void {
		$this->use_packages( array( array( 'rates' => array( 'flat_rate:1' => 'rate' ) ) ) );
		$cart = $this->cart( false, true, '6,00 €' );

		self::assertSame( '', CartState::shipping_text( $cart ) );
	}

	/**
	 * Estimated shipping already included in the total is shown, not deferred.
	 *
	 * This mirrors a store where shipping costs do not require an address and
	 * the default customer location is the shop base address.
	 */
	public function test_estimated_shipping_included_in_total_is_shown(): void {
		$this->use_packages( array( array( 'rates' => array( 'flat_rate:1' => 'rate' ) ) ) );
		$cart = $this->cart( true, true, '<span class="amount"><bdi>6,00&nbsp;<span>&euro;</span></bdi></span>' );

		self::assertSame( "6,00\u{00a0}€", CartState::shipping_text( $cart ) );
	}

	/** A zero-cost chosen rate is reported with WooCommerce's own free label. */
	public function test_free_shipping_included_in_total_is_shown(): void {
		$this->use_packages( array( array( 'rates' => array( 'free_shipping:2' => 'rate' ) ) ) );
		$cart = $this->cart( true, true, 'Free!' );

		self::assertSame( 'Free!', CartState::shipping_text( $cart ) );
	}

	/** WooCommerce leaves shipping out of the total until it may show it. */
	public function test_shipping_is_deferred_when_woocommerce_does_not_show_it(): void {
		$this->use_packages( array( array( 'rates' => array( 'flat_rate:1' => 'rate' ) ) ) );
		$cart = $this->cart( true, false, '6,00 €' );

		self::assertSame( 'Calculated at checkout', CartState::shipping_text( $cart ) );
	}

	/** Packages without any rate never read as free shipping. */
	public function test_shipping_is_deferred_when_no_rate_is_available(): void {
		$this->use_packages( array( array( 'rates' => array() ) ) );
		$cart = $this->cart( true, true, 'Free!' );

		self::assertSame( 'Calculated at checkout', CartState::shipping_text( $cart ) );
	}

	/**
	 * Build an isolated cart double.
	 *
	 * @param bool   $needs_shipping Whether the cart contains shippable items.
	 * @param bool   $show_shipping  WooCommerce's WC_Cart::show_shipping() result.
	 * @param string $shipping_total Formatted WooCommerce shipping total.
	 */
	private function cart( bool $needs_shipping, bool $show_shipping, string $shipping_total ): WC_Cart {
		$cart                 = new WC_Cart();
		$cart->needs_shipping = $needs_shipping;
		$cart->show_shipping  = $show_shipping;
		$cart->shipping_total = $shipping_total;

		return $cart;
	}

	/**
	 * Replace the WooCommerce container with calculated shipping packages.
	 *
	 * @param list<array<string, mixed>> $packages Calculated shipping packages.
	 */
	private function use_packages( array $packages ): void {
		$GLOBALS['sfcart_test_woocommerce'] = new class( $packages ) {
			/**
			 * Isolated WooCommerce session.
			 *
			 * @var object|null
			 */
			public ?object $session = null;

			/**
			 * Build the container.
			 *
			 * @param list<array<string, mixed>> $packages Calculated shipping packages.
			 */
			public function __construct( private array $packages ) {
			}

			/** Return a shipping service double. */
			public function shipping(): object {
				return new class( $this->packages ) {
					/**
					 * Build the shipping service double.
					 *
					 * @param list<array<string, mixed>> $packages Calculated shipping packages.
					 */
					public function __construct( private array $packages ) {
					}

					/**
					 * Return the calculated packages.
					 *
					 * @return list<array<string, mixed>>
					 */
					public function get_packages(): array {
						return $this->packages;
					}
				};
			}
		};
	}
}
