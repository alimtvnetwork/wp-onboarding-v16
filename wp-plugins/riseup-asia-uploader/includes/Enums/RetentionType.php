<?php
/**
 * RetentionType — Snapshot retention policy types.
 *
 * @package RiseupAsia\Enums
 */

namespace RiseupAsia\Enums;

enum RetentionType: string
{
    case Days  = 'Days';
    case Count = 'Count';
    case None  = 'None';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }

    public function isDays(): bool  { return $this->isEqual(self::Days); }
    public function isCount(): bool { return $this->isEqual(self::Count); }
    public function isNone(): bool  { return $this->isEqual(self::None); }
}
