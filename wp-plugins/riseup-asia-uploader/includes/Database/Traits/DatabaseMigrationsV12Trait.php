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

use Throwable;

use RiseupAsia\Enums\TableType;

trait DatabaseMigrationsV12Trait {

    // ── SQL Constants ────────────────────────────────────────────────

    /** Transactions.Status: lowercase → PascalCase */
    private const V12_TRANSACTIONS_STATUS_QUERY = <<<'SQL'
        UPDATE %s SET Status = CASE Status
            WHEN 'success' THEN 'Success'
            WHEN 'failed'  THEN 'Failed'
        END
        WHERE Status IN ('success', 'failed')
    SQL;

    /** Transactions.Action: snake_case → PascalCase */
    private const V12_TRANSACTIONS_ACTION_QUERY = <<<'SQL'
        UPDATE %s SET Action = CASE Action
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
        WHERE Action = LOWER(Action)
    SQL;

    /** AgentSites.Status: lowercase → PascalCase */
    private const V12_AGENT_SITES_STATUS_QUERY = <<<'SQL'
        UPDATE %s SET Status = CASE Status
            WHEN 'pending'   THEN 'Pending'
            WHEN 'connected' THEN 'Connected'
            WHEN 'error'     THEN 'Error'
        END
        WHERE Status IN ('pending', 'connected', 'error')
    SQL;

    /** AgentActions.Status: lowercase → PascalCase */
    private const V12_AGENT_ACTIONS_STATUS_QUERY = <<<'SQL'
        UPDATE %s SET Status = CASE Status
            WHEN 'success' THEN 'Success'
            WHEN 'failed'  THEN 'Failed'
        END
        WHERE Status IN ('success', 'failed')
    SQL;

    /** Snapshots.Status: lowercase → PascalCase */
    private const V12_SNAPSHOTS_STATUS_QUERY = <<<'SQL'
        UPDATE %s SET Status = CASE Status
            WHEN 'pending'   THEN 'Pending'
            WHEN 'scheduled' THEN 'Scheduled'
            WHEN 'running'   THEN 'Running'
            WHEN 'complete'  THEN 'Complete'
            WHEN 'failed'    THEN 'Failed'
        END
        WHERE Status IN ('pending', 'scheduled', 'running', 'complete', 'failed')
    SQL;

    /** SnapshotProgress.Status: lowercase → PascalCase */
    private const V12_SNAPSHOT_PROGRESS_STATUS_QUERY = <<<'SQL'
        UPDATE %s SET Status = CASE Status
            WHEN 'pending'    THEN 'Pending'
            WHEN 'scheduled'  THEN 'Scheduled'
            WHEN 'running'    THEN 'Running'
            WHEN 'complete'   THEN 'Complete'
            WHEN 'failed'     THEN 'Failed'
        END
        WHERE Status IN ('pending', 'scheduled', 'running', 'complete', 'failed')
    SQL;

    /** SnapshotSettings.Value: lowercase/snake_case → PascalCase */
    private const V12_SNAPSHOT_SETTINGS_QUERIES = [
        "UPDATE %s SET Value = 'PerTable'     WHERE Key = 'snapshot.mode'             AND Value = 'per_table'",
        "UPDATE %s SET Value = 'Incremental'  WHERE Key = 'snapshot.backup_type'      AND Value = 'incremental'",
        "UPDATE %s SET Value = 'Full'         WHERE Key = 'snapshot.backup_type'      AND Value = 'full'",
        "UPDATE %s SET Value = 'All'          WHERE Key = 'snapshot.plugin_selection'  AND Value = 'all'",
        "UPDATE %s SET Value = 'Active'       WHERE Key = 'snapshot.plugin_selection'  AND Value = 'active'",
        "UPDATE %s SET Value = 'None'         WHERE Key = 'snapshot.plugin_selection'  AND Value = 'none'",
        "UPDATE %s SET Value = 'Auto'         WHERE Key = 'snapshot.provider'          AND Value = 'auto'",
        "UPDATE %s SET Value = 'Native'       WHERE Key = 'snapshot.provider'          AND Value = 'native'",
        "UPDATE %s SET Value = 'WpReset'      WHERE Key = 'snapshot.provider'          AND Value = 'wp_reset'",
        "UPDATE %s SET Value = 'Updraft'      WHERE Key = 'snapshot.provider'          AND Value = 'updraft'",
        "UPDATE %s SET Value = 'WordPress'    WHERE Key = 'snapshot.scope'             AND Value = 'wordpress'",
        "UPDATE %s SET Value = 'All'          WHERE Key = 'snapshot.scope'             AND Value = 'all'",
        "UPDATE %s SET Value = 'Content'      WHERE Key = 'snapshot.scope'             AND Value = 'content'",
        "UPDATE %s SET Value = 'Custom'       WHERE Key = 'snapshot.scope'             AND Value = 'custom'",
        "UPDATE %s SET Value = 'Manual'       WHERE Key = 'snapshot.frequency'         AND Value = 'manual'",
        "UPDATE %s SET Value = 'Daily'        WHERE Key = 'snapshot.frequency'         AND Value = 'daily'",
        "UPDATE %s SET Value = 'Weekly'       WHERE Key = 'snapshot.frequency'         AND Value = 'weekly'",
        "UPDATE %s SET Value = 'Monthly'      WHERE Key = 'snapshot.frequency'         AND Value = 'monthly'",
        "UPDATE %s SET Value = 'Single'       WHERE Key = 'snapshot.mode'              AND Value = 'single'",
        "UPDATE %s SET Value = 'Legacy'       WHERE Key = 'snapshot.mode'              AND Value = 'legacy'",
    ];

