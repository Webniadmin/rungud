<?php
declare(strict_types=1);

namespace Rungud\Rest;

use Rungud\Capabilities;
use Rungud\Install\Schema;
use Rungud\Read\MembershipView;
use Rungud\Read\OrderView;
use Rungud\Site\Site;
use Rungud\Site\SiteApi;
use Rungud\Site\SiteUnavailable;
use Rungud\Stripe\StripeReader;

/**
 * People are WordPress users, read live. The CMS never creates accounts.
 */
final class PeopleController {

	public const PER_PAGE = 50;

	public static function register(): void {
		$read = Permission::cap( Capabilities::READ );
		register_rest_route( Routes::NS, '/people', array(
			'methods'             => 'GET',
			'callback'            => array( self::class, 'index' ),
			'permission_callback' => $read,
			'args'                => array(
				'search' => array( 'type' => 'string', 'default' => '' ),
				'page'   => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1 ),
			),
		) );
		register_rest_route( Routes::NS, '/people/(?P<id>\d+)', array(
			'methods'             => 'GET',
			'callback'            => array( self::class, 'show' ),
			'permission_callback' => $read,
		) );
		register_rest_route( Routes::NS, '/people/(?P<id>\d+)/access', array(
			'methods'             => 'GET',
			'callback'            => array( self::class, 'access' ),
			'permission_callback' => $read,
		) );
		register_rest_route( Routes::NS, '/people/(?P<id>\d+)/documents', array(
			'methods'             => 'GET',
			'callback'            => array( self::class, 'documents' ),
			'permission_callback' => $read,
		) );
	}

	public static function index( \WP_REST_Request $request ) {
		$search = trim( (string) $request['search'] );
		$args   = array(
			'number'  => self::PER_PAGE,
			'paged'   => (int) $request['page'],
			'orderby' => 'display_name',
			'order'   => 'ASC',
			'fields'  => 'all',
			'count_total' => true,
		);
		if ( '' !== $search ) {
			$args['search']         = '*' . $search . '*';
			$args['search_columns'] = array( 'user_email', 'display_name', 'user_login', 'user_nicename' );
		}
		$query = new \WP_User_Query( $args );
		$items = array();
		foreach ( (array) $query->get_results() as $user ) {
			$items[] = self::row( $user );
		}
		$total = (int) $query->get_total();
		return rest_ensure_response( array(
			'items' => $items,
			'total' => $total,
			'page'  => (int) $request['page'],
			'pages' => (int) max( 1, ceil( $total / self::PER_PAGE ) ),
		) );
	}

	/** Cheap per-row state for lists: no /access call per row. @return array<string,mixed> */
	public static function row( \WP_User $user ): array {
		$state = 'none';
		if ( Site::membership_active( $user->ID ) ) {
			$state = 'active';
		} elseif ( Site::has( 'valid_card_is_active' ) && Site::call( 'valid_card_is_active', $user->ID ) ) {
			$state = 'free_access';
		}
		return array(
			'id'         => $user->ID,
			'name'       => self::name( $user ),
			'email'      => $user->user_email,
			'membership' => $state,
			'plan'       => 'active' === $state ? Site::current_plan( $user->ID ) : null,
			'orders'     => function_exists( 'wc_get_orders' ) ? count( wc_get_orders( array( 'customer_id' => $user->ID, 'return' => 'ids', 'limit' => -1, 'type' => 'shop_order' ) ) ) : 0,
		);
	}

	public static function show( \WP_REST_Request $request ) {
		$user = get_userdata( (int) $request['id'] );
		if ( ! $user ) {
			return new \WP_Error( 'rungud_not_found', 'This person does not exist.', array( 'status' => 404 ) );
		}

		$access = null;
		$site_error = null;
		try {
			$access = SiteApi::get( '/access/' . $user->ID );
		} catch ( SiteUnavailable $e ) {
			$site_error = $e->getMessage();
		}

		$customer = class_exists( 'WC_Customer' ) ? new \WC_Customer( $user->ID ) : null;
		$orders   = function_exists( 'wc_get_orders' )
			? array_map( array( OrderView::class, 'summary' ), wc_get_orders( array( 'customer_id' => $user->ID, 'limit' => 50, 'orderby' => 'date', 'order' => 'DESC', 'type' => 'shop_order' ) ) )
			: array();

		return rest_ensure_response( array(
			'id'          => $user->ID,
			'name'        => self::name( $user ),
			'email'       => $user->user_email,
			'registered'  => substr( (string) $user->user_registered, 0, 10 ),
			'professional' => (bool) ( $access['user']['professional'] ?? false ),
			'address'     => $customer ? array_filter( array(
				'company'     => $customer->get_billing_company(),
				'street'      => trim( $customer->get_billing_address_1() . ' ' . $customer->get_billing_address_2() ),
				'postal_code' => $customer->get_billing_postcode(),
				'city'        => $customer->get_billing_city(),
				'country'     => $customer->get_billing_country(),
				'phone'       => $customer->get_billing_phone(),
			) ) : array(),
			'membership'  => $access ? MembershipView::from_access( $access, self::today() ) : null,
			'licences'    => $access ? array_values( (array) ( $access['licences'] ?? array() ) ) : array(),
			'orders'      => $orders,
			'certificates' => self::own_rows( 'certificates', $user->ID, 'completed_on' ),
			'education'   => self::own_rows( 'education_history', $user->ID, 'completed_on' ),
			'site_error'  => $site_error,
		) );
	}

	public static function access( \WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( ! get_userdata( $id ) ) {
			return new \WP_Error( 'rungud_not_found', 'This person does not exist.', array( 'status' => 404 ) );
		}
		try {
			$access = SiteApi::get( '/access/' . $id );
		} catch ( SiteUnavailable $e ) {
			return $e->to_wp_error();
		}
		return rest_ensure_response( array(
			'membership' => MembershipView::from_access( $access, self::today() ),
			'site'       => $access,
			'learndash'  => Site::learndash_active(),
		) );
	}

	/** Woo orders, Stripe invoices and CMS documents for one person. */
	public static function documents( \WP_REST_Request $request ) {
		$id   = (int) $request['id'];
		$user = get_userdata( $id );
		if ( ! $user ) {
			return new \WP_Error( 'rungud_not_found', 'This person does not exist.', array( 'status' => 404 ) );
		}
		$woo = function_exists( 'wc_get_orders' )
			? array_map( array( OrderView::class, 'summary' ), wc_get_orders( array( 'customer_id' => $id, 'limit' => 100, 'orderby' => 'date', 'order' => 'DESC', 'type' => 'shop_order' ) ) )
			: array();

		$stripe       = array();
		$stripe_state = 'ok';
		try {
			$access = SiteApi::get( '/access/' . $id );
			$subs   = array_filter( array_column( (array) ( $access['membership']['subscriptions'] ?? array() ), 'stripe_subscription_id' ) );
			if ( $subs && ! StripeReader::configured() ) {
				$stripe_state = 'not_configured';
			}
			foreach ( $subs as $sub ) {
				if ( 'ok' !== $stripe_state ) {
					break;
				}
				$stripe = array_merge( $stripe, StripeReader::invoices_for_subscription( (string) $sub ) );
			}
		} catch ( SiteUnavailable $e ) {
			$stripe_state = 'site_unavailable';
		} catch ( \Throwable $e ) {
			$stripe_state = 'error';
		}

		return rest_ensure_response( array(
			'woo'          => $woo,
			'stripe'       => $stripe,
			'stripe_state' => $stripe_state,
			'cms'          => self::own_rows( 'invoices', $id, 'created_at' ),
			'certificates' => self::own_rows( 'certificates', $id, 'completed_on' ),
		) );
	}

	public static function name( \WP_User $user ): string {
		$full = trim( $user->first_name . ' ' . $user->last_name );
		return '' !== $full ? $full : $user->display_name;
	}

	public static function today(): \DateTimeImmutable {
		return new \DateTimeImmutable( wp_date( 'Y-m-d' ) );
	}

	/** @return list<array<string,mixed>> */
	private static function own_rows( string $table, int $user_id, string $order_by ): array {
		global $wpdb;
		$t = Schema::table( $table );
		return (array) $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$t} WHERE user_id = %d ORDER BY {$order_by} DESC LIMIT 100", $user_id ), // phpcs:ignore WordPress.DB.PreparedSQL
			ARRAY_A
		);
	}
}
