<?php
/**
 * CloudStorageBackupType — Full vs incremental backup type.
 *
 * @package RiseupAsia\Enums
 * @since   2.16.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum CloudStorageBackupType: string
{
    case Full        = 'Full';
    case Incremental = 'Incremental';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }

    public function isFull(): bool        { return $this->isEqual(self::Full); }
    public function isIncremental(): bool  { return $this->isEqual(self::Incremental); }

    /** Display label for UI. */
    public function label(): string
    {
        return match($this) {
            self::Full        => 'Full Backup',
            self::Incremental => 'Incremental Backup',
        };
    }
}
