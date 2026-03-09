<?php
/**
 * Restore Table Trait
 *
 * Master table restoration: single table restore and file-level orchestration.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.15.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use Throwable;
use Exception;

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\RestoreStrategyType;
use RiseupAsia\Helpers\BooleanHelpers;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\ResultHelper;

trait RestoreTableTrait {
    use RestoreSqliteValidationTrait;

    private function restoreMasterTables(
        array $restoreOrder,
        array $tableInventory,
        string $snapshotDir,
        array $options,
    ): array {
        $tablesRestored = 0;
        $totalRows = 0;
        $errors = array();

        foreach ($restoreOrder as $table) {
            $result = $this->restoreSingleMasterTable($table, $tableInventory, $snapshotDir);

            if ($result === null) {
                $errors[] = $result[ResponseKeyType::Error->value] ?? $table . ': skipped';
                continue;
            }

            if ($result[ResponseKeyType::Success->value]) {
                $tablesRestored++;
                $totalRows += $result[ResponseKeyType::Rows->value];
                $this->log(LogLevelType::Info->value, sprintf('Restored: %s (%d rows)', $table, $result[ResponseKeyType::Rows->value]));
                continue;
            }

            $errors[] = $table . ': ' . $result[ResponseKeyType::Error->value];
            $this->log(LogLevelType::Error->value, 'Restore failed: ' . $table, array(ResponseKeyType::Error->value => $result[ResponseKeyType::Error->value]));

            $isStrictMode = !empty($options[ResponseKeyType::Strict->value] ?? null);

            if ($isStrictMode) {
                throw new Exception('Strict mode: table restore failed for ' . $table);
            }
        }

        return array(
            ResponseKeyType::TablesRestored->value => $tablesRestored,
            ResponseKeyType::TotalRows->value      => $totalRows,
            ResponseKeyType::Errors->value         => $errors,
        );
    }

    private function restoreSingleMasterTable(
        string $table,
        array $tableInventory,
        string $snapshotDir,
    ): ?array {
        $tableInfo = $tableInventory[$table] ?? null;
        $isTableInfoMissing = ($tableInfo === null);

        if ($isTableInfoMissing) {
            return ResultHelper::error(
                $table . ': not found in inventory',
                array(ResponseKeyType::Rows->value => 0),
            );
        }

        $sqlitePath = $snapshotDir . '/' . $tableInfo[ResponseKeyType::SqliteFile->value];

        if (PathHelper::isFileMissing($sqlitePath)) {
            $this->log(LogLevelType::Error->value, 'SQLite file missing for table', array(
                'table' => $table,
                'file'  => $tableInfo[ResponseKeyType::SqliteFile->value],
            ));

            return ResultHelper::error(
                'SQLite file missing (' . $tableInfo[ResponseKeyType::SqliteFile->value] . ')',
                array(ResponseKeyType::Rows->value => 0),
            );
        }

        return $this->restoreTableFromFile($sqlitePath, $table, RestoreStrategyType::Truncate->value);
    }

    private function restoreTableFromFile(
        string $sqlitePath,
        string $table,
        string $strategy = 'truncate',
    ): array {
        try {
            $validated = $this->openAndValidateSqliteTable($sqlitePath, $table);
            $isValidationFailed = BooleanHelpers::isResultFailed($validated);

            if ($isValidationFailed) {
                return $validated;
            }

            return $this->batchInsertToMysql(
                $validated[ResponseKeyType::Sqlite->value],
                $table,
                $validated[ResponseKeyType::Columns->value],
                $strategy,
                $validated[ResponseKeyType::RowCount->value],
            );
        } catch (Throwable $e) {
            return ResultHelper::errorFromException(
                $e,
                array(ResponseKeyType::Rows->value => 0),
            );
        }
    }
}
