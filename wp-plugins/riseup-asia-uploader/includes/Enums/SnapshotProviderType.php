<?php
/**
 * SnapshotProviderType — Snapshot provider identifiers.
 *
 * @package RiseupAsia\Enums
 */

namespace RiseupAsia\Enums;

enum SnapshotProviderType: string
{
    case WpReset = 'WpReset';
    case Updraft = 'Updraft';
    case Native  = 'Native';
    case Auto    = 'Auto';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }

    public function isWpReset(): bool { return $this->isEqual(self::WpReset); }
    public function isUpdraft(): bool { return $this->isEqual(self::Updraft); }
    public function isNative(): bool  { return $this->isEqual(self::Native); }
    public function isAuto(): bool    { return $this->isEqual(self::Auto); }
}
