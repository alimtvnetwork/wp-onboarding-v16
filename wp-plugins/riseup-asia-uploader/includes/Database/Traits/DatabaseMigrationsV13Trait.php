<?php
/**
 * DatabaseMigrationsV13Trait — PascalCase table and column name migration.
 *
 * Renames all 12 custom SQLite tables and their columns from snake_case
 * to PascalCase per the database naming convention spec.
 *
 * @package RiseupAsia\Database\Traits
 * @since   2.4.0
 */

namespace RiseupAsia\Database\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use Throwable;
use PDO;

trait DatabaseMigrationsV13Trait {

    // ── Table Renames ────────────────────────────────────────────────

    private const V13_TABLE_RENAMES = [
        'transactions'        => 'Transactions',
        'agent_sites'         => 'AgentSites',
        'agent_actions'       => 'AgentActions',
        'snapshots'           => 'Snapshots',
        'snapshot_progress'   => 'SnapshotProgress',
        'snapshot_jobs'       => 'SnapshotJobs',
        'snapshot_settings'   => 'SnapshotSettings',
        'snapshot_exports'    => 'SnapshotExports',
        'file_cache'          => 'FileCache',
        'remote_plugins_cache' => 'RemotePluginsCache',
        'error_sessions'      => 'ErrorSessions',
        'flash_state'         => 'FlashState',
    ];

    // ── Column Renames (per new table name) ──────────────────────────

    private const V13_COLUMN_RENAMES = [
        'Transactions' => [
            'id' => 'Id', 'action' => 'Action', 'plugin_slug' => 'PluginSlug',
            'post_id' => 'PostId', 'user_login' => 'UserLogin', 'user_id' => 'UserId',
            'ip_address' => 'IpAddress', 'details' => 'Details', 'status' => 'Status',
            'error_msg' => 'ErrorMsg', 'created_at' => 'CreatedAt',
            'plugin_file' => 'PluginFile', 'was_active' => 'WasActive',
            'triggered_by' => 'TriggeredBy', 'agent_site_id' => 'AgentSiteId',
            'source_machine' => 'SourceMachine', 'plugin_version' => 'PluginVersion',
            'upload_source' => 'UploadSource',
        ],
        'AgentSites' => [
            'id' => 'Id', 'name' => 'Name', 'url' => 'Url', 'username' => 'Username',
            'app_password_encrypted' => 'AppPasswordEncrypted',
            'redirect_url' => 'RedirectUrl', 'redirect_resolved' => 'RedirectResolved',
            'redirect_resolved_at' => 'RedirectResolvedAt', 'status' => 'Status',
            'last_sync' => 'LastSync', 'last_error' => 'LastError',
            'created_at' => 'CreatedAt', 'updated_at' => 'UpdatedAt',
        ],
        'AgentActions' => [
            'id' => 'Id', 'agent_site_id' => 'AgentSiteId', 'action' => 'Action',
            'target_plugin' => 'TargetPlugin', 'status' => 'Status',
            'details' => 'Details', 'error_msg' => 'ErrorMsg', 'created_at' => 'CreatedAt',
        ],
        'Snapshots' => [
            'id' => 'Id', 'sequence' => 'Sequence', 'filename' => 'Filename',
            'filepath' => 'Filepath', 'created_at' => 'CreatedAt',
            'completed_at' => 'CompletedAt', 'status' => 'Status',
            'provider' => 'Provider', 'scope' => 'Scope',
            'tables_json' => 'TablesJson', 'table_counts_json' => 'TableCountsJson',
            'total_rows' => 'TotalRows', 'file_size' => 'FileSize',
            'duration_ms' => 'DurationMs', 'triggered_by' => 'TriggeredBy',
            'trigger_source' => 'TriggerSource', 'error_message' => 'ErrorMessage',
            'metadata_json' => 'MetadataJson', 'import_source' => 'ImportSource',
            'error' => 'Error',
        ],
        'SnapshotProgress' => [
            'id' => 'Id', 'snapshot_id' => 'SnapshotId', 'table_name' => 'TableName',
            'status' => 'Status', 'rows_total' => 'RowsTotal',
            'rows_exported' => 'RowsExported', 'started_at' => 'StartedAt',
            'completed_at' => 'CompletedAt', 'error_message' => 'ErrorMessage',
        ],
        'SnapshotJobs' => [
            'id' => 'Id', 'snapshot_dir' => 'SnapshotDir', 'tables_json' => 'TablesJson',
            'pool_size' => 'PoolSize', 'current_batch' => 'CurrentBatch',
            'tables_exported' => 'TablesExported', 'total_rows' => 'TotalRows',
            'errors_json' => 'ErrorsJson', 'status' => 'Status',
            'config_json' => 'ConfigJson', 'created_at' => 'CreatedAt',
            'updated_at' => 'UpdatedAt', 'completed_at' => 'CompletedAt',
        ],
        'SnapshotSettings' => [
            'key' => 'Key', 'value' => 'Value', 'type' => 'Type',
            'updated_at' => 'UpdatedAt',
        ],
        'SnapshotExports' => [
            'id' => 'Id', 'snapshot_id' => 'SnapshotId',
            'zip_filename' => 'ZipFilename', 'zip_path' => 'ZipPath',
            'zip_size' => 'ZipSize', 'included_ids' => 'IncludedIds',
            'incremental_count' => 'IncrementalCount', 'created_at' => 'CreatedAt',
            'expires_at' => 'ExpiresAt', 'status' => 'Status',
        ],
        'FileCache' => [
            'plugin_slug' => 'PluginSlug', 'relative_path' => 'RelativePath',
            'md5_hash' => 'Md5Hash', 'modified_at' => 'ModifiedAt',
            'file_size' => 'FileSize', 'cached_at' => 'CachedAt',
        ],
        'RemotePluginsCache' => [
            'id' => 'Id', 'site_id' => 'SiteId', 'data_json' => 'DataJson',
            'fetched_at' => 'FetchedAt', 'expires_at' => 'ExpiresAt',
        ],
        'ErrorSessions' => [
            'id' => 'Id', 'level' => 'Level', 'message' => 'Message',
            'file' => 'File', 'line' => 'Line', 'context_json' => 'ContextJson',
            'stack_trace' => 'StackTrace', 'created_at' => 'CreatedAt',
        ],
        'FlashState' => [
            'key' => 'Key', 'value' => 'Value', 'updated_at' => 'UpdatedAt',
        ],
    ];

