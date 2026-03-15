<?php
/**
 * CloudStorageBackupStatusType — Backup job status identifiers.
 *
 * @package RiseupAsia\Enums
 * @since   2.16.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum CloudStorageBackupStatusType: string
{
    case Pending   = 'Pending';
    case Uploading = 'Uploading';
    case Success   = 'Success';
    case Failed    = 'Failed';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }

    public function isPending(): bool   { return $this->isEqual(self::Pending); }
    public function isUploading(): bool { return $this->isEqual(self::Uploading); }
    public function isSuccess(): bool   { return $this->isEqual(self::Success); }
    public function isFailed(): bool    { return $this->isEqual(self::Failed); }

    public function isTerminal(): bool { return $this->isSuccess() || $this->isFailed(); }

    /** Display label for UI. */
    public function label(): string
    {
        return match($this) {
            self::Pending   => 'Pending',
            self::Uploading => 'Uploading',
            self::Success   => 'Success',
            self::Failed    => 'Failed',
        };
    }
}
