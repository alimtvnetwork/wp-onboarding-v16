<?php
/**
 * DatabaseMigrationsV12Trait — PascalCase enum value migration.
 *
 * Upgrades all stored snake_case/lowercase/UPPERCASE enum strings
 * to PascalCase to match the canonical PHP enum values.
 *
 * @package RiseupAsia\Database\Traits
 * @since   2.3.0
 */

namespace RiseupAsia\Database\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\TableType;

trait DatabaseMigrationsV12Trait {

    // ── SQL Constants ────────────────────────────────────────────────

    /** transactions.status: lowercase → PascalCase */
    private const V12_TRANSACTIONS_STATUS_QUERY = <<<'SQL'
        UPDATE %s SET status = CASE status
            WHEN 'success' THEN 'Success'
            WHEN 'failed'  THEN 'Failed'
        END
        WHERE status IN ('success', 'failed')
    SQL;

    /** transactions.action: snake_case → PascalCase */
    private const V12_TRANSACTIONS_ACTION_QUERY = <<<'SQL'
        UPDATE %s SET action = CASE action
            WHEN 'upload'                    THEN 'Upload'
            WHEN 'upload_active'             THEN 'UploadActive'
            WHEN 'upload_initiated'          THEN 'UploadInitiated'
            WHEN 'enable'                    THEN 'Enable'
            WHEN 'disable'                   THEN 'Disable'
            WHEN 'delete'                    THEN 'Delete'
            WHEN 'file_replace'              THEN 'FileReplace'
            WHEN 'file_delete'               THEN 'FileDelete'
            WHEN 'sync'                      THEN 'Sync'
            WHEN 'sync_delete'               THEN 'SyncDelete'
            WHEN 'post_create'               THEN 'PostCreate'
            WHEN 'post_update'               THEN 'PostUpdate'
            WHEN 'category_create'           THEN 'CategoryCreate'
            WHEN 'media_upload'              THEN 'MediaUpload'
            WHEN 'auth_failed'               THEN 'AuthFailed'
            WHEN 'export_self'               THEN 'ExportSelf'
            WHEN 'export_plugin'             THEN 'ExportPlugin'
            WHEN 'update_check'              THEN 'UpdateCheck'
            WHEN 'update_resolve'            THEN 'UpdateResolve'
            WHEN 'update_download'           THEN 'UpdateDownload'
            WHEN 'update_install'            THEN 'UpdateInstall'
            WHEN 'agent_add'                 THEN 'AgentAdd'
            WHEN 'agent_remove'              THEN 'AgentRemove'
            WHEN 'agent_test'                THEN 'AgentTest'
            WHEN 'agent_sync'                THEN 'AgentSync'
            WHEN 'agent_plugin_enable'       THEN 'AgentPluginEnable'
            WHEN 'agent_plugin_disable'      THEN 'AgentPluginDisable'
            WHEN 'agent_plugin_delete'       THEN 'AgentPluginDelete'
            WHEN 'agent_plugin_update'       THEN 'AgentPluginUpdate'
            WHEN 'agent_api_error'           THEN 'AgentApiError'
            WHEN 'snapshot_create'           THEN 'SnapshotCreate'
            WHEN 'snapshot_restore'          THEN 'SnapshotRestore'
            WHEN 'snapshot_delete'           THEN 'SnapshotDelete'
            WHEN 'snapshot_export'           THEN 'SnapshotExport'
            WHEN 'snapshot_import'           THEN 'SnapshotImport'
            WHEN 'snapshot_cleanup'          THEN 'SnapshotCleanup'
            WHEN 'snapshot_full_backup'      THEN 'SnapshotFullBackup'
            WHEN 'snapshot_incremental'      THEN 'SnapshotIncremental'
            WHEN 'snapshot_restore_per_table' THEN 'SnapshotRestorePerTable'
            WHEN 'snapshot_import_per_table' THEN 'SnapshotImportPerTable'
            WHEN 'snapshot_settings_update'  THEN 'SnapshotSettingsUpdate'
            WHEN 'snapshot_zip_build'        THEN 'SnapshotZipBuild'
            WHEN 'snapshot_zip_expire'       THEN 'SnapshotZipExpire'
            WHEN 'snapshot_zip_download'     THEN 'SnapshotZipDownload'
        END
        WHERE action = LOWER(action) AND action NOT LIKE '%[A-Z]%'
    SQL;

