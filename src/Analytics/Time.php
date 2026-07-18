<?php
/**
 * Site-time and UTC conversion helpers for analytics.
 *
 * @package StarfinitiCart
 */

namespace Starfiniti\Cart\Analytics;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Throwable;

/**
 * Keeps the analytics storage contract in UTC while reports use site-local days.
 */
final class Time {

	/**
	 * Return today's date in the configured WordPress timezone.
	 *
	 * @param string $modifier Optional relative date modifier.
	 */
	public static function site_date( string $modifier = '' ): string {
		$now = new DateTimeImmutable( 'now', self::site_timezone() );
		if ( '' !== $modifier ) {
			$modified = $now->modify( $modifier );
			$now      = false !== $modified ? $modified : $now;
		}

		return $now->format( 'Y-m-d' );
	}

	/**
	 * Convert inclusive local calendar days to an exclusive UTC SQL range.
	 *
	 * @param string $from First local calendar day.
	 * @param string $to Last local calendar day.
	 * @return array{from: string, to_exclusive: string}
	 */
	public static function local_days_to_utc_range( string $from, string $to ): array {
		$timezone = self::site_timezone();
		$start    = new DateTimeImmutable( $from . ' 00:00:00', $timezone );
		$end      = new DateTimeImmutable( $to . ' 00:00:00', $timezone );
		$end      = $end->modify( '+1 day' );

		return array(
			'from'         => $start->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
			'to_exclusive' => $end->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
		);
	}

	/**
	 * Normalize a timestamp, date object, or UTC string for SQL storage.
	 *
	 * @param mixed $date Candidate UTC date.
	 */
	public static function utc_mysql( mixed $date ): string {
		try {
			if ( $date instanceof DateTimeInterface ) {
				return DateTimeImmutable::createFromInterface( $date )
					->setTimezone( new DateTimeZone( 'UTC' ) )
					->format( 'Y-m-d H:i:s' );
			}
			if ( is_numeric( $date ) ) {
				return ( new DateTimeImmutable( '@' . (int) $date ) )
					->setTimezone( new DateTimeZone( 'UTC' ) )
					->format( 'Y-m-d H:i:s' );
			}

			return ( new DateTimeImmutable( (string) $date, new DateTimeZone( 'UTC' ) ) )
				->setTimezone( new DateTimeZone( 'UTC' ) )
				->format( 'Y-m-d H:i:s' );
		} catch ( Throwable ) {
			return current_time( 'mysql', true );
		}
	}

	/**
	 * Convert a legacy site-local MySQL datetime to UTC storage.
	 *
	 * @param string $date Legacy local datetime.
	 */
	public static function local_mysql_to_utc( string $date ): string {
		try {
			return ( new DateTimeImmutable( $date, self::site_timezone() ) )
				->setTimezone( new DateTimeZone( 'UTC' ) )
				->format( 'Y-m-d H:i:s' );
		} catch ( Throwable ) {
			return current_time( 'mysql', true );
		}
	}

	/**
	 * Convert one UTC SQL datetime to the configured site timezone.
	 *
	 * @param string $date UTC SQL datetime.
	 * @param string $format Output date format.
	 */
	public static function utc_to_site( string $date, string $format = 'Y-m-d H:i:s' ): string {
		try {
			return ( new DateTimeImmutable( $date, new DateTimeZone( 'UTC' ) ) )
				->setTimezone( self::site_timezone() )
				->format( $format );
		} catch ( Throwable ) {
			return $date;
		}
	}

	/** Resolve the WordPress timezone with a safe UTC fallback for isolated tests. */
	private static function site_timezone(): DateTimeZone {
		return function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
	}

	/** Prevent construction. */
	private function __construct() {
	}
}
