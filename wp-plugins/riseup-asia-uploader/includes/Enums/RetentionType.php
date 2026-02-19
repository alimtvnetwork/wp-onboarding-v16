<?php
/**
 * RetentionType — Snapshot retention policy types.
 *
 * @package RiseupAsia\Enums
 */

namespace RiseupAsia\Enums;

/**
 * Snapshot retention policy types.
 */
enum RetentionType: string
{
    case Days  = 'days';
    case Count = 'count';
    case None  = 'none';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }

    public function isDays(): bool  { return $this->isEqual(self::Days); }
    public function isCount(): bool { return $this->isEqual(self::Count); }
    public function isNone(): bool  { return $this->isEqual(self::None); }
}
