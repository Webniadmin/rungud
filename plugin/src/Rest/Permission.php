<?php
declare(strict_types=1);

namespace Rungud\Rest;

/**
 * Every rungud/v1 route uses one of these as permission_callback
 * (CLAUDE.md rule 9). Public routes are listed in Routes::PUBLIC and
 * checked by a test.
 */
final class Permission {

	/** @return callable(\WP_REST_Request):(true|\WP_Error) */
	public static function cap( string $capability ): callable {
		return static function () use ( $capability ) {
			if ( ! is_user_logged_in() ) {
				return new \WP_Error( 'rungud_unauthenticated', 'Please log in.', array( 'status' => 401 ) );
			}
			if ( ! current_user_can( $capability ) ) {
				return new \WP_Error( 'rungud_forbidden', 'Your role cannot do this.', array( 'status' => 403 ) );
			}
			return true;
		};
	}

	/** For the handful of routes that must be reachable without a session. */
	public static function public(): callable {
		return '__return_true';
	}
}
