<?php
declare(strict_types=1);

namespace Rungud\Command;

use Rungud\Install\Schema;
use Rungud\Stripe\StripeReader;

/**
 * Documents the CMS sends but never re-renders: the PDF is fetched from where
 * it lives (Stripe's invoice_pdf, the Woo PDF plugin's URL) and attached.
 */
final class Documents {

	/** Hosts a document may be fetched from. Anything else is refused. */
	public const ALLOWED_HOSTS = array( 'pay.stripe.com', 'invoice.stripe.com', 'files.stripe.com' );

	/**
	 * @return array{name:string, data:string}
	 * @throws Refused|\RuntimeException
	 */
	public static function fetch( string $type, string $ref ): array {
		if ( 'stripe_invoice' === $type ) {
			$inv = StripeReader::invoice( $ref );
			if ( empty( $inv['pdf'] ) ) {
				throw new Refused( 'Stripe has no PDF for this invoice.', 'no_pdf' );
			}
			return array( 'name' => ( $inv['number'] ?: $ref ) . '.pdf', 'data' => self::download( (string) $inv['pdf'] ) );
		}
		if ( 'woo_invoice' === $type ) {
			// No WooCommerce PDF invoice plugin on the site yet (PROMPT §8 #1).
			throw new Refused( 'There is no PDF for website orders yet — the PDF invoice plugin is not installed on the website.', 'no_pdf_plugin' );
		}
		throw new Refused( 'Unknown document type.', 'refused' );
	}

	private static function download( string $url ): string {
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );
		if ( 'https' !== wp_parse_url( $url, PHP_URL_SCHEME ) || ! in_array( $host, self::ALLOWED_HOSTS, true ) ) {
			throw new Refused( 'The document address is not a Stripe address.', 'no_pdf' );
		}
		$res = wp_safe_remote_get( $url, array( 'timeout' => 20, 'redirection' => 3 ) );
		if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
			throw new \RuntimeException( 'The PDF could not be downloaded.' );
		}
		return (string) wp_remote_retrieve_body( $res );
	}

	/**
	 * Default cover text in the RECIPIENT's language (build-package A4.14).
	 *
	 * @return array{subject:string, body:string}
	 */
	public static function cover( string $type, string $lang, string $name, string $label ): array {
		$first = trim( explode( ' ', $name )[0] ?? '' );
		if ( 'checkout_link' === $type ) {
			return 'de' === $lang
				? array( 'subject' => 'Deine Mitgliedschaft bei inZENtive', 'body' => "Hallo {$first},\n\nhier ist der Link, um den Plan „{$label}“ abzuschliessen. Du meldest dich mit deinem bestehenden Konto an und bezahlst direkt auf der Website.\n\nHerzliche Grüsse\nGudrun, inZENtive" )
				: array( 'subject' => 'Your inZENtive membership', 'body' => "Hello {$first},\n\nhere is the link to take the plan “{$label}”. You log in with your existing account and pay directly on the website.\n\nKind regards\nGudrun, inZENtive" );
		}
		return 'de' === $lang
			? array( 'subject' => "Deine Rechnung {$label}", 'body' => "Hallo {$first},\n\nim Anhang findest du die Rechnung {$label}.\n\nHerzliche Grüsse\nGudrun, inZENtive" )
			: array( 'subject' => "Your invoice {$label}", 'body' => "Hello {$first},\n\nplease find invoice {$label} attached.\n\nKind regards\nGudrun, inZENtive" );
	}

	/** @param array<string,mixed> $p */
	public static function log_send( array $p, string $status ): void {
		global $wpdb;
		$wpdb->insert(
			Schema::table( 'document_sends' ),
			array(
				'doc_type' => (string) $p['doc_type'],
				'doc_ref'  => (string) $p['doc_ref'],
				'to_email' => (string) $p['to'],
				'language' => (string) $p['lang'],
				'subject'  => (string) $p['subject'],
				'body'     => (string) $p['body'],
				'status'   => $status,
				'sent_by'  => get_current_user_id(),
				'sent_at'  => gmdate( 'Y-m-d H:i:s' ),
			)
		);
	}
}
