<?php
declare(strict_types=1);

namespace Rungud\Command;

/** Formatting for audit summaries and e-mails (CLAUDE.md rule 8) — the PHP twin of web/src/lib/format.ts. */
final class Fmt {

	public static function date( ?string $ymd ): string {
		if ( ! $ymd || ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})/', $ymd, $m ) ) {
			return '—';
		}
		return "{$m[3]}.{$m[2]}.{$m[1]}";
	}

	public static function money( string|float|null $amount, string $currency, string $lang ): string {
		if ( null === $amount || '' === $amount ) {
			return '—';
		}
		$value    = round( (float) $amount, 2 );
		$negative = $value < 0;
		$abs      = abs( $value );
		$sign     = $negative ? "\u{2212}" : '';
		if ( 'CHF' === $currency ) {
			return 'CHF ' . $sign . number_format( $abs, 2, '.', "'" );
		}
		if ( 'EUR' !== $currency ) {
			return 'de' === $lang ? $sign . number_format( $abs, 2, ',', '.' ) . " {$currency}" : "{$currency} {$sign}" . number_format( $abs, 2, '.', ',' );
		}
		return 'de' === $lang ? $sign . number_format( $abs, 2, ',', '.' ) . ' €' : $sign . '€' . number_format( $abs, 2, '.', ',' );
	}
}
