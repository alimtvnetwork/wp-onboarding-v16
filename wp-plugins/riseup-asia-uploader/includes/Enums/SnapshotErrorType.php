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

    /** Check if this enum case equals the given case. */
    public function isEqual(self $other): bool
    {
        return $this === $other;
    }

    /** Check if this enum case differs from the given case. */
    public function isOtherThan(self $other): bool
    {
        return $this !== $other;
    }

    /** Check if the receiver matches any of the given cases. */
    public function isAnyOf(self ...$others): bool
    {
        return in_array($this, $others, true);
    }

    /** Check if this is an export-related error. */
    public function isExport(): bool
    {
        return $this->isAnyOf(self::ExportNotFound, self::ExportBuildFailed, self::ExportTokenInvalid);
    }

    /** Check if this is a restore-related error. */
    public function isRestore(): bool
    {
        return $this->isAnyOf(self::RestoreFailed, self::RestoreNoConfirm);
    }
}
