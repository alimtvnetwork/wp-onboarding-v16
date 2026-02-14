<?php
/**
 * SnapshotProviderType — Snapshot provider identifiers.
 *
 * @package RiseupAsia\Enums
 */

namespace RiseupAsia\Enums;

/**
 * Snapshot provider identifiers.
 */
enum SnapshotProviderType: string
{
    case WpReset = 'wp_reset';
    case Updraft = 'updraft';
    case Native  = 'native';
    case Auto    = 'auto';

    public function isEqual(self $other): bool { return $this === $other; }

    public function isWpReset(): bool { return $this->isEqual(self::WpReset); }
    public function isUpdraft(): bool { return $this->isEqual(self::Updraft); }
    public function isNative(): bool  { return $this->isEqual(self::Native); }
    public function isAuto(): bool    { return $this->isEqual(self::Auto); }
}
