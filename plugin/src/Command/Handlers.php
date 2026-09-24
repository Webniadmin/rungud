<?php
declare(strict_types=1);

namespace Rungud\Command;

use Rungud\Capabilities;
use Rungud\Install\Schema;
use Rungud\Mail\Mailer;
use Rungud\Mail\Template;
use Rungud\Rest\PeopleController;
use Rungud\Settings;
use Rungud\Site\SiteApi;
use Rungud\Stripe\StripeReader;
use Rungud\Woo\Attendees;
use Rungud\Woo\RedeemCodes;
use Rungud\Woo\Refunds;

/**
 * Every command kind, runnable from its REST route and again from a manual
 * retry (same params). Parameter validation happens in the controllers;
 * handlers assume clean params and describe the command in both languages.
 */
final class Handlers {

	/** kind → [method, capability] */
	public const KINDS = array(
		'licence_add'      => array( 'licence_add', Capabilities::WRITE ),
		'licence_verdict'  => array( 'licence_verdict', Capabilities::WRITE ),
		'access_grant'     => array( 'access_grant', Capabilities::WRITE ),
		'membership'       => array( 'membership', Capabilities::FINANCE ),
		'redeem_code'      => array( 'redeem_code', Capabilities::WRITE ),
		'attendees'        => array( 'attendees', Capabilities::WRITE ),
		'woo_refund'       => array( 'woo_refund', Capabilities::FINANCE ),
		'document_send'    => array( 'document_send', Capabilities::SEND ),
		'checkout_link'    => array( 'checkout_link', Capabilities::SEND ),
	);

	/** Site routes each membership action needs (website-integration.md §3). */
	public const MEMBERSHIP_ROUTES = array(
		'cancel-at-period-end' => '/membership/cancel-at-period-end',
		'cancel-now'           => '/membership/cancel-now',
		'refund-cancel'        => '/membership/refund-cancel',
	);

	/** @param array<string,mixed> $params @return array<string,mixed>|\WP_Error */
	public static function dispatch( string $kind, array $params, ?string $request_id = null, ?int $retry_of = null ) {
		if ( ! isset( self::KINDS[ $kind ] ) ) {
			return new \WP_Error( 'rungud_unknown_command', 'Unknown command.', array( 'status' => 400 ) );
		}
		[ $method, $cap ] = self::KINDS[ $kind ];
		if ( ! current_user_can( $cap ) ) {
			return new \WP_Error( 'rungud_forbidden', 'Your role cannot do this.', array( 'status' => 403 ) );
		}
		return self::$method( $params, $request_id, $retry_of );
	}

	private static function person( int $user_id ): string {
		$u = get_userdata( $user_id );
		return $u ? PeopleController::name( $u ) : "#{$user_id}";
	}

	private static function programme( string $slug ): string {
		static $titles = null;
		if ( null === $titles ) {
			$titles = array();
			try {
				foreach ( SiteApi::get( '/programs' ) as $p ) {
					$titles[ (string) $p['slug'] ] = html_entity_decode( (string) $p['title'], ENT_QUOTES );
				}
			} catch ( \Throwable $e ) {
				unset( $e );
			}
		}
		return $titles[ $slug ] ?? $slug;
	}

	/* ------------------------------------------------------------ site */

	private static function licence_add( array $p, ?string $rid, ?int $retry ) {
		$who  = self::person( (int) $p['user_id'] );
		$prog = self::programme( (string) $p['program'] );
		$until = $p['valid_until'] ? Fmt::date( $p['valid_until'] ) : null;
		return Commands::run(
			array(
				'kind' => 'licence_add', 'params' => $p, 'entity' => 'licence', 'entity_id' => $p['user_id'] . '/' . $p['program'],
				'summary_en' => "Licence {$p['number']} for {$prog} added for {$who}" . ( $until ? ", valid until {$until}." : ', no expiry.' ),
				'summary_de' => "Lizenz {$p['number']} für {$prog} bei {$who} eingetragen" . ( $until ? ", gültig bis {$until}." : ', ohne Ablauf.' ),
				'request_id' => $rid, 'retry_of' => $retry,
			),
			static fn( string $key ) => SiteApi::call( 'POST', '/licences', array(
				'user_id'      => (int) $p['user_id'],
				'program'      => (string) $p['program'],
				'organisation' => (string) $p['organisation'],
				'number'       => (string) $p['number'],
				'valid_until'  => $p['valid_until'] ?: 0,
			), Capabilities::WRITE, $key )
		);
	}

