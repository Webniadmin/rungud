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

	/**
	 * Keys that may also come from the WP admin settings page, with their
	 * settings key. RUNGUD_JWT_SECRET is deliberately absent: it lives only in
	 * wp-config.php or the environment, never in the database.
	 */
	public const STORE_KEYS = array(
		'RUNGUD_CORS_ORIGINS'   => 'cors_origins',
		'RUNGUD_SMTP_HOST'      => 'smtp_host',
		'RUNGUD_SMTP_PORT'      => 'smtp_port',
		'RUNGUD_SMTP_USER'      => 'smtp_user',
		'RUNGUD_SMTP_PASS'      => 'smtp_pass',
		'RUNGUD_SMTP_FROM'      => 'smtp_from',
		'RUNGUD_SMTP_FROM_NAME' => 'smtp_from_name',
		'STRIPE_SECRET_KEY'     => 'stripe_secret_key',
		'STRIPE_WEBHOOK_SECRET' => 'stripe_webhook_secret',
	);

	/** @var (callable(string):mixed)|null */
	private static $store = null;

	/** @param (callable(string):mixed)|null $store settings-key → value */
	public static function set_store( ?callable $store ): void {
		self::$store = $store;
	}

	/** Where a key's value comes from — shown on the settings page. */
	public static function source( string $key ): string {
		if ( defined( $key ) ) {
			return 'constant';
		}
		$env = getenv( $key );
		if ( false !== $env && '' !== $env ) {
			return 'env';
		}
		return 'settings';
	}

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
		if ( self::$store && isset( self::STORE_KEYS[ $key ] ) ) {
			$stored = ( self::$store )( self::STORE_KEYS[ $key ] );
			if ( is_scalar( $stored ) && '' !== (string) $stored ) {
				return (string) $stored;
			}
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
		foreach ( preg_split( '/[\s,]+/', $raw ) ?: array() as $origin ) {
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
