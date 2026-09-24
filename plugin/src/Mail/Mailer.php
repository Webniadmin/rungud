<?php
declare(strict_types=1);

namespace Rungud\Mail;

use Rungud\Config;

/**
 * Sends the CMS's e-mails from the site's own address and server
 * (info@inzentive.online, SPF/DKIM hold). Mode:
 *
 *   send  deliver through wp_mail
 *   log   deliver nothing; the send is recorded as "logged" (tests, local
 *         copies, staging — which may run against the real SMTP server)
 *
 * RUNGUD_MAIL_MODE decides; when unset, only a WordPress whose environment
 * type is `production` sends.
 */
final class Mailer {

	public const FROM      = 'info@inzentive.online';
	public const FROM_NAME = 'inZENtive';

	public static function mode(): string {
		$mode = Config::get( 'RUNGUD_MAIL_MODE', '' );
		if ( in_array( $mode, array( 'send', 'log' ), true ) ) {
			return $mode;
		}
		return 'production' === wp_get_environment_type() ? 'send' : 'log';
	}

	/**
	 * @param list<array{name:string, data:string}> $attachments in-memory files
	 * @return string 'sent' | 'logged'
	 * @throws \RuntimeException when delivery fails
	 */
	public static function send( string $to, string $subject, string $html, array $attachments = array() ): string {
		if ( ! is_email( $to ) ) {
			throw new \RuntimeException( 'Not a valid e-mail address.' );
		}
		if ( 'log' === self::mode() ) {
			return 'logged';
		}

		$from      = (string) Config::get( 'RUNGUD_SMTP_FROM', self::FROM );
		$from_name = (string) Config::get( 'RUNGUD_SMTP_FROM_NAME', self::FROM_NAME );
		$files     = array();
		$dir       = trailingslashit( get_temp_dir() ) . 'rungud-' . wp_generate_password( 12, false );
		wp_mkdir_p( $dir );
		foreach ( $attachments as $a ) {
			$path = $dir . '/' . sanitize_file_name( $a['name'] );
			file_put_contents( $path, $a['data'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$files[] = $path;
		}

		$set_from = static fn() => $from;
		$set_name = static fn() => $from_name;
		$smtp     = static function ( $phpmailer ) {
			$host = Config::get( 'RUNGUD_SMTP_HOST', '' );
			if ( ! $host ) {
				return; // the site's own mail configuration applies
			}
			$phpmailer->isSMTP();
			$phpmailer->Host       = $host;
			$phpmailer->Port       = (int) Config::get( 'RUNGUD_SMTP_PORT', '587' );
			$phpmailer->SMTPSecure = 'tls';
			$phpmailer->SMTPAuth   = true;
			$phpmailer->Username   = (string) Config::get( 'RUNGUD_SMTP_USER', '' );
			$phpmailer->Password   = (string) Config::get( 'RUNGUD_SMTP_PASS', '' );
		};
		$error    = null;
		$on_error = static function ( \WP_Error $e ) use ( &$error ) {
			$error = $e->get_error_message();
		};

		add_filter( 'wp_mail_from', $set_from, PHP_INT_MAX );
		add_filter( 'wp_mail_from_name', $set_name, PHP_INT_MAX );
		add_action( 'phpmailer_init', $smtp, PHP_INT_MAX );
		add_action( 'wp_mail_failed', $on_error );
		try {
			$ok = wp_mail( $to, $subject, $html, array( 'Content-Type: text/html; charset=UTF-8' ), $files );
		} finally {
			remove_filter( 'wp_mail_from', $set_from, PHP_INT_MAX );
			remove_filter( 'wp_mail_from_name', $set_name, PHP_INT_MAX );
			remove_action( 'phpmailer_init', $smtp, PHP_INT_MAX );
			remove_action( 'wp_mail_failed', $on_error );
			array_map( 'wp_delete_file', $files );
			@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		if ( ! $ok ) {
			throw new \RuntimeException( $error ?: 'The mail server did not accept the message.' );
		}
		return 'sent';
	}
}
