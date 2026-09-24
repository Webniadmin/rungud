<?php
declare(strict_types=1);

namespace Rungud\Site;

/**
 * Read-only access to the site theme's PHP functions (namespace App\).
 * Only functions that read. Every call is guarded: a missing function means
 * the site does not offer it (yet) and callers show that instead of guessing.
 */
final class Site {

	public static function has( string $fn ): bool {
		return function_exists( 'App\\' . $fn );
	}

	/** @param mixed ...$args */
	public static function call( string $fn, ...$args ): mixed {
		if ( ! self::has( $fn ) ) {
			throw new SiteUnavailable( 'missing_function', "The website has no App\\{$fn}()." );
		}
		return ( 'App\\' . $fn )( ...$args );
	}

	public static function learndash_active(): bool {
		return defined( 'LEARNDASH_VERSION' ) && function_exists( 'learndash_get_users_group_ids' );
	}

	/** Orders for an event, one row per order: App\event_attendees(). @return list<array<string,mixed>> */
	public static function event_orders( int $event_id ): array {
		return array_values( (array) self::call( 'event_attendees', $event_id, false ) );
	}

	public static function waitlist_count( string $event_slug ): ?int {
		return self::has( 'waitlist_count' ) ? (int) self::call( 'waitlist_count', $event_slug, 'pending' ) : null;
	}

	/** @return array<string,array<string,mixed>> plan slug → plan */
	public static function plans(): array {
		return (array) self::call( 'membership_plans' );
	}

	/** User ids enrolled in a plan's LearnDash group. @return list<int> */
	public static function plan_member_ids( string $plan_slug ): array {
		if ( ! self::learndash_active() || ! function_exists( 'learndash_get_groups_user_ids' ) ) {
			return array();
		}
		$group = (int) self::call( 'membership_group_id', $plan_slug );
		return $group ? array_map( 'intval', (array) learndash_get_groups_user_ids( $group ) ) : array();
	}

	public static function membership_active( int $user_id ): bool {
		return self::has( 'membership_is_active' ) && (bool) self::call( 'membership_is_active', $user_id );
	}

	public static function current_plan( int $user_id ): ?string {
		if ( ! self::has( 'current_plan' ) ) {
			return null;
		}
		$plan = self::call( 'current_plan', $user_id );
		return is_string( $plan ) && '' !== $plan ? $plan : null;
	}
}
