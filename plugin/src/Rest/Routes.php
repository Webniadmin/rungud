<?php
declare(strict_types=1);

namespace Rungud\Rest;

final class Routes {

	public const NS = 'rungud/v1';

	/** Routes that need no session. Anything else must check a capability. */
	public const PUBLIC = array(
		'/rungud/v1/auth/login',
		'/rungud/v1/auth/refresh',
	);

	public static function register(): void {
		add_action(
			'rest_api_init',
			static function () {
				AuthController::register();
				TodayController::register();
			}
		);
	}
}
