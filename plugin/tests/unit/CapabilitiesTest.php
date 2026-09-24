<?php
declare(strict_types=1);

namespace Rungud\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rungud\Capabilities as C;

final class CapabilitiesTest extends TestCase {

	public function test_robert_reads_and_promises_nothing_else(): void {
		$this->assertSame( array( C::READ, C::PROMISE ), C::map()[ C::ROLE_OWNER ] );
	}

	public function test_gudrun_has_everything_but_settings(): void {
		$caps = C::map()[ C::ROLE_BACKOFFICE ];
		$this->assertNotContains( C::SETTINGS, $caps );
		foreach ( array( C::READ, C::PROMISE, C::WRITE, C::FINANCE, C::SEND ) as $cap ) {
			$this->assertContains( $cap, $caps );
		}
	}

	public function test_admin_has_all(): void {
		$this->assertSame( C::all(), C::map()['administrator'] );
	}
}
