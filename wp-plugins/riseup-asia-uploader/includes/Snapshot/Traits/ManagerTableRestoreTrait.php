<?php
/**
 * ManagerTableRestoreTrait — Low-level MySQL table restore from SQLite.
 *
 * Handles single-table restore, truncate-and-insert, batch insertion,
 * and pre-restore backup creation.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

trait ManagerTableRestoreTrait {

    /**
     * Restore a single table from SQLite to MySQL.
     *
     * @param PDO    $sqlite SQLite PDO instance.
     * @param string $table  Table name.
     * @return array Result with rows count.
     */
    private function restoreTable($sqlite, $table) {
        try {
            $check = $sqlite->query("SELECT name FROM sqlite_master WHERE type='table' AND name='{$table}'");
            if (!$check->fetch()) {
                return array('success' => false, 'error' => 'Table not found in snapshot', 'rows' => 0);
            }

            $columns_result = $sqlite->query("PRAGMA table_info('{$table}')");
            $columns = $columns_result->fetchAll(PDO::FETCH_ASSOC);
            $column_names = array_column($columns, 'name');

            return $this->truncateAndInsert($sqlite, $table, $column_names);
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage(), 'rows' => 0);
        }
    }

    /**
     * Truncate a MySQL table and batch-insert rows from SQLite.
     *
     * @param PDO    $sqlite       SQLite PDO connection.
     * @param string $table        Table name.
     * @param array  $column_names Column name list.
     * @return array Result with success and rows count.
     */
    private function truncateAndInsert($sqlite, $table, $column_names) {
        $this->wpdb->query("START TRANSACTION");

        try {
            $this->wpdb->query("SET FOREIGN_KEY_CHECKS = 0");
            $this->wpdb->query("TRUNCATE TABLE `{$table}`");

            $count_stmt = $sqlite->query("SELECT COUNT(*) FROM `{$table}`");
            $row_count = $count_stmt->fetchColumn();

            $total_rows = $this->insertBatchFromSqlite($sqlite, $table, $column_names, $row_count);

            $this->wpdb->query("SET FOREIGN_KEY_CHECKS = 1");
            $this->wpdb->query("COMMIT");

            return array('success' => true, 'rows' => $total_rows);
        } catch (Exception $e) {
            $this->wpdb->query("ROLLBACK");
            $this->wpdb->query("SET FOREIGN_KEY_CHECKS = 1");
            throw $e;
        }
    }

    /**
     * Batch-insert rows from a SQLite table into MySQL.
     *
     * @param PDO    $sqlite       SQLite PDO connection.
     * @param string $table        Table name.
     * @param array  $column_names Column name list.
     * @param int    $row_count    Total row count.
     * @return int Total rows inserted.
     */
    private function insertBatchFromSqlite($sqlite, $table, $column_names, $row_count) {
        $batch_size = SNAPSHOT_BATCH_SIZE;
        $offset = 0;
        $total_rows = 0;
        $columns_sql = '`' . implode('`, `', $column_names) . '`';
        $placeholders_sql = implode(', ', array_fill(0, count($column_names), '%s'));

        while ($offset < $row_count) {
            $rows = $sqlite->query("SELECT * FROM `{$table}` LIMIT {$batch_size} OFFSET {$offset}")->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $values = array();
                foreach ($column_names as $col) {
                    $values[] = isset($row[$col]) ? $row[$col] : null;
                }

                $sql = "INSERT INTO `{$table}` ({$columns_sql}) VALUES ({$placeholders_sql})";
                $this->wpdb->query($this->wpdb->prepare($sql, $values));
                $total_rows++;
            }

            $offset += $batch_size;
        }

        return $total_rows;
    }

    /**
     * Create a pre-restore backup snapshot.
     *
     * @param int $original_snapshot_id Original snapshot being restored.
     * @return array Result.
     */
    private function createPreRestoreBackup($original_snapshot_id) {
        $provider = $this->getProvider();
        if (!$provider) {
            return array('success' => false, 'error' => 'No provider available');
        }

        return $provider->createSnapshot(array(
            'scope' => SNAPSHOT_SCOPE_WORDPRESS,
            'trigger' => SNAPSHOT_TRIGGER_API,
            'pre_restore_of' => $original_snapshot_id,
        ));
    }
}
