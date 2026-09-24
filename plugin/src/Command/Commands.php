<?php
declare(strict_types=1);

namespace Rungud\Command;

use Rungud\Audit\Audit;
use Rungud\Site\SiteUnavailable;

/**
 * Every command the CMS sends runs through here (CLAUDE.md rules 3 and 5):
 * a human click, one audit row with a German and English summary, and a
 * clear outcome.
 *
 *   ok       done
 *   refused  the target answered "no" (e.g. a reason is missing) — the dialog
 *            shows the message; logged; not a Today item
 *   failed   the target did not answer or broke — logged, shown on Today as
 *            "did not reach the website", retried by hand
 *
 * The client sends a request id per dialog: a double click, or a network
 * retry of the same POST, runs the command once and answers with the first
 * outcome. The website gets its own fresh idempotency key per attempt, so a
 * manual retry is not answered with a stored refusal.
 */
final class Commands {

	/**
	 * @param array{
	 *   kind:string, params:array<string,mixed>, entity:string, entity_id:string|int,
	 *   summary_en:string, summary_de:string, request_id?:?string, retry_of?:?int, before?:mixed
	 * } $meta
	 * @param callable(string $attempt_key):mixed $fn the command; returns its result
	 * @return array{ok:true, result:mixed, audit_id:int, replay?:bool}|\WP_Error
	 */
	public static function run( array $meta, callable $fn ) {
		$request_id = isset( $meta['request_id'] ) && '' !== $meta['request_id'] ? (string) $meta['request_id'] : null;
		if ( null !== $request_id && ! preg_match( '/^[0-9a-f-]{36}$/i', $request_id ) ) {
			return new \WP_Error( 'rungud_bad_request_id', 'Invalid request id.', array( 'status' => 400 ) );
		}
		if ( null !== $request_id && ( $seen = Audit::by_request_id( $request_id ) ) ) {
			if ( Audit::OK === $seen['status'] ) {
				return array( 'ok' => true, 'result' => $seen['after'], 'audit_id' => $seen['id'], 'replay' => true );
			}
			return self::error( $seen['status'], (string) $seen['error'], $seen['id'] );
		}

		$attempt = wp_generate_uuid4();
		$row     = array(
			'entity'     => $meta['entity'],
			'entity_id'  => (string) $meta['entity_id'],
			'action'     => $meta['kind'],
			'summary_en' => $meta['summary_en'],
			'summary_de' => $meta['summary_de'],
			'request_id' => $request_id,
			'retry_of'   => $meta['retry_of'] ?? null,
			'command'    => array( 'kind' => $meta['kind'], 'params' => $meta['params'] ),
		);
		if ( array_key_exists( 'before', $meta ) ) {
			$row['before'] = $meta['before'];
		}

		// A summary describes the command as done; a row that was not done must say so in its own words.
		$not_done = static fn( array $r ) => array_merge( $r, array( 'summary_en' => 'Not done: ' . $r['summary_en'], 'summary_de' => 'Nicht ausgeführt: ' . $r['summary_de'] ) );

		try {
			$result = $fn( $attempt );
		} catch ( SiteUnavailable $e ) {
			$status = $e->is_refusal() ? Audit::REFUSED : Audit::FAILED;
			$id     = Audit::record( $not_done( $row ) + array( 'status' => $status, 'error' => $e->getMessage() ) );
			return self::error( $status, $e->getMessage(), $id );
		} catch ( Refused $e ) {
			$id = Audit::record( $not_done( $row ) + array( 'status' => Audit::REFUSED, 'error' => $e->getMessage() ) );
			return self::error( Audit::REFUSED, $e->getMessage(), $id );
		} catch ( \Throwable $e ) {
			$id = Audit::record( $not_done( $row ) + array( 'status' => Audit::FAILED, 'error' => $e->getMessage() ) );
			return self::error( Audit::FAILED, $e->getMessage(), $id );
		}

		$id = Audit::record( $row + array( 'status' => Audit::OK, 'after' => $result ) );
		return array( 'ok' => true, 'result' => $result, 'audit_id' => $id );
	}

	private static function error( string $status, string $message, int $audit_id ): \WP_Error {
		return Audit::REFUSED === $status
			? new \WP_Error( 'rungud_refused', $message, array( 'status' => 422, 'audit_id' => $audit_id ) )
			: new \WP_Error( 'rungud_command_failed', $message, array( 'status' => 502, 'audit_id' => $audit_id ) );
	}
}
