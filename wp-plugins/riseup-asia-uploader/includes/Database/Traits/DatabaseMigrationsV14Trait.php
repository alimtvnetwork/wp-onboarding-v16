<?php
/**
 * DatabaseMigrationsV14Trait — PascalCase value migration for remaining columns.
 *
 * Normalizes TriggeredBy, UploadSource, and TriggerSource values
 * from legacy snake_case/lowercase to PascalCase enum values.
 *
 * @package RiseupAsia\Database\Traits
 * @since   2.5.0
 */

namespace RiseupAsia\Database\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use Throwable;
use RiseupAsia\Enums\TableType;

trait DatabaseMigrationsV14Trait {

    // ── SQL Constants ────────────────────────────────────────────────

    /** Transactions.TriggeredBy: snake_case/lowercase → PascalCase (TriggerSourceType) */
    private const V14_TRANSACTIONS_TRIGGERED_BY_QUERY = <<<'SQL'
        UPDATE %s SET TriggeredBy = CASE TriggeredBy
            WHEN 'api'        THEN 'Api'
            WHEN 'dashboard'  THEN 'Dashboard'
            WHEN 'agent_push' THEN 'AgentPush'
            WHEN 'cron'       THEN 'Cron'
            WHEN 'cli'        THEN 'Cli'
        END
        WHERE TriggeredBy IN ('api', 'dashboard', 'agent_push', 'cron', 'cli')
    SQL;

    /** Transactions.UploadSource: snake_case → PascalCase (UploadSourceType) */
    private const V14_TRANSACTIONS_UPLOAD_SOURCE_QUERY = <<<'SQL'
        UPDATE %s SET UploadSource = CASE UploadSource
            WHEN 'upload_script' THEN 'Script'
            WHEN 'rest_api'      THEN 'RestApi'
            WHEN 'admin_ui'      THEN 'AdminUi'
            WHEN 'wp_cli'        THEN 'WpCli'
        END
        WHERE UploadSource IN ('upload_script', 'rest_api', 'admin_ui', 'wp_cli')
    SQL;

    /** Snapshots.TriggeredBy: lowercase → PascalCase (SnapshotTriggerType) */
    private const V14_SNAPSHOTS_TRIGGERED_BY_QUERY = <<<'SQL'
        UPDATE %s SET TriggeredBy = CASE TriggeredBy
            WHEN 'manual'    THEN 'Manual'
            WHEN 'scheduled' THEN 'Scheduled'
            WHEN 'cron'      THEN 'Cron'
            WHEN 'api'       THEN 'Api'
        END
        WHERE TriggeredBy IN ('manual', 'scheduled', 'cron', 'api')
    SQL;

    /** Snapshots.TriggerSource: snake_case/lowercase → PascalCase (TriggerSourceType) */
    private const V14_SNAPSHOTS_TRIGGER_SOURCE_QUERY = <<<'SQL'
        UPDATE %s SET TriggerSource = CASE TriggerSource
            WHEN 'api'        THEN 'Api'
            WHEN 'dashboard'  THEN 'Dashboard'
            WHEN 'agent_push' THEN 'AgentPush'
            WHEN 'cron'       THEN 'Cron'
            WHEN 'cli'        THEN 'Cli'
        END
        WHERE TriggerSource IN ('api', 'dashboard', 'agent_push', 'cron', 'cli')
    SQL;

    // ── Helpers ────────────────────────────────────────────────────────

    /** Execute SQL only if the target column exists in the table. */
    private function execIfColumnExists(string $table, string $column, string $sql): void {
        $stmt = $this->pdo->query("PRAGMA table_info($table)");
        $columns = $stmt->fetchAll(\PDO::FETCH_COLUMN, 1);
        $hasColumn = in_array($column, $columns, true);

        if ($hasColumn === false) {
            $this->fileLogger->info("Skipping migration: column $column not found in $table");

            return;
        }

        $this->pdo->exec($sql);
    }

    // ── Migration Entry Point ────────────────────────────────────────

    private function migrateV14PascalCaseRemainingValues(int $current): void {
        if ($current >= 14) {
            return;
        }

        $this->fileLogger->info('Applying migration v14: PascalCase value normalization for TriggeredBy, UploadSource, TriggerSource');

        $txn = TableType::Transactions->value;
        $snapshots = TableType::Snapshots->value;

        $this->pdo->beginTransaction();

        try {
            // 1. Transactions.TriggeredBy
            $this->execIfColumnExists($txn, 'TriggeredBy', sprintf(self::V14_TRANSACTIONS_TRIGGERED_BY_QUERY, $txn));

            // 2. Transactions.UploadSource
            $this->execIfColumnExists($txn, 'UploadSource', sprintf(self::V14_TRANSACTIONS_UPLOAD_SOURCE_QUERY, $txn));

            // 3. Snapshots.TriggeredBy
            $this->execIfColumnExists($snapshots, 'TriggeredBy', sprintf(self::V14_SNAPSHOTS_TRIGGERED_BY_QUERY, $snapshots));

            // 4. Snapshots.TriggerSource
            $this->execIfColumnExists($snapshots, 'TriggerSource', sprintf(self::V14_SNAPSHOTS_TRIGGER_SOURCE_QUERY, $snapshots));

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            $this->fileLogger->logCriticalException($e, 'Migration v14 failed — rolled back');
        }

        $this->recordMigration(14);
    }
}
