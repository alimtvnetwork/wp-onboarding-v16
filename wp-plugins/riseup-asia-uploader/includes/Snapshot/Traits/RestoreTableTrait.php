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
        $tables_restored = 0;
        $total_rows = 0;
        $errors = array();

        foreach ($restoreOrder as $table) {
            $result = $this->restoreSingleMasterTable($table, $tableInventory, $snapshotDir);

            if ($result === null) {
                $errors[] = $result[ResponseKeyType::Error->value] ?? $table . ': skipped';
                continue;
            }

            if ($result[ResponseKeyType::Success->value]) {
                $tables_restored++;
                $total_rows += $result[ResponseKeyType::Rows->value];
                $this->log(LogLevelType::Info->value, sprintf('Restored: %s (%d rows)', $table, $result[ResponseKeyType::Rows->value]));
                continue;
            }

            $errors[] = $table . ': ' . $result[ResponseKeyType::Error->value];
            $this->log(LogLevelType::Error->value, 'Restore failed: ' . $table, array(ResponseKeyType::Error->value => $result[ResponseKeyType::Error->value]));

            $isStrictMode = BooleanHelpers::hasValue($options['strict'] ?? null);

            if ($isStrictMode) {

                throw new Exception('Strict mode: table restore failed for ' . $table);
            }
        }

        return array(
            ResponseKeyType::TablesRestored->value => $tables_restored,
            ResponseKeyType::TotalRows->value      => $total_rows,
            ResponseKeyType::Errors->value         => $errors,
        );
    }

    private function restoreSingleMasterTable(
        string $table,
        array $tableInventory,
        string $snapshotDir,
    ): ?array {
        $table_info = $tableInventory[$table] ?? null;
        $isTableInfoMissing = ($table_info === null);

        if ($isTableInfoMissing) {

            return ResultHelper::error(
                $table . ': not found in inventory',
                array(ResponseKeyType::Rows->value => 0),
            );
        }

        $sqlite_path = $snapshotDir . '/' . $table_info['sqlite_file'];
        if (PathHelper::isFileMissing($sqlite_path)) {
            $this->log(LogLevelType::Error->value, 'SQLite file missing for table', array('table' => $table, 'file' => $table_info['sqlite_file']));

            return ResultHelper::error(
                'SQLite file missing (' . $table_info['sqlite_file'] . ')',
                array(ResponseKeyType::Rows->value => 0),
            );
        }

        return $this->restoreTableFromFile($sqlite_path, $table, RestoreStrategyType::Truncate->value);
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
                $validated['sqlite'], $table, $validated['columns'],
                $strategy, $validated['row_count']
            );
        } catch (Throwable $e) {

            return ResultHelper::errorFromException(
                $e,
                array(ResponseKeyType::Rows->value => 0),
            );
        }
    }
}