    /** ErrorSessions.Level: UPPERCASE → PascalCase */
    private const V12_ERROR_SESSIONS_LEVEL_QUERY = <<<'SQL'
        UPDATE %s SET Level = CASE Level
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
        WHERE Level IN ('DEBUG', 'INFO', 'WARN', 'WARNING', 'ERROR', 'debug', 'info', 'warn', 'warning', 'error')
    SQL;

    /** SnapshotExports.Status: lowercase → PascalCase */
    private const V12_SNAPSHOT_EXPORTS_STATUS_QUERY = <<<'SQL'
        UPDATE %s SET Status = CASE Status
            WHEN 'valid'    THEN 'Valid'
            WHEN 'expired'  THEN 'Expired'
            WHEN 'building' THEN 'Building'
        END
        WHERE Status IN ('valid', 'expired', 'building')
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
        $errorSessions = TableType::ErrorSessions->value;

        $this->pdo->beginTransaction();

        try {
            // 1. Transactions.Status
            $this->execIfColumnExists($txn, 'Status', sprintf(self::V12_TRANSACTIONS_STATUS_QUERY, $txn));

            // 2. Transactions.Action (WHERE clause catches all-lowercase rows)
            $this->execIfColumnExists($txn, 'Action', sprintf(self::V12_TRANSACTIONS_ACTION_QUERY, $txn));

            // 3. AgentSites.Status
            $this->execIfColumnExists($agentSites, 'Status', sprintf(self::V12_AGENT_SITES_STATUS_QUERY, $agentSites));

            // 4. AgentActions.Status
            $this->execIfColumnExists($agentActions, 'Status', sprintf(self::V12_AGENT_ACTIONS_STATUS_QUERY, $agentActions));

            // 5. Snapshots.Status
            $this->execIfColumnExists($snapshots, 'Status', sprintf(self::V12_SNAPSHOTS_STATUS_QUERY, $snapshots));

            // 6. SnapshotProgress.Status
            $this->execIfColumnExists($snapshotProgress, 'Status', sprintf(self::V12_SNAPSHOT_PROGRESS_STATUS_QUERY, $snapshotProgress));

            // 7. SnapshotSettings.Value (Key-specific updates)
            foreach (self::V12_SNAPSHOT_SETTINGS_QUERIES as $query) {
                $this->execIfColumnExists($snapshotSettings, 'Value', sprintf($query, $snapshotSettings));
            }

            // 8. ErrorSessions.Level
            $this->execIfColumnExists($errorSessions, 'Level', sprintf(self::V12_ERROR_SESSIONS_LEVEL_QUERY, $errorSessions));

            // 9. SnapshotExports.Status
            $this->execIfColumnExists($snapshotExports, 'Status', sprintf(self::V12_SNAPSHOT_EXPORTS_STATUS_QUERY, $snapshotExports));

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            $this->fileLogger->logCriticalException($e, 'Migration v12 failed — rolled back');
        }

        $this->recordMigration(12);
    }
}
