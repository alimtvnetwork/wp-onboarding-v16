<?php
/**
 * WorkerProgressTrait — Table-level progress tracking for snapshot worker.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\TableType;

trait WorkerProgressTrait {

    /**
     * Initialize progress records for all tables.
     *
     * @param array $tables Table names.
     */
    private function initProgressRecords($tables) {
        $pdo = $this->db->getPdo();
        if (!$pdo) return;

        try {
            $stmt = $pdo->prepare("INSERT OR REPLACE INTO " . TableType::SnapshotProgress->value . "
                (snapshot_id, table_name, status, rows_total, rows_exported, started_at)
                VALUES (0, ?, 'pending', 0, 0, ?)");

            $now = gmdate('c');
            foreach ($tables as $table) {
                $count = (int) $this->wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
                $stmt->execute(array($table, $now));

                $pdo->exec("UPDATE " . TableType::SnapshotProgress->value .
                    " SET rows_total = {$count} WHERE snapshot_id = 0 AND table_name = '{$table}'");
            }
        } catch (Exception $e) {
            $this->log(LogLevelType::Warn->value, 'Failed to init progress records', array('error' => $e->getMessage()));
        }
    }

    /**
     * Update progress for a table.
     *
     * @param string      $table  Table name.
     * @param string      $status Status: pending, running, complete, failed.
     * @param int         $rows   Rows exported.
     * @param string|null $error  Error message if failed.
     */
    private function updateProgress($table, $status, $rows = 0, $error = null) {
        $pdo = $this->db->getPdo();
        if (!$pdo) return;

        try {
            $now = gmdate('c');
            $stmt = $pdo->prepare("UPDATE " . TableType::SnapshotProgress->value . "
                SET status = ?, rows_exported = ?, completed_at = ?, error_message = ?
                WHERE snapshot_id = 0 AND table_name = ?");
            $stmt->execute(array(
                $status, $rows,
                ($status === 'complete' || $status === 'failed') ? $now : null,
                $error, $table,
            ));
        } catch (Exception $e) {
            // Non-fatal
        }
    }
}
