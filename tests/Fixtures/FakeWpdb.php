<?php
/**
 * Minimal wpdb test double.
 *
 * @package StarfinitiCart
 */

declare(strict_types=1);

namespace Starfiniti\Cart\Tests\Fixtures;

/**
 * Supports the subset of wpdb used by migration tests.
 */
final class FakeWpdb {

	/**
	 * Current table prefix.
	 *
	 * @var string
	 */
	public string $prefix = 'wp_';

	/**
	 * Last insert identifier.
	 *
	 * @var int
	 */
	public int $insert_id = 0;

	/**
	 * In-memory table rows keyed by table name.
	 *
	 * @var array<string, array<int, array<string, mixed>>>
	 */
	public array $tables = array();

	/**
	 * Most recent prepared query before placeholder replacement.
	 *
	 * @var string
	 */
	private string $prepared_query = '';

	/**
	 * Values supplied to the most recent prepared query.
	 *
	 * @var list<mixed>
	 */
	private array $prepared_args = array();

	/**
	 * Escape a LIKE value.
	 *
	 * @param string $text Text to escape.
	 */
	public function esc_like( string $text ): string {
		return $text;
	}

	/**
	 * Replace simple placeholders in a query.
	 *
	 * @param string $query   Query with placeholders.
	 * @param mixed  ...$args Placeholder values.
	 */
	public function prepare( string $query, mixed ...$args ): string {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}
		$this->prepared_query = $query;
		$this->prepared_args  = array_values( $args );
		foreach ( $args as $arg ) {
			$query = preg_replace( '/%s|%d/', (string) $arg, $query, 1 ) ?? $query;
		}

		return $query;
	}

	/**
	 * Return one scalar value for supported queries.
	 *
	 * @param string $query Query string.
	 * @return mixed
	 */
	public function get_var( string $query ): mixed {
		if ( preg_match( '/SHOW TABLES LIKE ([a-zA-Z0-9_]+)/', $query, $match ) ) {
			return array_key_exists( $match[1], $this->tables ) ? $match[1] : null;
		}
		if ( preg_match( '/SELECT COUNT\\(\\*\\) FROM ([a-zA-Z0-9_]+)/', $query, $match ) ) {
			return count( $this->tables[ $match[1] ] ?? array() );
		}
		if ( preg_match( '/SELECT id FROM ([a-zA-Z0-9_]+) WHERE event_key = ([^\\s]+) LIMIT 1/', $query, $match ) ) {
			foreach ( $this->tables[ $match[1] ] ?? array() as $row ) {
				if ( (string) ( $row['event_key'] ?? '' ) === $match[2] ) {
					return $row['id'];
				}
			}
		}

		return null;
	}

	/**
	 * Return rows for supported SELECT queries.
	 *
	 * @param string $query  Query string.
	 * @param string $output Output format.
	 * @return array
	 */
	public function get_results( string $query, string $output = '' ): array {
		unset( $output );
		if ( preg_match( '/SELECT \\* FROM ([a-zA-Z0-9_]+)/', $query, $match ) ) {
			$rows = $this->tables[ $match[1] ] ?? array();
			if ( preg_match( '/WHERE oid IN \\(([0-9,]+)\\)/', $query, $where ) ) {
				$ids  = array_map( 'intval', explode( ',', $where[1] ) );
				$rows = array_values( array_filter( $rows, static fn( array $row ): bool => in_array( (int) ( $row['oid'] ?? 0 ), $ids, true ) ) );
			}
			usort( $rows, static fn( array $left, array $right ): int => (int) ( $left['oid'] ?? 0 ) <=> (int) ( $right['oid'] ?? 0 ) );
			if ( preg_match( '/LIMIT ([0-9]+) OFFSET ([0-9]+)/', $query, $limit ) ) {
				$rows = array_slice( $rows, (int) $limit[2], (int) $limit[1] );
			}

			return $rows;
		}

		return array();
	}

	/**
	 * Insert one row.
	 *
	 * @param string $table Table name.
	 * @param array  $data  Insert data.
	 */
	public function insert( string $table, array $data ): bool {
		$rows                   = $this->tables[ $table ] ?? array();
		$this->insert_id        = count( $rows ) + 1;
		$data['id']             = $this->insert_id;
		$rows[]                 = $data;
		$this->tables[ $table ] = $rows;

		return true;
	}

	/**
	 * Update one row by id.
	 *
	 * @param string $table Table name.
	 * @param array  $data  Update data.
	 * @param array  $where Where clause.
	 */
	public function update( string $table, array $data, array $where ): bool {
		foreach ( $this->tables[ $table ] ?? array() as $index => $row ) {
			if ( (int) ( $row['id'] ?? 0 ) === (int) ( $where['id'] ?? 0 ) ) {
				$this->tables[ $table ][ $index ] = array_merge( $row, $data );
				return true;
			}
		}

		return false;
	}

	/**
	 * Execute supported write queries.
	 *
	 * @param string $query Query string.
	 */
	public function query( string $query ): bool {
		if (
			str_starts_with( $query, 'INSERT INTO ' ) &&
			preg_match( '/^INSERT INTO ([a-zA-Z0-9_]+) \(([^)]+)\) VALUES/', $this->prepared_query, $insert )
		) {
			$table   = $insert[1];
			$columns = array_map(
				static fn( string $column ): string => trim( $column, " `\t\n\r\0\x0B" ),
				explode( ',', $insert[2] )
			);
			if ( count( $columns ) !== count( $this->prepared_args ) ) {
				return false;
			}

			$data = array_combine( $columns, $this->prepared_args );
			if ( ! is_array( $data ) ) {
				return false;
			}

			foreach ( $this->tables[ $table ] ?? array() as $index => $row ) {
				if ( (string) ( $row['event_key'] ?? '' ) !== (string) ( $data['event_key'] ?? '' ) ) {
					continue;
				}

				$data['id']         = $row['id'];
				$data['created_at'] = $row['created_at'];

				$this->tables[ $table ][ $index ] = array_merge( $row, $data );

				$this->insert_id = (int) $row['id'];
				return true;
			}

			return $this->insert( $table, $data );
		}

		if ( preg_match( '/DROP TABLE IF EXISTS ([a-zA-Z0-9_]+)/', $query, $match ) ) {
			unset( $this->tables[ $match[1] ] );
		}

		return true;
	}
}
