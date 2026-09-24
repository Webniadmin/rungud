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
		require_once dirname( __DIR__, 2 ) . '/rungud-cms.php';
	}
);

require $tests_dir . '/includes/bootstrap.php';
