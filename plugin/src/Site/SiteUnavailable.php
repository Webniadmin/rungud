<?php
declare(strict_types=1);

namespace Rungud\Site;

/** The website could not answer: route missing, site function missing, or an error from the site. */
final class SiteUnavailable extends \RuntimeException {

	public function __construct( public readonly string $reason, string $message = '', public readonly int $status = 502 ) {
		parent::__construct( $message ?: $reason );
	}

	public function to_wp_error(): \WP_Error {
		return new \WP_Error(
			'rungud_site_unavailable',
			'The website did not answer: ' . $this->getMessage(),
			array( 'status' => 502, 'reason' => $this->reason )
		);
	}
}
