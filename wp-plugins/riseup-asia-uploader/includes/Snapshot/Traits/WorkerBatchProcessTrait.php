<?php
/**
 * WorkerBatchProcessTrait — WP-Cron batch dispatch and job batch processing.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

use RiseupAsia\Enums\LogLevelType;

trait WorkerBatchProcessTrait {

    /**
     * Process a single worker batch (called by WP-Cron).
     *
     * @param array $args { job_id: int }
     */
    public function processWorkerBatch($args) {
        $job_id = $args['job_id'] ?? 0;
        if (!$job_id) {
            $this->log(LogLevelType::Error->value, 'Worker batch called without job_id');
            return;
        }

        $pdo = $this->db->get_pdo();
        if (!$pdo) {
            $this->log(LogLevelType::Error->value, 'No database connection for worker batch');
            return;
        }

        try {
            $job = $this->getJob($pdo, $job_id);
            if (!$job) {
                $this->log(LogLevelType::Error->value, 'Job not found', array('job_id' => $job_id));
                return;
            }

            $this->updateJobStatus($pdo, $job_id, SNAPSHOT_JOB_STATUS_PROCESSING);
            $this->processJobBatch($pdo, $job_id, $job);
        } catch (Exception $e) {
            $this->log(LogLevelType::Error->value, 'Worker batch failed', array(
                'job_id' => $job_id, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString(),
            ));
            $this->updateJobStatus($pdo, $job_id, SNAPSHOT_JOB_STATUS_FAILED, $e->getMessage());
        }
    }

    /**
     * Process a single batch within a job.
     *
     * @param PDO   $pdo   Database connection.
     * @param int   $jobId Job ID.
     * @param array $job   Job record.
     */
    private function processJobBatch(PDO $pdo, int $jobId, array $job): void {
        $all_tables  = json_decode($job['tables_json'], true);
        $pool_size   = (int) $job['pool_size'];
        $batch_index = (int) $job['current_batch'];
        $batches     = array_chunk($all_tables, $pool_size);

        if ($batch_index >= count($batches)) {
            $this->finalizeJob($pdo, $jobId, $job['snapshot_dir']);
            return;
        }

        $this->log(LogLevelType::Info->value, sprintf('Batch %d/%d: exporting %d tables',
            $batch_index + 1, count($batches), count($batches[$batch_index])
        ));

        $rootPdo = $this->openRootDbForBatch($job['snapshot_dir']);
        $result = $this->exportBatchTables($batches[$batch_index], $job['snapshot_dir'], $rootPdo);
        $rootPdo = null;

        $this->updateJobBatchProgress($pdo, $jobId, $batch_index + 1, $result['exported'], $result['rows'], $result['errors']);

        $next_batch = $batch_index + 1;
        if ($next_batch < count($batches)) {
            $this->scheduleNextBatch($jobId);
            $this->log(LogLevelType::Info->value, sprintf('Next batch scheduled (%d/%d)', $next_batch + 1, count($batches)));
        } else {
            $this->finalizeJob($pdo, $jobId, $job['snapshot_dir']);
        }
    }

    /**
     * Open a-root.db for batch registration.
     *
     * @param string $snapshotDir Snapshot directory.
     * @return PDO|null Root PDO or null.
     */
    private function openRootDbForBatch(string $snapshotDir): ?PDO {
        $root_path = $snapshotDir . '/a-root.db';
        if (RiseupBooleanHelpers::is_file_missing($root_path)) {
            return null;
        }

        $rootPdo = new PDO('sqlite:' . $root_path);
        $rootPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $rootPdo;
    }
}
