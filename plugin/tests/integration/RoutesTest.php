<?php
declare(strict_types=1);

namespace Rungud\Tests\Integration;

use Rungud\Capabilities as C;
use Rungud\Rest\Routes;

/**
 * CLAUDE.md rule 9: every route checks a capability. This test fails the
 * moment somebody registers a rungud/v1 route without one.
 */
final class RoutesTest extends TestCase {

	public function test_every_route_checks_a_capability(): void {
		$routes = rest_get_server()->get_routes( Routes::NS );
		$this->assertNotEmpty( $routes );
		foreach ( $routes as $route => $handlers ) {
			if ( '/' . Routes::NS === $route ) {
				continue; // namespace index
			}
			foreach ( $handlers as $handler ) {
				$cb = $handler['permission_callback'] ?? null;
				$this->assertNotNull( $cb, "{$route} has no permission_callback" );
				if ( in_array( $route, Routes::PUBLIC, true ) ) {
					continue;
				}
				$this->assertNotSame( '__return_true', $cb, "{$route} is public but not listed in Routes::PUBLIC" );
			}
		}
	}

	public function test_logged_out_gets_401_and_site_users_get_403(): void {
		wp_set_current_user( 0 );
		$this->assertError( $this->call( 'GET', '/today' ), 401, 'rungud_unauthenticated' );
		wp_set_current_user( $this->make_user( 'customer' )->ID );
		$this->assertError( $this->call( 'GET', '/today' ), 403, 'rungud_forbidden' );
	}

	public function test_robert_can_read(): void {
		wp_set_current_user( $this->make_user( C::ROLE_OWNER )->ID );
		$this->assertSame( 200, $this->call( 'GET', '/today' )->get_status() );
	}
}
