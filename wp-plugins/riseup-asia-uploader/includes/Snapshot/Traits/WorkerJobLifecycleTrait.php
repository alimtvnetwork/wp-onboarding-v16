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
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\SnapshotConfigType;
use RiseupAsia\Enums\SnapshotJobStatusType;
use RiseupAsia\Enums\TableType;
use RiseupAsia\Helpers\DateHelper;

trait WorkerJobLifecycleTrait {

    private function createJob(
        string $snapshotDir,
        array $tables,
        array $config,
    ): int|false {
        $pdo = $this->db->getPdo();
        $isPdoMissing = ($pdo === null);

        if ($isPdoMissing) {

            return false;
        }

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS " . TableType::SnapshotJobs->value . " (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                SnapshotDir TEXT NOT NULL,
                TablesJson TEXT NOT NULL,
                PoolSize INTEGER NOT NULL DEFAULT " . SnapshotConfigType::WorkerPoolDefault->value . ",
                CurrentBatch INTEGER NOT NULL DEFAULT 0,
                TablesExported INTEGER NOT NULL DEFAULT 0,
                TotalRows INTEGER NOT NULL DEFAULT 0,
                ErrorsJson TEXT DEFAULT '[]',
                Status TEXT NOT NULL DEFAULT '" . SnapshotJobStatusType::Queued->value . "',
                ConfigJson TEXT,
                CreatedAt TEXT NOT NULL,
                UpdatedAt TEXT NOT NULL,
                CompletedAt TEXT
            )");

            $now = DateHelper::nowIso();
            $stmt = $pdo->prepare("INSERT INTO " . TableType::SnapshotJobs->value . "
                (SnapshotDir, TablesJson, PoolSize, Status, ConfigJson, CreatedAt, UpdatedAt)
                VALUES (?, ?, ?, ?, ?, ?, ?)");

            $stmt->execute(array(
                $snapshotDir,
                json_encode($tables),
                $this->poolSize,
                SnapshotJobStatusType::Queued->value,
                json_encode($config),
                $now,
                $now,
            ));

            return (int) $pdo->lastInsertId();
        } catch (Throwable $e) {
            $this->log(LogLevelType::Error->value, 'Failed to create snapshot job', array('error' => $e->getMessage()));

            return false;
        }
    }

    private function getJob(PDO $pdo, int $jobId): ?array {
        $stmt = $pdo->prepare("SELECT * FROM " . TableType::SnapshotJobs->value . " WHERE Id = ?");
        $stmt->execute(array($jobId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function updateJobStatus(
        PDO $pdo,
        int $jobId,
        string $status,
        ?string $error = null,
    ): void {
        $now = DateHelper::nowIso();
        $completed = ($status === SnapshotJobStatusType::Complete->value || $status === SnapshotJobStatusType::Failed->value) ? $now : null;

        $stmt = $pdo->prepare("UPDATE " . TableType::SnapshotJobs->value . "
            SET Status = ?, UpdatedAt = ?, CompletedAt = COALESCE(?, CompletedAt) WHERE Id = ?");

        $stmt->execute(array(
            $status,
            $now,
            $completed,
            $jobId,
        ));

        if ($error) {
            $job = $this->getJob($pdo, $jobId);
            $errors = json_decode($job['ErrorsJson'] ?? '[]', true);
            $errors[] = $error;
            $stmt2 = $pdo->prepare("UPDATE " . TableType::SnapshotJobs->value . " SET ErrorsJson = ? WHERE Id = ?");
            $stmt2->execute(array(json_encode($errors), $jobId));
        }
    }

    private function updateJobBatchProgress(
        PDO $pdo,
        int $jobId,
        int $nextBatch,
        int $batchExported,
        int $batchRows,
        array $batchErrors,
    ): void {
        $now = DateHelper::nowIso();
        $job = $this->getJob($pdo, $jobId);
        $all_errors = array_merge(json_decode($job['ErrorsJson'] ?? '[]', true), $batchErrors);

        $stmt = $pdo->prepare("UPDATE " . TableType::SnapshotJobs->value . "
            SET CurrentBatch = ?, TablesExported = TablesExported + ?,
                TotalRows = TotalRows + ?, ErrorsJson = ?, UpdatedAt = ?
            WHERE Id = ?");

        $stmt->execute(array(
            $nextBatch,
            $batchExported,
            $batchRows,
            json_encode($all_errors),
            $now,
            $jobId,
        ));
    }

    private function finalizeJob(
        PDO $pdo,
        int $jobId,
        string $snapshotDir,
    ): void {
        $job = $this->getJob($pdo, $jobId);
        $root_path = $snapshotDir . '/a-root.db';

        if (file_exists($root_path)) {
            try {
                $rootPdo = new PDO('sqlite:' . $root_path);
                $rootPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->rootDb->updateStats($rootPdo, (int) $job['TablesExported'], (int) $job['TotalRows']);
                $rootPdo = null;
            } catch (Throwable $e) {
                $this->log(LogLevelType::Warn->value, 'Failed to finalize a-root.db stats', array('error' => $e->getMessage()));
            }
        }

        $this->updateJobStatus($pdo, $jobId, SnapshotJobStatusType::Complete->value);

        $errors = json_decode($job['ErrorsJson'] ?? '[]', true);

        $this->log(LogLevelType::Info->value, 'Snapshot job complete', array(
            'job_id'                              => $jobId,
            'tables_exported'                     => $job['TablesExported'],
            ResponseKeyType::TotalRows->value     => $job['TotalRows'],
            ResponseKeyType::Errors->value        => count($errors),
        ));
    }

    private function scheduleNextBatch(int $jobId): void {
        wp_schedule_single_event(
            time() + 5,
            HookType::CronSnapshotWorkerBatch->value,
            array(array('job_id' => $jobId)),
        );
    }
}
