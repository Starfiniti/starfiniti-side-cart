<?php
/**
 * Minimal WordPress error fixture.
 *
 * @package StarfinitiCart
 */

declare(strict_types=1);

if ( ! class_exists( 'WP_Error' ) ) {
	/** Minimal WordPress error object for isolated updater tests. */
	final class WP_Error {
		/**
		 * Create an isolated WordPress error.
		 *
		 * @param string $code    Error code.
		 * @param string $message Error message.
		 */
		public function __construct( private string $code = '', private string $message = '' ) {
		}

		/** Return the primary error code. */
		public function get_error_code(): string {
			return $this->code;
		}

		/** Return the primary error message. */
		public function get_error_message(): string {
			return $this->message;
		}
	}
}
