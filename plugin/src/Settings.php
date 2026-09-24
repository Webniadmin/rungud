<?php
declare(strict_types=1);

namespace Rungud;

use Rungud\Audit\Audit;
use Rungud\Install\Schema;

/**
 * Key/value settings in wp_rungud_settings. Every write is audited with the
 * old and new value (secrets masked).
 */
final class Settings {

	/** Keys the settings page may store. Secrets are masked in audit rows. */
	public const KEYS = array(
		'cors_origins'          => array( 'secret' => false ),
		'stripe_secret_key'     => array( 'secret' => true ),
		'stripe_webhook_secret' => array( 'secret' => true ),
		'smtp_host'             => array( 'secret' => false ),
		'smtp_port'             => array( 'secret' => false ),
		'smtp_user'             => array( 'secret' => false ),
		'smtp_pass'             => array( 'secret' => true ),
		'smtp_from'             => array( 'secret' => false ),
		'smtp_from_name'        => array( 'secret' => false ),
		'pricing_url'           => array( 'secret' => false ),
	);

	/** @var array<string,mixed>|null */
	private static ?array $cache = null;

	public static function get( string $key, mixed $fallback = null ): mixed {
		self::load();
		return array_key_exists( $key, self::$cache ) ? self::$cache[ $key ] : $fallback;
	}

	public static function set( string $key, mixed $value ): void {
		if ( ! isset( self::KEYS[ $key ] ) ) {
			throw new \InvalidArgumentException( "Unknown setting: {$key}" );
		}
		global $wpdb;
		$before = self::get( $key );
		if ( $before === $value ) {
			return;
		}
		$wpdb->replace(
			Schema::table( 'settings' ),
			array(
				'setting_key' => $key,
				'value_json'  => wp_json_encode( $value ),
				'updated_by'  => get_current_user_id() ?: null,
				'updated_at'  => gmdate( 'Y-m-d H:i:s' ),
			)
		);
		self::$cache[ $key ] = $value;

		$secret = self::KEYS[ $key ]['secret'];
		Audit::record(
			array(
				'entity'     => 'settings',
				'entity_id'  => $key,
				'action'     => 'update',
				'before'     => array( $key => $secret ? self::mask( $before ) : $before ),
				'after'      => array( $key => $secret ? self::mask( $value ) : $value ),
				'summary_en' => "Setting “{$key}” changed.",
				'summary_de' => "Einstellung „{$key}“ geändert.",
			)
		);
	}

	public static function flush(): void {
		self::$cache = null;
	}

	private static function mask( mixed $value ): string {
		return ( null === $value || '' === $value ) ? '(empty)' : '••••' . substr( (string) $value, -4 );
	}

	private static function load(): void {
		if ( null !== self::$cache ) {
			return;
		}
		global $wpdb;
		self::$cache = array();
		$rows        = $wpdb->get_results( 'SELECT setting_key, value_json FROM ' . Schema::table( 'settings' ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		foreach ( (array) $rows as $row ) {
			self::$cache[ $row['setting_key'] ] = json_decode( $row['value_json'], true );
		}
	}
}
