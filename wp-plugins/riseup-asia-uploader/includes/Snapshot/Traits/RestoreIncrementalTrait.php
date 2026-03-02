<?php
/**
 * Restore Incremental Trait
 *
 * Incremental backup application during restore.
 * Supports both old snake_case and new PascalCase root DB schemas.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.15.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use PDO;
use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\RestoreModeType;
use RiseupAsia\Helpers\BooleanHelpers;
use RiseupAsia\Helpers\PathHelper;

trait RestoreIncrementalTrait {
    use RootDbCompatTrait;

    private function applyIncrementalsPhase(
        PDO $rootPdo,
        string $snapshotDir,
        array $restoreOrder,
        string $mode,
        bool $applyIncrementals,
    ): array {
        $restoreMode = RestoreModeType::tryFrom($mode);
        $isIncrementalMode = ($restoreMode !== null && $restoreMode->isIncremental());
        $shouldApply = ($applyIncrementals && ($restoreMode === null || $restoreMode->isOtherThan(RestoreModeType::Incremental))) || $isIncrementalMode;
        $isSkipped = ($shouldApply === false);

        if ($isSkipped) {
            return array(
                ResponseKeyType::Applied->value   => 0,
                ResponseKeyType::TotalRows->value  => 0,
                ResponseKeyType::Errors->value     => array(),
            );
        }

        return $this->applyIncrementals($rootPdo, $snapshotDir, $restoreOrder);
    }

    private function applyIncrementals(
        PDO $rootPdo,
        string $snapshotDir,
        array $restoreOrder,
    ): array {
        $table = $this->resolveRootTable($rootPdo, 'IncrementalBackups', 'incremental_backups');
        $seqCol = $this->resolveRootCol($rootPdo, $table, 'SequenceNum', 'sequence_num');
        $folderCol = $this->resolveRootCol($rootPdo, $table, 'FolderName', 'folder_name');
        $pathCol = $this->resolveRootCol($rootPdo, $table, 'RelativePath', 'relative_path');

        $incrementals = $rootPdo->query(
            "SELECT {$seqCol} AS sequenceNum, {$folderCol} AS folderName, {$pathCol} AS relativePath FROM {$table} ORDER BY {$seqCol} ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        if (empty($incrementals)) {
            return array(
                ResponseKeyType::Applied->value   => 0,
                ResponseKeyType::TotalRows->value  => 0,
                ResponseKeyType::Errors->value     => array(),
            );
        }

        $this->log(LogLevelType::Info->value, 'Applying incrementals', array(ResponseKeyType::Count->value => count($incrementals)));

        $applied = 0;
        $totalRows = 0;
        $errors = array();

        foreach ($incrementals as $inc) {
            $result = $this->applySingleIncremental($inc, $snapshotDir, $restoreOrder);
            $totalRows += $result[ResponseKeyType::Rows->value];
            $applied++;
            $hasErrors = !empty($result[ResponseKeyType::Errors->value]);

            if ($hasErrors) {
                $errors = array_merge($errors, $result[ResponseKeyType::Errors->value]);
            }
        }

        return array(
            ResponseKeyType::Applied->value   => $applied,
            ResponseKeyType::TotalRows->value  => $totalRows,
            ResponseKeyType::Errors->value     => $errors,
        );
    }

    private function applySingleIncremental(
        array $inc,
        string $snapshotDir,
        array $restoreOrder,
    ): array {
        $incDir = $snapshotDir . '/' . rtrim($inc['relativePath'], '/');

        if (PathHelper::isDirMissing($incDir)) {
            $this->log(LogLevelType::Warn->value, 'Incremental directory missing', array('folder' => $inc['folderName']));

            return array(
                ResponseKeyType::Rows->value   => 0,
                ResponseKeyType::Errors->value => array('Incremental directory missing: ' . $inc['folderName']),
            );
        }

        $this->log(LogLevelType::Info->value, 'Applying incremental: ' . $inc['folderName']);

        $sqliteFiles = glob($incDir . '/*.sqlite');
        $incRows = 0;
        $errors = array();

        foreach ($sqliteFiles as $sqliteFile) {
            $table = basename($sqliteFile, '.sqlite');

            if (BooleanHelpers::isAbsentFromList($table, $restoreOrder)) {
                continue;
            }

            $result = $this->restoreTableFromFile($sqliteFile, $table, 'merge');

            if ($result[ResponseKeyType::Success->value]) {
                $incRows += $result[ResponseKeyType::Rows->value];
                $this->log(LogLevelType::Info->value, sprintf(
                    'Incremental %s: %s (+%d rows)',
                    $inc['folderName'],
                    $table,
                    $result[ResponseKeyType::Rows->value],
                ));
            } else {
                $errors[] = sprintf(
                    'Incremental %s/%s: %s',
                    $inc['folderName'],
                    $table,
                    $result[ResponseKeyType::Error->value],
                );
            }
        }

        return array(
            ResponseKeyType::Rows->value   => $incRows,
            ResponseKeyType::Errors->value => $errors,
        );
    }
}
