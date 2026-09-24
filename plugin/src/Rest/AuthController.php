<?php
declare(strict_types=1);

namespace Rungud\Rest;

use Rungud\Audit\Audit;
use Rungud\Auth\Guard;
use Rungud\Auth\InvalidToken;
use Rungud\Auth\NotConfigured;
use Rungud\Auth\Sessions;
use Rungud\Capabilities;

final class AuthController {

	public const MAX_FAILS_PER_USER = 5;
	public const MAX_FAILS_PER_IP   = 20;
	public const LOCK_WINDOW        = 15 * MINUTE_IN_SECONDS;

	public static function register(): void {
		register_rest_route(
			Routes::NS,
			'/auth/login',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'login' ),
				'permission_callback' => Permission::public(),
				'args'                => array(
					'username' => array( 'type' => 'string', 'required' => true ),
					'password' => array( 'type' => 'string', 'required' => true ),
				),
			)
		);
		register_rest_route(
			Routes::NS,
			'/auth/refresh',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'refresh' ),
				'permission_callback' => Permission::public(),
				'args'                => array(
					'refresh_token' => array( 'type' => 'string', 'required' => true ),
				),
			)
		);
		register_rest_route(
			Routes::NS,
			'/auth/logout',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'logout' ),
				'permission_callback' => Permission::cap( Capabilities::READ ),
			)
		);
		register_rest_route(
			Routes::NS,
			'/auth/me',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'me' ),
				'permission_callback' => Permission::cap( Capabilities::READ ),
			)
		);
	}

	public static function login( \WP_REST_Request $request ) {
		$username = trim( (string) $request['username'] );
		$ip       = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
		$user_key = 'rungud_fail_u_' . md5( strtolower( $username ) );
		$ip_key   = 'rungud_fail_ip_' . md5( $ip );

		if ( (int) get_transient( $user_key ) >= self::MAX_FAILS_PER_USER || (int) get_transient( $ip_key ) >= self::MAX_FAILS_PER_IP ) {
			return new \WP_Error( 'rungud_locked', 'Too many attempts. Please wait 15 minutes.', array( 'status' => 429 ) );
		}

		try {
			$tokens = Guard::tokens();
		} catch ( NotConfigured $e ) {
			return new \WP_Error( 'rungud_not_configured', 'Login is not configured on this site.', array( 'status' => 503 ) );
		}

		$user = wp_authenticate( $username, (string) $request['password'] );
		if ( is_wp_error( $user ) ) {
			set_transient( $user_key, (int) get_transient( $user_key ) + 1, self::LOCK_WINDOW );
			set_transient( $ip_key, (int) get_transient( $ip_key ) + 1, self::LOCK_WINDOW );
			// One message for unknown user and wrong password: do not reveal which accounts exist.
			return new \WP_Error( 'rungud_bad_credentials', 'Email or password is wrong.', array( 'status' => 401 ) );
		}
		if ( ! $user->has_cap( Capabilities::READ ) ) {
			return new \WP_Error( 'rungud_forbidden', 'This account has no access to rungud.', array( 'status' => 403 ) );
		}
		delete_transient( $user_key );

		$session = Sessions::start( $user->ID );
		wp_set_current_user( $user->ID );
		Audit::record(
			array(
				'entity'     => 'session',
				'entity_id'  => $user->ID,
				'action'     => 'login',
				'summary_en' => "{$user->display_name} logged in.",
				'summary_de' => "{$user->display_name} hat sich angemeldet.",
			)
		);
		return rest_ensure_response( self::token_response( $tokens->issue( $user->ID, $session['family'], time() ), $session['refresh_token'], $user ) );
	}

	public static function refresh( \WP_REST_Request $request ) {
		try {
			$tokens  = Guard::tokens();
			$rotated = Sessions::rotate( (string) $request['refresh_token'] );
		} catch ( NotConfigured $e ) {
			return new \WP_Error( 'rungud_not_configured', 'Login is not configured on this site.', array( 'status' => 503 ) );
		} catch ( InvalidToken $e ) {
			return new \WP_Error( 'rungud_invalid_token', 'Your session has ended. Please log in again.', array( 'status' => 401 ) );
		}
		$user = get_userdata( $rotated['user_id'] );
		if ( ! $user || ! $user->has_cap( Capabilities::READ ) ) {
			Sessions::revoke_family( $rotated['family'], 'no_access' );
			return new \WP_Error( 'rungud_forbidden', 'This account has no access to rungud.', array( 'status' => 403 ) );
		}
		return rest_ensure_response( self::token_response( $tokens->issue( $user->ID, $rotated['family'], time() ), $rotated['refresh_token'], $user ) );
	}

	public static function logout() {
		$family = Guard::current_family();
		if ( $family ) {
			Sessions::revoke_family( $family, 'logout' );
		}
		return rest_ensure_response( array( 'ok' => true ) );
	}

	public static function me() {
		return rest_ensure_response( self::user_payload( wp_get_current_user() ) );
	}

	/** @return array<string,mixed> */
	public static function user_payload( \WP_User $user ): array {
		$locale = get_user_locale( $user );
		return array(
			'id'           => $user->ID,
			'name'         => $user->display_name,
			'email'        => $user->user_email,
			'role'         => Capabilities::role_of( $user ),
			'capabilities' => Capabilities::flags( $user ),
			'lang'         => str_starts_with( $locale, 'de' ) ? 'de' : 'en',
		);
	}

	/** @return array<string,mixed> */
	private static function token_response( string $access, string $refresh, \WP_User $user ): array {
		return array(
			'access_token'  => $access,
			'token_type'    => 'Bearer',
			'expires_in'    => \Rungud\Auth\Tokens::ACCESS_TTL,
			'refresh_token' => $refresh,
			'user'          => self::user_payload( $user ),
		);
	}
}
