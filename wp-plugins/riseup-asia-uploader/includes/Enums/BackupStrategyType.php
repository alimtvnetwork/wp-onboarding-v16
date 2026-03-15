<?php
/**
 * BackupStrategyType — Cloud storage backup strategy identifiers.
 *
 * @package RiseupAsia\Enums
 * @since   2.16.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum BackupStrategyType: string
{
    case FullOnly           = 'FullOnly';
    case FullAndIncremental = 'FullAndIncremental';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }

    public function isFullOnly(): bool           { return $this->isEqual(self::FullOnly); }
    public function isFullAndIncremental(): bool  { return $this->isEqual(self::FullAndIncremental); }

    /** Display label for UI. */
    public function label(): string
    {
        return match($this) {
            self::FullOnly           => 'Full backups only',
            self::FullAndIncremental => 'Full + Incremental backups',
        };
    }
}
