<?php
declare(strict_types=1);

namespace Rungud\Rest;

use Rungud\Capabilities;
use Rungud\Read\MembershipView;
use Rungud\Site\Site;
use Rungud\Site\SiteApi;
use Rungud\Site\SiteUnavailable;

/**
 * Everybody with a paid plan (LearnDash plan groups) or free access (valid
 * card), with the state derived by MembershipView. Read live on every call.
 */
final class MembershipsController {

	public const FILTERS = array( 'expiring', 'ending', 'active', 'free_access', 'all' );

	public static function register(): void {
		register_rest_route( Routes::NS, '/memberships', array(
			'methods'             => 'GET',
			'callback'            => array( self::class, 'index' ),
			'permission_callback' => Permission::cap( Capabilities::READ ),
			'args'                => array(
				'filter' => array( 'type' => 'string', 'enum' => self::FILTERS, 'default' => 'expiring' ),
			),
		) );
	}

	public static function index( \WP_REST_Request $request ) {
		$ids = array();
		try {
			foreach ( array_keys( Site::plans() ) as $slug ) {
				$ids = array_merge( $ids, Site::plan_member_ids( (string) $slug ) );
			}
		} catch ( SiteUnavailable $e ) {
			return $e->to_wp_error();
		}
		// Free access lives in user meta the site writes; reading it is allowed, writing never.
		$ids = array_merge( $ids, get_users( array( 'meta_key' => '_inzentive_valid_card', 'fields' => 'ID', 'number' => -1 ) ) );
		$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );

		$today  = PeopleController::today();
		$rows   = array();
		$counts = array_fill_keys( self::FILTERS, 0 );
		$errors = 0;
		foreach ( $ids as $id ) {
			$user = get_userdata( $id );
			if ( ! $user ) {
				continue;
			}
			try {
				$view = MembershipView::from_access( SiteApi::get( '/access/' . $id ), $today );
			} catch ( SiteUnavailable $e ) {
				++$errors;
				continue;
			}
			if ( 'none' === $view['status'] ) {
				continue;
			}
			$row = array( 'user_id' => $id, 'name' => PeopleController::name( $user ), 'email' => $user->user_email ) + $view;
			++$counts['all'];
			++$counts[ $view['status'] ];
			if ( $view['expiring'] ) {
				++$counts['expiring'];
			}
			$rows[] = $row;
		}

		$filter = (string) $request['filter'];
		$rows   = array_values( array_filter( $rows, static fn( $r ) => 'all' === $filter || ( 'expiring' === $filter ? $r['expiring'] : $r['status'] === $filter ) ) );
		usort( $rows, static fn( $a, $b ) => strcmp( (string) ( $a['until'] ?? '9999' ), (string) ( $b['until'] ?? '9999' ) ) );

		return rest_ensure_response( array(
			'items'     => $rows,
			'counts'    => $counts,
			'learndash' => Site::learndash_active(),
			'errors'    => $errors,
		) );
	}
}
