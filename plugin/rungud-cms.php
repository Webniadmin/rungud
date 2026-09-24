<?php
/**
 * Plugin Name:       rungud CMS
 * Description:       Back office for inZENtive. Reads the site's WooCommerce, LearnDash and Stripe data and sends commands through the site's own functions.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * Author:            Webni
 * Text Domain:       rungud-cms
 */

defined( 'ABSPATH' ) || exit;

define( 'RUNGUD_CMS_VERSION', '0.1.0' );
define( 'RUNGUD_CMS_FILE', __FILE__ );
define( 'RUNGUD_CMS_DIR', __DIR__ );

$rungud_autoload = __DIR__ . '/vendor/autoload.php';
if ( ! is_readable( $rungud_autoload ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>rungud CMS: <code>vendor/</code> is missing. Run <code>composer install --no-dev</code> in the plugin folder.</p></div>';
		}
	);
	return;
}
require_once $rungud_autoload;

Rungud\Plugin::instance()->boot();
