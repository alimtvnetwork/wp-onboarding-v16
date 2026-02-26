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
        $totalRows = 0;
        $exportedTables = 0;
        $errors = array();
        $batches = array_chunk($seedOrder, $this->poolSize);

        foreach ($batches as $batchIndex => $batchTables) {
            $this->log(LogLevelType::Info->value, sprintf(
                'Processing batch %d/%d (%d tables)',
                $batchIndex + 1,
                count($batches),
                count($batchTables),
            ));

            $result = $this->exportBatchTables($batchTables, $snapshotDir, $rootPdo);
            $totalRows += $result[ResponseKeyType::Rows->value];
            $exportedTables += $result[ResponseKeyType::Exported->value];
            $errors = array_merge($errors, $result[ResponseKeyType::Errors->value]);
        }

        return array(
            ResponseKeyType::TotalRows->value     => $totalRows,
            ResponseKeyType::ExportedTables->value => $exportedTables,
            ResponseKeyType::Errors->value         => $errors,
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

                $this->updateProgress(
                    $table,
                    SnapshotStatusType::Complete->value,
                    $result[ResponseKeyType::Rows->value],
                );
            } else {
                $errors[] = $table . ': ' . $result[ResponseKeyType::Error->value];
                $this->updateProgress(
                    $table,
                    SnapshotStatusType::Failed->value,
                    0,
                    $result[ResponseKeyType::Error->value],
                );
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
            ResponseKeyType::JobId->value       => $jobId,
            ResponseKeyType::Directory->value   => $prepared[ResponseKeyType::DirName->value],
            ResponseKeyType::TotalTables->value => count($seedOrder),
            ResponseKeyType::PoolSize->value    => $this->poolSize,
            'setupTime'                         => round($duration, 2) . 's',
        ));

        return ResultHelper::ok(array(
            ResponseKeyType::Directory->value   => $prepared[ResponseKeyType::DirName->value],
            ResponseKeyType::Path->value        => $prepared[ResponseKeyType::SnapshotDir->value],
            ResponseKeyType::JobId->value       => $jobId,
            ResponseKeyType::TotalTables->value => count($seedOrder),
            ResponseKeyType::PoolSize->value    => $this->poolSize,
            ResponseKeyType::Tables->value      => 0,
            ResponseKeyType::TotalRows->value   => 0,
            ResponseKeyType::Errors->value      => array(),
            ResponseKeyType::Duration->value    => $duration,
            ResponseKeyType::Status->value              => SnapshotJobStatusType::Queued->value,
        ));
    }

    private function buildSyncSnapshotResult(
        array $prepared,
        array $export,
        float $startTime,
    ): array {
        return ResultHelper::ok(array(
            ResponseKeyType::Directory->value  => $prepared[ResponseKeyType::DirName->value],
            ResponseKeyType::Path->value       => $prepared[ResponseKeyType::SnapshotDir->value],
            ResponseKeyType::Tables->value     => $export[ResponseKeyType::ExportedTables->value],
            ResponseKeyType::TotalRows->value  => $export[ResponseKeyType::TotalRows->value],
            ResponseKeyType::Errors->value     => $export[ResponseKeyType::Errors->value],
            ResponseKeyType::Duration->value   => microtime(true) - $startTime,
        ));
    }
}
