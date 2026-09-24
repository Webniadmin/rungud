<?php
declare(strict_types=1);

namespace Rungud\Tests\Integration;

/**
 * Base for tests that need the site team's local copy: LearnDash (commercial,
 * not installable in CI) and the site's inzentive/v1 endpoints.
 *
 * Tag every subclass with @group local-site. CI excludes the group; run it
 * with `composer test:local-site` after adding LearnDash and the site's
 * inzentive plugin to plugin/.wp-env.override.json (see README).
 */
abstract class LocalSiteTestCase extends TestCase {

	public function set_up(): void {
		parent::set_up();
		if ( defined( 'RUNGUD_SITE_DOUBLE' ) || ! defined( 'LEARNDASH_VERSION' ) ) {
			$this->markTestSkipped( 'LearnDash is not installed — local-site tests need the site team\'s copy.' );
		}
	}
}
