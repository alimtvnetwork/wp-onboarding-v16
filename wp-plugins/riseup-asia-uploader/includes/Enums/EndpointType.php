<?php
/**
 * EndpointType — REST API endpoint path fragments.
 *
 * @package RiseupAsia\Enums
 * @since   1.58.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

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
    case PluginBackup        = 'plugins/backup';
    case PluginBackupRestore = 'plugins/backup-restore';
    case PluginBackupList    = 'plugins/backup-list';
    case PluginBackupDelete  = 'plugins/backup-delete';

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

    // ── Remote Log Management ────────────────────────────────────────
    case LogsStatus    = 'logs/status';
    case LogsClear     = 'logs/clear';
    case LogsConfirm   = 'logs/clear/confirm';
    case LogsEmail     = 'logs/email';

    // ── User Management ─────────────────────────────────────────────
    case Users             = 'users';
    case UserId            = 'users/(?P<id>\d+)';
    case UserAppPassword   = 'users/app-password';
    case UsersExport       = 'users/export';
    case UsersImport       = 'users/import';
    case UsersExportSqlite = 'users/export-sqlite';
    case UsersImportSqlite = 'users/import-sqlite';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }

    /** Prefixes value with '/' for register_rest_route(). */
    public function route(): string
    {
        return '/' . $this->value;
    }

    public function isSnapshot(): bool { return str_starts_with($this->value, 'snapshots/'); }
    public function isAgent(): bool    { return str_starts_with($this->value, 'agents'); }
    public function isPlugin(): bool   { return str_starts_with($this->value, 'plugins/'); }
    public function isUser(): bool     { return str_starts_with($this->value, 'users'); }
}
