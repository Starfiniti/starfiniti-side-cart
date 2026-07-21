<?php
/**
 * Session-scoped cart request token tests.
 *
 * @package StarfinitiCart
 */

declare(strict_types=1);

namespace Starfiniti\Cart\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Starfiniti\Cart\Cart\SessionToken;

/** Verify public mutation tokens stay bound to one WooCommerce session. */
final class SessionTokenTest extends TestCase {

	/** A session receives one stable cryptographically random token. */
	public function test_token_is_stable_and_verifiable_within_session(): void {
		$session                                     = $this->session();
		$GLOBALS['sfcart_test_woocommerce']->session = $session;

		$token = SessionToken::current();

		self::assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $token );
		self::assertSame( $token, SessionToken::current() );
		self::assertTrue( SessionToken::verify( $token ) );
		self::assertFalse( SessionToken::verify( str_repeat( '0', 64 ) ) );
	}

	/** A copied token is rejected after the visitor session changes. */
	public function test_token_cannot_be_reused_across_sessions(): void {
		$GLOBALS['sfcart_test_woocommerce']->session = $this->session();
		$first                                       = SessionToken::current();

		$GLOBALS['sfcart_test_woocommerce']->session = $this->session();
		$second                                      = SessionToken::current();

		self::assertNotSame( $first, $second );
		self::assertFalse( SessionToken::verify( $first ) );
		self::assertTrue( SessionToken::verify( $second ) );
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
