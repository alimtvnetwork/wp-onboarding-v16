<?php
/**
 * SnapshotErrorType — Snapshot Error Code Constants
 *
 * @package RiseupAsia\Enums
 * @since   1.58.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Error codes returned in snapshot operation responses.
 */
enum SnapshotErrorType: string
{
    case LockExists         = 'SNAPSHOT_LOCK_EXISTS';
    case NotFound           = 'SNAPSHOT_NOT_FOUND';
    case Corrupt            = 'SNAPSHOT_CORRUPT';
    case TooLarge           = 'SNAPSHOT_TOO_LARGE';
    case RestoreFailed      = 'RESTORE_FAILED';
    case RestoreNoConfirm   = 'RESTORE_NO_CONFIRM';
    case ProviderNotAvail   = 'PROVIDER_NOT_AVAILABLE';
    case IncrementalNoParent = 'INCREMENTAL_NO_PARENT';
    case ExportNotFound     = 'EXPORT_NOT_FOUND';
    case ExportBuildFailed  = 'EXPORT_BUILD_FAILED';
    case ExportTokenInvalid = 'EXPORT_TOKEN_INVALID';

    /** Check if this enum case equals the given case. */
    public function isEqual(self $other): bool
    {
        return $this === $other;
    }

    /** Check if this is an export-related error. */
    public function isExport(): bool
    {
        return str_starts_with($this->value, 'EXPORT_');
    }

    /** Check if this is a restore-related error. */
    public function isRestore(): bool
    {
        return str_starts_with($this->value, 'RESTORE_');
    }
}
