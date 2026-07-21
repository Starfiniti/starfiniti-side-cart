<?php
/**
 * Coarse public analytics abuse controls.
 *
 * @package StarfinitiCart
 */

namespace Starfiniti\Cart\Analytics;

/**
 * Limits public analytics writes without persisting raw network addresses.
 */
final class PublicRateLimiter {

	/** Default client attempts per ten-minute window. */
	private const CLIENT_LIMIT = 300;

	/** Default site-wide attempts per ten-minute window. */
	private const SITE_LIMIT = 5000;

	/** Consume one client and site-wide quota slot. */
	public static function consume(): bool {
		$client_key = self::client_key();
		if ( '' !== $client_key && ! self::consume_bucket( 'client_' . $client_key, self::client_limit() ) ) {
			return false;
		}

		return self::consume_bucket( 'site', self::site_limit() );
	}

	/** Return the filtered per-client quota. */
	private static function client_limit(): int {
		/**
		 * Filters the maximum public analytics requests from one anonymized client.
		 *
		 * @param int $limit Requests per ten-minute window.
		 */
		return max( 1, absint( apply_filters( 'sfcart_public_analytics_client_limit', self::CLIENT_LIMIT ) ) );
	}

	/** Return the filtered site-wide quota. */
	private static function site_limit(): int {
		/**
		 * Filters the maximum public analytics requests accepted by one site.
		 *
		 * @param int $limit Requests per ten-minute window.
		 */
		return max( 1, absint( apply_filters( 'sfcart_public_analytics_site_limit', self::SITE_LIMIT ) ) );
	}

	/**
	 * Consume one atomic quota bucket.
	 *
	 * @param string $bucket Anonymous quota bucket identifier.
	 * @param int    $limit  Maximum requests in the active window.
	 */
	private static function consume_bucket( string $bucket, int $limit ): bool {
		$window = intdiv( time(), PublicEventPolicy::QUOTA_WINDOW_SECONDS );
		$key    = 'sfcart_public_' . substr( hash( 'sha256', $bucket . '|' . $window ), 0, 32 );
		$ttl    = PublicEventPolicy::QUOTA_WINDOW_SECONDS + MINUTE_IN_SECONDS;

		if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ) {
			if ( wp_cache_add( $key, 1, 'sfcart_rate_limits', $ttl ) ) {
				return true;
			}

			$count = wp_cache_incr( $key, 1, 'sfcart_rate_limits' );
			return is_int( $count ) && $count <= $limit;
		}

		$option_name = '_transient_' . $key;
		if ( add_option( $option_name, 1, '', false ) ) {
			add_option( '_transient_timeout_' . $key, time() + $ttl, '', false );
			return true;
		}

		global $wpdb;
		if ( is_object( $wpdb ) && isset( $wpdb->options ) && is_string( $wpdb->options ) ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
			$sql     = $wpdb->prepare(
				"UPDATE {$wpdb->options} SET option_value = CAST(option_value AS UNSIGNED) + 1 WHERE option_name = %s AND CAST(option_value AS UNSIGNED) < %d",
				$option_name,
				$limit
			);
			$updated = is_string( $sql ) ? $wpdb->query( $sql ) : false;
			// phpcs:enable
			return 1 === $updated;
		}

		// This path is used only by incomplete test/development WordPress doubles.
		$count = max( 0, (int) get_option( $option_name, 0 ) );
		if ( $count >= $limit ) {
			return false;
		}
		update_option( $option_name, $count + 1, false );
		return true;
	}

	/** Return a keyed, irreversible identifier for the session and network peer. */
	private static function client_key(): string {
		$address     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';
		$address     = false !== filter_var( $address, FILTER_VALIDATE_IP ) ? $address : '';
		$session     = function_exists( 'WC' ) ? WC()->session : null;
		$customer_id = is_object( $session ) && is_callable( array( $session, 'get_customer_id' ) )
			? sanitize_text_field( (string) $session->get_customer_id() )
			: '';

		if ( '' === $address && '' === $customer_id ) {
			return '';
		}

		return substr( hash_hmac( 'sha256', $customer_id . '|' . $address, wp_salt( 'nonce' ) ), 0, 32 );
	}

	/** Prevent construction. */
	private function __construct() {
	}
}
