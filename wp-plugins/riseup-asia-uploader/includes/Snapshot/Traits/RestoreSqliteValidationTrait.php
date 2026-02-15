<?php
/**
 * RestoreSqliteValidationTrait — SQLite file validation and batch MySQL insertion.
 *
 * @package RiseupAsiaUploader
 * @since   1.15.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\RestoreStrategyType;

trait RestoreSqliteValidationTrait {

    /**
     * Open a SQLite file and validate the table exists with columns.
     *
     * @param string $sqlitePath Path to SQLite file.
     * @param string $table      Table name.
     * @return array Result with sqlite, columns, row_count, or error.
     */
    private function openAndValidateSqliteTable(string $sqlitePath, string $table): array {
        $sqlite = new PDO('sqlite:' . $sqlitePath);
        $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $tableExists = $this->sqliteTableExists($sqlite, $table);
        if (!$tableExists) {
            $sqlite = null;
            return array('success' => false, 'error' => 'Table not found in SQLite file', 'rows' => 0);
        }

        $column_names = $this->getSqliteColumnNames($sqlite, $table);
        if (empty($column_names)) {
            $sqlite = null;
            return array('success' => false, 'error' => 'No columns found in SQLite table', 'rows' => 0);
        }

        $row_count = (int) $sqlite->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
        return array('success' => true, 'sqlite' => $sqlite, 'columns' => $column_names, 'row_count' => $row_count);
    }

    /**
     * Check if a table exists in a SQLite database.
     *
     * @param PDO    $sqlite SQLite connection.
     * @param string $table  Table name.
     * @return bool
     */
    private function sqliteTableExists(PDO $sqlite, string $table): bool {
        $escaped = str_replace("'", "''", $table);
        $check = $sqlite->query("SELECT name FROM sqlite_master WHERE type='table' AND name='{$escaped}'");
        return (bool) $check->fetch();
    }

    /**
     * Get column names from a SQLite table.
     *
     * @param PDO    $sqlite SQLite connection.
     * @param string $table  Table name.
     * @return array Column names.
     */
    private function getSqliteColumnNames(PDO $sqlite, string $table): array {
        $escaped = str_replace("'", "''", $table);
        $columns = $sqlite->query("PRAGMA table_info('{$escaped}')")->fetchAll(PDO::FETCH_ASSOC);
        return array_column($columns, 'name');
    }

    /**
     * Batch insert rows from SQLite into MySQL.
     *
     * @param PDO    $sqlite   SQLite connection.
     * @param string $table    Table name.
     * @param array  $columns  Column names.
     * @param string $strategy Insert strategy.
     * @param int    $rowCount Total row count.
     * @return array Result with success, rows.
     */
    private function batchInsertToMysql(PDO $sqlite, string $table, array $columns, string $strategy, int $rowCount): array {
        $this->wpdb->query("START TRANSACTION");

        try {
            if ($strategy === RestoreStrategyType::Truncate->value) {
                $this->wpdb->query("TRUNCATE TABLE `{$table}`");
            }

            $sql_template = $this->buildInsertTemplate($table, $columns, $strategy);
            $total_rows = $this->insertAllBatches($sqlite, $table, $columns, $sql_template, $rowCount);

            $this->wpdb->query("COMMIT");
            $sqlite = null;
            return array('success' => true, 'rows' => $total_rows);
        } catch (Exception $e) {
            $this->wpdb->query("ROLLBACK");
            throw $e;
        }
    }

    /**
     * Build the SQL INSERT/REPLACE template.
     *
     * @param string $table    Table name.
     * @param array  $columns  Column names.
     * @param string $strategy Insert strategy.
     * @return string SQL template.
     */
    private function buildInsertTemplate(string $table, array $columns, string $strategy): string {
        $columns_sql = '`' . implode('`, `', $columns) . '`';
        $placeholders = implode(', ', array_fill(0, count($columns), '%s'));
        $verb = ($strategy === RestoreStrategyType::Merge->value) ? 'REPLACE' : 'INSERT';
        return "{$verb} INTO `{$table}` ({$columns_sql}) VALUES ({$placeholders})";
    }

    /**
     * Insert all batches from SQLite into MySQL.
     *
     * @param PDO    $sqlite      SQLite connection.
     * @param string $table       Table name.
     * @param array  $columns     Column names.
     * @param string $sqlTemplate SQL template.
     * @param int    $rowCount    Total rows.
     * @return int Total rows inserted.
     */
    private function insertAllBatches(PDO $sqlite, string $table, array $columns, string $sqlTemplate, int $rowCount): int {
        $total_rows = 0;
        $offset = 0;

        while ($offset < $rowCount) {
            $rows = $sqlite->query("SELECT * FROM `{$table}` LIMIT {$this->batchSize} OFFSET {$offset}")
                ->fetchAll(PDO::FETCH_ASSOC);

            $total_rows += $this->insertRowBatch($rows, $columns, $sqlTemplate);
            $offset += $this->batchSize;
        }

        return $total_rows;
    }

    /**
     * Insert a single batch of rows.
     *
     * @param array  $rows        Row data.
     * @param array  $columns     Column names.
     * @param string $sqlTemplate SQL template.
     * @return int Rows inserted.
     */
    private function insertRowBatch(array $rows, array $columns, string $sqlTemplate): int {
        foreach ($rows as $row) {
            $values = array_map(function($col) use ($row) {
                return $row[$col] ?? null;
            }, $columns);
            $this->wpdb->query($this->wpdb->prepare($sqlTemplate, $values));
        }
        return count($rows);
    }
}
