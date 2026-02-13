<?php
/**
 * WorkerBatchTrait — Batch processing for snapshot worker.
 *
 * Handles WP-Cron batch dispatch, synchronous batch export,
 * and batch-level table export coordination.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

trait WorkerBatchTrait {

    /**
     * Process a single worker batch (called by WP-Cron).
     *
     * @param array $args { job_id: int }
     */
    public function processWorkerBatch($args) {
        $job_id = $args['job_id'] ?? 0;
        if (!$job_id) {
            $this->log('ERROR', 'Worker batch called without job_id');
            return;
        }

        $pdo = $this->db->get_pdo();
        if (!$pdo) {
            $this->log('ERROR', 'No database connection for worker batch');
            return;
        }

        try {
            $job = $this->getJob($pdo, $job_id);
            if (!$job) {
                $this->log('ERROR', 'Job not found', array('job_id' => $job_id));
                return;
            }

            $this->updateJobStatus($pdo, $job_id, SNAPSHOT_JOB_STATUS_PROCESSING);
            $this->processJobBatch($pdo, $job_id, $job);
        } catch (Exception $e) {
            $this->log('ERROR', 'Worker batch failed', array(
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

        $this->log('INFO', sprintf('Batch %d/%d: exporting %d tables',
            $batch_index + 1, count($batches), count($batches[$batch_index])
        ));

        $rootPdo = $this->openRootDbForBatch($job['snapshot_dir']);
        $result = $this->exportBatchTables($batches[$batch_index], $job['snapshot_dir'], $rootPdo);
        $rootPdo = null;

        $this->updateJobBatchProgress($pdo, $jobId, $batch_index + 1, $result['exported'], $result['rows'], $result['errors']);

        $next_batch = $batch_index + 1;
        if ($next_batch < count($batches)) {
            $this->scheduleNextBatch($jobId);
            $this->log('INFO', sprintf('Next batch scheduled (%d/%d)', $next_batch + 1, count($batches)));
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

    /**
     * Export all tables in pool-sized batches synchronously.
     *
     * @param array  $seedOrder   Ordered table list.
     * @param string $snapshotDir Snapshot directory.
     * @param PDO    $rootPdo     Root DB connection.
     * @return array Export results.
     */
    private function exportBatchesSynchronously(array $seedOrder, string $snapshotDir, PDO $rootPdo): array {
        $total_rows = 0;
        $exported_tables = 0;
        $errors = array();
        $batches = array_chunk($seedOrder, $this->poolSize);

        foreach ($batches as $batch_index => $batch_tables) {
            $this->log('INFO', sprintf('Processing batch %d/%d (%d tables)',
                $batch_index + 1, count($batches), count($batch_tables)
            ));

            $result = $this->exportBatchTables($batch_tables, $snapshotDir, $rootPdo);
            $total_rows += $result['rows'];
            $exported_tables += $result['exported'];
            $errors = array_merge($errors, $result['errors']);
        }

        return array('total_rows' => $total_rows, 'exported_tables' => $exported_tables, 'errors' => $errors);
    }

    /**
     * Export a batch of tables to SQLite files.
     *
     * @param array    $tables      Table names.
     * @param string   $snapshotDir Snapshot directory.
     * @param PDO|null $rootPdo     Root DB connection for registration.
     * @return array Result with rows, exported, errors.
     */
    private function exportBatchTables(array $tables, string $snapshotDir, ?PDO $rootPdo): array {
        $rows = 0;
        $exported = 0;
        $errors = array();

        foreach ($tables as $table) {
            $this->updateProgress($table, 'running');
            $result = $this->exportTableToFile($snapshotDir, $table);

            if ($result['success']) {
                $rows += $result['rows'];
                $exported++;
                if ($rootPdo) {
                    $this->rootDb->registerTable(
                        $rootPdo, $table, $result['rows'],
                        $result['filename'], $result['file_size'], $result['checksum']
                    );
                }
                $this->updateProgress($table, 'complete', $result['rows']);
            } else {
                $errors[] = $table . ': ' . $result['error'];
                $this->updateProgress($table, 'failed', 0, $result['error']);
            }
        }

        return array('rows' => $rows, 'exported' => $exported, 'errors' => $errors);
    }

    /**
     * Build the async snapshot result.
     *
     * @param array $prepared  Prepared context.
     * @param array $seedOrder Seed order.
     * @param int   $jobId     Job ID.
     * @param float $startTime Start time.
     * @return array Result.
     */
    private function buildAsyncSnapshotResult(array $prepared, array $seedOrder, int $jobId, float $startTime): array {
        $duration = microtime(true) - $startTime;

        $this->log('INFO', 'Snapshot job created, first batch scheduled', array(
            'job_id' => $jobId, 'directory' => $prepared['dir_name'],
            'total_tables' => count($seedOrder), 'pool_size' => $this->poolSize,
            'setup_time' => round($duration, 2) . 's',
        ));

        return array(
            'success' => true, 'directory' => $prepared['dir_name'], 'path' => $prepared['snapshot_dir'],
            'job_id' => $jobId, 'total_tables' => count($seedOrder), 'pool_size' => $this->poolSize,
            'tables' => 0, 'total_rows' => 0, 'errors' => array(),
            'duration' => $duration, 'status' => SNAPSHOT_JOB_STATUS_QUEUED,
        );
    }

    /**
     * Build the sync snapshot result.
     *
     * @param array $prepared  Prepared context.
     * @param array $export    Export results.
     * @param float $startTime Start time.
     * @return array Result.
     */
    private function buildSyncSnapshotResult(array $prepared, array $export, float $startTime): array {
        return array(
            'success' => true, 'directory' => $prepared['dir_name'], 'path' => $prepared['snapshot_dir'],
            'tables' => $export['exported_tables'], 'total_rows' => $export['total_rows'],
            'errors' => $export['errors'], 'duration' => microtime(true) - $startTime,
        );
    }
}
