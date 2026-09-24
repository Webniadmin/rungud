<?php
declare(strict_types=1);

namespace Rungud\Site;

/** The website could not answer: route missing, site function missing, or an error from the site. */
final class SiteUnavailable extends \RuntimeException {

	public function __construct( public readonly string $reason, string $message = '', public readonly int $status = 502 ) {
		parent::__construct( $message ?: $reason );
	}

	/**
	 * The site answered and said no (validation, conflict, unknown record) —
	 * as opposed to not answering: server error, missing route, or a
	 * permission problem between the CMS and the site (a setup fault).
	 */
	public function is_refusal(): bool {
		if ( 'missing_route' === $this->reason || 'missing_function' === $this->reason ) {
			return false;
		}
		if ( in_array( $this->status, array( 401, 403 ), true ) ) {
			return false;
		}
		return $this->status >= 400 && $this->status < 500;
	}

	public function to_wp_error(): \WP_Error {
		return new \WP_Error(
			'rungud_site_unavailable',
			'The website did not answer: ' . $this->getMessage(),
			array( 'status' => 502, 'reason' => $this->reason )
		);
	}
}
