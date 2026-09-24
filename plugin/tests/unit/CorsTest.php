<?php
declare(strict_types=1);

namespace Rungud\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rungud\Rest\Cors;

final class CorsTest extends TestCase {

	private const ALLOW = array(
		'https://rungud.vercel.app',
		'https://rungud-*-webniadmin.vercel.app',
		'http://localhost:5173',
	);

	public function test_exact_origin_is_allowed(): void {
		$this->assertTrue( Cors::allowed( 'https://rungud.vercel.app', self::ALLOW ) );
		$this->assertTrue( Cors::allowed( 'http://localhost:5173', self::ALLOW ) );
	}

	public function test_vercel_preview_wildcard_matches_one_label_fragment(): void {
		$this->assertTrue( Cors::allowed( 'https://rungud-git-feature-x-webniadmin.vercel.app', self::ALLOW ) );
		$this->assertFalse( Cors::allowed( 'https://rungud-evil.com.x-webniadmin.vercel.app', self::ALLOW ) );
		$this->assertFalse( Cors::allowed( 'https://rungud--webniadmin.vercel.app', self::ALLOW ) );
	}

	public function test_unknown_or_malformed_origins_are_refused(): void {
		$this->assertFalse( Cors::allowed( 'https://evil.example', self::ALLOW ) );
		$this->assertFalse( Cors::allowed( 'http://rungud.vercel.app', self::ALLOW ) );
		$this->assertFalse( Cors::allowed( 'https://rungud.vercel.app.evil.example', self::ALLOW ) );
		$this->assertFalse( Cors::allowed( 'null', self::ALLOW ) );
		$this->assertFalse( Cors::allowed( 'http://localhost:5174', self::ALLOW ) );
	}

	public function test_a_bare_star_is_never_a_wildcard_for_everything(): void {
		$this->assertFalse( Cors::allowed( 'https://anything.example', array( '*' ) ) );
		$this->assertFalse( Cors::allowed( 'https://a.example', array( 'https://*' ) ) );
	}

	public function test_headers_only_for_allowed_origin_and_always_vary(): void {
		$this->assertSame( array( 'Vary' => 'Origin' ), Cors::headers_for( 'https://evil.example', self::ALLOW ) );
		$this->assertSame( array( 'Vary' => 'Origin' ), Cors::headers_for( null, self::ALLOW ) );
		$h = Cors::headers_for( 'https://rungud.vercel.app', self::ALLOW );
		$this->assertSame( 'https://rungud.vercel.app', $h['Access-Control-Allow-Origin'] );
		$this->assertStringContainsString( 'Authorization', $h['Access-Control-Allow-Headers'] );
		$this->assertArrayNotHasKey( 'Access-Control-Allow-Credentials', $h );
	}
}
