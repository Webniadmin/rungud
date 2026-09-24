<?php
declare(strict_types=1);

namespace Rungud\Woo;

use Rungud\Command\Refused;

/**
 * Named participants on a company order: `_inzentive_attendees` =
 * [{name, email}] on the order LINE ITEM, length = quantity
 * (website-integration.md §2). Written through WC_Order_Item, read back the same way.
 */
final class Attendees {

	public const META = '_inzentive_attendees';

	/** @return list<array{name:string, email:string}> */
	public static function read( \WC_Order_Item $item ): array {
		$raw = $item->get_meta( self::META );
		return is_array( $raw ) ? array_values( array_map( static fn( $a ) => array( 'name' => (string) ( $a['name'] ?? '' ), 'email' => (string) ( $a['email'] ?? '' ) ), $raw ) ) : array();
	}

	/**
	 * @param mixed $input
	 * @return list<array{name:string, email:string}>
	 * @throws Refused with a message for the person typing
	 */
	public static function validate( \WC_Order_Item_Product $item, mixed $input ): array {
		$quantity = (int) $item->get_quantity();
		if ( ! is_array( $input ) || count( $input ) !== $quantity ) {
			throw new Refused( "This line has {$quantity} places; enter {$quantity} names." );
		}
		$out = array();
		foreach ( array_values( $input ) as $i => $a ) {
			$name  = trim( sanitize_text_field( (string) ( $a['name'] ?? '' ) ) );
			$email = trim( sanitize_email( (string) ( $a['email'] ?? '' ) ) );
			if ( '' === $name ) {
				throw new Refused( 'Place ' . ( $i + 1 ) . ' has no name.' );
			}
			if ( '' !== (string) ( $a['email'] ?? '' ) && ! is_email( $email ) ) {
				throw new Refused( 'Place ' . ( $i + 1 ) . ': the e-mail address is not valid.' );
			}
			$out[] = array( 'name' => mb_substr( $name, 0, 190 ), 'email' => $email );
		}
		return $out;
	}

	/** @param list<array{name:string, email:string}> $list */
	public static function write( \WC_Order_Item_Product $item, array $list ): void {
		$item->update_meta_data( self::META, $list );
		$item->save();
	}
}
