<?php
/**
 * Public cart analytics validation and abuse controls.
 *
 * @package StarfinitiCart
 */

namespace Starfiniti\Cart\Analytics;

/**
 * Converts public UI signals into bounded, server-owned analytics events.
 */
final class PublicEventPolicy {

	/** Maximum persisted event attempts per WooCommerce session window. */
	public const MAX_EVENTS_PER_WINDOW = 60;

	/** Quota window in seconds. */
	public const QUOTA_WINDOW_SECONDS = 600;

	/** Supported event types. */
	private const TYPES = array( 'cart_open', 'cart_close', 'cart_interaction', 'checkout_click' );

	/** Supported cart interaction actions. */
	private const INTERACTIONS = array(
		'quantity_update',
		'item_remove',
		'coupon_apply',
		'coupon_remove',
		'recommendation_add',
		'addon_select',
		'addon_remove',
		'addon_variation',
	);

	/**
	 * Normalize one public event without trusting client cart state.
	 *
	 * @param string $type       Requested event type.
	 * @param string $action     Requested interaction action.
	 * @param string $outcome    Requested interaction outcome.
	 * @param bool   $cart_empty Server-resolved cart state.
	 * @param int    $item_count Server-resolved cart item count.
	 * @param int    $timestamp  Current Unix timestamp.
	 * @return array{type: string, status: string, event_id: string, metadata: array{item_count: int, outcome: string}}|null
	 */
	public static function normalize( string $type, string $action, string $outcome, bool $cart_empty, int $item_count, int $timestamp ): ?array {
		$type = sanitize_key( $type );
		if ( ! in_array( $type, self::TYPES, true ) ) {
			return null;
		}

		if ( 'cart_interaction' === $type ) {
			$action = sanitize_key( $action );
			if ( ! in_array( $action, self::INTERACTIONS, true ) || ! in_array( $outcome, array( 'success', 'failed' ), true ) ) {
				return null;
			}
		} else {
			$action  = $cart_empty ? 'empty' : 'non_empty';
			$outcome = '';
		}

		$bucket = match ( $type ) {
			'checkout_click' => 300,
			'cart_interaction' => 10,
			default => 30,
		};
		$period = intdiv( max( 0, $timestamp ), $bucket );

		return array(
			'type'     => $type,
			'status'   => $action,
			'event_id' => substr( hash( 'sha256', $type . '|' . $action . '|' . $outcome . '|' . $period ), 0, 32 ),
			'metadata' => array(
				'item_count' => max( 0, $item_count ),
				'outcome'    => $outcome,
			),
		);
	}

	/**
	 * Consume one bounded session quota slot.
	 *
	 * @param mixed $state     Previously stored quota state.
	 * @param int   $timestamp Current Unix timestamp.
	 * @return array{allowed: bool, state: array{window: int, count: int}}
	 */
	public static function consume_quota( mixed $state, int $timestamp ): array {
		$window = intdiv( max( 0, $timestamp ), self::QUOTA_WINDOW_SECONDS );
		$count  = 0;
		if ( is_array( $state ) && (int) ( $state['window'] ?? -1 ) === $window ) {
			$count = max( 0, (int) ( $state['count'] ?? 0 ) );
		}

		if ( $count >= self::MAX_EVENTS_PER_WINDOW ) {
			return array(
				'allowed' => false,
				'state'   => array(
					'window' => $window,
					'count'  => $count,
				),
			);
		}

		return array(
			'allowed' => true,
			'state'   => array(
				'window' => $window,
				'count'  => $count + 1,
			),
		);
	}

	/** Prevent construction. */
	private function __construct() {
	}
}
