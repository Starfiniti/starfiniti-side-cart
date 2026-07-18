<?php
/**
 * Compatibility boundary tests.
 *
 * @package StarfinitiCart
 */

declare(strict_types=1);

namespace Starfiniti\Cart\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Starfiniti\Cart\Cart\ProductSnapshot;
use Starfiniti\Cart\Compatibility\CompatibilityManager;
use WC_Product;

/** Verify integrations convert and opt in at safe shared boundaries. */
final class CompatibilityTest extends TestCase {

	/** Reset isolated filter and product state. */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['sfcart_test_hooks']    = array();
		$GLOBALS['sfcart_test_actions']  = array();
		$GLOBALS['sfcart_test_products'] = array();
	}

	/** LiteSpeed receives the custom nonce registration before frontend output. */
	public function test_litespeed_nonce_and_purge_adapters_are_registered(): void {
		CompatibilityManager::register();

		self::assertContains( array( 'litespeed_nonce', array( 'sfcart_cart' ) ), $GLOBALS['sfcart_test_actions'] );
		self::assertSame( 10, has_action( 'sfcart_settings_updated', array( CompatibilityManager::class, 'purge_page_cache' ) ) );

		CompatibilityManager::tag_page_cache();
		self::assertContains( array( 'litespeed_tag_add', array( 'sfcart' ) ), $GLOBALS['sfcart_test_actions'] );

		CompatibilityManager::purge_page_cache();
		self::assertContains( array( 'litespeed_purge', array( 'sfcart' ) ), $GLOBALS['sfcart_test_actions'] );
		self::assertNotContains( array( 'litespeed_purge_all', array() ), $GLOBALS['sfcart_test_actions'] );
	}

	/** Currency adapters convert stored thresholds, never already-converted cart totals. */
	public function test_currency_adapter_is_registered_only_for_thresholds(): void {
		CompatibilityManager::register();

		self::assertSame( 20, has_action( 'sfcart_reward_threshold', array( CompatibilityManager::class, 'convert_reward_amount' ) ) );
		self::assertFalse( has_action( 'sfcart_reward_amount', array( CompatibilityManager::class, 'convert_reward_amount' ) ) );
	}

	/** Configured identifiers resolve through the active-language hook once. */
	public function test_product_resolution_uses_current_language_mapping(): void {
		$translated                          = new WC_Product( 22 );
		$GLOBALS['sfcart_test_products'][22] = $translated;
		add_filter( 'sfcart_product_id_for_current_language', static fn( int $product_id ): int => 11 === $product_id ? 22 : $product_id );

		self::assertSame( $translated, ProductSnapshot::resolve( 11 ) );
	}

	/** Complex products fail closed until an adapter explicitly supports their fields. */
	public function test_complex_products_require_explicit_adapter_opt_in(): void {
		$product = new class( 33 ) extends WC_Product {
			/** Report that the isolated product exists. */
			public function exists(): bool {
				return true;
			}

			/** Report that the isolated product is purchasable. */
			public function is_purchasable(): bool {
				return true;
			}

			/** Report that the isolated product is in stock. */
			public function is_in_stock(): bool {
				return true;
			}

			/** Return a complex product type. */
			public function get_type(): string {
				return 'bundle';
			}
		};

		self::assertFalse( ProductSnapshot::is_supported( $product ) );
		add_filter( 'sfcart_product_is_supported', static fn( bool $supported ): bool => $supported || ! $supported );
		self::assertTrue( ProductSnapshot::is_supported( $product ) );
	}
}
