<?php
/**
 * RestoreSqliteValidationTrait — SQLite file validation and batch MySQL insertion.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.15.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use PDO;
use Throwable;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\RestoreStrategyType;
use RiseupAsia\Helpers\ResultHelper;

trait RestoreSqliteValidationTrait {
    private function openAndValidateSqliteTable(string $sqlitePath, string $table): array {
        $sqlite = new PDO('sqlite:' . $sqlitePath);
        $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $tableExists = $this->sqliteTableExists($sqlite, $table);
        $isTableMissing = ($tableExists === false);

        if ($isTableMissing) {
            $sqlite = null;

            return ResultHelper::error(
                'Table not found in SQLite file',
                array(ResponseKeyType::Rows->value => 0),
            );
        }

        $columnNames = $this->getSqliteColumnNames($sqlite, $table);

        if (empty($columnNames)) {
            $sqlite = null;

            return ResultHelper::error(
                'No columns found in SQLite table',
                array(ResponseKeyType::Rows->value => 0),
            );
        }

        $rowCount = (int) $sqlite->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();

        return ResultHelper::ok(array(
            'sqlite'                         => $sqlite,
            ResponseKeyType::Columns->value  => $columnNames,
            ResponseKeyType::RowCount->value => $rowCount,
        ));
    }

    private function sqliteTableExists(PDO $sqlite, string $table): bool {
        $escaped = str_replace("'", "''", $table);
        $check = $sqlite->query("SELECT name FROM sqlite_master WHERE type='table' AND name='{$escaped}'");

        return (bool) $check->fetch();
    }

    private function getSqliteColumnNames(PDO $sqlite, string $table): array {
        $escaped = str_replace("'", "''", $table);
        $columns = $sqlite->query("PRAGMA table_info('{$escaped}')")->fetchAll(PDO::FETCH_ASSOC);

        return array_column($columns, 'name');
    }

    private function batchInsertToMysql(
        PDO $sqlite,
        string $table,
        array $columns,
        string $strategy,
        int $rowCount,
    ): array {
        $this->wpdb->query("START TRANSACTION");

        try {
            if ($strategy === RestoreStrategyType::Truncate->value) {
                $this->wpdb->query("TRUNCATE TABLE `{$table}`");
            }

            $sqlTemplate = $this->buildInsertTemplate($table, $columns, $strategy);
            $totalRows = $this->insertAllBatches($sqlite, $table, $columns, $sqlTemplate, $rowCount);

            $this->wpdb->query("COMMIT");
            $sqlite = null;

            return ResultHelper::ok(array(
                ResponseKeyType::Rows->value => $totalRows,
            ));
        } catch (Throwable $e) {
            $this->wpdb->query("ROLLBACK");

            throw $e;
        }
    }

    private function buildInsertTemplate(
        string $table,
        array $columns,
        string $strategy,
    ): string {
        $columnsSql = '`' . implode('`, `', $columns) . '`';
        $placeholders = implode(', ', array_fill(0, count($columns), '%s'));
        $verb = ($strategy === RestoreStrategyType::Merge->value) ? 'REPLACE' : 'INSERT';

        return "{$verb} INTO `{$table}` ({$columnsSql}) VALUES ({$placeholders})";
    }

    private function insertAllBatches(
        PDO $sqlite,
        string $table,
        array $columns,
        string $sqlTemplate,
        int $rowCount,
    ): int {
        $totalRows = 0;
        $offset = 0;

        while ($offset < $rowCount) {
            $rows = $sqlite->query("SELECT * FROM `{$table}` LIMIT {$this->batchSize} OFFSET {$offset}")
                ->fetchAll(PDO::FETCH_ASSOC);

            $totalRows += $this->insertRowBatch($rows, $columns, $sqlTemplate);
            $offset += $this->batchSize;
        }

        return $totalRows;
    }

    private function insertRowBatch(
        array $rows,
        array $columns,
        string $sqlTemplate,
    ): int {
        foreach ($rows as $row) {
            $values = array_map(function($col) use ($row) {
                return $row[$col] ?? null;
            }, $columns);

            $this->wpdb->query($this->wpdb->prepare($sqlTemplate, $values));
        }

        return count($rows);
    }
}
