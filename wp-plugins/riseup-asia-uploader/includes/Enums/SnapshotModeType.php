<?php
/**
 * SnapshotModeType — Snapshot mode (full vs incremental).
 *
 * @package RiseupAsia\Enums
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum SnapshotModeType: string
{
    case Full        = 'Full';
    case Incremental = 'Incremental';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }

    public function isFull(): bool        { return $this->isEqual(self::Full); }
    public function isIncremental(): bool { return $this->isEqual(self::Incremental); }
}
