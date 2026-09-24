<?php
/**
 * Test double of the site theme (namespace App\) and its inzentive/v1 routes,
 * for CI where the real theme and LearnDash are absent. Shapes mirror
 * wp-content/themes/inzentive/app/api.php as read on 24.09.2026. Loaded only
 * when the real App\api_can() is missing.
 */

namespace Rungud\Tests\Fixtures {

	final class SiteStore {
		/** @var array<int,array<string,mixed>> user id → /access payload */
		public static array $access = array();
		/** @var list<array<string,mixed>> */
		public static array $events = array();
		/** @var array<int,list<array<string,mixed>>> event id → App\event_attendees() rows */
		public static array $attendees = array();
		/** @var array<string,int> event slug → waiting */
		public static array $waiting = array();
		/** @var array<string,list<int>> plan slug → user ids in its group */
		public static array $plan_members = array();
		/** @var array<int,string> user id → plan slug */
		public static array $active = array();
		/** @var list<array{route:string, params:array<string,mixed>, key:string}> commands the site received */
		public static array $received = array();
		/** @var array<string,\WP_Error> route → error to answer with */
		public static array $fail = array();
		/** @var list<array<string,mixed>> */
		public static array $programs = array();

		public static function reset(): void {
			self::$access = self::$events = self::$attendees = self::$waiting = self::$plan_members = self::$active = self::$received = self::$fail = self::$programs = array();
		}

		/** @return \WP_Error|array<string,mixed> */
		public static function command( string $route, \WP_REST_Request $r ) {
			self::$received[] = array( 'route' => $route, 'params' => $r->get_params(), 'key' => (string) $r->get_header( 'idempotency_key' ) );
			return self::$fail[ $route ] ?? array( 'ok' => true, 'route' => $route );
		}
	}
}

namespace App {

	use Rungud\Tests\Fixtures\SiteStore;

	function api_can() {
		$capability = (string) apply_filters( 'inzentive/api/capability', 'edit_users' );
		return current_user_can( $capability ) ? true : new \WP_Error( 'inzentive_api_forbidden', 'This account may not use the back-office API.', array( 'status' => rest_authorization_required_code() ) );
	}

	function membership_plans() {
		return array(
			'membership-monthly'         => array( 'title' => 'Monthly', 'audience' => 'b2c' ),
			'membership-annual'          => array( 'title' => 'Annual', 'audience' => 'b2c' ),
			'instructor-annual'          => array( 'title' => 'Instructor', 'audience' => 'b2b' ),
			'instructor-practice-annual' => array( 'title' => 'Instructor + practice', 'audience' => 'b2b' ),
		);
	}

	function membership_group_id( $slug ) {
		return crc32( (string) $slug ) % 100000 + 1;
	}

	function membership_is_active( $userId = null ) {
		return isset( SiteStore::$active[ (int) $userId ] );
	}

	function current_plan( $userId = null ) {
		return SiteStore::$active[ (int) $userId ] ?? null;
	}

	function valid_card_is_active( $userId = null ) {
		return ! empty( SiteStore::$access[ (int) $userId ]['valid_card']['active'] );
	}

	function event_attendees( $eventId, $paidOnly = false ) {
		return SiteStore::$attendees[ (int) $eventId ] ?? array();
	}

	function waitlist_count( $slug, $status = 'pending' ) {
		return SiteStore::$waiting[ (string) $slug ] ?? 0;
	}
}

namespace {

	use Rungud\Tests\Fixtures\SiteStore;

	define( 'RUNGUD_SITE_DOUBLE', true );
	define( 'LEARNDASH_VERSION', 'double' );

	function learndash_get_users_group_ids( $user_id ) {
		return array();
	}

	function learndash_get_groups_user_ids( $group_id ) {
		foreach ( SiteStore::$plan_members as $slug => $ids ) {
			if ( \App\membership_group_id( $slug ) === (int) $group_id ) {
				return $ids;
			}
		}
		return array();
	}

	add_action(
		'rest_api_init',
		static function () {
			register_rest_route( 'inzentive/v1', '/events', array(
				'methods'             => 'GET',
				'callback'            => static fn() => SiteStore::$events,
				'permission_callback' => 'App\\api_can',
			) );
			register_rest_route( 'inzentive/v1', '/programs', array( 'methods' => 'GET', 'callback' => static fn() => SiteStore::$programs, 'permission_callback' => 'App\\api_can' ) );
			register_rest_route( 'inzentive/v1', '/plans', array(
				'methods'             => 'GET',
				'callback'            => static fn() => array(
					array( 'slug' => 'membership-annual', 'title' => 'Annual', 'audience' => 'b2c', 'professional' => false, 'price' => 192.0, 'currency' => 'EUR' ),
					array( 'slug' => 'instructor-annual', 'title' => 'Instructor', 'audience' => 'b2b', 'professional' => true, 'price' => 200.0, 'currency' => 'EUR' ),
				),
				'permission_callback' => 'App\\api_can',
			) );
			register_rest_route( 'inzentive/v1', '/licences/pending', array( 'methods' => 'GET', 'callback' => static fn() => array(), 'permission_callback' => 'App\\api_can' ) );
			register_rest_route( 'inzentive/v1', '/licences', array( 'methods' => 'POST', 'callback' => static fn( $r ) => SiteStore::command( '/licences', $r ), 'permission_callback' => 'App\\api_can' ) );
			register_rest_route( 'inzentive/v1', '/licences/(?P<user_id>\d+)/(?P<program>[a-z0-9-]+)/(?P<verdict>verify|reject|revoke)', array( 'methods' => 'POST', 'callback' => static fn( $r ) => SiteStore::command( '/licences/verdict', $r ), 'permission_callback' => 'App\\api_can' ) );
			register_rest_route( 'inzentive/v1', '/access/grant', array( 'methods' => 'POST', 'callback' => static fn( $r ) => SiteStore::command( '/access/grant', $r ), 'permission_callback' => 'App\\api_can' ) );
			register_rest_route( 'inzentive/v1', '/access/(?P<user_id>\d+)', array(
				'methods'             => 'GET',
				'callback'            => static function ( \WP_REST_Request $r ) {
					$id = (int) $r['user_id'];
					if ( ! get_userdata( $id ) ) {
						return new \WP_Error( 'inzentive_api_no_user', 'No such user.', array( 'status' => 404 ) );
					}
					return SiteStore::$access[ $id ] ?? array(
						'user'       => array( 'id' => $id, 'professional' => false ),
						'membership' => array( 'active' => false, 'billing_continues' => false, 'plan' => null, 'subscriptions' => array() ),
						'licences'   => array(),
						'valid_card' => null,
						'paying'     => false,
						'group_ids'  => array(),
						'courses'    => array(),
					);
				},
				'permission_callback' => 'App\\api_can',
			) );
		}
	);
}
