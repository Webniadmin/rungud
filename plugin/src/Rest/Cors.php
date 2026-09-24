<?php
declare(strict_types=1);

namespace Rungud\Rest;

use Rungud\Config;

/**
 * CORS for rungud/v1 only, from the allow-list (RUNGUD_CORS_ORIGINS).
 * WordPress core echoes any Origin back on every REST route; for our
 * namespace that is replaced by the allow-list.
 *
 * Entries are exact origins, or contain one `*` inside the host for Vercel
 * preview URLs, e.g. `https://rungud-*-webniadmin.vercel.app`.
 */
final class Cors {

	public const ALLOW_HEADERS = 'Authorization, Content-Type, X-Request-Id';
	public const ALLOW_METHODS = 'GET, POST, PUT, PATCH, OPTIONS';
	public const MAX_AGE       = 600;

	public static function register(): void {
		add_filter( 'rest_pre_serve_request', array( self::class, 'serve' ), 9, 4 );
	}

	/**
	 * @param bool $served
	 * @param mixed $result
	 */
	public static function serve( $served, $result, \WP_REST_Request $request, \WP_REST_Server $server ) {
		if ( ! str_starts_with( $request->get_route(), '/rungud/v1' ) ) {
			return $served;
		}
		remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );
		$origin = get_http_origin();
		foreach ( self::headers_for( $origin ? (string) $origin : null, Config::cors_origins() ) as $name => $value ) {
			$server->send_header( $name, $value );
		}
		return $served;
	}

	/**
	 * @param list<string> $allow
	 * @return array<string,string>
	 */
	public static function headers_for( ?string $origin, array $allow ): array {
		$headers = array( 'Vary' => 'Origin' );
		if ( null === $origin || ! self::allowed( $origin, $allow ) ) {
			return $headers;
		}
		return $headers + array(
			'Access-Control-Allow-Origin'  => $origin,
			'Access-Control-Allow-Methods' => self::ALLOW_METHODS,
			'Access-Control-Allow-Headers' => self::ALLOW_HEADERS,
			'Access-Control-Max-Age'       => (string) self::MAX_AGE,
		);
	}

	/** @param list<string> $allow */
	public static function allowed( string $origin, array $allow ): bool {
		$origin = rtrim( $origin, '/' );
		if ( ! preg_match( '#^https?://[a-z0-9.-]+(:\d+)?$#i', $origin ) ) {
			return false;
		}
		foreach ( $allow as $entry ) {
			if ( ! str_contains( $entry, '*' ) ) {
				if ( strcasecmp( $entry, $origin ) === 0 ) {
					return true;
				}
				continue;
			}
			// One wildcard, host part only, matching a single DNS label fragment (no dots).
			if ( substr_count( $entry, '*' ) !== 1 || ! preg_match( '#^https?://[^/]*\*[^/]*$#', $entry ) ) {
				continue;
			}
			$regex = '#^' . str_replace( '\*', '[a-z0-9-]+', preg_quote( $entry, '#' ) ) . '$#i';
			if ( preg_match( $regex, $origin ) ) {
				return true;
			}
		}
		return false;
	}
}
