<?php
/**
 * WorkerBatchExportTrait — Batch table export and result builders.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\SnapshotJobStatusType;
use RiseupAsia\Enums\SnapshotStatusType;

trait WorkerBatchExportTrait {

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
            $this->log(LogLevelType::Info->value, sprintf('Processing batch %d/%d (%d tables)',
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
            $this->updateProgress($table, SnapshotStatusType::Running->value);
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
                $this->updateProgress($table, SnapshotStatusType::Complete->value, $result['rows']);
            } else {
                $errors[] = $table . ': ' . $result['error'];
                $this->updateProgress($table, SnapshotStatusType::Failed->value, 0, $result['error']);
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

        $this->log(LogLevelType::Info->value, 'Snapshot job created, first batch scheduled', array(
            'job_id' => $jobId, 'directory' => $prepared['dir_name'],
            'total_tables' => count($seedOrder), 'pool_size' => $this->poolSize,
            'setup_time' => round($duration, 2) . 's',
        ));

        return array(
            'success' => true, 'directory' => $prepared['dir_name'], 'path' => $prepared['snapshot_dir'],
            'job_id' => $jobId, 'total_tables' => count($seedOrder), 'pool_size' => $this->poolSize,
            'tables' => 0, 'total_rows' => 0, 'errors' => array(),
            'duration' => $duration, 'status' => SnapshotJobStatusType::Queued->value,
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
