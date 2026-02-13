<?php
/**
 * Restore Table Trait
 *
 * Master table restoration: single table restore, SQLite validation, batch insert.
 *
 * @package RiseupAsiaUploader
 * @since   1.15.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait RestoreTableTrait {

    /**
     * Restore master tables from SQLite files.
     *
     * @param array  $restoreOrder   Tables in restore order.
     * @param array  $tableInventory Table inventory map.
     * @param string $snapshotDir    Snapshot directory.
     * @param array  $options        Restore options.
     * @return array Result with tables_restored, total_rows, errors.
     */
    private function restoreMasterTables(array $restoreOrder, array $tableInventory, string $snapshotDir, array $options): array {
        $tables_restored = 0;
        $total_rows = 0;
        $errors = array();

        foreach ($restoreOrder as $table) {
            $result = $this->restoreSingleMasterTable($table, $tableInventory, $snapshotDir);

            if ($result === null) {
                $errors[] = $result['error'] ?? $table . ': skipped';
                continue;
            }

            if ($result['success']) {
                $tables_restored++;
                $total_rows += $result['rows'];
                $this->log('INFO', sprintf('Restored: %s (%d rows)', $table, $result['rows']));
                continue;
            }

            $errors[] = $table . ': ' . $result['error'];
            $this->log('ERROR', 'Restore failed: ' . $table, array('error' => $result['error']));

            if (!empty($options['strict'])) {
                throw new Exception('Strict mode: table restore failed for ' . $table);
            }
        }

        return array('tables_restored' => $tables_restored, 'total_rows' => $total_rows, 'errors' => $errors);
    }

    /**
     * Restore a single master table.
     *
     * @param string $table          Table name.
     * @param array  $tableInventory Table inventory.
     * @param string $snapshotDir    Snapshot directory.
     * @return array|null Result or null if missing.
     */
    private function restoreSingleMasterTable(string $table, array $tableInventory, string $snapshotDir): ?array {
        $table_info = $tableInventory[$table] ?? null;
        if (!$table_info) {
            return array('success' => false, 'error' => $table . ': not found in inventory', 'rows' => 0);
        }

        $sqlite_path = $snapshotDir . '/' . $table_info['sqlite_file'];
        if (RiseupBooleanHelpers::is_file_missing($sqlite_path)) {
            $this->log('ERROR', 'SQLite file missing for table', array('table' => $table, 'file' => $table_info['sqlite_file']));
            return array('success' => false, 'error' => 'SQLite file missing (' . $table_info['sqlite_file'] . ')', 'rows' => 0);
        }

        return $this->restoreTableFromFile($sqlite_path, $table, 'truncate');
    }

    /**
     * Restore a single table from its individual SQLite file into MySQL.
     *
     * @param string $sqlite_path Path to the table's .sqlite file.
     * @param string $table       MySQL table name.
     * @param string $strategy    'truncate' or 'merge'.
     * @return array Result: success, rows, error.
     */
    private function restoreTableFromFile($sqlite_path, $table, $strategy = 'truncate') {
        try {
            $validated = $this->openAndValidateSqliteTable($sqlite_path, $table);
            if (!$validated['success']) {
                return $validated;
            }

            return $this->batchInsertToMysql(
                $validated['sqlite'], $table, $validated['columns'],
                $strategy, $validated['row_count']
            );
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage(), 'rows' => 0);
        }
    }

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
            if ($strategy === 'truncate') {
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
        $verb = ($strategy === 'merge') ? 'REPLACE' : 'INSERT';
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
