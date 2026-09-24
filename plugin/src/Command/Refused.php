<?php
declare(strict_types=1);

namespace Rungud\Command;

/** A command the CMS itself refuses before sending (e.g. refund above what was paid). Message is shown to the user. */
final class Refused extends \RuntimeException {}
