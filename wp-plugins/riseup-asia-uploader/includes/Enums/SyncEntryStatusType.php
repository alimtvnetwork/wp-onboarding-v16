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

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }
}
