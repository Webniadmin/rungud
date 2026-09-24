<?php
declare(strict_types=1);

namespace Rungud;

/**
 * Reads deployment configuration. Secrets never live in the repository: they
 * come from a wp-config.php constant first, then from the process environment.
 * The WP admin settings page (phase 1) is the last fallback for non-secret keys.
 */
final class Config {

	/** Every key the plugin understands. Anything else is a programming error. */
	public const KEYS = array(
		'RUNGUD_JWT_SECRET',
		'RUNGUD_CORS_ORIGINS',
		'RUNGUD_SMTP_HOST',
		'RUNGUD_SMTP_PORT',
		'RUNGUD_SMTP_USER',
		'RUNGUD_SMTP_PASS',
		'RUNGUD_SMTP_FROM',
		'RUNGUD_SMTP_FROM_NAME',
		'STRIPE_SECRET_KEY',
		'STRIPE_WEBHOOK_SECRET',
	);

	public static function get( string $key, ?string $fallback = null ): ?string {
		if ( ! in_array( $key, self::KEYS, true ) ) {
			throw new \InvalidArgumentException( "Unknown config key: {$key}" );
		}
		if ( defined( $key ) ) {
			$value = constant( $key );
			return is_scalar( $value ) ? (string) $value : $fallback;
		}
		$env = getenv( $key );
		if ( false !== $env && '' !== $env ) {
			return $env;
		}
		return $fallback;
	}

	/**
	 * Comma-separated origins → normalised list (no trailing slash, no blanks).
	 *
	 * @return list<string>
	 */
	public static function cors_origins(): array {
		$raw = self::get( 'RUNGUD_CORS_ORIGINS', '' ) ?? '';
		$out = array();
		foreach ( explode( ',', $raw ) as $origin ) {
			$origin = rtrim( trim( $origin ), '/' );
			if ( '' !== $origin ) {
				$out[] = $origin;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/** Stripe test keys only outside production; used to refuse a live key on staging. */
	public static function stripe_is_test_mode(): bool {
		$key = self::get( 'STRIPE_SECRET_KEY', '' ) ?? '';
		return str_starts_with( $key, 'sk_test_' ) || str_starts_with( $key, 'rk_test_' );
	}
}
