<?php
declare(strict_types=1);

namespace Rungud\Tests\Integration;

use Rungud\Audit\Audit;

final class AuditTest extends TestCase {

	private function row( array $extra = array() ): array {
		return array_merge(
			array(
				'entity'     => 'membership',
				'entity_id'  => 12,
				'action'     => 'cancel_at_period_end',
				'summary_en' => 'Membership of Beata Tothova cancelled at period end (07.09.2027).',
				'summary_de' => 'Mitgliedschaft von Beata Tothova zum Laufzeitende gekündigt (07.09.2027).',
			),
			$extra
		);
	}

	public function test_both_summaries_are_required(): void {
		$this->expectException( \InvalidArgumentException::class );
		Audit::record( array( 'entity' => 'x', 'action' => 'y', 'summary_en' => 'only English' ) );
	}

	public function test_run_records_ok_with_actor(): void {
		$gudrun = $this->make_user( 'rungud_backoffice' );
		wp_set_current_user( $gudrun->ID );
		$result = Audit::run( static fn() => 'done', $this->row( array( 'after' => static fn( $r ) => array( 'result' => $r ) ) ) );
		$this->assertSame( 'done', $result );
		$this->assertSame( 0, Audit::count_unresolved_failures() );
		global $wpdb;
		$row = $wpdb->get_row( 'SELECT * FROM ' . \Rungud\Install\Schema::table( 'audit' ) . ' ORDER BY id DESC LIMIT 1', ARRAY_A );
		$this->assertSame( 'ok', $row['status'] );
		$this->assertSame( (string) $gudrun->ID, $row['actor_id'] );
		$this->assertSame( '{"result":"done"}', $row['after_json'] );
	}

	public function test_failure_is_recorded_rethrown_and_resolved_by_a_manual_retry(): void {
		try {
			Audit::run( static function () { throw new \RuntimeException( 'website did not answer' ); }, $this->row() );
			$this->fail( 'exception expected' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'website did not answer', $e->getMessage() );
		}
		$this->assertSame( 1, Audit::count_unresolved_failures() );
		$failure = Audit::unresolved_failures()[0];
		$this->assertSame( 'website did not answer', $failure['error'] );

		// A retry that fails again does not add a second open item.
		try {
			Audit::run( static function () { throw new \RuntimeException( 'still down' ); }, $this->row( array( 'retry_of' => $failure['id'] ) ) );
		} catch ( \RuntimeException $e ) {
			unset( $e );
		}
		$this->assertSame( 1, Audit::count_unresolved_failures() );

		Audit::run( static fn() => true, $this->row( array( 'retry_of' => $failure['id'] ) ) );
		$this->assertSame( 0, Audit::count_unresolved_failures() );
	}
}
