<?php
declare(strict_types=1);

namespace Rungud\Mail;

/**
 * The CMS's e-mail frame: dark background, Helvetica, no rounded corners,
 * light button with black text (PROMPT §8 #7). Replaced by the site team's
 * sample HTML when it arrives. Content is escaped; line breaks kept.
 */
final class Template {

	public static function render( string $body_text, ?string $button_url = null, ?string $button_label = null ): string {
		$body = nl2br( esc_html( $body_text ) );
		$btn  = $button_url
			? '<p style="margin:28px 0 0"><a href="' . esc_url( $button_url ) . '" style="display:inline-block;background:#E6E2DD;color:#000000;text-decoration:none;padding:12px 22px;font-weight:bold">' . esc_html( (string) $button_label ) . '</a></p>'
			: '';
		return '<!doctype html><html><body style="margin:0;background:#1E2C36;padding:32px 0">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">'
			. '<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%">'
			. '<tr><td style="font-family:Helvetica,Arial,sans-serif;color:#FFFFFF;font-size:20px;letter-spacing:.06em;padding:0 24px 20px">inZENtive</td></tr>'
			. '<tr><td style="font-family:Helvetica,Arial,sans-serif;color:#E6E2DD;font-size:15px;line-height:1.6;padding:0 24px">' . $body . $btn . '</td></tr>'
			. '<tr><td style="font-family:Helvetica,Arial,sans-serif;color:#8DA0AE;font-size:12px;padding:28px 24px 0">inZENtive · info@inzentive.online</td></tr>'
			. '</table></td></tr></table></body></html>';
	}
}
