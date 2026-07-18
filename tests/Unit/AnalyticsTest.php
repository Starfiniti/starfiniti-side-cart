<?php
/**
 * Analytics utility tests.
 *
 * @package StarfinitiCart
 */

declare(strict_types=1);

namespace Starfiniti\Cart\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Starfiniti\Cart\Analytics\CsvExporter;

/**
 * Verify export hardening that does not require WordPress integration.
 */
final class AnalyticsTest extends TestCase {

	/**
	 * Formula-like CSV values are prefixed before they reach spreadsheet apps.
	 */
	public function test_csv_cells_escape_spreadsheet_formulas(): void {
		self::assertSame( "'=cmd", CsvExporter::cell( '=cmd' ) );
		self::assertSame( "'+SUM(A1:A2)", CsvExporter::cell( '+SUM(A1:A2)' ) );
		self::assertSame( "'-10", CsvExporter::cell( '-10' ) );
		self::assertSame( "'@user", CsvExporter::cell( '@user' ) );
		self::assertSame( 'Normal text', CsvExporter::cell( 'Normal text' ) );
	}
}
