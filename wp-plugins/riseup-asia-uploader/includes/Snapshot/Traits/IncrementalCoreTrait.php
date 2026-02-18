<?php
/**
 * IncrementalCoreTrait — Preparation, export orchestration, and finalization for incremental backups.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.14.0
 */

namespace RiseupAsia\Snapshot\Traits;

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Helpers\PathHelper;
use PDO;
use Throwable;

trait IncrementalCoreTrait {

    private function prepareIncrementalDir(string $rootPath): array {
        $rootPdo = new PDO('sqlite:' . $rootPath);
        $rootPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $master_tables = $this->getMasterTableInventory($rootPdo);
        if (empty($master_tables)) {
            $rootPdo = null;

            return array('success' => false, 'error' => 'No tables found in master snapshot');
        }

        $sequence = $this->getNextSequence($rootPdo);
        $folder_name = sprintf('%02d_%s', $sequence, date('Y-m-d'));
        $master_dir = dirname($rootPath);
        $incremental_dir = $master_dir . '/incremental/' . $folder_name;

        $isDirCreationFailed = (PathHelper::makeDirectory($incremental_dir, true) === false);
        if ($isDirCreationFailed) {
            $rootPdo = null;

            return array('success' => false, 'error' => 'Failed to create incremental directory: ' . $folder_name);
        }

        $this->log(LogLevelType::Info->value, 'Incremental directory created', array('sequence' => $sequence, 'folder_name' => $folder_name));

        return array('success' => true, 'rootPdo' => $rootPdo, 'master_tables' => $master_tables, 'sequence' => $sequence, 'folder_name' => $folder_name, 'incremental_dir' => $incremental_dir);
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

            if ($result['success']) {
                $tables_changed++;
                $total_new_rows += $result['rows'];
                $exported_tables[] = $result['entry'];
            } else {
                $errors[] = $table_name . ': ' . $result['error'];
            }
        }

        return array('tables_changed' => $tables_changed, 'total_new_rows' => $total_new_rows, 'errors' => $errors, 'exported_tables' => $exported_tables);
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

        $snapshot_id = $this->registerIncrementalSnapshot($title, $masterDir, $folderName, $sequence, $export['tables_changed'], $export['total_new_rows'], $incrementalDir);

        $this->log(LogLevelType::Info->value, 'Incremental backup complete', array(
            'snapshot_id' => $snapshot_id, 'sequence' => $sequence,
            'tables_changed' => $export['tables_changed'], 'total_new_rows' => $export['total_new_rows'],
            'errors' => count($export['errors']), 'duration' => round($duration, 2) . 's',
        ));

        $this->invalidateParentZipExport($masterDir);

        return array(
            'success' => true, 'snapshot_id' => $snapshot_id, 'sequence' => $sequence,
            'folder_name' => $folderName, 'path' => $incrementalDir,
            'tables_changed' => $export['tables_changed'], 'total_new_rows' => $export['total_new_rows'],
            'tables' => $export['exported_tables'], 'errors' => $export['errors'], 'duration' => $duration,
        );
    }
}
