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
    case Replace = 'replace';
    case Delete  = 'delete';

    public function isEqual(self $other): bool { return $this === $other; }

    public function isReplace(): bool { return $this->isEqual(self::Replace); }
    public function isDelete(): bool  { return $this->isEqual(self::Delete); }
}
