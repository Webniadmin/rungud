<?php
declare(strict_types=1);

namespace Rungud\Read;

/**
 * WooCommerce order → API shape. Reads through Woo's own objects only.
 * Invoice PDF: none installed on the site yet (PROMPT §8 #1) — `invoice_pdf`
 * stays null until the PDF plugin exposes a URL.
 */
final class OrderView {

	/** @return array<string,mixed> */
	public static function summary( \WC_Order $order ): array {
		$items = array();
		foreach ( $order->get_items() as $item_id => $item ) {
			/** @var \WC_Order_Item_Product $item */
			$event_id = (int) $item->get_meta( '_inzentive_event_id' );
			$items[]  = array(
				'id'         => (int) $item_id,
				'name'       => $item->get_name(),
				'quantity'   => (int) $item->get_quantity(),
				'total'      => Money::dec( (float) $item->get_total() + (float) $item->get_total_tax() ),
				'product_id' => (int) $item->get_product_id(),
				'event_id'   => $event_id ?: null,
			);
		}
		$created = $order->get_date_created();
		return array(
			'id'          => $order->get_id(),
			'number'      => (string) $order->get_order_number(),
			'date'        => $created ? $created->date( 'Y-m-d' ) : null,
			'status'      => $order->get_status(),
			'paid'        => $order->is_paid(),
			'customer'    => array(
				'user_id' => $order->get_customer_id() ?: null,
				'name'    => trim( $order->get_formatted_billing_full_name() ) ?: null,
				'company' => $order->get_billing_company() ?: null,
				'email'   => $order->get_billing_email() ?: null,
			),
			'items'       => $items,
			'total'       => Money::dec( $order->get_total() ),
			'refunded'    => Money::dec( $order->get_total_refunded() ),
			'currency'    => $order->get_currency(),
			'invoice_pdf' => null,
		);
	}
}
