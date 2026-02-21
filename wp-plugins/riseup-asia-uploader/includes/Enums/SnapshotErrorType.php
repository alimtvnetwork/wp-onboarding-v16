<?php
/**
 * SnapshotErrorType — Snapshot error code constants.
 *
 * @package RiseupAsia\Enums
 * @since   1.58.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum SnapshotErrorType: string
{
    case LockExists          = 'LockExists';
    case NotFound            = 'NotFound';
    case Corrupt             = 'Corrupt';
    case TooLarge            = 'TooLarge';
    case RestoreFailed       = 'RestoreFailed';
    case RestoreNoConfirm    = 'RestoreNoConfirm';
    case ProviderNotAvail    = 'ProviderNotAvail';
    case IncrementalNoParent = 'IncrementalNoParent';
    case ExportNotFound      = 'ExportNotFound';
    case ExportBuildFailed   = 'ExportBuildFailed';
    case ExportTokenInvalid  = 'ExportTokenInvalid';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }

    public function isExport(): bool
    {
        return $this->isAnyOf(self::ExportNotFound, self::ExportBuildFailed, self::ExportTokenInvalid);
    }

    public function isRestore(): bool
    {
        return $this->isAnyOf(self::RestoreFailed, self::RestoreNoConfirm);
    }
}
