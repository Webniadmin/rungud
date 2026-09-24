<?php
declare(strict_types=1);

namespace Rungud\Tests\Integration;

use Rungud\Auth\Guard;
use Rungud\Capabilities;

abstract class TestCase extends \WP_UnitTestCase {

	protected function set_up(): void {
		parent::set_up();
		Guard::reset();
		\Rungud\Settings::flush();
		unset( $_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REQUEST_URI'] );
	}

	protected function make_user( string $role, string $password = 'correct horse battery' ): \WP_User {
		$id = self::factory()->user->create(
			array(
				'role'         => $role,
				'user_pass'    => $password,
				'display_name' => ucfirst( str_replace( 'rungud_', '', $role ) ) . ' Tester',
			)
		);
		return get_userdata( $id );
	}

	/** @param array<string,mixed> $params */
	protected function call( string $method, string $route, array $params = array() ): \WP_REST_Response {
		$request = new \WP_REST_Request( $method, '/rungud/v1' . $route );
		foreach ( $params as $k => $v ) {
			$request->set_param( $k, $v );
		}
		return rest_do_request( $request );
	}

	/**
	 * Simulates an HTTP request carrying a Bearer token: runs the Guard exactly
	 * as WordPress would at determine_current_user time.
	 */
	protected function authenticate_bearer( string $token, string $route = '/today' ): void {
		Guard::reset();
		wp_set_current_user( 0 );
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
		$_SERVER['REQUEST_URI']        = '/wp-json/rungud/v1' . $route;
		$user_id                       = Guard::determine_current_user( false );
		wp_set_current_user( (int) $user_id );
	}

	/** @return array<string,mixed> */
	protected function login( \WP_User $user, string $password = 'correct horse battery' ): array {
		wp_set_current_user( 0 );
		$response = $this->call( 'POST', '/auth/login', array( 'username' => $user->user_login, 'password' => $password ) );
		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
		return $response->get_data();
	}

	protected function assertError( \WP_REST_Response $response, int $status, string $code ): void {
		$this->assertSame( $status, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$this->assertSame( $code, $response->get_data()['code'] ?? null );
	}

	protected static function caps(): array {
		return Capabilities::all();
	}
}
