<?php
declare(strict_types=1);

namespace Rungud\Audit;

use Rungud\Install\Schema;

/**
 * Append-only audit log (CLAUDE.md rule 5). There is no update and no delete
 * method on purpose. A failed command stays failed; a manual retry is its own
 * row pointing at the failure through `retry_of`.
 */
final class Audit {

	public const OK     = 'ok';
	public const FAILED = 'failed';

	/**
	 * @param array{
	 *   entity:string, action:string, summary_en:string, summary_de:string,
	 *   entity_id?:string|int, status?:string, error?:string|null,
	 *   before?:mixed, after?:mixed, request_id?:string|null, retry_of?:int|null
	 * } $row
	 * @return int audit id
	 */
	public static function record( array $row ): int {
		foreach ( array( 'entity', 'action', 'summary_en', 'summary_de' ) as $required ) {
			if ( empty( $row[ $required ] ) ) {
				throw new \InvalidArgumentException( "Audit row needs {$required}." );
			}
		}
		$status = $row['status'] ?? self::OK;
		if ( ! in_array( $status, array( self::OK, self::FAILED ), true ) ) {
			throw new \InvalidArgumentException( "Unknown audit status: {$status}" );
		}

		global $wpdb;
		$user  = wp_get_current_user();
		$data  = array(
			'created_at'  => gmdate( 'Y-m-d H:i:s' ),
			'actor_id'    => $user->ID ?: null,
			'actor_label' => $user->ID ? $user->display_name : 'system',
			'entity'      => $row['entity'],
			'entity_id'   => (string) ( $row['entity_id'] ?? '' ),
			'action'      => $row['action'],
			'status'      => $status,
			'error'       => $row['error'] ?? null,
			'before_json' => array_key_exists( 'before', $row ) ? wp_json_encode( $row['before'] ) : null,
			'after_json'  => array_key_exists( 'after', $row ) ? wp_json_encode( $row['after'] ) : null,
			'summary_en'  => $row['summary_en'],
			'summary_de'  => $row['summary_de'],
			'request_id'  => $row['request_id'] ?? null,
			'retry_of'    => $row['retry_of'] ?? null,
		);
		if ( false === $wpdb->insert( Schema::table( 'audit' ), $data ) ) {
			// An audit row that cannot be written must stop the caller: nothing is silent.
			throw new \RuntimeException( 'Audit row could not be written: ' . $wpdb->last_error );
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * Runs a command and records exactly one audit row for it — ok or failed.
	 * The command's exception is re-thrown after the failure is recorded.
	 *
	 * @template T
	 * @param callable():T $command
	 * @param array<string,mixed> $row same as record(); `after` may be a callable receiving the result
	 * @return T
	 */
	public static function run( callable $command, array $row ): mixed {
		try {
			$result = $command();
		} catch ( \Throwable $e ) {
			unset( $row['after'] );
			self::record( array_merge( $row, array( 'status' => self::FAILED, 'error' => $e->getMessage() ) ) );
			throw $e;
		}
		if ( isset( $row['after'] ) && is_callable( $row['after'] ) ) {
			$row['after'] = ( $row['after'] )( $result );
		}
		self::record( array_merge( $row, array( 'status' => self::OK ) ) );
		return $result;
	}

	/**
	 * Failed rows that no later successful retry resolved — what Today shows as
	 * "did not reach the website".
	 *
	 * @return list<array<string,mixed>>
	 */
	public static function unresolved_failures( int $limit = 50 ): array {
		global $wpdb;
		$t   = Schema::table( 'audit' );
		$sql = $wpdb->prepare(
			// retry_of always points at the original failure, so one successful retry resolves it.
			"SELECT f.* FROM {$t} f
			 WHERE f.status = 'failed'
			   AND f.retry_of IS NULL
			   AND NOT EXISTS (
			     SELECT 1 FROM {$t} r WHERE r.status = 'ok' AND r.retry_of = f.id
			   )
			 ORDER BY f.created_at DESC, f.id DESC
			 LIMIT %d",
			$limit
		); // phpcs:ignore WordPress.DB.PreparedSQL
		return array_map( array( self::class, 'shape' ), (array) $wpdb->get_results( $sql, ARRAY_A ) );
	}

	public static function count_unresolved_failures(): int {
		global $wpdb;
		$t = Schema::table( 'audit' );
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$t} f
			 WHERE f.status = 'failed' AND f.retry_of IS NULL
			   AND NOT EXISTS (SELECT 1 FROM {$t} r WHERE r.status = 'ok' AND r.retry_of = f.id)"
		); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/** @return list<array<string,mixed>> newest first */
	public static function for_entity( string $entity, string $entity_id, int $limit = 100 ): array {
		global $wpdb;
		$t = Schema::table( 'audit' );
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$t} WHERE entity = %s AND entity_id = %s ORDER BY id DESC LIMIT %d", $entity, $entity_id, $limit ), // phpcs:ignore WordPress.DB.PreparedSQL
			ARRAY_A
		);
		return array_map( array( self::class, 'shape' ), (array) $rows );
	}

	/**
	 * @param list<string> $exclude_entities
	 * @return array{items:list<array<string,mixed>>, total:int, page:int, pages:int}
	 */
	public static function page( int $page, int $per_page, ?string $status = null, array $exclude_entities = array() ): array {
		global $wpdb;
		$t     = Schema::table( 'audit' );
		$conds = array();
		if ( $status ) {
			$conds[] = $wpdb->prepare( 'status = %s', $status );
		}
		foreach ( $exclude_entities as $entity ) {
			$conds[] = $wpdb->prepare( 'entity <> %s', $entity );
		}
		$where = $conds ? 'WHERE ' . implode( ' AND ', $conds ) : '';
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} {$where}" ); // phpcs:ignore WordPress.DB.PreparedSQL
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$t} {$where} ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, ( max( 1, $page ) - 1 ) * $per_page ), // phpcs:ignore WordPress.DB.PreparedSQL
			ARRAY_A
		);
		$items = array_map( array( self::class, 'shape' ), (array) $rows );
		// A failure is "open" until a successful retry points at it.
		$resolved = array();
		$ids      = array_column( array_filter( $items, static fn( $r ) => self::FAILED === $r['status'] ), 'id' );
		if ( $ids ) {
			$in       = implode( ',', array_map( 'intval', $ids ) );
			$resolved = array_map( 'intval', (array) $wpdb->get_col( "SELECT DISTINCT retry_of FROM {$t} WHERE status = 'ok' AND retry_of IN ({$in})" ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		}
		foreach ( $items as &$item ) {
			$item['open'] = self::FAILED === $item['status'] && null === $item['retry_of'] && ! in_array( $item['id'], $resolved, true );
		}
		unset( $item );
		return array( 'items' => $items, 'total' => $total, 'page' => $page, 'pages' => (int) max( 1, ceil( $total / $per_page ) ) );
	}

	/** @param list<string> $exclude_entities */
	public static function count_since( string $gmt, array $exclude_entities = array() ): int {
		global $wpdb;
		$t   = Schema::table( 'audit' );
		$sql = $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE created_at >= %s", $gmt ); // phpcs:ignore WordPress.DB.PreparedSQL
		foreach ( $exclude_entities as $entity ) {
			$sql .= $wpdb->prepare( ' AND entity <> %s', $entity );
		}
		return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/** @param array<string,mixed> $row */
	private static function shape( array $row ): array {
		$row['id']       = (int) $row['id'];
		$row['actor_id'] = null === $row['actor_id'] ? null : (int) $row['actor_id'];
		$row['retry_of'] = null === $row['retry_of'] ? null : (int) $row['retry_of'];
		$row['before']   = null === $row['before_json'] ? null : json_decode( $row['before_json'], true );
		$row['after']    = null === $row['after_json'] ? null : json_decode( $row['after_json'], true );
		unset( $row['before_json'], $row['after_json'] );
		return $row;
	}
}
