<?php
/**
 * PathLogFileType — Log file path fragments.
 *
 * @package RiseupAsia\Enums
 * @since   1.58.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum PathLogFileType: string
{
    case Log        = '/log.txt';
    case FatalError = '/fatal-errors.log';
    case Stacktrace = '/stacktrace.txt';
    case Error      = '/error.txt';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }
}
