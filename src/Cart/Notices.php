<?php
/**
 * WooCommerce notice serialization.
 *
 * @package StarfinitiCart
 */

namespace Starfiniti\Cart\Cart;

/**
 * Converts WooCommerce notices into safe plain-text response data.
 */
final class Notices {

	/**
	 * Collect and clear the current WooCommerce notices.
	 *
	 * @return list<array{type: string, message: string}>
	 */
	public static function collect(): array {
		$collected = array();

		foreach ( wc_get_notices() as $type => $notices ) {
			foreach ( $notices as $notice ) {
				$message = is_array( $notice ) ? (string) ( $notice['notice'] ?? '' ) : (string) $notice;
				$message = trim( html_entity_decode( wp_strip_all_tags( $message ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );

				if ( '' !== $message ) {
					$collected[] = array(
						'type'    => sanitize_key( (string) $type ),
						'message' => $message,
					);
				}
			}
		}

		wc_clear_notices();

		return $collected;
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
