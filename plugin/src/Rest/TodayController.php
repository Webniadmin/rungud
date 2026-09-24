<?php
declare(strict_types=1);

namespace Rungud\Rest;

use Rungud\Audit\Audit;
use Rungud\Capabilities;

/**
 * GET /today — the sentence list for the current role (prototype pageToday).
 *
 * Each item is language-neutral: a key the UI turns into a sentence, a count,
 * a tone (attn | bad | calm) and where the button goes. The order is the
 * prototype's order. Only items with a count above zero are returned.
 *
 * Phase 1: the structure is final; only `commands_failed` reads real data.
 * Every other item is listed under `not_yet_available` until its phase wires it.
 */
final class TodayController {

	/** role → ordered list of [key, tone, screen, tab?] */
	public const ITEMS = array(
		'backoffice' => array(
			array( 'invoices_awaiting_approval', 'attn', 'invoices' ),
			array( 'memberships_to_verify', 'attn', 'members' ),
			array( 'invoices_overdue', 'bad', 'payments' ),
			array( 'memberships_expiring_30d', 'attn', 'members' ),
			array( 'commands_failed', 'bad', 'sync' ),
			array( 'code_claims_open', 'attn', 'codes' ),
			array( 'health_declarations_missing', 'attn', 'event' ),
			array( 'deposits_unpaid', 'attn', 'invoices' ),
			array( 'licences_expiring', 'attn', 'certs' ),
			array( 'licences_pending', 'attn', 'people' ),
			array( 'messages_suggested', 'calm', 'messages' ),
			array( 'waiting_list', 'calm', 'event', 'waiting' ),
			array( 'events_starting_soon', 'calm', 'event' ),
		),
		'owner'      => array(
			array( 'next_event', 'calm', 'event' ),
			array( 'memberships_expiring_30d', 'attn', 'members' ),
			array( 'registrations_7d', 'calm', 'events' ),
			array( 'promises_unused', 'calm', 'codes' ),
			array( 'code_claims_to_decide', 'attn', 'codes' ),
			array( 'licences_expiring', 'attn', 'certs' ),
			array( 'watching_after_expiry', 'attn', 'messages' ),
			array( 'messages_awaiting_approval', 'calm', 'messages' ),
		),
	);

	public static function register(): void {
		register_rest_route(
			Routes::NS,
			'/today',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'get' ),
				'permission_callback' => Permission::cap( Capabilities::READ ),
			)
		);
	}

	public static function get() {
		$role = Capabilities::role_of( wp_get_current_user() );
		// Webni sees Gudrun's list: the admin is also an operator.
		$list = self::ITEMS[ 'owner' === $role ? 'owner' : 'backoffice' ];

		$items   = array();
		$pending = array();
		foreach ( $list as $def ) {
			$data = self::resolve( $def[0] );
			if ( null === $data ) {
				$pending[] = $def[0];
				continue;
			}
			if ( $data['count'] <= 0 ) {
				continue;
			}
			$items[] = array(
				'key'    => $def[0],
				'tone'   => $def[1],
				'count'  => $data['count'],
				'params' => $data['params'],
				'target' => array_filter( array( 'screen' => $def[2], 'tab' => $def[3] ?? null ) ),
			);
		}

		return rest_ensure_response(
			array(
				'date'              => wp_date( 'Y-m-d' ),
				'role'              => $role,
				'items'             => $items,
				'not_yet_available' => $pending,
			)
		);
	}

	/** @return array{count:int, params:array<string,mixed>}|null null = not wired yet */
	private static function resolve( string $key ): ?array {
		switch ( $key ) {
			case 'commands_failed':
				$count = Audit::count_unresolved_failures();
				$first = $count ? ( Audit::unresolved_failures( 1 )[0] ?? null ) : null;
				return array(
					'count'  => $count,
					'params' => $first ? array(
						'audit_id'   => $first['id'],
						'summary_en' => $first['summary_en'],
						'summary_de' => $first['summary_de'],
					) : array(),
				);
			case 'licences_pending':
				try {
					$pending = \Rungud\Site\SiteApi::get( '/licences/pending' );
				} catch ( \Rungud\Site\SiteUnavailable $e ) {
					return null;
				}
				return array( 'count' => count( $pending ), 'params' => array() );
			default:
				return null;
		}
	}
}
