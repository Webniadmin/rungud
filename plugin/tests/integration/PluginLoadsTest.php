<?php
declare(strict_types=1);

namespace Rungud\Tests\Integration;

use Rungud\Plugin;

final class PluginLoadsTest extends \WP_UnitTestCase {

	public function test_plugin_is_booted(): void {
		$this->assertTrue( defined( 'RUNGUD_CMS_VERSION' ) );
		$this->assertTrue( Plugin::instance()->is_booted() );
	}

	public function test_woocommerce_is_available(): void {
		$this->assertTrue( function_exists( 'wc_get_orders' ) );
	}
}
