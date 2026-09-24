<?php
declare(strict_types=1);

namespace Rungud\Rest;

use Rungud\Audit\Audit;
use Rungud\Capabilities;
use Rungud\Command\Documents;
use Rungud\Command\Handlers;
use Rungud\Mail\Mailer;
use Rungud\Read\Money;
use Rungud\Settings;
use Rungud\Site\SiteApi;
use Rungud\Site\SiteUnavailable;
use Rungud\Stripe\StripeReader;
use Rungud\Woo\RedeemCodes;
use Rungud\Woo\Refunds;

/**
 * Phase 3: the commands Gudrun sends (website-integration.md §2). Every POST
 * carries a request_id (one per dialog), validates first — a malformed
 * request is not a command and leaves no audit row — then runs through
 * Handlers → Commands.
 */
final class CommandsController {

	public static function register(): void {
		$read  = Permission::cap( Capabilities::READ );
		$write = Permission::cap( Capabilities::WRITE );
		$fin   = Permission::cap( Capabilities::FINANCE );
		$send  = Permission::cap( Capabilities::SEND );
		$rid   = array( 'request_id' => array( 'type' => 'string', 'required' => true ) );

		register_rest_route( Routes::NS, '/site/capabilities', array( 'methods' => 'GET', 'callback' => array( self::class, 'capabilities' ), 'permission_callback' => $read ) );
		register_rest_route( Routes::NS, '/programs', array( 'methods' => 'GET', 'callback' => array( self::class, 'programs' ), 'permission_callback' => $read ) );
		register_rest_route( Routes::NS, '/plans', array( 'methods' => 'GET', 'callback' => array( self::class, 'plans' ), 'permission_callback' => $read ) );

		register_rest_route( Routes::NS, '/people/(?P<id>\d+)/licences', array(
			'methods' => 'POST', 'callback' => array( self::class, 'licence_add' ), 'permission_callback' => $write,
			'args' => $rid + array(
				'program'      => array( 'type' => 'string', 'required' => true ),
				'organisation' => array( 'type' => 'string', 'default' => 'inZENtive' ),
				'number'       => array( 'type' => 'string', 'required' => true ),
				'valid_until'  => array( 'type' => array( 'string', 'null' ), 'default' => null ),
			),
		) );
		register_rest_route( Routes::NS, '/people/(?P<id>\d+)/licences/(?P<program>[a-z0-9-]+)/(?P<verdict>verify|reject|revoke)', array(
			'methods' => 'POST', 'callback' => array( self::class, 'licence_verdict' ), 'permission_callback' => $write,
			'args' => $rid + array(
				'reason'      => array( 'type' => 'string', 'default' => '' ),
				'valid_until' => array( 'type' => array( 'string', 'null' ), 'default' => null ),
			),
		) );
		register_rest_route( Routes::NS, '/people/(?P<id>\d+)/access/grant', array(
			'methods' => 'POST', 'callback' => array( self::class, 'grant' ), 'permission_callback' => $write,
			'args' => $rid + array(
				'scope'      => array( 'type' => 'string', 'enum' => array( 'b2c', 'all' ), 'required' => true ),
				'expires_at' => array( 'type' => 'string', 'required' => true ),
				'reason'     => array( 'type' => 'string', 'required' => true ),
			),
		) );
		register_rest_route( Routes::NS, '/people/(?P<id>\d+)/membership/(?P<action>cancel-at-period-end|cancel-now|refund-cancel)', array(
			'methods' => 'POST', 'callback' => array( self::class, 'membership' ), 'permission_callback' => $fin,
			'args' => $rid + array(
				'amount' => array( 'type' => array( 'string', 'null' ), 'default' => null ),
				'reason' => array( 'type' => 'string', 'default' => '' ),
			),
		) );
		register_rest_route( Routes::NS, '/people/(?P<id>\d+)/checkout-link', array(
			array( 'methods' => 'GET', 'callback' => array( self::class, 'checkout_preview' ), 'permission_callback' => $send, 'args' => array( 'plan' => array( 'type' => 'string', 'required' => true ), 'lang' => array( 'type' => 'string', 'enum' => array( 'en', 'de' ), 'default' => 'de' ) ) ),
			array(
				'methods' => 'POST', 'callback' => array( self::class, 'checkout_link' ), 'permission_callback' => $send,
				'args' => $rid + array(
					'plan'    => array( 'type' => 'string', 'required' => true ),
					'lang'    => array( 'type' => 'string', 'enum' => array( 'en', 'de' ), 'required' => true ),
					'subject' => array( 'type' => 'string', 'required' => true ),
					'body'    => array( 'type' => 'string', 'required' => true ),
				),
			),
		) );
		register_rest_route( Routes::NS, '/people/(?P<id>\d+)/redeem-codes', array(
			array( 'methods' => 'GET', 'callback' => array( self::class, 'redeem_list' ), 'permission_callback' => $read ),
			array(
				'methods' => 'POST', 'callback' => array( self::class, 'redeem_create' ), 'permission_callback' => $write,
				'args' => $rid + array(
					'days'     => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 365, 'required' => true ),
					'scope'    => array( 'type' => 'string', 'enum' => array( 'b2c', 'all' ), 'required' => true ),
					'restrict' => array( 'type' => 'boolean', 'default' => true ),
					'note'     => array( 'type' => 'string', 'default' => '' ),
				),
			),
		) );
		register_rest_route( Routes::NS, '/orders/(?P<id>\d+)/attendees', array(
			'methods' => 'POST', 'callback' => array( self::class, 'attendees' ), 'permission_callback' => $write,
			'args' => $rid + array(
				'item_id'   => array( 'type' => 'integer', 'required' => true ),
				'attendees' => array( 'type' => 'array', 'required' => true ),
			),
		) );
		register_rest_route( Routes::NS, '/orders/(?P<id>\d+)/refund', array(
			'methods' => 'POST', 'callback' => array( self::class, 'refund' ), 'permission_callback' => $fin,
			'args' => $rid + array(
				'amount' => array( 'type' => 'string', 'required' => true ),
				'reason' => array( 'type' => 'string', 'required' => true ),
			),
		) );
		register_rest_route( Routes::NS, '/documents/cover', array(
			'methods' => 'GET', 'callback' => array( self::class, 'cover' ), 'permission_callback' => $send,
			'args' => array(
				'doc_type' => array( 'type' => 'string', 'enum' => array( 'stripe_invoice', 'woo_invoice' ), 'required' => true ),
				'label'    => array( 'type' => 'string', 'required' => true ),
				'lang'     => array( 'type' => 'string', 'enum' => array( 'en', 'de' ), 'required' => true ),
				'name'     => array( 'type' => 'string', 'default' => '' ),
			),
		) );
		register_rest_route( Routes::NS, '/documents/send', array(
			'methods' => 'POST', 'callback' => array( self::class, 'send' ), 'permission_callback' => $send,
			'args' => $rid + array(
				'doc_type'  => array( 'type' => 'string', 'enum' => array( 'stripe_invoice', 'woo_invoice' ), 'required' => true ),
				'doc_ref'   => array( 'type' => 'string', 'required' => true ),
				'doc_label' => array( 'type' => 'string', 'required' => true ),
				'to'        => array( 'type' => 'string', 'required' => true ),
				'lang'      => array( 'type' => 'string', 'enum' => array( 'en', 'de' ), 'required' => true ),
				'subject'   => array( 'type' => 'string', 'required' => true ),
				'body'      => array( 'type' => 'string', 'required' => true ),
			),
		) );
		register_rest_route( Routes::NS, '/audit/(?P<id>\d+)/retry', array(
			'methods' => 'POST', 'callback' => array( self::class, 'retry' ), 'permission_callback' => $write,
			'args' => $rid,
		) );
	}

	/* ------------------------------------------------------------ reads */

	public static function capabilities() {
		$membership = array();
		foreach ( Handlers::MEMBERSHIP_ROUTES as $action => $route ) {
			$membership[ $action ] = SiteApi::has_route( $route );
		}
		return rest_ensure_response( array(
			'membership'  => $membership,
			'licences'    => SiteApi::has_route( '/licences' ),
			'grant'       => SiteApi::has_route( '/access/grant' ),
			'woo_pdf'     => false,
			'stripe'      => StripeReader::configured(),
			'mail_mode'   => Mailer::mode(),
			'pricing_url' => '' !== (string) Settings::get( 'pricing_url', '' ),
		) );
	}

	/** Programmes from the site, plus the next free licence number per prefix. */
	public static function programs() {
		try {
			$programs = SiteApi::get( '/programs' );
		} catch ( SiteUnavailable $e ) {
			return $e->to_wp_error();
		}
		$out = array();
		foreach ( $programs as $p ) {
			$prefix = strtoupper( (string) ( $p['licence_prefix'] ?? '' ) );
			$out[]  = array(
				'slug'          => (string) $p['slug'],
				'title'         => html_entity_decode( (string) $p['title'], ENT_QUOTES ),
				'audience'      => (string) ( $p['audience'] ?? 'b2c' ),
				'takes_licence' => (bool) ( $p['takes_licence'] ?? false ),
				'licence_prefix' => $prefix ?: null,
				'next_number'   => $prefix ? LicenceNumbers::next( $prefix, (array) ( $p['certification_numbers'] ?? array() ) ) : null,
			);
		}
		return rest_ensure_response( array( 'items' => $out ) );
	}

	public static function plans() {
		try {
			$plans = SiteApi::get( '/plans' );
		} catch ( SiteUnavailable $e ) {
			return $e->to_wp_error();
		}
		return rest_ensure_response( array(
			'items' => array_map( static fn( $p ) => array(
				'slug'         => (string) $p['slug'],
				'title'        => html_entity_decode( (string) $p['title'], ENT_QUOTES ),
				'audience'     => (string) ( $p['audience'] ?? 'b2c' ),
				'professional' => (bool) ( $p['professional'] ?? false ),
				'price'        => Money::dec( $p['price'] ?? null ),
				'currency'     => (string) ( $p['currency'] ?? 'EUR' ),
				'cycle'        => $p['cycle'] ?? null,
			), $plans ),
		) );
	}

	public static function redeem_list( \WP_REST_Request $r ) {
		return rest_ensure_response( array( 'items' => RedeemCodes::for_user( (int) $r['id'] ) ) );
	}

	public static function cover( \WP_REST_Request $r ) {
		return rest_ensure_response( Documents::cover( (string) $r['doc_type'], (string) $r['lang'], (string) $r['name'], (string) $r['label'] ) );
	}

	public static function checkout_preview( \WP_REST_Request $r ) {
		$user = get_userdata( (int) $r['id'] );
		if ( ! $user ) {
			return self::not_found();
		}
		$plan = self::plan( (string) $r['plan'] );
		if ( is_wp_error( $plan ) ) {
			return $plan;
		}
		return rest_ensure_response( Documents::cover( 'checkout_link', (string) $r['lang'], PeopleController::name( $user ), (string) $plan['title'] ) + array( 'to' => $user->user_email, 'url' => (string) Settings::get( 'pricing_url', '' ) ) );
	}

	/* --------------------------------------------------------- commands */

	public static function licence_add( \WP_REST_Request $r ) {
		if ( ! get_userdata( (int) $r['id'] ) ) {
			return self::not_found();
		}
		$number = strtoupper( trim( (string) $r['number'] ) );
		$until  = self::date_or_null( $r['valid_until'] );
		if ( '' === $number ) {
			return self::invalid( 'A licence needs a number.' );
		}
		if ( is_wp_error( $until ) ) {
			return $until;
		}
		return self::out( Handlers::dispatch( 'licence_add', array(
			'user_id' => (int) $r['id'], 'program' => sanitize_title( (string) $r['program'] ),
			'organisation' => sanitize_text_field( (string) $r['organisation'] ) ?: 'inZENtive',
			'number' => $number, 'valid_until' => $until,
		), (string) $r['request_id'] ) );
	}

	public static function licence_verdict( \WP_REST_Request $r ) {
		if ( ! get_userdata( (int) $r['id'] ) ) {
			return self::not_found();
		}
		$verdict = (string) $r['verdict'];
		$reason  = sanitize_textarea_field( (string) $r['reason'] );
		if ( 'verify' !== $verdict && '' === trim( $reason ) ) {
			return self::invalid( 'Give a reason — the person reads it on the website.' );
		}
		$until = self::date_or_null( $r['valid_until'] );
		if ( is_wp_error( $until ) ) {
			return $until;
		}
		return self::out( Handlers::dispatch( 'licence_verdict', array(
			'user_id' => (int) $r['id'], 'program' => (string) $r['program'], 'verdict' => $verdict, 'reason' => $reason, 'valid_until' => $until,
		), (string) $r['request_id'] ) );
	}

	public static function grant( \WP_REST_Request $r ) {
		if ( ! get_userdata( (int) $r['id'] ) ) {
			return self::not_found();
		}
		$until = self::date_or_null( $r['expires_at'] );
		if ( is_wp_error( $until ) || null === $until ) {
			return self::invalid( 'Choose the last day of free access.' );
		}
		if ( $until <= wp_date( 'Y-m-d' ) ) {
			return self::invalid( 'The last day must be after today.' );
		}
		$reason = sanitize_textarea_field( (string) $r['reason'] );
		if ( '' === trim( $reason ) ) {
			return self::invalid( 'Give a reason.' );
		}
		return self::out( Handlers::dispatch( 'access_grant', array(
			'user_id' => (int) $r['id'], 'scope' => (string) $r['scope'], 'expires_at' => $until, 'reason' => $reason,
		), (string) $r['request_id'] ) );
	}

	public static function membership( \WP_REST_Request $r ) {
		if ( ! get_userdata( (int) $r['id'] ) ) {
			return self::not_found();
		}
		$action = (string) $r['action'];
		$amount = null;
		if ( 'refund-cancel' === $action ) {
			$amount = self::amount( $r['amount'] );
			if ( is_wp_error( $amount ) ) {
				return $amount;
			}
		}
		return self::out( Handlers::dispatch( 'membership', array(
			'user_id' => (int) $r['id'], 'action' => $action, 'amount' => $amount, 'reason' => sanitize_textarea_field( (string) $r['reason'] ),
		), (string) $r['request_id'] ) );
	}

	public static function checkout_link( \WP_REST_Request $r ) {
		$user = get_userdata( (int) $r['id'] );
		if ( ! $user ) {
			return self::not_found();
		}
		$plan = self::plan( (string) $r['plan'] );
		if ( is_wp_error( $plan ) ) {
			return $plan;
		}
		// The site refuses a B2C plan on a professional account (website-integration.md §2).
		$professional = false;
		try {
			$professional = (bool) ( SiteApi::get( '/access/' . $user->ID )['user']['professional'] ?? false );
		} catch ( SiteUnavailable $e ) {
			unset( $e );
		}
		if ( $professional && ! $plan['professional'] ) {
			return self::invalid( 'This is a professional account; the website only sells it an instructor plan.' );
		}
		return self::out( Handlers::dispatch( 'checkout_link', array(
			'user_id' => $user->ID, 'plan' => $plan['slug'], 'plan_title' => $plan['title'], 'lang' => (string) $r['lang'],
			'subject' => sanitize_text_field( (string) $r['subject'] ), 'body' => sanitize_textarea_field( (string) $r['body'] ),
		), (string) $r['request_id'] ) );
	}

	public static function redeem_create( \WP_REST_Request $r ) {
		if ( ! get_userdata( (int) $r['id'] ) ) {
			return self::not_found();
		}
		return self::out( Handlers::dispatch( 'redeem_code', array(
			'user_id' => (int) $r['id'], 'days' => (int) $r['days'], 'scope' => (string) $r['scope'],
			'restrict' => (bool) $r['restrict'], 'note' => sanitize_text_field( (string) $r['note'] ),
		), (string) $r['request_id'] ) );
	}

	public static function attendees( \WP_REST_Request $r ) {
		$list = array_map(
			static fn( $a ) => array( 'name' => (string) ( is_array( $a ) ? ( $a['name'] ?? '' ) : '' ), 'email' => (string) ( is_array( $a ) ? ( $a['email'] ?? '' ) : '' ) ),
			(array) $r['attendees']
		);
		return self::out( Handlers::dispatch( 'attendees', array(
			'order_id' => (int) $r['id'], 'item_id' => (int) $r['item_id'], 'attendees' => $list,
		), (string) $r['request_id'] ) );
	}

	public static function refund( \WP_REST_Request $r ) {
		$amount = self::amount( $r['amount'] );
		if ( is_wp_error( $amount ) ) {
			return $amount;
		}
		$reason = sanitize_textarea_field( (string) $r['reason'] );
		if ( '' === trim( $reason ) ) {
			return self::invalid( 'Give a reason for the refund.' );
		}
		return self::out( Handlers::dispatch( 'woo_refund', array( 'order_id' => (int) $r['id'], 'amount' => $amount, 'reason' => $reason ), (string) $r['request_id'] ) );
	}

	public static function send( \WP_REST_Request $r ) {
		$to = sanitize_email( (string) $r['to'] );
		if ( ! is_email( $to ) ) {
			return self::invalid( 'Enter a valid e-mail address.' );
		}
		return self::out( Handlers::dispatch( 'document_send', array(
			'doc_type' => (string) $r['doc_type'], 'doc_ref' => sanitize_text_field( (string) $r['doc_ref'] ),
			'doc_label' => sanitize_text_field( (string) $r['doc_label'] ), 'to' => $to, 'lang' => (string) $r['lang'],
			'subject' => sanitize_text_field( (string) $r['subject'] ), 'body' => sanitize_textarea_field( (string) $r['body'] ),
		), (string) $r['request_id'] ) );
	}

	/** Runs a failed command again with the same parameters; the new attempt points at the failure. */
	public static function retry( \WP_REST_Request $r ) {
		$row = Audit::find( (int) $r['id'] );
		if ( ! $row || Audit::FAILED !== $row['status'] || empty( $row['command']['kind'] ) ) {
			return new \WP_Error( 'rungud_not_retryable', 'This entry cannot be retried.', array( 'status' => 409 ) );
		}
		$original = $row['retry_of'] ? (int) $row['retry_of'] : (int) $row['id'];
		return self::out( Handlers::dispatch( (string) $row['command']['kind'], (array) $row['command']['params'], (string) $r['request_id'], $original ) );
	}

	/* ---------------------------------------------------------- helpers */

	/** @param array<string,mixed>|\WP_Error $result */
	private static function out( $result ) {
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	private static function not_found(): \WP_Error {
		return new \WP_Error( 'rungud_not_found', 'This person does not exist.', array( 'status' => 404 ) );
	}

	private static function invalid( string $message ): \WP_Error {
		return new \WP_Error( 'rungud_invalid', $message, array( 'status' => 400 ) );
	}

	/** @return string|null|\WP_Error Y-m-d */
	private static function date_or_null( mixed $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}
		if ( ! is_string( $value ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) || ! checkdate( (int) substr( $value, 5, 2 ), (int) substr( $value, 8, 2 ), (int) substr( $value, 0, 4 ) ) ) {
			return self::invalid( 'Not a valid date.' );
		}
		return $value;
	}

	/** @return string|\WP_Error */
	private static function amount( mixed $value ) {
		$s = str_replace( ',', '.', trim( (string) $value ) );
		if ( ! preg_match( '/^\d{1,7}(\.\d{1,2})?$/', $s ) || (float) $s <= 0 ) {
			return self::invalid( 'Enter an amount above zero, e.g. 120.00.' );
		}
		return Money::dec( $s );
	}

	/** @return array<string,mixed>|\WP_Error */
	private static function plan( string $slug ) {
		try {
			foreach ( SiteApi::get( '/plans' ) as $p ) {
				if ( (string) $p['slug'] === $slug ) {
					return array( 'slug' => $slug, 'title' => html_entity_decode( (string) $p['title'], ENT_QUOTES ), 'professional' => (bool) ( $p['professional'] ?? false ) );
				}
			}
		} catch ( SiteUnavailable $e ) {
			return $e->to_wp_error();
		}
		return self::invalid( 'Unknown plan.' );
	}
}
