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
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\SnapshotJobStatusType;
use RiseupAsia\Helpers\PathHelper;

trait WorkerBatchProcessTrait {
    public function processWorkerBatch(array $args): void {
        $job_id = $args['job_id'] ?? 0;
        $isJobIdMissing = ($job_id === 0);

        if ($isJobIdMissing) {
            $this->log(LogLevelType::Error->value, 'Worker batch called without job_id');

            return;
        }

        $pdo = $this->db->getPdo();
        $isPdoMissing = ($pdo === null);

        if ($isPdoMissing) {
            $this->log(LogLevelType::Error->value, 'No database connection for worker batch');

            return;
        }

        try {
            $job = $this->getJob($pdo, $job_id);
            $isJobMissing = ($job === null || $job === false);

            if ($isJobMissing) {
                $this->log(LogLevelType::Error->value, 'Job not found', array('job_id' => $job_id));

                return;
            }

            $this->updateJobStatus($pdo, $job_id, SnapshotJobStatusType::Processing->value);
            $this->processJobBatch($pdo, $job_id, $job);
        } catch (Throwable $e) {
            $this->log(LogLevelType::Error->value, 'Worker batch failed', array(
                'job_id'                        => $job_id,
                ResponseKeyType::Error->value   => $e->getMessage(),
                'trace'                         => $e->getTraceAsString(),
            ));
            $this->updateJobStatus($pdo, $job_id, SnapshotJobStatusType::Failed->value, $e->getMessage());
        }
    }

    private function processJobBatch(
        PDO $pdo,
        int $jobId,
        array $job,
    ): void {
        $allTables  = json_decode($job['TablesJson'], true);
        $poolSize   = (int) $job['PoolSize'];
        $batchIndex = (int) $job['CurrentBatch'];
        $batches    = array_chunk($allTables, $poolSize);

        if ($batchIndex >= count($batches)) {
            $this->finalizeJob($pdo, $jobId, $job['SnapshotDir']);

            return;
        }

        $this->log(LogLevelType::Info->value, sprintf(
            'Batch %d/%d: exporting %d tables',
            $batchIndex + 1,
            count($batches),
            count($batches[$batchIndex]),
        ));

        $rootPdo = $this->openRootDbForBatch($job['SnapshotDir']);
        $result = $this->exportBatchTables($batches[$batchIndex], $job['SnapshotDir'], $rootPdo);
        $rootPdo = null;

        $this->updateJobBatchProgress(
            $pdo,
            $jobId,
            $batchIndex + 1,
            $result[ResponseKeyType::Exported->value],
            $result[ResponseKeyType::Rows->value],
            $result[ResponseKeyType::Errors->value],
        );

        $nextBatch = $batchIndex + 1;

        if ($nextBatch < count($batches)) {
            $this->scheduleNextBatch($jobId);
            $this->log(LogLevelType::Info->value, sprintf('Next batch scheduled (%d/%d)', $nextBatch + 1, count($batches)));
        } else {
            $this->finalizeJob($pdo, $jobId, $job['SnapshotDir']);
        }
    }

    private function openRootDbForBatch(string $snapshotDir): ?PDO {
        $rootPath = $snapshotDir . '/a-root.db';

        if (PathHelper::isFileMissing($rootPath)) {

            return null;
        }

        $rootPdo = new PDO('sqlite:' . $rootPath);
        $rootPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $rootPdo;
    }
}
