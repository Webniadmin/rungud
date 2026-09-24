<?php
declare(strict_types=1);

namespace Rungud\Woo;

use Rungud\Command\Refused;
use Rungud\Install\Schema;
use Rungud\Read\Money;

/**
 * Refunds of website orders through WooCommerce (wc_create_refund). When the
 * order's payment gateway can refund (card), the money goes back through it;
 * otherwise (bank transfer) WooCommerce records the refund and Gudrun sends
 * the money in e-banking — the dialog says which one happens.
 */
final class Refunds {

	/** @return array{remaining:string, gateway:string, automatic:bool} */
	public static function options( \WC_Order $order ): array {
		return array(
			'remaining' => Money::dec( $order->get_remaining_refund_amount() ) ?? '0.00',
			'gateway'   => (string) $order->get_payment_method_title(),
			'automatic' => self::automatic( $order ),
		);
	}

	public static function automatic( \WC_Order $order ): bool {
		$gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();
		$gateway  = $gateways[ $order->get_payment_method() ] ?? null;
		return $gateway && $gateway->supports( 'refunds' );
	}

	/**
	 * @return array{refund_id:int, amount:string, automatic:bool}
	 * @throws Refused|\RuntimeException
	 */
	public static function create( \WC_Order $order, string $amount, string $reason ): array {
		$remaining = (float) $order->get_remaining_refund_amount();
		$value     = round( (float) $amount, 2 );
		if ( $value <= 0 ) {
			throw new Refused( 'The refund amount must be more than zero.', 'amount_invalid' );
		}
		if ( $value > round( $remaining, 2 ) + 0.001 ) {
			throw new Refused( 'The refund is larger than what is left to refund on this order (' . Money::dec( $remaining ) . ').', 'refund_too_large' );
		}
		$automatic = self::automatic( $order );
		$refund    = wc_create_refund(
			array(
				'order_id'       => $order->get_id(),
				'amount'         => $value,
				'reason'         => $reason,
				'refund_payment' => $automatic,
				'restock_items'  => false,
			)
		);
		if ( is_wp_error( $refund ) ) {
			throw new \RuntimeException( $refund->get_error_message() );
		}

		global $wpdb;
		$wpdb->insert(
			Schema::table( 'refunds' ),
			array(
				'status'      => 'executed',
				'method'      => $automatic ? 'woo' : 'bank',
				'amount'      => Money::dec( $value ),
				'currency'    => $order->get_currency(),
				'woo_order_id' => $order->get_id(),
				'reference'   => 'woo-refund-' . $refund->get_id(),
				'reason'      => $reason,
				'prepared_by' => get_current_user_id(),
				'prepared_at' => gmdate( 'Y-m-d H:i:s' ),
				'executed_by' => get_current_user_id(),
				'executed_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);

		return array( 'refund_id' => $refund->get_id(), 'amount' => Money::dec( $value ), 'automatic' => $automatic );
	}
}
