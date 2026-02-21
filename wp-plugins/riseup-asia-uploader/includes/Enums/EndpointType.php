<?php
/**
 * EndpointType — REST API Endpoint Path Fragments
 *
 * @package RiseupAsia\Enums
 * @since   1.58.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API endpoint path fragments.
 *
 * Organized by domain: Core, Plugin, Agent, Snapshot, Sync.
 */
enum EndpointType: string
{
    // ── Core ─────────────────────────────────────────────────────────
    case WpJson        = 'wp-json/';
    case Status        = 'status';
    case Upload        = 'upload';
    case UploadActive  = 'upload-active';
    case Plugins       = 'plugins';
    case PluginInfo    = 'plugins/info';
    case ExportSelf    = 'export-self';
    case Posts         = 'posts';
    case Categories    = 'categories';
    case Logs          = 'logs';
    case LogsStats     = 'logs/stats';
    case Openapi       = 'openapi';
    case OpcacheReset  = 'opcache-reset';
    case Media         = 'media';

    // ── Plugin ───────────────────────────────────────────────────────
    case PluginFiles   = 'plugins/files';
    case PluginFile    = 'plugins/file';
    case PluginEnable  = 'plugins/enable';
    case PluginDisable = 'plugins/disable';
    case PluginDelete  = 'plugins/delete';
    case PluginExists  = 'plugins/exists';
    case PluginExport  = 'plugins/export';

    // ── Sync ─────────────────────────────────────────────────────────
    case SyncManifest  = 'plugins/sync-manifest';
    case Sync          = 'plugins/sync';

    // ── Agent ────────────────────────────────────────────────────────
    case Agents        = 'agents';
    case AgentsAdd     = 'agents/add';
    case AgentsRemove  = 'agents/remove';
    case AgentsTest    = 'agents/test';
    case AgentsSync    = 'agents/sync';
    case AgentsPlugins = 'agents/plugins';
    case AgentAction   = 'agents/action';
    case AgentHistory  = 'agents/history';

    // ── Snapshot ─────────────────────────────────────────────────────
    case SnapshotList         = 'snapshots/list';
    case SnapshotSchedule     = 'snapshots/schedule';
    case SnapshotInfo         = 'snapshots/info';
    case SnapshotDelete       = 'snapshots/delete';
    case SnapshotRestore      = 'snapshots/restore';
    case SnapshotExport       = 'snapshots/export';
    case SnapshotImport       = 'snapshots/import';
    case SnapshotSettings     = 'snapshots/settings';
    case SnapshotProviders    = 'snapshots/providers';
    case SnapshotTables       = 'snapshots/tables';
    case SnapshotDependencies = 'snapshots/dependencies';
    case SnapshotExportPertable = 'snapshots/export-pertable';
    case SnapshotFullBackup   = 'snapshots/full-backup';
    case SnapshotIncremental  = 'snapshots/incremental';
    case SnapshotCleanup      = 'snapshots/cleanup';
    case SnapshotProgress     = 'snapshots/progress';
    case SnapshotDownload     = 'snapshots/download';
    case SnapshotDownloadFile = 'snapshots/download-file';

    // ── Error Log ───────────────────────────────────────────────────
    case ErrorLogs     = 'error-logs';
    case ErrorSessions = 'error-sessions';

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

    /**
     * Return the route path ready for register_rest_route().
     * Encapsulates the '/' prefix so callers never touch ->value for routing.
     */
    public function route(): string
    {
        return '/' . $this->value;
    }

    /** Check if this endpoint belongs to the snapshot domain. */
    public function isSnapshot(): bool
    {
        return str_starts_with($this->value, 'snapshots/');
    }

    /** Check if this endpoint belongs to the agent domain. */
    public function isAgent(): bool
    {
        return str_starts_with($this->value, 'agents');
    }

    /** Check if this endpoint belongs to the plugin domain. */
    public function isPlugin(): bool
    {
        return str_starts_with($this->value, 'plugins/');
    }
}
