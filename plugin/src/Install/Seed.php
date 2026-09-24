<?php
declare(strict_types=1);

namespace Rungud\Install;

/**
 * First-install defaults. Every seed is idempotent: it only inserts when the
 * table is empty, so re-activation never overwrites what an admin edited.
 * All values are placeholders until confirmed (see the phase 1 report).
 */
final class Seed {

	/** The last number used on the existing invoices is 100648 (Rechnung_100648). */
	public const FIRST_INVOICE_NUMBER = 100649;

	public static function run(): void {
		$entity_id = self::legal_entity();
		self::vat_profiles();
		self::number_sequence( $entity_id );
	}

	private static function legal_entity(): int {
		global $wpdb;
		$table = Schema::table( 'legal_entities' );
		$id    = (int) $wpdb->get_var( "SELECT id FROM {$table} ORDER BY id ASC LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL
		if ( $id ) {
			return $id;
		}
		$wpdb->insert(
			$table,
			array(
				'name'             => 'inZENtive',
				'owner_name'       => 'Robert Steinbacher',
				'postal_code'      => '8008',
				'city'             => 'Zürich',
				'country'          => 'CH',
				'email'            => 'info@inzentive.online',
				'currency_default' => 'EUR',
				'footer_en'        => 'inZENtive · Robert Steinbacher · CH-8008 Zürich',
				'footer_de'        => 'inZENtive · Robert Steinbacher · CH-8008 Zürich',
			)
		);
		return (int) $wpdb->insert_id;
	}

	private static function vat_profiles(): void {
		global $wpdb;
		$table = Schema::table( 'vat_profiles' );
		if ( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) > 0 ) { // phpcs:ignore WordPress.DB.PreparedSQL
			return;
		}
		$rows = array(
			array( 'de_standard', 'German VAT 19%', 'Deutsche MwSt. 19 %', 'DE', '19.00', 'standard', '', '',
				'Proposed because the event takes place in Germany.', 'Vorschlag, weil die Veranstaltung in Deutschland stattfindet.' ),
			array( 'ch_standard', 'Swiss VAT 8.1%', 'Schweizer MwSt. 8,1 %', 'CH', '8.10', 'standard', '', '',
				'Proposed because the event takes place in Switzerland.', 'Vorschlag, weil die Veranstaltung in der Schweiz stattfindet.' ),
			array( 'reverse_charge', 'Reverse charge', 'Reverse-Charge-Verfahren', null, '0.00', 'reverse_charge',
				'Reverse charge: VAT is owed by the recipient.', 'Reverse-Charge-Verfahren: Steuerschuldnerschaft des Leistungsempfängers.',
				'Proposed because the recipient is a company in another EU country with a VAT ID.', 'Vorschlag, weil der Rechnungsempfänger ein Unternehmen mit USt-IdNr. in einem anderen EU-Land ist.' ),
			array( 'not_taxable', 'Not taxable (export)', 'Nicht steuerbar (Export)', null, '0.00', 'not_taxable', '', '',
				'Proposed because the service is not taxable here.', 'Vorschlag, weil die Leistung hier nicht steuerbar ist.' ),
		);
		foreach ( $rows as $r ) {
			$wpdb->insert(
				$table,
				array(
					'code'           => $r[0],
					'name_en'        => $r[1],
					'name_de'        => $r[2],
					'country'        => $r[3],
					'rate'           => $r[4],
					'treatment'      => $r[5],
					'note_en'        => $r[6],
					'note_de'        => $r[7],
					'explanation_en' => $r[8],
					'explanation_de' => $r[9],
				)
			);
		}
	}

	private static function number_sequence( int $entity_id ): void {
		global $wpdb;
		$table = Schema::table( 'number_sequences' );
		$exists = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE doc_type = %s AND legal_entity_id = %d", 'invoice', $entity_id ) // phpcs:ignore WordPress.DB.PreparedSQL
		);
		if ( $exists ) {
			return;
		}
		$wpdb->insert(
			$table,
			array(
				'doc_type'        => 'invoice',
				'legal_entity_id' => $entity_id,
				'prefix'          => '',
				'next_number'     => self::FIRST_INVOICE_NUMBER,
				'updated_at'      => gmdate( 'Y-m-d H:i:s' ),
			)
		);
	}
}
