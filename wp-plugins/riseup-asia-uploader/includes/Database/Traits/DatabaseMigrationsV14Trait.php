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
            WHEN 'rest_api'      THEN 'RestAPI'
            WHEN 'admin_ui'      THEN 'AdminUI'
            WHEN 'wp_cli'        THEN 'WPCLI'
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
            $this->pdo->exec(sprintf(self::V14_TRANSACTIONS_TRIGGERED_BY_QUERY, $txn));

            // 2. Transactions.UploadSource
            $this->pdo->exec(sprintf(self::V14_TRANSACTIONS_UPLOAD_SOURCE_QUERY, $txn));

            // 3. Snapshots.TriggeredBy
            $this->pdo->exec(sprintf(self::V14_SNAPSHOTS_TRIGGERED_BY_QUERY, $snapshots));

            // 4. Snapshots.TriggerSource
            $this->pdo->exec(sprintf(self::V14_SNAPSHOTS_TRIGGER_SOURCE_QUERY, $snapshots));

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            $this->fileLogger->logException($e, 'Migration v14 failed — rolled back');

            throw $e;
        }

        $this->recordMigration(14);
    }
}
