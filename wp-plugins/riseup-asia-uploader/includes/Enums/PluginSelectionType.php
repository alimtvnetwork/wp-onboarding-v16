<?php
/**
 * PluginSelectionType — Plugin selection scope for snapshots.
 *
 * @package RiseupAsia\Enums
 */

namespace RiseupAsia\Enums;

/**
 * Plugin selection scope for snapshots.
 */
enum PluginSelectionType: string
{
    case All    = 'all';
    case Active = 'active';
    case None   = 'none';

    public function isEqual(self $other): bool { return $this === $other; }

    public function isAll(): bool    { return $this->isEqual(self::All); }
    public function isActive(): bool { return $this->isEqual(self::Active); }
    public function isNone(): bool   { return $this->isEqual(self::None); }
}