	private static function licence_verdict( array $p, ?string $rid, ?int $retry ) {
		$who  = self::person( (int) $p['user_id'] );
		$prog = self::programme( (string) $p['program'] );
		$v    = (string) $p['verdict'];
		$reason = trim( (string) ( $p['reason'] ?? '' ) );
		$en = array( 'verify' => 'verified', 'reject' => 'rejected', 'revoke' => 'revoked' )[ $v ];
		$de = array( 'verify' => 'verifiziert', 'reject' => 'abgelehnt', 'revoke' => 'entzogen' )[ $v ];
		return Commands::run(
			array(
				'kind' => 'licence_verdict', 'params' => $p, 'entity' => 'licence', 'entity_id' => $p['user_id'] . '/' . $p['program'],
				'summary_en' => "Licence for {$prog} of {$who} {$en}" . ( $reason ? " — reason: {$reason}" : '' ) . '.',
				'summary_de' => "Lizenz für {$prog} von {$who} {$de}" . ( $reason ? " — Grund: {$reason}" : '' ) . '.',
				'request_id' => $rid, 'retry_of' => $retry,
			),
			static fn( string $key ) => SiteApi::call( 'POST', '/licences/' . (int) $p['user_id'] . '/' . rawurlencode( (string) $p['program'] ) . '/' . $v, array_filter( array(
				'reason'      => $reason,
				'valid_until' => $p['valid_until'] ?? null,
			) ), Capabilities::WRITE, $key )
		);
	}

	private static function access_grant( array $p, ?string $rid, ?int $retry ) {
		$who   = self::person( (int) $p['user_id'] );
		$until = Fmt::date( (string) $p['expires_at'] );
		$scope_en = 'b2c' === $p['scope'] ? 'B2C programmes' : 'all programmes';
		$scope_de = 'b2c' === $p['scope'] ? 'B2C-Programme' : 'alle Programme';
		// End of the chosen day in the site's timezone.
		$end = ( new \DateTimeImmutable( (string) $p['expires_at'], wp_timezone() ) )->setTime( 23, 59, 59 );
		return Commands::run(
			array(
				'kind' => 'access_grant', 'params' => $p, 'entity' => 'access', 'entity_id' => (string) $p['user_id'],
				'summary_en' => "Free access to {$scope_en} for {$who} until {$until}. Reason: {$p['reason']}.",
				'summary_de' => "Kostenloser Zugang zu {$scope_de} für {$who} bis {$until}. Grund: {$p['reason']}.",
				'request_id' => $rid, 'retry_of' => $retry,
			),
			static fn( string $key ) => SiteApi::call( 'POST', '/access/grant', array(
				'user_id'    => (int) $p['user_id'],
				'scope'      => (string) $p['scope'],
				'expires_at' => $end->format( DATE_ATOM ),
				'reason'     => (string) $p['reason'],
			), Capabilities::WRITE, $key )
		);
	}

