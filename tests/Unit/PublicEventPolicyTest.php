<?php
/**
 * Public analytics abuse-control tests.
 *
 * @package StarfinitiCart
 */

declare(strict_types=1);

namespace Starfiniti\Cart\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Starfiniti\Cart\Analytics\PublicEventPolicy;

/** Verify public events are server-owned, deduplicated, and bounded. */
final class PublicEventPolicyTest extends TestCase {

	/** Cart state and counts come from the server, not the client action. */
	public function test_cart_state_event_is_normalized_from_server_state(): void {
		$event = PublicEventPolicy::normalize( 'cart_open', 'forged', 'failed', false, 7, 100 );

		self::assertNotNull( $event );
		self::assertSame( 'non_empty', $event['status'] );
		self::assertSame(
			array(
				'item_count' => 7,
				'outcome'    => '',
			),
			$event['metadata']
		);
	}

	/** Unknown event types, interaction actions, and outcomes are rejected. */
	public function test_unknown_public_events_are_rejected(): void {
		self::assertNull( PublicEventPolicy::normalize( 'unknown', '', '', true, 0, 100 ) );
		self::assertNull( PublicEventPolicy::normalize( 'cart_interaction', 'forged', 'success', true, 0, 100 ) );
		self::assertNull( PublicEventPolicy::normalize( 'cart_interaction', 'coupon_apply', 'maybe', true, 0, 100 ) );
	}

	/** Repeated events share a server-derived key inside their time bucket. */
	public function test_event_identifier_is_time_bucketed(): void {
		$first  = PublicEventPolicy::normalize( 'cart_interaction', 'coupon_apply', 'success', false, 2, 100 );
		$repeat = PublicEventPolicy::normalize( 'cart_interaction', 'coupon_apply', 'success', false, 2, 109 );
		$later  = PublicEventPolicy::normalize( 'cart_interaction', 'coupon_apply', 'success', false, 2, 110 );

		self::assertNotNull( $first );
		self::assertNotNull( $repeat );
		self::assertNotNull( $later );
		self::assertSame( $first['event_id'], $repeat['event_id'] );
		self::assertNotSame( $first['event_id'], $later['event_id'] );
	}

	/** One WooCommerce session cannot persist unbounded public events. */
	public function test_session_quota_is_bounded_and_resets(): void {
		$state = array();
		for ( $index = 0; $index < PublicEventPolicy::MAX_EVENTS_PER_WINDOW; ++$index ) {
			$result = PublicEventPolicy::consume_quota( $state, 100 );
			self::assertTrue( $result['allowed'] );
			$state = $result['state'];
		}

		$blocked = PublicEventPolicy::consume_quota( $state, 100 );
		self::assertFalse( $blocked['allowed'] );

		$reset = PublicEventPolicy::consume_quota( $state, 100 + PublicEventPolicy::QUOTA_WINDOW_SECONDS );
		self::assertTrue( $reset['allowed'] );
		self::assertSame( 1, $reset['state']['count'] );
	}
}
