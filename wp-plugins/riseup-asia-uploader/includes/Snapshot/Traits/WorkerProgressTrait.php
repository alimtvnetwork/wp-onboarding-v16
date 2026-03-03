<?php
/**
 * WorkerProgressTrait — Table-level progress tracking for snapshot worker.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   2.0.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use PDO;
use Throwable;
use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\SnapshotStatusType;
use RiseupAsia\Enums\TableType;
use RiseupAsia\Helpers\DateHelper;

trait WorkerProgressTrait {
    private function initProgressRecords(array $tables): void {
        $pdo = $this->db->getPdo();
        $isPdoMissing = ($pdo === null);

        if ($isPdoMissing) {

            return;
        }

        try {
            $stmt = $pdo->prepare("INSERT OR REPLACE INTO " . TableType::SnapshotProgress->value . "
                (SnapshotId, TableName, Status, RowsTotal, RowsExported, StartedAt)
                VALUES (0, ?, '" . SnapshotStatusType::Pending->value . "', 0, 0, ?)");

            $now = DateHelper::nowIso();

            foreach ($tables as $table) {
                $count = (int) $this->wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
                $stmt->execute(array($table, $now));

                $pdo->exec("UPDATE " . TableType::SnapshotProgress->value .
                    " SET RowsTotal = {$count} WHERE SnapshotId = 0 AND TableName = '{$table}'");
            }
        } catch (Throwable $e) {
            $this->logWarn($e, 'Failed to init progress records');
        }
    }

    private function updateProgress(
        string $table,
        string $status,
        int $rows = 0,
        ?string $error = null,
    ): void {
        $pdo = $this->db->getPdo();
        $isPdoMissing = ($pdo === null);

        if ($isPdoMissing) {

            return;
        }

        try {
            $now = DateHelper::nowIso();
            $stmt = $pdo->prepare("UPDATE " . TableType::SnapshotProgress->value . "
                SET Status = ?, RowsExported = ?, CompletedAt = ?, ErrorMessage = ?
                WHERE SnapshotId = 0 AND TableName = ?");

            $stmt->execute(array(
                $status,
                $rows,
                ($status === SnapshotStatusType::Complete->value || $status === SnapshotStatusType::Failed->value) ? $now : null,
                $error,
                $table,
            ));
        } catch (Throwable $e) {
            $this->logWarn($e, 'Failed to update progress');
        }
    }
}
