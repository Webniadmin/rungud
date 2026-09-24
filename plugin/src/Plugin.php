<?php
declare(strict_types=1);

namespace Rungud;

use Rungud\Admin\SettingsPage;
use Rungud\Auth\Guard;
use Rungud\Auth\Sessions;
use Rungud\Install\Schema;
use Rungud\Rest\Cors;
use Rungud\Rest\Routes;

/**
 * Plugin entry point. Registers hooks only; each area lives in its own class.
 */
final class Plugin {

	private static ?Plugin $instance = null;
	private bool $booted = false;

	public static function instance(): self {
		return self::$instance ??= new self();
	}

	/** Activation: tables, seeds, roles. Idempotent. Nothing is ever dropped. */
	public static function activate(): void {
		Schema::install();
		Capabilities::install();
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		Config::set_store( static fn( string $key ) => Settings::get( $key ) );

		add_action( 'plugins_loaded', array( self::class, 'maybe_upgrade' ) );
		Guard::register();
		Cors::register();
		Routes::register();
		SettingsPage::register();

		// A new password ends every rungud session of that user.
		add_action( 'after_password_reset', static fn( \WP_User $user ) => Sessions::revoke_user( $user->ID, 'password_reset' ) );
		add_action(
			'profile_update',
			static function ( int $user_id, \WP_User $old ) {
				$new = get_userdata( $user_id );
				if ( $new && $new->user_pass !== $old->user_pass ) {
					Sessions::revoke_user( $user_id, 'password_changed' );
				}
			},
			10,
			2
		);
	}

	public static function maybe_upgrade(): void {
		if ( get_option( Schema::OPTION ) !== Schema::DB_VERSION ) {
			self::activate();
		}
	}

	public function is_booted(): bool {
		return $this->booted;
	}
}
