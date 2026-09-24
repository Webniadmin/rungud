<?php
declare(strict_types=1);

namespace Rungud\Rest;

use Rungud\Install\Schema;

/**
 * Proposes the next licence number PREFIX-SEQ. Looks at every number already
 * in use — the site's licences (read from user meta, never written), the
 * programme's known certification numbers and the CMS's certificates — and
 * suggests max + 1, zero-padded to four digits. Gudrun can change it.
 */
final class LicenceNumbers {

	/** @param list<string> $known */
	public static function next( string $prefix, array $known = array() ): string {
		$max = 0;
		$scan = static function ( string $number ) use ( $prefix, &$max ) {
			if ( preg_match( '/^' . preg_quote( $prefix, '/' ) . '-(\d+)$/i', trim( $number ), $m ) ) {
				$max = max( $max, (int) $m[1] );
			}
		};
		array_map( $scan, $known );
		foreach ( get_users( array( 'meta_key' => '_inzentive_licenses', 'fields' => 'ID', 'number' => -1 ) ) as $uid ) {
			foreach ( (array) get_user_meta( (int) $uid, '_inzentive_licenses', true ) as $record ) {
				$scan( (string) ( $record['certificate_number'] ?? '' ) );
			}
		}
		global $wpdb;
		foreach ( (array) $wpdb->get_col( 'SELECT licence_number FROM ' . Schema::table( 'certificates' ) . ' WHERE licence_number IS NOT NULL' ) as $n ) { // phpcs:ignore WordPress.DB.PreparedSQL
			$scan( (string) $n );
		}
		return sprintf( '%s-%04d', $prefix, $max + 1 );
	}
}
