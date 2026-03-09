<?php
/**
 * WorkerBatchProcessTrait — WP-Cron batch dispatch and job batch processing.
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
use RiseupAsia\Enums\PathDatabaseType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\SnapshotJobStatusType;
use RiseupAsia\Helpers\PathHelper;

trait WorkerBatchProcessTrait {
    public function processWorkerBatch(array $args): void {
        $jobId = $args['jobId'] ?? 0;
        $isJobIdMissing = ($jobId === 0);

        if ($isJobIdMissing) {
            $this->log(LogLevelType::Error->value, 'Worker batch called without jobId');

            return;
        }

        $pdo = $this->db->getPdo();
        $isPdoMissing = ($pdo === null);

        if ($isPdoMissing) {
            $this->log(LogLevelType::Error->value, 'No database connection for worker batch');

            return;
        }

        $this->executeWorkerBatch($pdo, $jobId);
    }

    private function executeWorkerBatch(PDO $pdo, int $jobId): void {
        try {
            $job = $this->getJob($pdo, $jobId);
            $isJobMissing = ($job === null || $job === false);

            if ($isJobMissing) {
                $this->log(LogLevelType::Error->value, 'Job not found', array('jobId' => $jobId));

                return;
            }

            $this->updateJobStatus($pdo, $jobId, SnapshotJobStatusType::Processing->value);
            $this->processJobBatch($pdo, $jobId, $job);
        } catch (Throwable $e) {
            $this->handleBatchException($pdo, $jobId, $e);
        }
    }

    private function handleBatchException(PDO $pdo, int $jobId, Throwable $e): void {
        $this->log(LogLevelType::Error->value, 'Worker batch failed', array(
            'jobId'                         => $jobId,
            ResponseKeyType::Error->value   => $e->getMessage(),
            'trace'                         => $e->getTraceAsString(),
        ));
        $this->updateJobStatus($pdo, $jobId, SnapshotJobStatusType::Failed->value, $e->getMessage());
    }

    private function processJobBatch(PDO $pdo, int $jobId, array $job): void {
        $allTables  = json_decode($job['TablesJson'], true);
        $poolSize   = (int) $job['PoolSize'];
        $batchIndex = (int) $job['CurrentBatch'];
        $batches    = array_chunk($allTables, $poolSize);

        if ($batchIndex >= count($batches)) {
            $this->finalizeJob($pdo, $jobId, $job['SnapshotDir']);

            return;
        }

        $this->exportAndAdvanceBatch($pdo, $jobId, $job, $batches, $batchIndex);
    }

    private function exportAndAdvanceBatch(PDO $pdo, int $jobId, array $job, array $batches, int $batchIndex): void {
        $this->logBatchStart($batchIndex, $batches);

        $rootPdo = $this->openRootDbForBatch($job['SnapshotDir']);
        $result = $this->exportBatchTables($batches[$batchIndex], $job['SnapshotDir'], $rootPdo);
        $rootPdo = null;

        $this->updateJobBatchProgress(
            $pdo, $jobId, $batchIndex + 1,
            $result[ResponseKeyType::Exported->value],
            $result[ResponseKeyType::Rows->value],
            $result[ResponseKeyType::Errors->value],
        );

        $this->advanceOrFinalize($pdo, $jobId, $job['SnapshotDir'], $batchIndex + 1, count($batches));
    }

    private function logBatchStart(int $batchIndex, array $batches): void {
        $this->log(LogLevelType::Info->value, sprintf(
            'Batch %d/%d: exporting %d tables',
            $batchIndex + 1,
            count($batches),
            count($batches[$batchIndex]),
        ));
    }

    private function advanceOrFinalize(PDO $pdo, int $jobId, string $snapshotDir, int $nextBatch, int $totalBatches): void {
        if ($nextBatch < $totalBatches) {
            $this->scheduleNextBatch($jobId);
            $this->log(LogLevelType::Info->value, sprintf('Next batch scheduled (%d/%d)', $nextBatch + 1, $totalBatches));

            return;
        }

        $this->finalizeJob($pdo, $jobId, $snapshotDir);
    }

    private function openRootDbForBatch(string $snapshotDir): ?PDO {
        $rootPath = $snapshotDir . PathDatabaseType::Root->value;

        if (PathHelper::isFileMissing($rootPath)) {

            return null;
        }

        $rootPdo = new PDO('sqlite:' . $rootPath);
        $rootPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $rootPdo;
    }
}
