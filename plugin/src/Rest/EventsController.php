<?php
declare(strict_types=1);

namespace Rungud\Rest;

use Rungud\Audit\Audit;
use Rungud\Capabilities;
use Rungud\Read\Money;
use Rungud\Site\Site;
use Rungud\Site\SiteApi;
use Rungud\Site\SiteUnavailable;

/**
 * Events are the site's (inzentive_event + a hidden Woo ticket product).
 * Catalogue data from GET inzentive/v1/events; participants from the site's
 * App\event_attendees() — one row per order line, not per seat, until the
 * site stores `_inzentive_attendees` on the line item (phase 3).
 */
final class EventsController {

	public static function register(): void {
		$read = Permission::cap( Capabilities::READ );
		register_rest_route( Routes::NS, '/events', array(
			'methods'             => 'GET',
			'callback'            => array( self::class, 'index' ),
			'permission_callback' => $read,
			'args'                => array(
				'when' => array( 'type' => 'string', 'enum' => array( 'upcoming', 'past', 'all' ), 'default' => 'upcoming' ),
			),
		) );
		register_rest_route( Routes::NS, '/events/(?P<id>\d+)', array(
			'methods'             => 'GET',
			'callback'            => array( self::class, 'show' ),
			'permission_callback' => $read,
		) );
	}

	public static function index( \WP_REST_Request $request ) {
		try {
			$events = SiteApi::get( '/events' );
		} catch ( SiteUnavailable $e ) {
			return $e->to_wp_error();
		}
		$today = wp_date( 'Y-m-d' );
		$when  = (string) $request['when'];
		$out   = array();
		foreach ( $events as $event ) {
			$end = (string) ( ( $event['end'] ?? '' ) ?: ( $event['start'] ?? '' ) );
			if ( 'upcoming' === $when && '' !== $end && $end < $today ) {
				continue;
			}
			if ( 'past' === $when && ( '' === $end || $end >= $today ) ) {
				continue;
			}
			$out[] = self::shape( (array) $event );
		}
		usort( $out, static fn( $a, $b ) => ( 'past' === $when ? -1 : 1 ) * strcmp( (string) $a['start'], (string) $b['start'] ) );
		return rest_ensure_response( array( 'items' => $out ) );
	}

	public static function show( \WP_REST_Request $request ) {
		$id = (int) $request['id'];
		try {
			$events = SiteApi::get( '/events' );
		} catch ( SiteUnavailable $e ) {
			return $e->to_wp_error();
		}
		$event = null;
		foreach ( $events as $candidate ) {
			if ( (int) ( $candidate['id'] ?? 0 ) === $id ) {
				$event = (array) $candidate;
				break;
			}
		}
		if ( ! $event ) {
			return new \WP_Error( 'rungud_not_found', 'This event does not exist on the website.', array( 'status' => 404 ) );
		}

		$participants = array();
		$participants_error = null;
		try {
			foreach ( Site::event_orders( $id ) as $row ) {
				$participants[] = self::participant( (array) $row, $id );
			}
		} catch ( SiteUnavailable $e ) {
			$participants_error = $e->getMessage();
		}

		return rest_ensure_response( array(
			'event'              => self::shape( $event ),
			'participants'       => $participants,
			'participants_error' => $participants_error,
			'history'            => Audit::for_entity( 'event', (string) $id ),
		) );
	}

	/** @param array<string,mixed> $e @return array<string,mixed> */
	private static function shape( array $e ): array {
		$total = isset( $e['seats_total'] ) ? (int) $e['seats_total'] : null;
		$left  = isset( $e['seats_left'] ) ? (int) $e['seats_left'] : null;
		return array(
			'id'             => (int) $e['id'],
			'slug'           => (string) ( $e['slug'] ?? '' ),
			'title'          => html_entity_decode( (string) ( $e['title'] ?? '' ), ENT_QUOTES ),
			'status'         => (string) ( $e['status'] ?? '' ),
			'booking_status' => $e['booking_status'] ?? null,
			'start'          => $e['start'] ?? null,
			'end'            => $e['end'] ?? null,
			'time'           => $e['time'] ?? null,
			'time_end'       => $e['time_end'] ?? null,
			'venue'          => $e['venue'] ?? null,
			'location'       => $e['location'] ?? null,
			'audience'       => $e['audience'] ?? null,
			'price'          => Money::dec( $e['price'] ?? null ),
			'member_price'   => Money::dec( $e['member_price'] ?? null ),
			'currency'       => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'EUR',
			'seats_total'    => $total,
			'seats_left'     => $left,
			'booked'         => null !== $total && null !== $left ? max( 0, $total - $left ) : null,
			'waiting'        => Site::waitlist_count( (string) ( $e['slug'] ?? '' ) ),
			'product_id'     => isset( $e['product_id'] ) ? (int) $e['product_id'] : null,
			'url'            => $e['url'] ?? null,
		);
	}

	/**
	 * @param array<string,mixed> $row App\event_attendees() row
	 * @return array<string,mixed>
	 */
	private static function participant( array $row, int $event_id ): array {
		$order   = function_exists( 'wc_get_order' ) ? wc_get_order( (int) $row['order_id'] ) : null;
		$user_id = $order ? (int) $order->get_customer_id() : 0;
		// The site's row carries the line total without VAT; everywhere else the CMS shows what the
		// customer paid. Take the stored gross of this event's line(s) so both screens agree.
		$gross = null;
		if ( $order ) {
			foreach ( $order->get_items() as $item ) {
				if ( (int) $item->get_meta( '_inzentive_event_id' ) === $event_id ) {
					$gross = ( $gross ?? 0.0 ) + (float) $item->get_total() + (float) $item->get_total_tax();
				}
			}
		}
		$user    = $user_id ? get_userdata( $user_id ) : null;
		return array(
			'order_id'     => (int) $row['order_id'],
			'order_number' => (string) ( $row['order_number'] ?? '' ),
			'name'         => (string) ( $row['name'] ?? '' ),
			'company'      => $order ? ( $order->get_billing_company() ?: null ) : null,
			'email'        => (string) ( $row['email'] ?? '' ),
			'quantity'     => (int) ( $row['quantity'] ?? 1 ),
			'total'        => Money::dec( $gross ?? ( $row['total'] ?? null ) ),
			'currency'     => $order ? $order->get_currency() : null,
			'order_status' => (string) ( $row['status'] ?? '' ),
			'paid'         => (bool) ( $row['paid'] ?? false ),
			'date'         => isset( $row['date'] ) ? substr( (string) $row['date'], 0, 10 ) : null,
			'user_id'      => $user_id ?: null,
			'membership'   => $user ? PeopleController::row( $user )['membership'] : 'none',
			// Not stored on the site yet — shown as "not available", never guessed.
			'health_declaration' => null,
			'certificate'  => null,
		);
	}
}
