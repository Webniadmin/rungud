<?php
declare(strict_types=1);

namespace Rungud\Tests\Integration;

use Rungud\Audit\Audit;
use Rungud\Capabilities as C;
use Rungud\Rest\TodayController;

final class TodayTest extends TestCase {

	public function test_structure_for_gudrun(): void {
		wp_set_current_user( $this->make_user( C::ROLE_BACKOFFICE )->ID );
		$data = $this->call( 'GET', '/today' )->get_data();
		$this->assertSame( 'backoffice', $data['role'] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}$/', $data['date'] );
		$this->assertSame( array(), $data['items'] );
		$keys = array_column( TodayController::ITEMS['backoffice'], 0 );
		// Wired so far: failed commands (phase 1) and pending licences (phase 3).
		$this->assertSame( array_values( array_diff( $keys, array( 'commands_failed', 'licences_pending' ) ) ), $data['not_yet_available'] );
	}

	public function test_robert_gets_his_own_list(): void {
		wp_set_current_user( $this->make_user( C::ROLE_OWNER )->ID );
		$data = $this->call( 'GET', '/today' )->get_data();
		$this->assertSame( 'owner', $data['role'] );
		$this->assertContains( 'code_claims_to_decide', $data['not_yet_available'] );
		$this->assertNotContains( 'invoices_awaiting_approval', $data['not_yet_available'] );
	}

	public function test_failed_command_appears_until_retried(): void {
		wp_set_current_user( $this->make_user( C::ROLE_BACKOFFICE )->ID );
		$failed = Audit::record(
			array(
				'entity'     => 'access',
				'entity_id'  => 7,
				'action'     => 'revoke',
				'status'     => Audit::FAILED,
				'error'      => 'timeout',
				'summary_en' => "Monika Burkhard's Classroom access should have been removed.",
				'summary_de' => 'Monika Burkhards Classroom-Zugang sollte entzogen werden.',
			)
		);
		$items = $this->call( 'GET', '/today' )->get_data()['items'];
		$this->assertCount( 1, $items );
		$this->assertSame( 'commands_failed', $items[0]['key'] );
		$this->assertSame( 'bad', $items[0]['tone'] );
		$this->assertSame( 1, $items[0]['count'] );
		$this->assertSame( $failed, $items[0]['params']['audit_id'] );
		$this->assertSame( array( 'screen' => 'sync' ), $items[0]['target'] );

		Audit::record(
			array(
				'entity'     => 'access',
				'entity_id'  => 7,
				'action'     => 'revoke',
				'retry_of'   => $failed,
				'summary_en' => 'Retried: access removed.',
				'summary_de' => 'Wiederholt: Zugang entzogen.',
			)
		);
		$this->assertSame( array(), $this->call( 'GET', '/today' )->get_data()['items'] );
	}
}
