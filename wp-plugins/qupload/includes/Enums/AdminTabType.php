<?php
/**
 * AdminTabType — Tab identifiers for the error log page.
 *
 * @package QUpload\Enums
 * @since   2.1.0
 */

namespace QUpload\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum AdminTabType: string
{
    case Log        = 'log';
    case Error      = 'error';
    case Stacktrace = 'stacktrace';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }
}
