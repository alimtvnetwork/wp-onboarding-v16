<?php
/**
 * WorkerJobLifecycleTrait — Job creation, status updates, batch progress, and finalization.
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
use RiseupAsia\Enums\HookType;
use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\SnapshotJobStatusType;
use RiseupAsia\Enums\TableType;

trait WorkerJobLifecycleTrait {

    private function createJob(string $snapshotDir, array $tables, array $config): int|false {
        $pdo = $this->db->getPdo();
        if (!$pdo) return false;

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS " . TableType::SnapshotJobs->value . " (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                snapshot_dir TEXT NOT NULL,
                tables_json TEXT NOT NULL,
                pool_size INTEGER NOT NULL DEFAULT " . \RiseupAsia\Enums\SnapshotConfigType::WorkerPoolDefault->value . ",
                current_batch INTEGER NOT NULL DEFAULT 0,
                tables_exported INTEGER NOT NULL DEFAULT 0,
                total_rows INTEGER NOT NULL DEFAULT 0,
                errors_json TEXT DEFAULT '[]',
                status TEXT NOT NULL DEFAULT '" . SnapshotJobStatusType::Queued->value . "',
                config_json TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                completed_at TEXT
            )");

            $now = gmdate('c');
            $stmt = $pdo->prepare("INSERT INTO " . TableType::SnapshotJobs->value . "
                (snapshot_dir, tables_json, pool_size, status, config_json, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)");

            $stmt->execute(array(
                $snapshotDir, json_encode($tables), $this->poolSize,
                SnapshotJobStatusType::Queued->value, json_encode($config), $now, $now,
            ));

            return (int) $pdo->lastInsertId();
        } catch (Throwable $e) {
            $this->log(LogLevelType::Error->value, 'Failed to create snapshot job', array('error' => $e->getMessage()));
            return false;
        }
    }

    private function getJob(PDO $pdo, int $jobId): ?array {
        $stmt = $pdo->prepare("SELECT * FROM " . TableType::SnapshotJobs->value . " WHERE id = ?");
        $stmt->execute(array($jobId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function updateJobStatus(PDO $pdo, int $jobId, string $status, ?string $error = null): void {
        $now = gmdate('c');
        $completed = ($status === SnapshotJobStatusType::Complete->value || $status === SnapshotJobStatusType::Failed->value) ? $now : null;

        $stmt = $pdo->prepare("UPDATE " . TableType::SnapshotJobs->value . "
            SET status = ?, updated_at = ?, completed_at = COALESCE(?, completed_at) WHERE id = ?");
        $stmt->execute(array($status, $now, $completed, $jobId));

        if ($error) {
            $job = $this->getJob($pdo, $jobId);
            $errors = json_decode($job['errors_json'] ?? '[]', true);
            $errors[] = $error;
            $stmt2 = $pdo->prepare("UPDATE " . TableType::SnapshotJobs->value . " SET errors_json = ? WHERE id = ?");
            $stmt2->execute(array(json_encode($errors), $jobId));
        }
    }

    private function updateJobBatchProgress(PDO $pdo, int $jobId, int $nextBatch, int $batchExported, int $batchRows, array $batchErrors): void {
        $now = gmdate('c');
        $job = $this->getJob($pdo, $jobId);
        $all_errors = array_merge(json_decode($job['errors_json'] ?? '[]', true), $batchErrors);

        $stmt = $pdo->prepare("UPDATE " . TableType::SnapshotJobs->value . "
            SET current_batch = ?, tables_exported = tables_exported + ?,
                total_rows = total_rows + ?, errors_json = ?, updated_at = ?
            WHERE id = ?");

        $stmt->execute(array($nextBatch, $batchExported, $batchRows, json_encode($all_errors), $now, $jobId));
    }

    private function finalizeJob(PDO $pdo, int $jobId, string $snapshotDir): void {
        $job = $this->getJob($pdo, $jobId);

        $root_path = $snapshotDir . '/a-root.db';
        if (file_exists($root_path)) {
            try {
                $rootPdo = new PDO('sqlite:' . $root_path);
                $rootPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->rootDb->updateStats($rootPdo, (int) $job['tables_exported'], (int) $job['total_rows']);
                $rootPdo = null;
            } catch (Throwable $e) {
                $this->log(LogLevelType::Warn->value, 'Failed to finalize a-root.db stats', array('error' => $e->getMessage()));
            }
        }

        $this->updateJobStatus($pdo, $jobId, SnapshotJobStatusType::Complete->value);

        $errors = json_decode($job['errors_json'] ?? '[]', true);
        $this->log(LogLevelType::Info->value, 'Snapshot job complete', array(
            'job_id' => $jobId, 'tables_exported' => $job['tables_exported'],
            'total_rows' => $job['total_rows'], 'errors' => count($errors),
        ));
    }

    private function scheduleNextBatch(int $jobId): void {
        wp_schedule_single_event(time() + 5, HookType::CronSnapshotWorkerBatch->value, array(array('job_id' => $jobId)));
    }
}