    // ── Migration Entry Point ────────────────────────────────────────

    private function migrateV13PascalCaseTableAndColumnNames(int $current): void {
        if ($current >= 13) {
            return;
        }

        $this->fileLogger->info('Applying migration v13: PascalCase table and column name rename');

        $this->pdo->beginTransaction();

        try {
            $this->renameAllTables();
            $this->renameAllColumns();

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            $this->fileLogger->logCriticalException($e, 'Migration v13 failed — rolled back');
        }

        $this->recordMigration(13);
    }

    // ── Table Rename ─────────────────────────────────────────────────

    private function renameAllTables(): void {
        foreach (self::V13_TABLE_RENAMES as $oldName => $newName) {
            if ($this->sqliteTableExists($oldName)) {
                $this->pdo->exec("ALTER TABLE {$oldName} RENAME TO {$newName}");
                $this->fileLogger->debug("Renamed table: {$oldName} → {$newName}");
            }
        }
    }

    // ── Column Rename ────────────────────────────────────────────────

    private function renameAllColumns(): void {
        foreach (self::V13_COLUMN_RENAMES as $table => $columns) {
            if ($this->sqliteTableExists($table) === false) {
                continue;
            }

            $existingColumns = $this->getTableColumnNames($table);

            foreach ($columns as $oldCol => $newCol) {
                $isColumnPresent = in_array($oldCol, $existingColumns, true);

                if ($isColumnPresent) {
                    $this->pdo->exec("ALTER TABLE {$table} RENAME COLUMN {$oldCol} TO {$newCol}");
                }
            }

            $this->fileLogger->debug("Renamed columns in: {$table}");
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function sqliteTableExists(string $table): bool {
        $escaped = str_replace("'", "''", $table);
        $check = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='{$escaped}'");

        return (bool) $check->fetchColumn();
    }

    private function getTableColumnNames(string $table): array {
        $columns = [];
        $result = $this->pdo->query("PRAGMA table_info({$table})");

        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $row['name'];
        }

        return $columns;
    }
}
