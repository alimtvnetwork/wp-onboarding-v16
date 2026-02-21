<?php
/**
 * AdminTabType — Admin page tab identifiers.
 *
 * Tab slugs used in $_GET['tab'] comparisons and add_query_arg() calls.
 *
 * @package RiseupAsia\Enums
 * @since   2.5.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Tab identifiers for admin sub-navigation.
 */
enum AdminTabType: string
{
    // ── Error Page Tabs ─────────────────────────────────────────
    case Sessions   = 'sessions';
    case Log        = 'log';
    case Error      = 'error';
    case Stacktrace = 'stacktrace';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }

    /** Check if the receiver matches any of the given cases. */
    public function isAnyOf(self ...$others): bool
    {
        return in_array($this, $others, true);
    }

    /** Check if this tab is a log file tab (log, error, or stacktrace). */
    public function isLogFileTab(): bool
    {
        return $this->isAnyOf(self::Log, self::Error, self::Stacktrace);
    }
}
