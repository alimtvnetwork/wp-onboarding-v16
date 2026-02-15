<?php
/**
 * RestoreModeType — Snapshot restore mode values.
 *
 * @package RiseupAsia\Enums
 */

namespace RiseupAsia\Enums;

/**
 * Snapshot restore mode values.
 */
enum RestoreModeType: string
{
    case Full        = 'full';
    case Selective   = 'selective';
    case Incremental = 'incremental';

    public function isEqual(self $other): bool { return $this === $other; }

    public function isFull(): bool        { return $this->isEqual(self::Full); }
    public function isSelective(): bool   { return $this->isEqual(self::Selective); }
    public function isIncremental(): bool { return $this->isEqual(self::Incremental); }
}