    /** agent_sites.status: lowercase → PascalCase */
    private const V12_AGENT_SITES_STATUS_QUERY = <<<'SQL'
        UPDATE %s SET status = CASE status
            WHEN 'pending'   THEN 'Pending'
            WHEN 'connected' THEN 'Connected'
            WHEN 'error'     THEN 'Error'
        END
        WHERE status IN ('pending', 'connected', 'error')
    SQL;

    /** agent_actions.status: lowercase → PascalCase */
    private const V12_AGENT_ACTIONS_STATUS_QUERY = <<<'SQL'
        UPDATE %s SET status = CASE status
            WHEN 'success' THEN 'Success'
            WHEN 'failed'  THEN 'Failed'
        END
        WHERE status IN ('success', 'failed')
    SQL;

    /** snapshots.status: lowercase → PascalCase */
    private const V12_SNAPSHOTS_STATUS_QUERY = <<<'SQL'
        UPDATE %s SET status = CASE status
            WHEN 'pending'   THEN 'Pending'
            WHEN 'scheduled' THEN 'Scheduled'
            WHEN 'running'   THEN 'Running'
            WHEN 'complete'  THEN 'Complete'
            WHEN 'failed'    THEN 'Failed'
        END
        WHERE status IN ('pending', 'scheduled', 'running', 'complete', 'failed')
    SQL;

    /** snapshot_progress.status: lowercase → PascalCase */
    private const V12_SNAPSHOT_PROGRESS_STATUS_QUERY = <<<'SQL'
        UPDATE %s SET status = CASE status
            WHEN 'pending'    THEN 'Pending'
            WHEN 'scheduled'  THEN 'Scheduled'
            WHEN 'running'    THEN 'Running'
            WHEN 'complete'   THEN 'Complete'
            WHEN 'failed'     THEN 'Failed'
        END
        WHERE status IN ('pending', 'scheduled', 'running', 'complete', 'failed')
    SQL;

    /** snapshot_settings.value: lowercase/snake_case → PascalCase */
    private const V12_SNAPSHOT_SETTINGS_QUERIES = [
        "UPDATE %s SET value = 'PerTable'     WHERE key = 'snapshot.mode'             AND value = 'per_table'",
        "UPDATE %s SET value = 'Incremental'  WHERE key = 'snapshot.backup_type'      AND value = 'incremental'",
        "UPDATE %s SET value = 'Full'         WHERE key = 'snapshot.backup_type'      AND value = 'full'",
        "UPDATE %s SET value = 'All'          WHERE key = 'snapshot.plugin_selection'  AND value = 'all'",
        "UPDATE %s SET value = 'Active'       WHERE key = 'snapshot.plugin_selection'  AND value = 'active'",
        "UPDATE %s SET value = 'None'         WHERE key = 'snapshot.plugin_selection'  AND value = 'none'",
        "UPDATE %s SET value = 'Auto'         WHERE key = 'snapshot.provider'          AND value = 'auto'",
        "UPDATE %s SET value = 'Native'       WHERE key = 'snapshot.provider'          AND value = 'native'",
        "UPDATE %s SET value = 'WpReset'      WHERE key = 'snapshot.provider'          AND value = 'wp_reset'",
        "UPDATE %s SET value = 'Updraft'      WHERE key = 'snapshot.provider'          AND value = 'updraft'",
        "UPDATE %s SET value = 'WordPress'    WHERE key = 'snapshot.scope'             AND value = 'wordpress'",
        "UPDATE %s SET value = 'All'          WHERE key = 'snapshot.scope'             AND value = 'all'",
        "UPDATE %s SET value = 'Content'      WHERE key = 'snapshot.scope'             AND value = 'content'",
        "UPDATE %s SET value = 'Custom'       WHERE key = 'snapshot.scope'             AND value = 'custom'",
        "UPDATE %s SET value = 'Manual'       WHERE key = 'snapshot.frequency'         AND value = 'manual'",
        "UPDATE %s SET value = 'Daily'        WHERE key = 'snapshot.frequency'         AND value = 'daily'",
        "UPDATE %s SET value = 'Weekly'       WHERE key = 'snapshot.frequency'         AND value = 'weekly'",
        "UPDATE %s SET value = 'Monthly'      WHERE key = 'snapshot.frequency'         AND value = 'monthly'",
        "UPDATE %s SET value = 'Single'       WHERE key = 'snapshot.mode'              AND value = 'single'",
        "UPDATE %s SET value = 'Legacy'       WHERE key = 'snapshot.mode'              AND value = 'legacy'",
    ];

