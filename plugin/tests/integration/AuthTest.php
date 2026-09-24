<?php
declare(strict_types=1);

namespace Rungud\Tests\Integration;

use Rungud\Capabilities as C;

final class AuthTest extends TestCase {

	public function test_login_returns_tokens_and_the_role(): void {
		$gudrun = $this->make_user( C::ROLE_BACKOFFICE );
		$data   = $this->login( $gudrun );
		$this->assertSame( 'Bearer', $data['token_type'] );
		$this->assertNotEmpty( $data['access_token'] );
		$this->assertNotEmpty( $data['refresh_token'] );
		$this->assertSame( 'backoffice', $data['user']['role'] );
		$this->assertTrue( $data['user']['capabilities']['finance'] );
		$this->assertFalse( $data['user']['capabilities']['settings'] );
	}

	public function test_login_is_audited(): void {
		global $wpdb;
		$robert = $this->make_user( C::ROLE_OWNER );
		$this->login( $robert );
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . \Rungud\Install\Schema::table( 'audit' ) . " WHERE entity = 'session' AND entity_id = %s", (string) $robert->ID ), ARRAY_A );
		$this->assertSame( 'login', $row['action'] );
		$this->assertStringContainsString( 'angemeldet', $row['summary_de'] );
	}

	public function test_wrong_password_and_unknown_user_look_the_same(): void {
		$user = $this->make_user( C::ROLE_BACKOFFICE );
		$this->assertError( $this->call( 'POST', '/auth/login', array( 'username' => $user->user_login, 'password' => 'nope' ) ), 401, 'rungud_bad_credentials' );
		$this->assertError( $this->call( 'POST', '/auth/login', array( 'username' => 'nobody-here', 'password' => 'nope' ) ), 401, 'rungud_bad_credentials' );
	}

	public function test_five_failures_lock_the_account_for_a_while(): void {
		$user = $this->make_user( C::ROLE_BACKOFFICE );
		for ( $i = 0; $i < 5; $i++ ) {
			$this->call( 'POST', '/auth/login', array( 'username' => $user->user_login, 'password' => 'nope' ) );
		}
		$this->assertError( $this->call( 'POST', '/auth/login', array( 'username' => $user->user_login, 'password' => 'correct horse battery' ) ), 429, 'rungud_locked' );
	}

	public function test_site_user_without_rungud_role_cannot_log_in(): void {
		$customer = $this->make_user( 'customer' );
		$this->assertError( $this->call( 'POST', '/auth/login', array( 'username' => $customer->user_login, 'password' => 'correct horse battery' ) ), 403, 'rungud_forbidden' );
	}

	public function test_bearer_token_authenticates_rungud_routes(): void {
		$robert = $this->make_user( C::ROLE_OWNER );
		$data   = $this->login( $robert );
		$this->authenticate_bearer( $data['access_token'], '/auth/me' );
		$me = $this->call( 'GET', '/auth/me' );
		$this->assertSame( 200, $me->get_status() );
		$this->assertSame( $robert->ID, $me->get_data()['id'] );
		$this->assertSame( 'owner', $me->get_data()['role'] );
	}

	public function test_bearer_token_does_not_log_in_to_other_namespaces(): void {
		$data = $this->login( $this->make_user( 'administrator' ) );
		\Rungud\Auth\Guard::reset();
		wp_set_current_user( 0 );
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $data['access_token'];
		$_SERVER['REQUEST_URI']        = '/wp-json/wp/v2/users/me';
		$this->assertFalse( \Rungud\Auth\Guard::determine_current_user( false ) );
	}

	public function test_garbage_token_is_401(): void {
		$this->authenticate_bearer( 'not.a.token' );
		$this->assertSame( 0, get_current_user_id() );
		$this->assertInstanceOf( \WP_Error::class, \Rungud\Auth\Guard::authentication_errors( null ) );
		$this->assertSame( 'rungud_invalid_token', \Rungud\Auth\Guard::authentication_errors( null )->get_error_code() );
	}

	public function test_refresh_rotates_and_reuse_ends_the_session(): void {
		$data    = $this->login( $this->make_user( C::ROLE_BACKOFFICE ) );
		$rotated = $this->call( 'POST', '/auth/refresh', array( 'refresh_token' => $data['refresh_token'] ) );
		$this->assertSame( 200, $rotated->get_status() );
		$new = $rotated->get_data();
		$this->assertNotSame( $data['refresh_token'], $new['refresh_token'] );

		// The old refresh token again = stolen or raced: the whole session ends.
		$this->assertError( $this->call( 'POST', '/auth/refresh', array( 'refresh_token' => $data['refresh_token'] ) ), 401, 'rungud_invalid_token' );
		$this->assertError( $this->call( 'POST', '/auth/refresh', array( 'refresh_token' => $new['refresh_token'] ) ), 401, 'rungud_invalid_token' );
		$this->authenticate_bearer( $new['access_token'] );
		$this->assertSame( 0, get_current_user_id() );
	}

	public function test_logout_ends_the_session_immediately(): void {
		$data = $this->login( $this->make_user( C::ROLE_BACKOFFICE ) );
		$this->authenticate_bearer( $data['access_token'], '/auth/logout' );
		$this->assertSame( 200, $this->call( 'POST', '/auth/logout' )->get_status() );
		$this->authenticate_bearer( $data['access_token'] );
		$this->assertSame( 0, get_current_user_id() );
		$this->assertError( $this->call( 'POST', '/auth/refresh', array( 'refresh_token' => $data['refresh_token'] ) ), 401, 'rungud_invalid_token' );
	}

	public function test_password_change_ends_all_sessions(): void {
		$user = $this->make_user( C::ROLE_BACKOFFICE );
		$data = $this->login( $user );
		wp_update_user( array( 'ID' => $user->ID, 'user_pass' => 'a brand new password' ) );
		$this->authenticate_bearer( $data['access_token'] );
		$this->assertSame( 0, get_current_user_id() );
	}

	public function test_removing_the_role_ends_access_at_once(): void {
		$user = $this->make_user( C::ROLE_BACKOFFICE );
		$data = $this->login( $user );
		$user->set_role( 'subscriber' );
		$this->authenticate_bearer( $data['access_token'] );
		$this->assertSame( 0, get_current_user_id() );
	}

	public function test_login_works_while_an_expired_token_is_still_sent(): void {
		$user = $this->make_user( C::ROLE_BACKOFFICE );
		$this->authenticate_bearer( 'expired.or.garbage', '/auth/login' );
		$this->assertNull( \Rungud\Auth\Guard::authentication_errors( null ) );
		$this->login( $user );
	}
}
