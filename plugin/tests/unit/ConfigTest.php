<?php
declare(strict_types=1);

namespace Rungud\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rungud\Config;

final class ConfigTest extends TestCase {

	protected function tearDown(): void {
		putenv( 'RUNGUD_CORS_ORIGINS' );
		putenv( 'STRIPE_SECRET_KEY' );
		Config::set_store( null );
	}

	public function test_settings_store_is_the_last_fallback(): void {
		Config::set_store( static fn( string $k ) => array( 'smtp_host' => 'mail.example', 'stripe_secret_key' => 'rk_test_x' )[ $k ] ?? null );
		$this->assertSame( 'mail.example', Config::get( 'RUNGUD_SMTP_HOST' ) );
		putenv( 'STRIPE_SECRET_KEY=rk_test_env' );
		$this->assertSame( 'rk_test_env', Config::get( 'STRIPE_SECRET_KEY' ) );
		$this->assertSame( 'env', Config::source( 'STRIPE_SECRET_KEY' ) );
	}

	public function test_jwt_secret_never_comes_from_the_database(): void {
		Config::set_store( static fn() => 'stored-secret' );
		$this->assertNull( Config::get( 'RUNGUD_JWT_SECRET' ) );
	}

	public function test_cors_accepts_newlines(): void {
		putenv( "RUNGUD_CORS_ORIGINS=https://a.example\nhttps://b.example" );
		$this->assertSame( array( 'https://a.example', 'https://b.example' ), Config::cors_origins() );
	}

	public function test_unknown_key_is_refused(): void {
		$this->expectException( \InvalidArgumentException::class );
		Config::get( 'SOMETHING_ELSE' );
	}

	public function test_falls_back_when_unset(): void {
		$this->assertSame( 'x', Config::get( 'RUNGUD_SMTP_HOST', 'x' ) );
	}

	public function test_reads_environment(): void {
		putenv( 'RUNGUD_CORS_ORIGINS=https://a.example , https://b.example/,,https://a.example' );
		$this->assertSame( array( 'https://a.example', 'https://b.example' ), Config::cors_origins() );
	}

	public function test_empty_cors_is_empty_list(): void {
		$this->assertSame( array(), Config::cors_origins() );
	}

	public function test_stripe_test_mode_detection(): void {
		putenv( 'STRIPE_SECRET_KEY=rk_test_abc' );
		$this->assertTrue( Config::stripe_is_test_mode() );
		putenv( 'STRIPE_SECRET_KEY=rk_live_abc' );
		$this->assertFalse( Config::stripe_is_test_mode() );
	}
}
