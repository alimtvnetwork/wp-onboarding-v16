<?php
/**
 * SnapshotModeType — Snapshot mode (full vs incremental).
 *
 * @package RiseupAsia\Enums
 */

namespace RiseupAsia\Enums;

/**
 * Snapshot mode (full vs incremental).
 */
enum SnapshotModeType: string
{
    case Full        = 'Full';
    case Incremental = 'Incremental';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }

    /** Check if the receiver matches any of the given cases. */
    public function isAnyOf(self ...$others): bool
    {
        return in_array($this, $others, true);
    }

    public function isFull(): bool        { return $this->isEqual(self::Full); }
    public function isIncremental(): bool { return $this->isEqual(self::Incremental); }
}
