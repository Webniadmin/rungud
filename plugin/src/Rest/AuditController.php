<?php
declare(strict_types=1);

namespace Rungud\Rest;

use Rungud\Audit\Audit;
use Rungud\Capabilities;

/** The Website connection log: every command and send the CMS made. */
final class AuditController {

	public static function register(): void {
		register_rest_route( Routes::NS, '/audit', array(
			'methods'             => 'GET',
			'callback'            => array( self::class, 'index' ),
			'permission_callback' => Permission::cap( Capabilities::READ ),
			'args'                => array(
				'status' => array( 'type' => 'string', 'enum' => array( 'all', 'failed' ), 'default' => 'all' ),
				'page'   => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1 ),
			),
		) );
	}

	public static function index( \WP_REST_Request $request ) {
		// Logins are audited too, but they are not website commands.
		$page = Audit::page( (int) $request['page'], 50, 'failed' === $request['status'] ? Audit::FAILED : null, array( 'session' ) );
		return rest_ensure_response( $page + array(
			'open_failures' => Audit::count_unresolved_failures(),
			'last_72h'      => Audit::count_since( gmdate( 'Y-m-d H:i:s', time() - 72 * HOUR_IN_SECONDS ) ),
		) );
	}
}
