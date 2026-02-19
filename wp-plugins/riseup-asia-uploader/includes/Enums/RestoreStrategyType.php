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
    case Truncate = 'truncate';
    case Merge    = 'merge';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }

    public function isTruncate(): bool { return $this->isEqual(self::Truncate); }
    public function isMerge(): bool    { return $this->isEqual(self::Merge); }
}
