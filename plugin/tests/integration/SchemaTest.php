<?php
declare(strict_types=1);

namespace Rungud\Tests\Integration;

use Rungud\Install\Schema;
use Rungud\Install\Seed;
use Rungud\Plugin;

final class SchemaTest extends TestCase {

	public function test_activation_created_every_table(): void {
		global $wpdb;
		foreach ( Schema::tables() as $name ) {
			$table = Schema::table( $name );
			$this->assertSame( $table, $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ), "missing {$table}" );
		}
		$this->assertSame( Schema::DB_VERSION, get_option( Schema::OPTION ) );
	}

	public function test_no_copy_tables_of_site_data(): void {
		foreach ( array( 'people', 'site_orders', 'site_memberships', 'site_licences', 'site_coupons', 'sync_outbox', 'sync_log', 'stripe_invoices' ) as $forbidden ) {
			$this->assertNotContains( $forbidden, Schema::tables(), "CLAUDE.md rule 10: {$forbidden} would copy site data" );
		}
	}

	public function test_seeds_exist_and_reactivation_does_not_duplicate_them(): void {
		global $wpdb;
		Plugin::activate();
		Plugin::activate();
		$this->assertSame( '1', $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Schema::table( 'legal_entities' ) ) );
		$this->assertSame( '4', $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Schema::table( 'vat_profiles' ) ) );
		$seq = $wpdb->get_row( 'SELECT * FROM ' . Schema::table( 'number_sequences' ), ARRAY_A );
		$this->assertSame( 'invoice', $seq['doc_type'] );
		$this->assertGreaterThanOrEqual( Seed::FIRST_INVOICE_NUMBER, (int) $seq['next_number'] );
	}
}
