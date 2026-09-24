<?php
declare(strict_types=1);

namespace Rungud\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rungud\Auth\Guard;

final class GuardTest extends TestCase {

	public function test_reads_bearer_from_either_header(): void {
		$this->assertSame( 'abc.def.ghi', Guard::bearer( array( 'HTTP_AUTHORIZATION' => 'Bearer abc.def.ghi' ) ) );
		$this->assertSame( 'abc', Guard::bearer( array( 'REDIRECT_HTTP_AUTHORIZATION' => 'bearer abc' ) ) );
		$this->assertNull( Guard::bearer( array( 'HTTP_AUTHORIZATION' => 'Basic dXNlcjpwYXNz' ) ) );
		$this->assertNull( Guard::bearer( array() ) );
	}

	public function test_token_only_counts_on_rungud_routes(): void {
		$this->assertTrue( Guard::is_rungud_request( array( 'REQUEST_URI' => '/wp-json/rungud/v1/today' ), array() ) );
		$this->assertTrue( Guard::is_rungud_request( array( 'REQUEST_URI' => '/inzentive/wp-json/rungud/v1/auth/me?x=1' ), array() ) );
		$this->assertTrue( Guard::is_rungud_request( array(), array( 'rest_route' => '/rungud/v1/today' ) ) );
		$this->assertFalse( Guard::is_rungud_request( array( 'REQUEST_URI' => '/wp-json/wp/v2/users' ), array() ) );
		$this->assertFalse( Guard::is_rungud_request( array( 'REQUEST_URI' => '/wp-json/rungud/v10/x' ), array() ) );
		$this->assertFalse( Guard::is_rungud_request( array( 'REQUEST_URI' => '/wp-admin/?q=/wp-json/rungud/v1/' ), array() ) );
		$this->assertFalse( Guard::is_rungud_request( array(), array( 'rest_route' => '/inzentive/v1/access/1' ) ) );
	}

	public function test_login_and_refresh_ignore_a_stale_token(): void {
		$this->assertTrue( Guard::is_public_request( array( 'REQUEST_URI' => '/wp-json/rungud/v1/auth/login' ), array() ) );
		$this->assertTrue( Guard::is_public_request( array(), array( 'rest_route' => '/rungud/v1/auth/refresh/' ) ) );
		$this->assertFalse( Guard::is_public_request( array( 'REQUEST_URI' => '/wp-json/rungud/v1/auth/logout' ), array() ) );
		$this->assertFalse( Guard::is_public_request( array( 'REQUEST_URI' => '/wp-json/rungud/v1/auth/login-extra' ), array() ) );
	}
}
