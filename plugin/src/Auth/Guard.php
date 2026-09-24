<?php
declare(strict_types=1);

namespace Rungud\Auth;

use Rungud\Capabilities;
use Rungud\Config;
use Rungud\Rest\Routes;

/**
 * Authenticates rungud/v1 requests from the Bearer token. Only our namespace
 * accepts the token — it never logs anybody into the rest of the site.
 */
final class Guard {

	private static ?\WP_Error $error = null;

	public static function register(): void {
		add_filter( 'determine_current_user', array( self::class, 'determine_current_user' ), 20 );
		add_filter( 'rest_authentication_errors', array( self::class, 'authentication_errors' ), 20 );
	}

	public static function tokens(): Tokens {
		return new Tokens( (string) Config::get( 'RUNGUD_JWT_SECRET', '' ), home_url() );
	}

	/** @param int|false $user_id */
	public static function determine_current_user( $user_id ) {
		$token = self::bearer( $_SERVER );
		if ( null === $token || ! self::is_rungud_request( $_SERVER, $_GET, rest_get_url_prefix() ) ) {
			return $user_id;
		}
		// Login and refresh must work while the old access token is expired.
		if ( self::is_public_request( $_SERVER, $_GET, rest_get_url_prefix() ) ) {
			return $user_id;
		}
		try {
			$claims = self::tokens()->verify( $token, time() );
		} catch ( NotConfigured $e ) {
			self::$error = new \WP_Error( 'rungud_not_configured', 'Login is not configured on this site.', array( 'status' => 503 ) );
			return false;
		} catch ( InvalidToken $e ) {
			self::$error = new \WP_Error( 'rungud_invalid_token', 'Your session has ended. Please log in again.', array( 'status' => 401 ) );
			return false;
		}
		if ( ! Sessions::is_active( $claims['family'] ) ) {
			self::$error = new \WP_Error( 'rungud_invalid_token', 'Your session has ended. Please log in again.', array( 'status' => 401 ) );
			return false;
		}
		$user = get_userdata( $claims['user_id'] );
		if ( ! $user || ! $user->has_cap( Capabilities::READ ) ) {
			self::$error = new \WP_Error( 'rungud_forbidden', 'This account has no access to rungud.', array( 'status' => 403 ) );
			return false;
		}
		self::$current_family = $claims['family'];
		return $user->ID;
	}

	private static ?string $current_family = null;

	public static function current_family(): ?string {
		return self::$current_family;
	}

	/** @param \WP_Error|null|true $result */
	public static function authentication_errors( $result ) {
		return self::$error ?? $result;
	}

	/** Test hook: forget per-request state. */
	public static function reset(): void {
		self::$error          = null;
		self::$current_family = null;
	}

	/** @param array<string,mixed> $server */
	public static function bearer( array $server ): ?string {
		$header = $server['HTTP_AUTHORIZATION'] ?? $server['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
		if ( ! is_string( $header ) || ! preg_match( '/^\s*Bearer\s+(\S+)\s*$/i', $header, $m ) ) {
			return null;
		}
		return $m[1];
	}

	/**
	 * @param array<string,mixed> $server
	 * @param array<string,mixed> $get
	 */
	public static function is_public_request( array $server, array $get, string $prefix = 'wp-json' ): bool {
		$route = self::route( $server, $get, $prefix );
		return null !== $route && in_array( $route, Routes::PUBLIC, true );
	}

	/**
	 * The REST route of the current HTTP request, e.g. `/rungud/v1/today`.
	 *
	 * @param array<string,mixed> $server
	 * @param array<string,mixed> $get
	 */
	private static function route( array $server, array $get, string $prefix ): ?string {
		if ( isset( $get['rest_route'] ) && is_string( $get['rest_route'] ) ) {
			return '/' . trim( $get['rest_route'], '/' );
		}
		$path = (string) parse_url( (string) ( $server['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
		if ( preg_match( '#/' . preg_quote( trim( $prefix, '/' ), '#' ) . '(/.*)$#', $path, $m ) ) {
			return '/' . trim( $m[1], '/' );
		}
		return null;
	}

	/**
	 * @param array<string,mixed> $server
	 * @param array<string,mixed> $get
	 */
	public static function is_rungud_request( array $server, array $get, string $prefix = 'wp-json' ): bool {
		$route = self::route( $server, $get, $prefix );
		return null !== $route && ( '/rungud/v1' === $route || str_starts_with( $route, '/rungud/v1/' ) );
	}
}
