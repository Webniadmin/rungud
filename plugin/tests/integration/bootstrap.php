<?php
/**
 * Integration tests run inside wp-env's tests container, with WordPress,
 * WooCommerce and this plugin loaded.
 */
require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

$tests_dir = getenv( 'WP_TESTS_DIR' ) ?: dirname( __DIR__, 2 ) . '/vendor/wp-phpunit/wp-phpunit';
if ( ! getenv( 'WP_PHPUNIT__TESTS_CONFIG' ) && is_readable( '/wordpress-phpunit/wp-tests-config.php' ) ) {
	putenv( 'WP_PHPUNIT__TESTS_CONFIG=/wordpress-phpunit/wp-tests-config.php' );
}
define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills' );

require_once $tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		$plugins = dirname( __DIR__, 3 );
		require_once $plugins . '/woocommerce/woocommerce.php';
		// Local-site runs only: LearnDash and the site's own plugins, when present.
		if ( is_readable( $plugins . '/sfwd-lms/sfwd_lms.php' ) ) {
			require_once $plugins . '/sfwd-lms/sfwd_lms.php';
		}
		foreach ( array_filter( explode( ',', (string) getenv( 'RUNGUD_TEST_SITE_PLUGINS' ) ) ) as $extra ) {
			require_once $plugins . '/' . trim( $extra );
		}
		// CI: no site theme — use the double of App\ and inzentive/v1.
		if ( ! function_exists( 'App\\api_can' ) ) {
			require_once __DIR__ . '/fixtures/site-double.php';
		}
		require_once dirname( __DIR__, 2 ) . '/rungud-cms.php';
	}
);

require $tests_dir . '/includes/bootstrap.php';

// WooCommerce tables (orders are created in tests through Woo's own API).
if ( class_exists( 'WC_Install' ) ) {
	WC_Install::install();
}

// Real tables for the test run (inside test methods WP turns CREATE TABLE into temporary tables).
Rungud\Plugin::activate();

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/LocalSiteTestCase.php';
