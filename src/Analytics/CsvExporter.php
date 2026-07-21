<?php
/**
 * Analytics CSV export.
 *
 * @package StarfinitiCart
 */

namespace Starfiniti\Cart\Analytics;

/**
 * Builds CSV exports with spreadsheet-injection protection.
 */
final class CsvExporter {

	/**
	 * Build conversion CSV content.
	 *
	 * @param array<string, mixed> $filters Normalized filters.
	 */
	public static function conversions( array $filters ): string {
		$handle = fopen( 'php://temp/maxmemory:1048576', 'w+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Bounded in-memory stream spills to a temporary file.
		if ( false === $handle ) {
			return '';
		}

		self::write( $handle, $filters );

		rewind( $handle );
		$csv = stream_get_contents( $handle );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closes the generated CSV stream.

		return is_string( $csv ) ? $csv : '';
	}

	/**
	 * Stream conversion CSV directly to the response body.
	 *
	 * @param array<string, mixed> $filters Normalized filters.
	 */
	public static function output( array $filters ): void {
		$handle = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Streams an authenticated generated download.
		if ( false === $handle ) {
			return;
		}

		self::write( $handle, $filters );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Completes the streamed response.
	}

	/**
	 * Write a bounded CSV export to an open stream.
	 *
	 * @param resource             $handle Open writable stream.
	 * @param array<string, mixed> $filters Normalized filters.
	 */
	private static function write( $handle, array $filters ): void {
		fputcsv( $handle, array( 'Date', 'Cart event', 'Action', 'Order ID', 'Refund ID', 'Currency', 'Total', 'Attributed revenue', 'Refunded', 'Coupon' ) );
		$limit   = 500;
		$offset  = 0;
		$count   = 0;
		$maximum = max( 1, min( 250000, absint( apply_filters( 'sfcart_analytics_export_max_rows', 50000 ) ) ) );
		do {
			$batch_limit = min( $limit, $maximum - $offset );
			if ( $batch_limit <= 0 ) {
				break;
			}
			$rows = Reports::conversion_batch( $filters, $batch_limit, $offset );
			foreach ( $rows as $row ) {
				fputcsv(
					$handle,
					array_map(
						array( self::class, 'cell' ),
						array(
							$row['date'],
							$row['type'],
							$row['status'],
							$row['order_id'],
							$row['refund_id'],
							$row['currency'],
							$row['total'],
							$row['revenue'],
							$row['refunded'],
							$row['coupon'],
						)
					)
				);
			}
			$count   = count( $rows );
			$offset += $count;
		} while ( $count === $batch_limit && $offset < $maximum );
	}

	/**
	 * Escape values that spreadsheet software may treat as formulas.
	 *
	 * @param mixed $value Candidate cell value.
	 */
	public static function cell( mixed $value ): string {
		$cell = (string) $value;
		return preg_match( '/^[=\+\-@\t\r]/', $cell ) ? "'" . $cell : $cell;
	}

	/** Prevent construction. */
	private function __construct() {
	}
}
