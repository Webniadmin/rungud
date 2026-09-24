<?php
declare(strict_types=1);

namespace Rungud\Read;

/** Amounts leave the API as decimal strings ("583.10") — the frontend only formats them. */
final class Money {

	public static function dec( float|int|string|null $amount ): ?string {
		if ( null === $amount || '' === $amount ) {
			return null;
		}
		return number_format( round( (float) $amount, 2 ), 2, '.', '' );
	}
}
