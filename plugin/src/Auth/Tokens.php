<?php
declare(strict_types=1);

namespace Rungud\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Short-lived access tokens (HS256). Pure: no WordPress calls, so it is unit
 * tested. Refresh tokens are opaque and live in Sessions, not here.
 */
final class Tokens {

	public const AUDIENCE   = 'rungud';
	public const ACCESS_TTL = 900; // 15 minutes
	public const MIN_SECRET = 32;

	public function __construct(
		private readonly string $secret,
		private readonly string $issuer,
	) {
		if ( strlen( $secret ) < self::MIN_SECRET ) {
			throw new NotConfigured( 'RUNGUD_JWT_SECRET must be at least ' . self::MIN_SECRET . ' bytes.' );
		}
	}

	public function issue( int $user_id, string $family, int $now ): string {
		return JWT::encode(
			array(
				'iss' => $this->issuer,
				'aud' => self::AUDIENCE,
				'sub' => (string) $user_id,
				'sid' => $family,
				'iat' => $now,
				'nbf' => $now,
				'exp' => $now + self::ACCESS_TTL,
			),
			$this->secret,
			'HS256'
		);
	}

	/**
	 * @return array{user_id:int, family:string, exp:int}
	 * @throws InvalidToken
	 */
	public function verify( string $jwt, int $now ): array {
		$previous      = JWT::$timestamp;
		JWT::$timestamp = $now;
		try {
			$claims = (array) JWT::decode( $jwt, new Key( $this->secret, 'HS256' ) );
		} catch ( \Throwable $e ) {
			throw new InvalidToken( $e->getMessage(), 0, $e );
		} finally {
			JWT::$timestamp = $previous;
		}
		if ( ( $claims['iss'] ?? null ) !== $this->issuer || ( $claims['aud'] ?? null ) !== self::AUDIENCE ) {
			throw new InvalidToken( 'Token was not issued for this site.' );
		}
		$user_id = (int) ( $claims['sub'] ?? 0 );
		$family  = (string) ( $claims['sid'] ?? '' );
		if ( $user_id <= 0 || '' === $family ) {
			throw new InvalidToken( 'Token is missing its subject or session.' );
		}
		return array( 'user_id' => $user_id, 'family' => $family, 'exp' => (int) $claims['exp'] );
	}
}
