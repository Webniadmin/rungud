<?php
declare(strict_types=1);

namespace Rungud\Command;

/** A command the CMS itself refuses before sending (e.g. refund above what was paid). Message is shown to the user. */
final class Refused extends \RuntimeException {

	/** @param string $reason key the UI translates (reasons.* in en.json / de.json) */
	public function __construct( string $message, public readonly string $reason = 'refused' ) {
		parent::__construct( $message );
	}
}
