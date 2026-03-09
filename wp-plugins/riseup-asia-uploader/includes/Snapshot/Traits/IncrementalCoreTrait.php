<?php
/**
 * IncrementalCoreTrait — Preparation, export orchestration, and finalization for incremental backups.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.14.0
 */

namespace RiseupAsia\Snapshot\Traits;

use PDO;
use Throwable;

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Helpers\DateHelper;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\ResultHelper;

trait IncrementalCoreTrait {
    private function prepareIncrementalDir(string $rootPath): array {
        $rootPdo = new PDO('sqlite:' . $rootPath);
        $rootPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $masterTables = $this->getMasterTableInventory($rootPdo);

        if (empty($masterTables)) {
            $rootPdo = null;

            return ResultHelper::error('No tables found in master snapshot');
        }

        return $this->createIncrementalDirResult($rootPdo, $rootPath);
    }

    private function createIncrementalDirResult(PDO $rootPdo, string $rootPath): array {
        $sequence = $this->getNextSequence($rootPdo);
        $folderName = sprintf('%02d_%s', $sequence, DateHelper::nowDateOnly());
        $masterDir = dirname($rootPath);
        $incrementalDir = $masterDir . '/incremental/' . $folderName;

        $isDirCreationFailed = (PathHelper::makeDirectory($incrementalDir, true) === false);

        if ($isDirCreationFailed) {
            $rootPdo = null;

            return ResultHelper::error('Failed to create incremental directory: ' . $folderName);
        }

        $this->logIncrementalDirCreated($sequence, $folderName);

        return ResultHelper::ok(array(
            'rootPdo'                            => $rootPdo,
            'masterTables'                       => $this->getMasterTableInventory($rootPdo),
            ResponseKeyType::Sequence->value     => $sequence,
            ResponseKeyType::FolderName->value   => $folderName,
            'incrementalDir'                     => $incrementalDir,
        ));
    }

    private function logIncrementalDirCreated(int $sequence, string $folderName): void {
        $this->log(LogLevelType::Info->value, 'Incremental directory created', array(
            ResponseKeyType::Sequence->value   => $sequence,
            ResponseKeyType::FolderName->value => $folderName,
        ));
    }

    private function exportChangedTables(
        array $masterTables,
        string $incDir,
        PDO $rootPdo,
        int $sequence,
    ): array {
        $tablesChanged = 0;
        $totalNewRows = 0;
        $errors = array();
        $exportedTables = array();

        foreach ($masterTables as $tableName => $info) {
            $result = $this->exportTableDelta($tableName, $info, $incDir, $rootPdo, $sequence);

            if ($result === null) {
                continue;
            }

            if ($result[ResponseKeyType::Success->value]) {
                $tablesChanged++;
                $totalNewRows += $result[ResponseKeyType::Rows->value];
                $exportedTables[] = $result[ResponseKeyType::Entry->value];
            } else {
                $errors[] = $tableName . ': ' . $result[ResponseKeyType::Error->value];
            }
        }

        return array(
            ResponseKeyType::TablesChanged->value  => $tablesChanged,
            ResponseKeyType::TotalNewRows->value   => $totalNewRows,
            ResponseKeyType::Errors->value         => $errors,
            ResponseKeyType::ExportedTables->value => $exportedTables,
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
        $snapshotId = $this->registerIncrementalExport($title, $masterDir, $folderName, $sequence, $export, $incrementalDir);

        $this->logIncrementalComplete($snapshotId, $sequence, $export, $duration);
        $this->invalidateParentZipExport($masterDir);

        return $this->buildIncrementalResult($snapshotId, $sequence, $folderName, $incrementalDir, $export, $duration);
    }

    private function registerIncrementalExport(
        string $title,
        string $masterDir,
        string $folderName,
        int $sequence,
        array $export,
        string $incrementalDir,
    ): int {
        return $this->registerIncrementalSnapshot(
            $title,
            $masterDir,
            $folderName,
            $sequence,
            $export[ResponseKeyType::TablesChanged->value],
            $export[ResponseKeyType::TotalNewRows->value],
            $incrementalDir,
        );
    }

    private function logIncrementalComplete(int $snapshotId, int $sequence, array $export, float $duration): void {
        $this->log(LogLevelType::Info->value, 'Incremental backup complete', array(
            ResponseKeyType::SnapshotId->value   => $snapshotId,
            ResponseKeyType::Sequence->value     => $sequence,
            ResponseKeyType::TablesChanged->value => $export[ResponseKeyType::TablesChanged->value],
            ResponseKeyType::TotalNewRows->value  => $export[ResponseKeyType::TotalNewRows->value],
            ResponseKeyType::Errors->value        => count($export[ResponseKeyType::Errors->value]),
            ResponseKeyType::Duration->value      => round($duration, 2) . 's',
        ));
    }

    private function buildIncrementalResult(
        int $snapshotId,
        int $sequence,
        string $folderName,
        string $incrementalDir,
        array $export,
        float $duration,
    ): array {
        return ResultHelper::ok(array(
            ResponseKeyType::SnapshotId->value     => $snapshotId,
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
