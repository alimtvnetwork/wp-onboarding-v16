<?php
/**
 * BackupType — Plugin backup trigger types.
 *
 * @package RiseupAsia\Enums
 * @since   1.64.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum BackupType: string
{
    case PreUpdate  = 'pre_update';
    case PrePublish = 'pre_publish';
    case Manual     = 'manual';
    case Scheduled  = 'scheduled';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }

    public function isAutomatic(): bool
    {
        return $this->isAnyOf(self::PreUpdate, self::PrePublish, self::Scheduled);
    }
}
