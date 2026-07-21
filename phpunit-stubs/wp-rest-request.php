<?php
/**
 * Minimal REST request test double.
 *
 * @package StarfinitiCart
 */

if ( ! class_exists( 'WP_REST_Request' ) ) {
	/** Minimal REST request test double. */
	class WP_REST_Request {
		/**
		 * Create a request with isolated parameters.
		 *
		 * @param array<string, mixed> $parameters Request parameters.
		 */
		public function __construct( private array $parameters = array() ) {
		}

		/**
		 * Return one request parameter.
		 *
		 * @param string $key Parameter key.
		 */
		public function get_param( string $key ): mixed {
			return $this->parameters[ $key ] ?? null;
		}
	}
}
