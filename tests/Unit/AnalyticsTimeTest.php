<?php
/**
 * Analytics timezone tests.
 *
 * @package StarfinitiCart
 */

declare(strict_types=1);

namespace Starfiniti\Cart\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Starfiniti\Cart\Analytics\Time;

/** Verify UTC storage maps to WordPress-local reporting days across DST. */
final class AnalyticsTimeTest extends TestCase {

	/** Restore an isolated UTC site after every test. */
	protected function tearDown(): void {
		$GLOBALS['sfcart_test_timezone'] = 'UTC';
		parent::tearDown();
	}

	/** Spring-forward days produce a 23-hour UTC query range. */
	public function test_local_day_range_respects_daylight_saving_time(): void {
		$GLOBALS['sfcart_test_timezone'] = 'Europe/Ljubljana';

		$range = Time::local_days_to_utc_range( '2026-03-29', '2026-03-29' );

		self::assertSame( '2026-03-28 23:00:00', $range['from'] );
		self::assertSame( '2026-03-29 22:00:00', $range['to_exclusive'] );
	}

	/** UTC events are presented on the correct local calendar day. */
	public function test_utc_event_is_converted_to_site_time(): void {
		$GLOBALS['sfcart_test_timezone'] = 'Europe/Ljubljana';

		self::assertSame( '2026-07-18 01:30:00', Time::utc_to_site( '2026-07-17 23:30:00' ) );
	}
}
