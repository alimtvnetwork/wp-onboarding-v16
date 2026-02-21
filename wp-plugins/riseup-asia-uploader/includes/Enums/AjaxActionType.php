<?php
/**
 * AjaxActionType — WordPress AJAX action slugs.
 *
 * Every AJAX action registered via HookType::ajax() MUST reference
 * a case from this enum instead of using hardcoded strings.
 *
 * @package RiseupAsia\Enums
 * @since   2.5.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX action slug identifiers.
 */
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

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }

    /** Check if the receiver matches any of the given cases. */
    public function isAnyOf(self ...$others): bool
    {
        return in_array($this, $others, true);
    }
}