	private static function membership( array $p, ?string $rid, ?int $retry ) {
		$action = (string) $p['action'];
		$route  = self::MEMBERSHIP_ROUTES[ $action ] ?? null;
		if ( ! $route || ! SiteApi::has_route( $route ) ) {
			return new \WP_Error( 'rungud_site_missing', 'The website does not offer this command yet.', array( 'status' => 501 ) );
		}
		$who = self::person( (int) $p['user_id'] );
		$amount = isset( $p['amount'] ) ? Fmt::money( (string) $p['amount'], (string) ( $p['currency'] ?? 'EUR' ), 'en' ) : '';
		$amount_de = isset( $p['amount'] ) ? Fmt::money( (string) $p['amount'], (string) ( $p['currency'] ?? 'EUR' ), 'de' ) : '';
		$texts = array(
			'cancel-at-period-end' => array( "Membership of {$who} cancelled at the end of the period.", "Mitgliedschaft von {$who} zum Laufzeitende gekündigt." ),
			'cancel-now'           => array( "Membership of {$who} cancelled now.", "Mitgliedschaft von {$who} sofort gekündigt." ),
			'refund-cancel'        => array( "{$amount} refunded to {$who} and membership cancelled now.", "{$amount_de} an {$who} erstattet und Mitgliedschaft sofort gekündigt." ),
		)[ $action ];
		$reason = trim( (string) ( $p['reason'] ?? '' ) );
		return Commands::run(
			array(
				'kind' => 'membership', 'params' => $p, 'entity' => 'membership', 'entity_id' => (string) $p['user_id'],
				'summary_en' => $texts[0] . ( $reason ? " Reason: {$reason}." : '' ),
				'summary_de' => $texts[1] . ( $reason ? " Grund: {$reason}." : '' ),
				'request_id' => $rid, 'retry_of' => $retry,
			),
			static fn( string $key ) => SiteApi::call( 'POST', $route, array_filter( array(
				'user_id' => (int) $p['user_id'],
				'amount'  => $p['amount'] ?? null,
				'reason'  => $reason,
			), static fn( $v ) => null !== $v && '' !== $v ), Capabilities::FINANCE, $key )
		);
	}

	/* ------------------------------------------------------------- woo */

	private static function redeem_code( array $p, ?string $rid, ?int $retry ) {
		$user = get_userdata( (int) $p['user_id'] );
		if ( ! $user ) {
			return new \WP_Error( 'rungud_not_found', 'This person does not exist.', array( 'status' => 404 ) );
		}
		$who = self::person( $user->ID );
		$scope_en = 'b2c' === $p['scope'] ? 'B2C' : 'all licensed programmes';
		$scope_de = 'b2c' === $p['scope'] ? 'B2C' : 'alle lizenzierten Programme';
		return Commands::run(
			array(
				'kind' => 'redeem_code', 'params' => $p, 'entity' => 'redeem_code', 'entity_id' => (string) $user->ID,
				'summary_en' => "Redeem code for {$p['days']} days ({$scope_en}) created for {$who}" . ( $p['restrict'] ? ', only for their e-mail.' : '.' ),
				'summary_de' => "Einlösecode für {$p['days']} Tage ({$scope_de}) für {$who} angelegt" . ( $p['restrict'] ? ', nur für die eigene E-Mail.' : '.' ),
				'request_id' => $rid, 'retry_of' => $retry,
			),
			static fn() => RedeemCodes::create( $user, (int) $p['days'], (string) $p['scope'], (bool) $p['restrict'], (string) ( $p['note'] ?? '' ) )
		);
	}

	private static function attendees( array $p, ?string $rid, ?int $retry ) {
		$order = wc_get_order( (int) $p['order_id'] );
		$item  = $order ? $order->get_item( (int) $p['item_id'] ) : null;
		if ( ! $order || ! $item instanceof \WC_Order_Item_Product ) {
			return new \WP_Error( 'rungud_not_found', 'This order line does not exist.', array( 'status' => 404 ) );
		}
		$before = Attendees::read( $item );
		$count  = count( (array) $p['attendees'] );
		$names  = implode( ', ', array_map( static fn( $a ) => (string) ( $a['name'] ?? '' ), (array) $p['attendees'] ) );
		return Commands::run(
			array(
				'kind' => 'attendees', 'params' => $p, 'entity' => 'order', 'entity_id' => (string) $order->get_id(),
				'summary_en' => "Names for {$count} places on order #{$order->get_order_number()} ({$item->get_name()}): {$names}.",
				'summary_de' => "Namen für {$count} Plätze in Bestellung #{$order->get_order_number()} ({$item->get_name()}): {$names}.",
				'request_id' => $rid, 'retry_of' => $retry, 'before' => $before,
			),
			static function () use ( $item, $p ) {
				$list = Attendees::validate( $item, $p['attendees'] );
				Attendees::write( $item, $list );
				return $list;
			}
		);
	}

