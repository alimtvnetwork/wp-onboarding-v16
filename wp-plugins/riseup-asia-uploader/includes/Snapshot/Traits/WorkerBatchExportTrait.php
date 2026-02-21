<?php
/**
 * WorkerBatchExportTrait — Batch table export and result builders.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   2.0.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use PDO;
use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\SnapshotJobStatusType;
use RiseupAsia\Enums\SnapshotStatusType;
use RiseupAsia\Helpers\ResultHelper;

trait WorkerBatchExportTrait {

    private function exportBatchesSynchronously(
        array $seedOrder,
        string $snapshotDir,
        PDO $rootPdo,
    ): array {
        $total_rows = 0;
        $exported_tables = 0;
        $errors = array();
        $batches = array_chunk($seedOrder, $this->poolSize);

        foreach ($batches as $batch_index => $batch_tables) {
            $this->log(LogLevelType::Info->value, sprintf('Processing batch %d/%d (%d tables)',
                $batch_index + 1,
                count($batches),
                count($batch_tables),
            ));

            $result = $this->exportBatchTables($batch_tables, $snapshotDir, $rootPdo);
            $total_rows += $result[ResponseKeyType::Rows->value];
            $exported_tables += $result[ResponseKeyType::Exported->value];
            $errors = array_merge($errors, $result[ResponseKeyType::Errors->value]);
        }

        return array(
            ResponseKeyType::TotalRows->value => $total_rows,
            'exported_tables'                 => $exported_tables,
            ResponseKeyType::Errors->value    => $errors,
        );
    }

    private function exportBatchTables(
        array $tables,
        string $snapshotDir,
        ?PDO $rootPdo,
    ): array {
        $rows = 0;
        $exported = 0;
        $errors = array();

        foreach ($tables as $table) {
            $this->updateProgress($table, SnapshotStatusType::Running->value);
            $result = $this->exportTableToFile($snapshotDir, $table);

            if ($result[ResponseKeyType::Success->value]) {
                $rows += $result[ResponseKeyType::Rows->value];
                $exported++;
                if ($rootPdo) {
                    $this->rootDb->registerTable(
                        $rootPdo,
                        $table,
                        $result[ResponseKeyType::Rows->value],
                        $result[ResponseKeyType::Filename->value],
                        $result[ResponseKeyType::FileSize->value],
                        $result[ResponseKeyType::Checksum->value],
                    );
                }
                $this->updateProgress($table, SnapshotStatusType::Complete->value, $result[ResponseKeyType::Rows->value]);
            } else {
                $errors[] = $table . ': ' . $result[ResponseKeyType::Error->value];
                $this->updateProgress($table, SnapshotStatusType::Failed->value, 0, $result[ResponseKeyType::Error->value]);
            }
        }

        return array(
            ResponseKeyType::Rows->value     => $rows,
            ResponseKeyType::Exported->value => $exported,
            ResponseKeyType::Errors->value   => $errors,
        );
    }

    private function buildAsyncSnapshotResult(
        array $prepared,
        array $seedOrder,
        int $jobId,
        float $startTime,
    ): array {
        $duration = microtime(true) - $startTime;

        $this->log(LogLevelType::Info->value, 'Snapshot job created, first batch scheduled', array(
            'job_id' => $jobId,
            ResponseKeyType::Directory->value => $prepared['dir_name'],
            'total_tables' => count($seedOrder),
            'pool_size' => $this->poolSize,
            'setup_time' => round($duration, 2) . 's',
        ));

        return ResultHelper::ok(array(
            ResponseKeyType::Directory->value  => $prepared['dir_name'],
            ResponseKeyType::Path->value       => $prepared['snapshot_dir'],
            'job_id'                           => $jobId,
            'total_tables'                     => count($seedOrder),
            'pool_size'                        => $this->poolSize,
            ResponseKeyType::Tables->value     => 0,
            ResponseKeyType::TotalRows->value  => 0,
            ResponseKeyType::Errors->value     => array(),
            ResponseKeyType::Duration->value   => $duration,
            'status'                           => SnapshotJobStatusType::Queued->value,
        ));
    }

    private function buildSyncSnapshotResult(
        array $prepared,
        array $export,
        float $startTime,
    ): array {

        return ResultHelper::ok(array(
            ResponseKeyType::Directory->value  => $prepared['dir_name'],
            ResponseKeyType::Path->value       => $prepared['snapshot_dir'],
            ResponseKeyType::Tables->value     => $export['exported_tables'],
            ResponseKeyType::TotalRows->value  => $export[ResponseKeyType::TotalRows->value],
            ResponseKeyType::Errors->value     => $export[ResponseKeyType::Errors->value],
            ResponseKeyType::Duration->value   => microtime(true) - $startTime,
        ));
    }
}