    /** error_sessions.level: UPPERCASE → PascalCase */
    private const V12_ERROR_SESSIONS_LEVEL_QUERY = <<<'SQL'
        UPDATE error_sessions SET level = CASE level
            WHEN 'DEBUG'   THEN 'Debug'
            WHEN 'INFO'    THEN 'Info'
            WHEN 'WARN'    THEN 'Warn'
            WHEN 'WARNING' THEN 'Warn'
            WHEN 'ERROR'   THEN 'Error'
            WHEN 'debug'   THEN 'Debug'
            WHEN 'info'    THEN 'Info'
            WHEN 'warn'    THEN 'Warn'
            WHEN 'warning' THEN 'Warn'
            WHEN 'error'   THEN 'Error'
        END
        WHERE level IN ('DEBUG', 'INFO', 'WARN', 'WARNING', 'ERROR', 'debug', 'info', 'warn', 'warning', 'error')
    SQL;

    /** snapshot_exports.status: lowercase → PascalCase */
    private const V12_SNAPSHOT_EXPORTS_STATUS_QUERY = <<<'SQL'
        UPDATE %s SET status = CASE status
            WHEN 'valid'    THEN 'Valid'
            WHEN 'expired'  THEN 'Expired'
            WHEN 'building' THEN 'Building'
        END
        WHERE status IN ('valid', 'expired', 'building')
    SQL;

    // ── Migration Entry Point ────────────────────────────────────────

    private function migrateV12PascalCaseEnumValues(int $current): void {
        if ($current >= 12) {
            return;
        }

        $this->fileLogger->info('Applying migration v12: PascalCase enum value normalization');

        $txn = TableType::Transactions->value;
        $agentSites = TableType::AgentSites->value;
        $agentActions = TableType::AgentActions->value;
        $snapshots = TableType::Snapshots->value;
        $snapshotProgress = TableType::SnapshotProgress->value;
        $snapshotSettings = TableType::SnapshotSettings->value;
        $snapshotExports = TableType::SnapshotExports->value;

        $this->pdo->beginTransaction();

        try {
            // 1. transactions.status
            $this->pdo->exec(sprintf(self::V12_TRANSACTIONS_STATUS_QUERY, $txn));

            // 2. transactions.action (WHERE clause catches all-lowercase rows)
            $this->pdo->exec(sprintf(self::V12_TRANSACTIONS_ACTION_QUERY, $txn));

            // 3. agent_sites.status
            $this->pdo->exec(sprintf(self::V12_AGENT_SITES_STATUS_QUERY, $agentSites));

            // 4. agent_actions.status
            $this->pdo->exec(sprintf(self::V12_AGENT_ACTIONS_STATUS_QUERY, $agentActions));

            // 5. snapshots.status
            $this->pdo->exec(sprintf(self::V12_SNAPSHOTS_STATUS_QUERY, $snapshots));

            // 6. snapshot_progress.status
            $this->pdo->exec(sprintf(self::V12_SNAPSHOT_PROGRESS_STATUS_QUERY, $snapshotProgress));

            // 7. snapshot_settings.value (key-specific updates)
            foreach (self::V12_SNAPSHOT_SETTINGS_QUERIES as $query) {
                $this->pdo->exec(sprintf($query, $snapshotSettings));
            }

            // 8. error_sessions.level
            $this->pdo->exec(self::V12_ERROR_SESSIONS_LEVEL_QUERY);

            // 9. snapshot_exports.status
            $this->pdo->exec(sprintf(self::V12_SNAPSHOT_EXPORTS_STATUS_QUERY, $snapshotExports));

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            $this->fileLogger->logException($e, 'Migration v12 failed — rolled back');

            throw $e;
        }

        $this->recordMigration(12);
    }
}
