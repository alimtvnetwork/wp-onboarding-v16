<?php
/**
 * RestoreModeType — Snapshot restore mode values.
 *
 * @package RiseupAsia\Enums
 */

namespace RiseupAsia\Enums;

enum RestoreModeType: string
{
    case Full        = 'Full';
    case Selective   = 'Selective';
    case Incremental = 'Incremental';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }

    public function isFull(): bool        { return $this->isEqual(self::Full); }
    public function isSelective(): bool   { return $this->isEqual(self::Selective); }
    public function isIncremental(): bool { return $this->isEqual(self::Incremental); }
}
