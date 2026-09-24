<?php
declare(strict_types=1);

namespace Rungud\Stripe;

use Rungud\Config;
use Rungud\Read\Money;

/**
 * Reads membership invoices straight from Stripe (LearnDash does not record
 * renewals). Read-only here; cancel/refund arrive through the site in phase 3.
 */
final class StripeReader {

	/** @var (callable():object)|null test seam: returns a client with ->invoices->all() */
	private static $factory = null;

	public static function set_factory( ?callable $factory ): void {
		self::$factory = $factory;
	}

	public static function configured(): bool {
		return null !== self::$factory || '' !== (string) Config::get( 'STRIPE_SECRET_KEY', '' );
	}

	/**
	 * @return list<array{id:string, number:?string, date:?string, amount:?string, currency:string, status:string, pdf:?string, subscription_id:?string}>
	 * @throws \RuntimeException when Stripe is not configured or answers with an error
	 */
	public static function invoices_for_subscription( string $subscription_id, int $limit = 24 ): array {
		if ( ! self::configured() ) {
			throw new \RuntimeException( 'Stripe is not configured.' );
		}
		$client = self::$factory ? ( self::$factory )() : new \Stripe\StripeClient( (string) Config::get( 'STRIPE_SECRET_KEY' ) );
		$list   = $client->invoices->all( array( 'subscription' => $subscription_id, 'limit' => $limit ) );
		$out    = array();
		foreach ( $list->data as $inv ) {
			$out[] = array(
				'id'              => (string) $inv->id,
				'number'          => $inv->number ?? null,
				'date'            => isset( $inv->created ) ? wp_date( 'Y-m-d', (int) $inv->created ) : null,
				'amount'          => Money::dec( ( (int) ( $inv->total ?? 0 ) ) / 100 ),
				'currency'        => strtoupper( (string) ( $inv->currency ?? 'eur' ) ),
				'status'          => (string) ( $inv->status ?? '' ),
				'pdf'             => $inv->invoice_pdf ?? null,
				'subscription_id' => $subscription_id,
			);
		}
		return $out;
	}
}
