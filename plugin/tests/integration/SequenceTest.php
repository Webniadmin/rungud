<?php
declare(strict_types=1);

namespace Rungud\Tests\Integration;

use Rungud\Admin\SettingsPage;
use Rungud\Install\Schema;

final class SequenceTest extends TestCase {

	private function next(): int {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT next_number FROM ' . Schema::table( 'number_sequences' ) . " WHERE doc_type = 'invoice'" );
	}

	public function test_raise_is_allowed_and_audited(): void {
		$current = $this->next();
		$this->assertTrue( SettingsPage::raise_sequence( $current + 10 ) );
		$this->assertSame( $current + 10, $this->next() );
		global $wpdb;
		$this->assertSame( 'raise', $wpdb->get_var( 'SELECT action FROM ' . Schema::table( 'audit' ) . " WHERE entity = 'number_sequence' ORDER BY id DESC LIMIT 1" ) );
	}

	public function test_lowering_is_refused(): void {
		$current = $this->next();
		$result  = SettingsPage::raise_sequence( $current - 1 );
		$this->assertWPError( $result );
		$this->assertSame( $current, $this->next() );
	}
}
