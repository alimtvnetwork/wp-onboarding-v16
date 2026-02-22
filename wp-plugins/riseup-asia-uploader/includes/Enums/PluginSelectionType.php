<?php
/**
 * PluginSelectionType — Plugin selection scope for snapshots.
 *
 * @package RiseupAsia\Enums
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum PluginSelectionType: string
{
    case All    = 'All';
    case Active = 'Active';
    case None   = 'None';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }

    public function isAll(): bool    { return $this->isEqual(self::All); }
    public function isActive(): bool { return $this->isEqual(self::Active); }
    public function isNone(): bool   { return $this->isEqual(self::None); }
}
