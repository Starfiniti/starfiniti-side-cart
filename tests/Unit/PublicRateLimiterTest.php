<?php
/**
 * Public analytics coarse rate-limiter tests.
 *
 * @package StarfinitiCart
 */

declare(strict_types=1);

namespace Starfiniti\Cart\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Starfiniti\Cart\Analytics\PublicRateLimiter;

/** Verify public analytics cannot write without a bounded server quota. */
final class PublicRateLimiterTest extends TestCase {

	/** Reset request, transient, and filter state. */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['sfcart_test_local_transients']     = array();
		$GLOBALS['sfcart_test_options']              = array();
		$GLOBALS['sfcart_test_hooks']                = array();
		$GLOBALS['sfcart_test_woocommerce']->session = null;
		$_SERVER['REMOTE_ADDR']                      = '203.0.113.10';
	}

	/** One anonymized client cannot exceed its configured request budget. */
	public function test_client_quota_is_bounded_without_storing_raw_address(): void {
		add_filter( 'sfcart_public_analytics_client_limit', static fn(): int => 2 );
		add_filter( 'sfcart_public_analytics_site_limit', static fn(): int => 100 );

		self::assertTrue( PublicRateLimiter::consume() );
		self::assertTrue( PublicRateLimiter::consume() );
		self::assertFalse( PublicRateLimiter::consume() );
		self::assertStringNotContainsString( '203.0.113.10', (string) wp_json_encode( $GLOBALS['sfcart_test_local_transients'] ) );
	}

	/** A site-wide ceiling remains effective across different clients. */
	public function test_site_quota_is_bounded_across_clients(): void {
		add_filter( 'sfcart_public_analytics_client_limit', static fn(): int => 100 );
		add_filter( 'sfcart_public_analytics_site_limit', static fn(): int => 2 );

		self::assertTrue( PublicRateLimiter::consume() );
		$_SERVER['REMOTE_ADDR'] = '203.0.113.11';
		self::assertTrue( PublicRateLimiter::consume() );
		$_SERVER['REMOTE_ADDR'] = '203.0.113.12';
		self::assertFalse( PublicRateLimiter::consume() );
	}

	/** Visitors behind one proxy retain independent session-level buckets. */
	public function test_shared_address_is_partitioned_by_woocommerce_session(): void {
		add_filter( 'sfcart_public_analytics_client_limit', static fn(): int => 1 );
		add_filter( 'sfcart_public_analytics_site_limit', static fn(): int => 100 );
		$GLOBALS['sfcart_test_woocommerce']->session = new class() {
			/** Return the isolated session identifier. */
			public function get_customer_id(): string {
				return 'session-a';
			}
		};
		self::assertTrue( PublicRateLimiter::consume() );
		self::assertFalse( PublicRateLimiter::consume() );

		$GLOBALS['sfcart_test_woocommerce']->session = new class() {
			/** Return the isolated session identifier. */
			public function get_customer_id(): string {
				return 'session-b';
			}
		};
		self::assertTrue( PublicRateLimiter::consume() );
	}
}
