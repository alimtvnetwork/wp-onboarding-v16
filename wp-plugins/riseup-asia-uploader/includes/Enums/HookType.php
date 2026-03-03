<?php
/**
 * HookType — WordPress hook names.
 *
 * @package RiseupAsia\Enums
 * @since   1.57.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum HookType: string
{
    // ── Core Lifecycle ──────────────────────────────────────────
    case Init           = 'init';
    case PluginsLoaded  = 'plugins_loaded';
    case RestApiInit    = 'rest_api_init';
    case AdminInit      = 'admin_init';
    case Shutdown       = 'shutdown';

    // ── Plugin Lifecycle ────────────────────────────────────────
    case ActivatedPlugin   = 'activated_plugin';
    case DeactivatedPlugin = 'deactivated_plugin';
    case DeletedPlugin     = 'deleted_plugin';

    // ── Admin UI ────────────────────────────────────────────────
    case AdminNotices   = 'admin_notices';
    case AdminEnqueue   = 'admin_enqueue_scripts';
    case AdminMenu      = 'admin_menu';

    // ── Filters ─────────────────────────────────────────────────
    case RestPostDispatch                  = 'rest_post_dispatch';
    case PluginActionLinks                 = 'plugin_action_links';
    case PreSetSiteTransientUpdatePlugins  = 'pre_set_site_transient_update_plugins';
    case PluginsApi                        = 'plugins_api';
    case CronSchedules                     = 'cron_schedules';

    // ── Cron Hooks ──────────────────────────────────────────────
    case CronSnapshotScheduled   = 'riseup_snapshot_scheduled';
    case CronSnapshotImmediate   = 'riseup_snapshot_immediate';
    case CronSnapshotCleanup     = 'riseup_snapshot_cleanup';
    case CronSnapshotWorkerBatch = 'riseup_snapshot_worker_batch';
    case CronSnapshotRestore     = 'riseup_snapshot_restore';
    case CronSnapshotIncremental = 'riseup_snapshot_incremental';
    case CronLicenseRevalidate   = 'riseup_license_revalidate';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }

    public static function ajax(string $action): string
    {
        return 'wp_ajax_' . $action;
    }

    public static function ajaxNopriv(string $action): string
    {
        return 'wp_ajax_nopriv_' . $action;
    }
}
