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
    case Full        = 'full';
    case Incremental = 'incremental';

    public function isEqual(self $other): bool { return $this === $other; }

    public function isFull(): bool        { return $this->isEqual(self::Full); }
    public function isIncremental(): bool { return $this->isEqual(self::Incremental); }
}
