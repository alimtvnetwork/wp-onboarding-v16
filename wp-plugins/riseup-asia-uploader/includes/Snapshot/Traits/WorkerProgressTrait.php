<?php
/**
 * WorkerProgressTrait — Table-level progress tracking for snapshot worker.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\SnapshotStatusType;
use RiseupAsia\Enums\TableType;

trait WorkerProgressTrait {

    private function initProgressRecords(array $tables): void {
        $pdo = $this->db->getPdo();
        if (!$pdo) return;

        try {
            $stmt = $pdo->prepare("INSERT OR REPLACE INTO " . TableType::SnapshotProgress->value . "
                (snapshot_id, table_name, status, rows_total, rows_exported, started_at)
                VALUES (0, ?, '" . SnapshotStatusType::Pending->value . "', 0, 0, ?)");

            $now = gmdate('c');
            foreach ($tables as $table) {
                $count = (int) $this->wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
                $stmt->execute(array($table, $now));

                $pdo->exec("UPDATE " . TableType::SnapshotProgress->value .
                    " SET rows_total = {$count} WHERE snapshot_id = 0 AND table_name = '{$table}'");
            }
        } catch (Throwable $e) {
            $this->log(LogLevelType::Warn->value, 'Failed to init progress records', array('error' => $e->getMessage()));
        }
    }

    private function updateProgress(string $table, string $status, int $rows = 0, ?string $error = null): void {
        $pdo = $this->db->getPdo();
        if (!$pdo) return;

        try {
            $now = gmdate('c');
            $stmt = $pdo->prepare("UPDATE " . TableType::SnapshotProgress->value . "
                SET status = ?, rows_exported = ?, completed_at = ?, error_message = ?
                WHERE snapshot_id = 0 AND table_name = ?");
            $stmt->execute(array(
                $status, $rows,
                ($status === SnapshotStatusType::Complete->value || $status === SnapshotStatusType::Failed->value) ? $now : null,
                $error, $table,
            ));
        } catch (Throwable $e) {
            // Non-fatal
        }
    }
}
}
