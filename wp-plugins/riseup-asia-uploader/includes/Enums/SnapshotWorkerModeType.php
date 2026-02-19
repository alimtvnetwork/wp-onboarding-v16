<?php
/**
 * SnapshotWorkerModeType — Snapshot worker execution mode.
 *
 * @package RiseupAsia\Enums
 */

namespace RiseupAsia\Enums;

/**
 * Snapshot worker execution mode values.
 */
enum SnapshotWorkerModeType: string
{
    case PerTable = 'per_table';
    case Single   = 'single';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }

    public function isPerTable(): bool { return $this->isEqual(self::PerTable); }
    public function isSingle(): bool   { return $this->isEqual(self::Single); }
}
