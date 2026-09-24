<?php
declare(strict_types=1);

namespace Rungud\Woo;

use Rungud\Install\Schema;

/**
 * Timed-access redeem codes: WooCommerce coupons carrying the site's meta
 * (_inzentive_valid_card, _inzentive_card_days, _inzentive_card_scope). The
 * site redeems, checks and expires them (valid_card_redeem()); the CMS only
 * creates them through WC_Coupon and keeps the intent in wp_rungud_discount_codes.
 */
final class RedeemCodes {

	public const ALPHABET      = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
	public const CODE_VALID_DAYS = 60;

	public static function generate( string $prefix = 'TRY-' ): string {
		do {
			$code = $prefix;
			for ( $i = 0; $i < 4; $i++ ) {
				$code .= self::ALPHABET[ random_int( 0, strlen( self::ALPHABET ) - 1 ) ];
			}
		} while ( wc_get_coupon_id_by_code( $code ) );
		return $code;
	}

	/**
	 * @return array{code:string, coupon_id:int, days:int, scope:string, restricted_to:?string, code_valid_until:string}
	 */
	public static function create( \WP_User $user, int $days, string $scope, bool $restrict, string $note ): array {
		$code   = self::generate();
		$coupon = new \WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( 'percent' );
		$coupon->set_amount( 0 );
		$coupon->set_individual_use( true );
		$coupon->set_usage_limit( 1 );
		$coupon->set_usage_limit_per_user( 1 );
		$expires = ( new \DateTimeImmutable( 'now', wp_timezone() ) )->modify( '+' . self::CODE_VALID_DAYS . ' days' )->setTime( 23, 59, 59 );
		$coupon->set_date_expires( $expires->getTimestamp() );
		if ( $restrict ) {
			$coupon->set_email_restrictions( array( $user->user_email ) );
		}
		$coupon->set_description( trim( 'rungud · ' . $note ) );
		$coupon->update_meta_data( '_inzentive_valid_card', 'yes' );
		$coupon->update_meta_data( '_inzentive_card_days', $days );
		$coupon->update_meta_data( '_inzentive_card_scope', $scope );
		$id = $coupon->save();
		if ( ! $id ) {
			throw new \RuntimeException( 'WooCommerce did not save the coupon.' );
		}

		global $wpdb;
		$wpdb->insert(
			Schema::table( 'discount_codes' ),
			array(
				'code'           => $code,
				'woo_coupon_id'  => $id,
				'user_id'        => $user->ID,
				'kind'           => 'redeem',
				'one_per_person' => 1,
				'stackable'      => 0,
				'for_label'      => $user->user_email,
				'note'           => $note,
				'made_by'        => get_current_user_id(),
				'made_at'        => gmdate( 'Y-m-d H:i:s' ),
			)
		);

		return array(
			'code'             => $code,
			'coupon_id'        => $id,
			'days'             => $days,
			'scope'            => $scope,
			'restricted_to'    => $restrict ? $user->user_email : null,
			'code_valid_until' => $expires->format( 'Y-m-d' ),
		);
	}

	/** Codes the CMS made for this person, with live usage from WooCommerce. @return list<array<string,mixed>> */
	public static function for_user( int $user_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . Schema::table( 'discount_codes' ) . " WHERE user_id = %d AND kind = 'redeem' ORDER BY id DESC", $user_id ), // phpcs:ignore WordPress.DB.PreparedSQL
			ARRAY_A
		);
		$out = array();
		foreach ( (array) $rows as $r ) {
			$coupon  = $r['woo_coupon_id'] ? new \WC_Coupon( (int) $r['woo_coupon_id'] ) : null;
			$exists  = $coupon && $coupon->get_id();
			$expires = $exists && $coupon->get_date_expires() ? $coupon->get_date_expires()->date( 'Y-m-d' ) : null;
			$used    = $exists ? $coupon->get_usage_count() > 0 : false;
			$out[]   = array(
				'code'       => $r['code'],
				'days'       => $exists ? (int) $coupon->get_meta( '_inzentive_card_days' ) : null,
				'scope'      => $exists ? (string) $coupon->get_meta( '_inzentive_card_scope' ) : null,
				'made_at'    => substr( (string) $r['made_at'], 0, 10 ),
				'note'       => (string) $r['note'],
				'used'       => $used,
				'valid_until' => $expires,
				'state'      => ! $exists ? 'deleted' : ( $used ? 'used' : ( $expires && $expires < wp_date( 'Y-m-d' ) ? 'expired' : 'open' ) ),
			);
		}
		return $out;
	}
}
