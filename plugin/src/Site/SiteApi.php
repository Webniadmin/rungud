<?php
declare(strict_types=1);

namespace Rungud\Site;

use Rungud\Capabilities;

/**
 * Calls the site's own inzentive/v1 routes in-process (rest_do_request) —
 * the CMS lives in the same WordPress, so there is no HTTP hop and no
 * credential. The site guards its routes with the capability from the filter
 * `inzentive/api/capability` (default edit_users). We widen it to the rungud
 * capability our own route already checked, only for the duration of our call.
 */
final class SiteApi {

	/** @return array<mixed> */
	public static function get( string $route, string $capability = Capabilities::READ ): array {
		return self::call( 'GET', $route, array(), $capability );
	}

	/**
	 * @param array<string,mixed> $params
	 * @return array<mixed>
	 * @throws SiteUnavailable when the route is missing or answers with an error
	 */
	public static function call( string $method, string $route, array $params = array(), string $capability = Capabilities::READ, ?string $idempotency_key = null ): array {
		if ( ! current_user_can( $capability ) ) {
			throw new SiteUnavailable( 'forbidden', 'The current user may not do this.' );
		}
		$request = new \WP_REST_Request( $method, '/inzentive/v1' . $route );
		foreach ( $params as $k => $v ) {
			$request->set_param( $k, $v );
		}
		if ( $idempotency_key ) {
			$request->set_header( 'Idempotency-Key', $idempotency_key );
		}
		$grant = static fn() => $capability;
		add_filter( 'inzentive/api/capability', $grant, PHP_INT_MAX );
		try {
			$response = rest_do_request( $request );
		} finally {
			remove_filter( 'inzentive/api/capability', $grant, PHP_INT_MAX );
		}
		$data = rest_get_server()->response_to_data( $response, false );
		if ( $response->is_error() ) {
			$code = is_array( $data ) ? (string) ( $data['code'] ?? 'error' ) : 'error';
			if ( 'rest_no_route' === $code ) {
				throw new SiteUnavailable( 'missing_route', "The website has no {$method} {$route} yet." );
			}
			$message = is_array( $data ) ? (string) ( $data['message'] ?? '' ) : '';
			throw new SiteUnavailable( $code, $message, $response->get_status() );
		}
		return is_array( $data ) ? $data : array();
	}

	/** Whether the site registers a route (e.g. '/membership/cancel-now'), without calling it. */
	public static function has_route( string $route ): bool {
		$routes = rest_get_server()->get_routes( 'inzentive/v1' );
		$full   = '/inzentive/v1' . $route;
		if ( isset( $routes[ $full ] ) ) {
			return true;
		}
		foreach ( array_keys( $routes ) as $pattern ) {
			if ( preg_match( '@^' . $pattern . '$@i', $full ) ) {
				return true;
			}
		}
		return false;
	}

	public static function available(): bool {
		return (bool) rest_get_server()->get_routes( 'inzentive/v1' );
	}
}
