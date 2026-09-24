<?php
declare(strict_types=1);

namespace Rungud\Tests\Integration;

use Rungud\Capabilities as C;

final class CapabilitiesTest extends TestCase {

	public function test_roles_exist_with_exactly_their_caps(): void {
		foreach ( C::map() as $role_name => $caps ) {
			$role = get_role( $role_name );
			$this->assertNotNull( $role, $role_name );
			foreach ( C::all() as $cap ) {
				$this->assertSame( in_array( $cap, $caps, true ), $role->has_cap( $cap ), "{$role_name} / {$cap}" );
			}
		}
	}

	public function test_role_names_for_the_ui(): void {
		$this->assertSame( 'owner', C::role_of( $this->make_user( C::ROLE_OWNER ) ) );
		$this->assertSame( 'backoffice', C::role_of( $this->make_user( C::ROLE_BACKOFFICE ) ) );
		$this->assertSame( 'admin', C::role_of( $this->make_user( 'administrator' ) ) );
		$this->assertNull( C::role_of( $this->make_user( 'subscriber' ) ) );
		$this->assertNull( C::role_of( $this->make_user( 'shop_manager' ) ) );
	}
}
