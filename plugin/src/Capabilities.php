<?php
declare(strict_types=1);

namespace Rungud;

/**
 * Roles and capabilities (CLAUDE.md rule 9). The REST layer checks these on
 * every route; the UI only mirrors them.
 *
 *   rungud_owner       Robert — reads everything; may promise a discount and
 *                      decide claims. Nothing else.
 *   rungud_backoffice  Gudrun — everything operational and financial, no settings.
 *   administrator      Webni — everything including settings.
 */
final class Capabilities {

	public const READ     = 'rungud_read';      // see every screen
	public const PROMISE  = 'rungud_promise';   // promise a discount, decide code claims
	public const WRITE    = 'rungud_write';     // access, licences, attendees, codes, certificates
	public const FINANCE  = 'rungud_finance';   // invoices, payments, refunds, approvals
	public const SEND     = 'rungud_send';      // send documents and messages
	public const SETTINGS = 'rungud_settings';  // settings, imports

	public const ROLE_OWNER      = 'rungud_owner';
	public const ROLE_BACKOFFICE = 'rungud_backoffice';

	/** @return array<string,list<string>> role → rungud capabilities */
	public static function map(): array {
		return array(
			self::ROLE_OWNER      => array( self::READ, self::PROMISE ),
			self::ROLE_BACKOFFICE => array( self::READ, self::PROMISE, self::WRITE, self::FINANCE, self::SEND ),
			'administrator'       => array( self::READ, self::PROMISE, self::WRITE, self::FINANCE, self::SEND, self::SETTINGS ),
		);
	}

	/** @return list<string> */
	public static function all(): array {
		return array( self::READ, self::PROMISE, self::WRITE, self::FINANCE, self::SEND, self::SETTINGS );
	}

	/** Idempotent: safe on every activation and upgrade. */
	public static function install(): void {
		$labels = array(
			self::ROLE_OWNER      => 'rungud Owner',
			self::ROLE_BACKOFFICE => 'rungud Back office',
		);
		foreach ( self::map() as $role_name => $caps ) {
			$role = get_role( $role_name );
			if ( ! $role && isset( $labels[ $role_name ] ) ) {
				$role = add_role( $role_name, $labels[ $role_name ], array( 'read' => true ) );
			}
			if ( ! $role ) {
				continue;
			}
			foreach ( self::all() as $cap ) {
				if ( in_array( $cap, $caps, true ) ) {
					$role->add_cap( $cap );
				} elseif ( $role->has_cap( $cap ) ) {
					$role->remove_cap( $cap );
				}
			}
		}
	}

	/**
	 * The CMS role the UI shows. Derived from capabilities, so a user with two
	 * WP roles gets the widest one — which is why Robert's account must hold
	 * rungud_owner only (see README).
	 */
	public static function role_of( \WP_User $user ): ?string {
		if ( $user->has_cap( self::SETTINGS ) ) {
			return 'admin';
		}
		if ( $user->has_cap( self::FINANCE ) ) {
			return 'backoffice';
		}
		if ( $user->has_cap( self::READ ) ) {
			return 'owner';
		}
		return null;
	}

	/** @return array<string,bool> capability flags for the UI */
	public static function flags( \WP_User $user ): array {
		$out = array();
		foreach ( self::all() as $cap ) {
			$out[ substr( $cap, strlen( 'rungud_' ) ) ] = $user->has_cap( $cap );
		}
		return $out;
	}
}
