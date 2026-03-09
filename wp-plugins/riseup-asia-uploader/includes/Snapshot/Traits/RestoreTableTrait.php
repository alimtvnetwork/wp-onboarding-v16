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
            $outcome = $this->processRestoreOutcome($table, $result, $options);

            $tablesRestored += $outcome['restored'];
            $totalRows += $outcome['rows'];
            $errors = array_merge($errors, $outcome['errors']);
        }

        return array(
            ResponseKeyType::TablesRestored->value => $tablesRestored,
            ResponseKeyType::TotalRows->value      => $totalRows,
            ResponseKeyType::Errors->value         => $errors,
        );
    }

    private function processRestoreOutcome(string $table, ?array $result, array $options): array {
        if ($result === null) {
            return array('restored' => 0, 'rows' => 0, 'errors' => array($table . ': skipped'));
        }

        if ($result[ResponseKeyType::Success->value]) {
            $this->log(LogLevelType::Info->value, sprintf('Restored: %s (%d rows)', $table, $result[ResponseKeyType::Rows->value]));

            return array('restored' => 1, 'rows' => $result[ResponseKeyType::Rows->value], 'errors' => array());
        }

        return $this->handleRestoreFailure($table, $result, $options);
    }

    private function handleRestoreFailure(string $table, array $result, array $options): array {
        $this->log(LogLevelType::Error->value, 'Restore failed: ' . $table, array(
            ResponseKeyType::Error->value => $result[ResponseKeyType::Error->value],
        ));

        $isStrictMode = !empty($options[ResponseKeyType::Strict->value] ?? null);

        if ($isStrictMode) {
            throw new Exception('Strict mode: table restore failed for ' . $table);
        }

        return array('restored' => 0, 'rows' => 0, 'errors' => array($table . ': ' . $result[ResponseKeyType::Error->value]));
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

        return $this->restoreFromInventory($table, $tableInfo, $snapshotDir);
    }

    private function restoreFromInventory(string $table, array $tableInfo, string $snapshotDir): ?array {
        $sqlitePath = $snapshotDir . '/' . $tableInfo[ResponseKeyType::SqliteFile->value];

        if (PathHelper::isFileMissing($sqlitePath)) {
            $this->logMissingSqliteFile($table, $tableInfo);

            return ResultHelper::error(
                'SQLite file missing (' . $tableInfo[ResponseKeyType::SqliteFile->value] . ')',
                array(ResponseKeyType::Rows->value => 0),
            );
        }

        return $this->restoreTableFromFile($sqlitePath, $table, RestoreStrategyType::Truncate->value);
    }

    private function logMissingSqliteFile(string $table, array $tableInfo): void {
        $this->log(LogLevelType::Error->value, 'SQLite file missing for table', array(
            'table' => $table,
            'file'  => $tableInfo[ResponseKeyType::SqliteFile->value],
        ));
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
