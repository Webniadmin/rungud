<?php
declare(strict_types=1);

namespace Rungud\Rest;

use Rungud\Capabilities;
use Rungud\Read\Money;
use Rungud\Read\OrderView;

/** WooCommerce orders, read through Woo's API. Refunds arrive in phase 3. */
final class OrdersController {

	public const PER_PAGE = 50;

	public static function register(): void {
		$read = Permission::cap( Capabilities::READ );
		register_rest_route( Routes::NS, '/orders', array(
			'methods'             => 'GET',
			'callback'            => array( self::class, 'index' ),
			'permission_callback' => $read,
			'args'                => array(
				'search' => array( 'type' => 'string', 'default' => '' ),
				'status' => array( 'type' => 'string', 'default' => 'any' ),
				'page'   => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1 ),
			),
		) );
		register_rest_route( Routes::NS, '/orders/(?P<id>\d+)', array(
			'methods'             => 'GET',
			'callback'            => array( self::class, 'show' ),
			'permission_callback' => $read,
		) );
	}

	public static function index( \WP_REST_Request $request ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return new \WP_Error( 'rungud_no_woocommerce', 'WooCommerce is not active.', array( 'status' => 503 ) );
		}
		$page   = (int) $request['page'];
		$search = trim( (string) $request['search'] );
		$status = sanitize_key( (string) $request['status'] );
		$args   = array( 'type' => 'shop_order', 'orderby' => 'date', 'order' => 'DESC', 'status' => 'any' === $status ? array_keys( wc_get_order_statuses() ) : array( 'wc-' . $status ) );

		if ( '' !== $search ) {
			$ids   = array_map( 'intval', (array) wc_order_search( $search ) );
			$ids   = array_values( array_filter( $ids, static fn( $id ) => ( $o = wc_get_order( $id ) ) && 'shop_order' === $o->get_type() && in_array( 'wc-' . $o->get_status(), (array) $args['status'], true ) ) );
			rsort( $ids );
			$total = count( $ids );
			$slice = array_slice( $ids, ( $page - 1 ) * self::PER_PAGE, self::PER_PAGE );
			$items = array_map( static fn( $id ) => OrderView::summary( wc_get_order( $id ) ), $slice );
		} else {
			$result = wc_get_orders( $args + array( 'limit' => self::PER_PAGE, 'page' => $page, 'paginate' => true ) );
			$total  = (int) $result->total;
			$items  = array_map( array( OrderView::class, 'summary' ), $result->orders );
		}

		return rest_ensure_response( array(
			'items' => $items,
			'total' => $total,
			'page'  => $page,
			'pages' => (int) max( 1, ceil( $total / self::PER_PAGE ) ),
		) );
	}

	public static function show( \WP_REST_Request $request ) {
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( (int) $request['id'] ) : null;
		if ( ! $order || 'shop_order' !== $order->get_type() ) {
			return new \WP_Error( 'rungud_not_found', 'This order does not exist.', array( 'status' => 404 ) );
		}
		$data            = OrderView::summary( $order );
		$data['refunds'] = array_map(
			static fn( \WC_Order_Refund $r ) => array(
				'id'     => $r->get_id(),
				'date'   => $r->get_date_created() ? $r->get_date_created()->date( 'Y-m-d' ) : null,
				'amount' => Money::dec( $r->get_amount() ),
				'reason' => $r->get_reason(),
			),
			$order->get_refunds()
		);
		return rest_ensure_response( $data );
	}
}
