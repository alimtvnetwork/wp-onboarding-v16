<?php
/**
 * SyncActionType — File sync action types.
 *
 * @package RiseupAsia\Enums
 */

namespace RiseupAsia\Enums;

/**
 * File sync action types.
 */
enum SyncActionType: string
{
    case Replace = 'Replace';
    case Delete  = 'Delete';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }

    /** Check if the receiver matches any of the given cases. */
    public function isAnyOf(self ...$others): bool
    {
        return in_array($this, $others, true);
    }

    public function isReplace(): bool { return $this->isEqual(self::Replace); }
    public function isDelete(): bool  { return $this->isEqual(self::Delete); }
}
