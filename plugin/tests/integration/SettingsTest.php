<?php
declare(strict_types=1);

namespace Rungud\Tests\Integration;

use Rungud\Config;
use Rungud\Install\Schema;
use Rungud\Settings;

final class SettingsTest extends TestCase {

	public function test_secret_is_stored_but_masked_in_the_audit(): void {
		Settings::set( 'stripe_secret_key', 'rk_test_1234567890abcd' );
		Settings::flush();
		$this->assertSame( 'rk_test_1234567890abcd', Settings::get( 'stripe_secret_key' ) );
		$this->assertSame( 'rk_test_1234567890abcd', Config::get( 'STRIPE_SECRET_KEY' ) );
		global $wpdb;
		$after = $wpdb->get_var( 'SELECT after_json FROM ' . Schema::table( 'audit' ) . " WHERE entity = 'settings' ORDER BY id DESC LIMIT 1" );
		$this->assertStringNotContainsString( 'rk_test_1234567890', $after );
		$this->assertStringContainsString( 'abcd', $after );
	}

	public function test_wp_config_constant_wins_over_the_settings_page(): void {
		// wp-env defines RUNGUD_CORS_ORIGINS in wp-config.php.
		Settings::set( 'cors_origins', 'https://ignored.example' );
		$this->assertSame( 'constant', Config::source( 'RUNGUD_CORS_ORIGINS' ) );
		$this->assertNotContains( 'https://ignored.example', Config::cors_origins() );
	}
}
