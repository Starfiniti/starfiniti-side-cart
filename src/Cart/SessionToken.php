<?php
/**
 * Session-scoped public request token.
 *
 * @package StarfinitiCart
 */

namespace Starfiniti\Cart\Cart;

/**
 * Binds public cart mutations to the active WooCommerce session.
 */
final class SessionToken {

	/** WooCommerce session storage key. */
	private const SESSION_KEY = 'sfcart_request_token';

	/** Return the current token, creating it when necessary. */
	public static function current(): string {
		$session = function_exists( 'WC' ) ? WC()->session : null;
		if ( ! is_object( $session ) || ! is_callable( array( $session, 'get' ) ) || ! is_callable( array( $session, 'set' ) ) ) {
			return '';
		}

		$token = $session->get( self::SESSION_KEY, '' );
		if ( is_string( $token ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
			return $token;
		}

		try {
			$token = bin2hex( random_bytes( 32 ) );
		} catch ( \Throwable ) {
			$token = hash( 'sha256', wp_generate_uuid4() . '|' . wp_salt( 'nonce' ) );
		}

		$session->set( self::SESSION_KEY, $token );
		return $token;
	}

	/**
	 * Verify a submitted token without accepting an empty session.
	 *
	 * @param string $token Submitted token.
	 */
	public static function verify( string $token ): bool {
		$current = self::current();
		return '' !== $current && 1 === preg_match( '/^[a-f0-9]{64}$/', $token ) && hash_equals( $current, $token );
	}

	/** Prevent construction. */
	private function __construct() {
	}
}
