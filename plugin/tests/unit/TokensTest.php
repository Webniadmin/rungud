<?php
declare(strict_types=1);

namespace Rungud\Tests\Unit;

use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;
use Rungud\Auth\InvalidToken;
use Rungud\Auth\NotConfigured;
use Rungud\Auth\Tokens;

final class TokensTest extends TestCase {

	private const SECRET = 'unit-test-secret-unit-test-secret-0123456789';
	private const ISS    = 'https://inzentive.example';
	private const NOW    = 1790000000;

	private function tokens(): Tokens {
		return new Tokens( self::SECRET, self::ISS );
	}

	public function test_short_secret_disables_login(): void {
		$this->expectException( NotConfigured::class );
		new Tokens( 'too-short', self::ISS );
	}

	public function test_round_trip(): void {
		$jwt    = $this->tokens()->issue( 42, 'fam-1', self::NOW );
		$claims = $this->tokens()->verify( $jwt, self::NOW + 60 );
		$this->assertSame( 42, $claims['user_id'] );
		$this->assertSame( 'fam-1', $claims['family'] );
		$this->assertSame( self::NOW + Tokens::ACCESS_TTL, $claims['exp'] );
	}

	public function test_expired_token_is_refused(): void {
		$jwt = $this->tokens()->issue( 42, 'fam-1', self::NOW );
		$this->expectException( InvalidToken::class );
		$this->tokens()->verify( $jwt, self::NOW + Tokens::ACCESS_TTL + 1 );
	}

	public function test_token_from_another_site_is_refused(): void {
		$other = new Tokens( self::SECRET, 'https://other.example' );
		$jwt   = $other->issue( 42, 'fam-1', self::NOW );
		$this->expectException( InvalidToken::class );
		$this->tokens()->verify( $jwt, self::NOW );
	}

	public function test_wrong_signature_is_refused(): void {
		$jwt = ( new Tokens( str_repeat( 'x', 40 ), self::ISS ) )->issue( 42, 'fam-1', self::NOW );
		$this->expectException( InvalidToken::class );
		$this->tokens()->verify( $jwt, self::NOW );
	}

	public function test_alg_none_is_refused(): void {
		$header  = rtrim( strtr( base64_encode( '{"typ":"JWT","alg":"none"}' ), '+/', '-_' ), '=' );
		$payload = rtrim( strtr( base64_encode( json_encode( array( 'iss' => self::ISS, 'aud' => 'rungud', 'sub' => '1', 'sid' => 'f', 'exp' => self::NOW + 100 ) ) ), '+/', '-_' ), '=' );
		$this->expectException( InvalidToken::class );
		$this->tokens()->verify( "{$header}.{$payload}.", self::NOW );
	}

	public function test_missing_session_claim_is_refused(): void {
		$jwt = JWT::encode( array( 'iss' => self::ISS, 'aud' => 'rungud', 'sub' => '1', 'exp' => self::NOW + 100 ), self::SECRET, 'HS256' );
		$this->expectException( InvalidToken::class );
		$this->tokens()->verify( $jwt, self::NOW );
	}
}
