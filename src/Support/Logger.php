<?php
/**
 * WooCommerce-backed plugin logger.
 *
 * @package StarfinitiCart
 */

namespace Starfiniti\Cart\Support;

use Throwable;

/**
 * Writes structured messages through WooCommerce without making logging fatal.
 */
final class Logger {

	/**
	 * WooCommerce log source.
	 */
	private const SOURCE = 'starfiniti-cart';

	/**
	 * Write an informational message.
	 *
	 * @param string               $message Message without user secrets.
	 * @param array<string, mixed> $context Structured context.
	 */
	public static function info( string $message, array $context = array() ): void {
		self::write( 'info', $message, $context );
	}

	/**
	 * Write an error message.
	 *
	 * @param string               $message Message without user secrets.
	 * @param array<string, mixed> $context Structured context.
	 */
	public static function error( string $message, array $context = array() ): void {
		self::write( 'error', $message, $context );
	}

	/**
	 * Log a caught exception without exposing its details in an admin notice.
	 *
	 * @param string    $message Summary without user secrets.
	 * @param Throwable $error   Caught error.
	 */
	public static function exception( string $message, Throwable $error ): void {
		self::error(
			$message,
			array(
				'exception_class' => get_class( $error ),
				'exception_code'  => $error->getCode(),
				'exception_file'  => $error->getFile(),
				'exception_line'  => $error->getLine(),
				'exception_text'  => $error->getMessage(),
			)
		);
	}

	/**
	 * Write through the WooCommerce logger when it is available.
	 *
	 * @param string               $level   WooCommerce log level.
	 * @param string               $message Message without user secrets.
	 * @param array<string, mixed> $context Structured context.
	 */
	private static function write( string $level, string $message, array $context ): void {
		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}

		try {
			$logger = wc_get_logger();

			if ( ! is_object( $logger ) || ! method_exists( $logger, 'log' ) ) {
				return;
			}

			$context['source'] = self::SOURCE;
			$logger->log( $level, $message, $context );
		} catch ( Throwable ) {
			// Logging must never break activation or storefront requests.
			return;
		}
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
