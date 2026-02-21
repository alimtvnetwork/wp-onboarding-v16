<?php
/**
 * IncrementalCoreTrait — Preparation, export orchestration, and finalization for incremental backups.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.14.0
 */

namespace RiseupAsia\Snapshot\Traits;

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\ResultHelper;
use PDO;
use Throwable;

trait IncrementalCoreTrait {

    private function prepareIncrementalDir(string $rootPath): array {
        $rootPdo = new PDO('sqlite:' . $rootPath);
        $rootPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $master_tables = $this->getMasterTableInventory($rootPdo);
        if (empty($master_tables)) {
            $rootPdo = null;

            return ResultHelper::error('No tables found in master snapshot');
        }

        $sequence = $this->getNextSequence($rootPdo);
        $folder_name = sprintf('%02d_%s', $sequence, date('Y-m-d'));
        $master_dir = dirname($rootPath);
        $incremental_dir = $master_dir . '/incremental/' . $folder_name;

        $isDirCreationFailed = (PathHelper::makeDirectory($incremental_dir, true) === false);
        if ($isDirCreationFailed) {
            $rootPdo = null;

            return ResultHelper::error('Failed to create incremental directory: ' . $folder_name);
        }

        $this->log(LogLevelType::Info->value, 'Incremental directory created', array(
            ResponseKeyType::Sequence->value => $sequence,
            ResponseKeyType::FolderName->value => $folder_name,
        ));

        return ResultHelper::ok(array(
            'rootPdo'                            => $rootPdo,
            'master_tables'                      => $master_tables,
            ResponseKeyType::Sequence->value     => $sequence,
            ResponseKeyType::FolderName->value   => $folder_name,
            'incremental_dir'                    => $incremental_dir,
        ));
    }

    private function exportChangedTables(
        array $masterTables,
        string $incDir,
        PDO $rootPdo,
        int $sequence,
    ): array {
        $tables_changed = 0;
        $total_new_rows = 0;
        $errors = array();
        $exported_tables = array();

        foreach ($masterTables as $table_name => $info) {
            $result = $this->exportTableDelta($table_name, $info, $incDir, $rootPdo, $sequence);
            if ($result === null) continue;

            if ($result[ResponseKeyType::Success->value]) {
                $tables_changed++;
                $total_new_rows += $result[ResponseKeyType::Rows->value];
                $exported_tables[] = $result[ResponseKeyType::Entry->value];
            } else {
                $errors[] = $table_name . ': ' . $result[ResponseKeyType::Error->value];
            }
        }

        return array(
            ResponseKeyType::TablesChanged->value => $tables_changed,
            ResponseKeyType::TotalNewRows->value  => $total_new_rows,
            ResponseKeyType::Errors->value        => $errors,
            ResponseKeyType::ExportedTables->value => $exported_tables,
        );
    }

    private function finalizeIncremental(
        string $title,
        string $masterDir,
        string $folderName,
        int $sequence,
        array $export,
        string $incrementalDir,
        float $startTime,
    ): array {
        $duration = microtime(true) - $startTime;

        $snapshot_id = $this->registerIncrementalSnapshot(
            $title,
            $masterDir,
            $folderName,
            $sequence,
            $export[ResponseKeyType::TablesChanged->value],
            $export[ResponseKeyType::TotalNewRows->value],
            $incrementalDir,
        );

        $this->log(LogLevelType::Info->value, 'Incremental backup complete', array(
            ResponseKeyType::SnapshotId->value     => $snapshot_id,
            ResponseKeyType::Sequence->value       => $sequence,
            ResponseKeyType::TablesChanged->value   => $export[ResponseKeyType::TablesChanged->value],
            ResponseKeyType::TotalNewRows->value    => $export[ResponseKeyType::TotalNewRows->value],
            ResponseKeyType::Errors->value          => count($export[ResponseKeyType::Errors->value]),
            ResponseKeyType::Duration->value        => round($duration, 2) . 's',
        ));

        $this->invalidateParentZipExport($masterDir);

        return ResultHelper::ok(array(
            ResponseKeyType::SnapshotId->value     => $snapshot_id,
            ResponseKeyType::Sequence->value       => $sequence,
            ResponseKeyType::FolderName->value     => $folderName,
            ResponseKeyType::Path->value           => $incrementalDir,
            ResponseKeyType::TablesChanged->value  => $export[ResponseKeyType::TablesChanged->value],
            ResponseKeyType::TotalNewRows->value   => $export[ResponseKeyType::TotalNewRows->value],
            ResponseKeyType::Tables->value         => $export[ResponseKeyType::ExportedTables->value],
            ResponseKeyType::Errors->value         => $export[ResponseKeyType::Errors->value],
            ResponseKeyType::Duration->value       => $duration,
        ));
    }
}
