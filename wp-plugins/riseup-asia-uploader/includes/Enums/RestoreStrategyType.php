<?php
/**
 * RestoreStrategyType — Table restore strategy values.
 *
 * @package RiseupAsia\Enums
 */

namespace RiseupAsia\Enums;

/**
 * Table restore strategy values.
 */
enum RestoreStrategyType: string
{
    case Truncate = 'Truncate';
    case Merge    = 'Merge';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }

    /** Check if the receiver matches any of the given cases. */
    public function isAnyOf(self ...$others): bool
    {
        return in_array($this, $others, true);
    }

    public function isTruncate(): bool { return $this->isEqual(self::Truncate); }
    public function isMerge(): bool    { return $this->isEqual(self::Merge); }
}
