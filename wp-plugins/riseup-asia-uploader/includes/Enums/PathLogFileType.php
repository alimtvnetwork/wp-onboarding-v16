<?php
/**
 * PathLogFileType — Log File Path Fragments
 *
 * @package RiseupAsia\Enums
 * @since   1.58.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Log file path fragments.
 */
enum PathLogFileType: string
{
    case Log        = '/log.txt';
    case FatalError = '/fatal-errors.log';
    case Stacktrace = '/stacktrace.txt';
    case Error      = '/error.txt';

    /** Check if this enum case equals the given case. */
    public function isEqual(self $other): bool
    {
        return $this === $other;
    }

    /** Check if this enum case differs from the given case. */
    public function isOtherThan(self $other): bool
    {
        return $this !== $other;
    }

    /** Check if the receiver matches any of the given cases. */
    public function isAnyOf(self ...$others): bool
    {
        return in_array($this, $others, true);
    }
}
