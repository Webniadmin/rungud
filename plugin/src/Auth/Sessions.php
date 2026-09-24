<?php
declare(strict_types=1);

namespace Rungud\Auth;

use Rungud\Install\Schema;

/**
 * Refresh-token sessions. A login starts a family; every refresh rotates the
 * token inside that family. Presenting a token that was already rotated means
 * it leaked or two tabs raced — the whole family is revoked.
 * Logout revokes the family, which also kills its access tokens at once
 * (Guard checks the family on every request).
 */
final class Sessions {

	public const REFRESH_TTL = 30 * DAY_IN_SECONDS;

	/** @return array{refresh_token:string, family:string} */
	public static function start( int $user_id ): array {
		$family = wp_generate_uuid4();
		return array(
			'refresh_token' => self::insert( $user_id, $family ),
			'family'        => $family,
		);
	}

	/**
	 * @return array{user_id:int, family:string, refresh_token:string}
	 * @throws InvalidToken
	 */
	public static function rotate( string $refresh_token ): array {
		global $wpdb;
		$t   = Schema::table( 'sessions' );
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$t} WHERE token_hash = %s", self::hash( $refresh_token ) ), // phpcs:ignore WordPress.DB.PreparedSQL
			ARRAY_A
		);
		if ( ! $row ) {
			throw new InvalidToken( 'Unknown refresh token.' );
		}
		if ( null !== $row['revoked_at'] ) {
			self::revoke_family( $row['family'], 'reuse' );
			throw new InvalidToken( 'Refresh token was already used.' );
		}
		if ( strtotime( $row['expires_at'] . ' UTC' ) <= time() ) {
			throw new InvalidToken( 'Refresh token expired.' );
		}
		// Claim the row atomically; a concurrent refresh with the same token loses and counts as reuse.
		$claimed = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$t} SET revoked_at = %s, revoked_reason = 'rotated' WHERE id = %d AND revoked_at IS NULL", // phpcs:ignore WordPress.DB.PreparedSQL
				gmdate( 'Y-m-d H:i:s' ),
				$row['id']
			)
		);
		if ( 1 !== $claimed ) {
			self::revoke_family( $row['family'], 'reuse' );
			throw new InvalidToken( 'Refresh token was already used.' );
		}
		$user_id = (int) $row['user_id'];
		return array(
			'user_id'       => $user_id,
			'family'        => $row['family'],
			'refresh_token' => self::insert( $user_id, $row['family'] ),
		);
	}

	public static function is_active( string $family ): bool {
		global $wpdb;
		$t = Schema::table( 'sessions' );
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$t} WHERE family = %s AND revoked_at IS NULL AND expires_at > %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL
				$family,
				gmdate( 'Y-m-d H:i:s' )
			)
		);
	}

	public static function revoke_family( string $family, string $reason ): void {
		global $wpdb;
		$t = Schema::table( 'sessions' );
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$t} SET revoked_at = %s, revoked_reason = %s WHERE family = %s AND revoked_at IS NULL", // phpcs:ignore WordPress.DB.PreparedSQL
				gmdate( 'Y-m-d H:i:s' ),
				$reason,
				$family
			)
		);
	}

	public static function revoke_user( int $user_id, string $reason ): void {
		global $wpdb;
		$t = Schema::table( 'sessions' );
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$t} SET revoked_at = %s, revoked_reason = %s WHERE user_id = %d AND revoked_at IS NULL", // phpcs:ignore WordPress.DB.PreparedSQL
				gmdate( 'Y-m-d H:i:s' ),
				$reason,
				$user_id
			)
		);
	}

	private static function insert( int $user_id, string $family ): string {
		global $wpdb;
		$token = rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' );
		$now   = time();
		$wpdb->insert(
			Schema::table( 'sessions' ),
			array(
				'user_id'    => $user_id,
				'family'     => $family,
				'token_hash' => self::hash( $token ),
				'created_at' => gmdate( 'Y-m-d H:i:s', $now ),
				'expires_at' => gmdate( 'Y-m-d H:i:s', $now + self::REFRESH_TTL ),
				'ip'         => substr( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ), 0, 45 ),
				'user_agent' => substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ), 0, 255 ),
			)
		);
		return $token;
	}

	private static function hash( string $token ): string {
		return hash( 'sha256', $token );
	}
}
