<?php
/**
 * SyncEntryStatusType — Per-file result statuses for sync push operations.
 *
 * @package RiseupAsia\Enums
 * @since   2.0.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum SyncEntryStatusType: string
{
    case Success = 'Success';
    case Error   = 'Error';
    case Ignored = 'Ignored';
    case Skipped = 'Skipped';

    /** Check if this enum case equals the given case. */
    public function isEqual(self $other): bool { return $this === $other; }

    /** Check if this enum case differs from the given case. */
    public function isOtherThan(self $other): bool { return $this !== $other; }

    /** Check if the receiver matches any of the given cases. */
    public function isAnyOf(self ...$others): bool
    {
        return in_array($this, $others, true);
    }
}