	private static function woo_refund( array $p, ?string $rid, ?int $retry ) {
		$order = wc_get_order( (int) $p['order_id'] );
		if ( ! $order || 'shop_order' !== $order->get_type() ) {
			return new \WP_Error( 'rungud_not_found', 'This order does not exist.', array( 'status' => 404 ) );
		}
		$cur  = $order->get_currency();
		$who  = trim( $order->get_formatted_billing_full_name() ) ?: $order->get_billing_email();
		$auto = Refunds::automatic( $order );
		return Commands::run(
			array(
				'kind' => 'woo_refund', 'params' => $p, 'entity' => 'order', 'entity_id' => (string) $order->get_id(),
				'summary_en' => Fmt::money( (string) $p['amount'], $cur, 'en' ) . " refunded on order #{$order->get_order_number()} ({$who})" . ( $auto ? ' to the original payment method' : ', to be sent by bank transfer' ) . ". Reason: {$p['reason']}.",
				'summary_de' => Fmt::money( (string) $p['amount'], $cur, 'de' ) . " für Bestellung #{$order->get_order_number()} ({$who}) erstattet" . ( $auto ? ' auf das ursprüngliche Zahlungsmittel' : ', per Überweisung zu senden' ) . ". Grund: {$p['reason']}.",
				'request_id' => $rid, 'retry_of' => $retry,
			),
			static fn() => Refunds::create( $order, (string) $p['amount'], (string) $p['reason'] )
		);
	}

	/* ------------------------------------------------------------ mail */

	private static function document_send( array $p, ?string $rid, ?int $retry ) {
		$label = 'stripe_invoice' === $p['doc_type'] ? 'Stripe invoice' : 'invoice';
		$label_de = 'stripe_invoice' === $p['doc_type'] ? 'Stripe-Rechnung' : 'Rechnung';
		return Commands::run(
			array(
				'kind' => 'document_send', 'params' => $p, 'entity' => 'document', 'entity_id' => $p['doc_type'] . ':' . $p['doc_ref'],
				'summary_en' => ucfirst( $label ) . " {$p['doc_label']} sent to {$p['to']}.",
				'summary_de' => "{$label_de} {$p['doc_label']} an {$p['to']} gesendet.",
				'request_id' => $rid, 'retry_of' => $retry,
			),
			static function () use ( $p ) {
				$file   = Documents::fetch( (string) $p['doc_type'], (string) $p['doc_ref'] );
				$status = Mailer::send( (string) $p['to'], (string) $p['subject'], Template::render( (string) $p['body'] ), array( $file ) );
				Documents::log_send( $p, $status );
				return array( 'status' => $status, 'to' => $p['to'] );
			}
		);
	}

	private static function checkout_link( array $p, ?string $rid, ?int $retry ) {
		$user = get_userdata( (int) $p['user_id'] );
		$url  = (string) Settings::get( 'pricing_url', '' );
		if ( ! $user || '' === $url ) {
			return new \WP_Error( 'rungud_no_pricing_url', 'The pricing page address is not set (Settings → rungud).', array( 'status' => 409, 'reason' => 'pricing_url_missing' ) );
		}
		$who = self::person( $user->ID );
		return Commands::run(
			array(
				'kind' => 'checkout_link', 'params' => $p, 'entity' => 'membership', 'entity_id' => (string) $user->ID,
				'summary_en' => "Link to buy the plan “{$p['plan_title']}” sent to {$who} ({$user->user_email}).",
				'summary_de' => "Link zum Kauf des Plans „{$p['plan_title']}“ an {$who} ({$user->user_email}) gesendet.",
				'request_id' => $rid, 'retry_of' => $retry,
			),
			static function () use ( $p, $user, $url ) {
				$button = 'de' === $p['lang'] ? 'Plan auswählen' : 'Choose the plan';
				$status = Mailer::send( $user->user_email, (string) $p['subject'], Template::render( (string) $p['body'], $url, $button ) );
				Documents::log_send( array( 'doc_type' => 'checkout_link', 'doc_ref' => (string) $p['plan'], 'to' => $user->user_email, 'lang' => $p['lang'], 'subject' => $p['subject'], 'body' => $p['body'] ), $status );
				return array( 'status' => $status, 'to' => $user->user_email );
			}
		);
	}
}
