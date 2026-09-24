<?php
declare(strict_types=1);

namespace Rungud;

/**
 * Plugin entry point. Hooks are registered here; each area (tables, REST, cron,
 * Stripe webhook) is added in its own build phase.
 */
final class Plugin {

	private static ?Plugin $instance = null;
	private bool $booted = false;

	public static function instance(): self {
		return self::$instance ??= new self();
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;
	}

	public function is_booted(): bool {
		return $this->booted;
	}
}
