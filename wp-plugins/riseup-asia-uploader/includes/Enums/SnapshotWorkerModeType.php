<?php
/**
 * SnapshotWorkerModeType — Snapshot worker execution mode.
 *
 * @package RiseupAsia\Enums
 */

namespace RiseupAsia\Enums;

enum SnapshotWorkerModeType: string
{
    case PerTable = 'PerTable';
    case Single   = 'Single';
    case Legacy   = 'Legacy';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }

    public function isPerTable(): bool { return $this->isEqual(self::PerTable); }
    public function isSingle(): bool   { return $this->isEqual(self::Single); }
    public function isLegacy(): bool   { return $this->isEqual(self::Legacy); }
}
