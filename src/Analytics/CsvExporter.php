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
		$handle = fopen( 'php://temp', 'w+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- In-memory stream for generated CSV.
		if ( false === $handle ) {
			return '';
		}

		fputcsv( $handle, array( 'Date', 'Cart event', 'Action', 'Order ID', 'Refund ID', 'Currency', 'Total', 'Attributed revenue', 'Refunded', 'Coupon' ) );
		$limit  = 500;
		$offset = 0;
		$count  = 0;
		do {
			$rows = Reports::conversion_batch( $filters, $limit, $offset );
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
		} while ( $count === $limit );

		rewind( $handle );
		$csv = stream_get_contents( $handle );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closes the in-memory CSV stream.

		return is_string( $csv ) ? $csv : '';
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
