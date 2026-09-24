<?php
declare(strict_types=1);

namespace Rungud\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rungud\Config;

final class ConfigTest extends TestCase {

	protected function tearDown(): void {
		putenv( 'RUNGUD_CORS_ORIGINS' );
		putenv( 'STRIPE_SECRET_KEY' );
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
