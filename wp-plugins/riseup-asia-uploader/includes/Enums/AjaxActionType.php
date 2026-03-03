<?php
/**
 * AjaxActionType — WordPress AJAX action slugs.
 *
 * @package RiseupAsia\Enums
 * @since   2.5.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum AjaxActionType: string
{
    // ── Update Actions ──────────────────────────────────────────
    case TestUpdateConnection = 'riseup_test_update_connection';
    case ClearUpdateCache     = 'riseup_clear_update_cache';
    case CheckForUpdates      = 'riseup_check_for_updates';

    // ── Snapshot Actions ────────────────────────────────────────
    case SaveSnapshotSettings    = 'riseup_save_snapshot_settings';
    case RunSnapshotCleanup      = 'riseup_run_snapshot_cleanup';
    case GetSnapshotStorageStats = 'riseup_get_snapshot_storage_stats';

    // ── Error Actions ───────────────────────────────────────────
    case DismissErrorFlash  = 'riseup_dismiss_error_flash';
    case ClearErrorSessions = 'riseup_clear_error_sessions';

    // ── Log Actions ─────────────────────────────────────────────
    case ReadLogFile  = 'riseup_read_log_file';
    case ClearLogFile = 'riseup_clear_log_file';

    // ── License Actions ─────────────────────────────────────────
    case LicenseSave       = 'riseup_license_save';
    case LicenseActivate   = 'riseup_license_activate';
    case LicenseDeactivate = 'riseup_license_deactivate';
    case LicenseRemove     = 'riseup_license_remove';
    case LicenseRefresh    = 'riseup_license_refresh';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }
}
