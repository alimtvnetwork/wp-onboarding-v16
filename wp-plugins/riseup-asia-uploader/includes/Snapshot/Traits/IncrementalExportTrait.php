<?php
/**
 * IncrementalExportTrait — Delta row export to SQLite.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

trait IncrementalExportTrait {

    /**
     * Export delta rows (id > last_max_id) for a table to an incremental SQLite file.
     *
     * @param string $incremental_dir Incremental directory path.
     * @param string $table           MySQL table name.
     * @param string $pk_column       Primary key column.
     * @param int    $last_max_id     Last max ID from previous backup.
     * @param int    $expected_count  Expected number of new rows.
     * @return array Result.
     */
    private function exportDeltaRows($incremental_dir, $table, $pk_column, $last_max_id, $expected_count) {
        $filename = $table . '.sqlite';
        $filepath = $incremental_dir . '/' . $filename;

        try {
            $sqlite = $this->createIncrementalSqliteTable($filepath, $table);
            $exported = $this->batchExportDelta($sqlite, $table, $pk_column, $last_max_id);
            $sqlite = null;

            return array(
                'success' => true, 'rows' => $exported,
                'file_size' => filesize($filepath), 'checksum' => md5_file($filepath),
            );
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage(), 'rows' => 0, 'file_size' => 0, 'checksum' => '');
        }
    }

    /**
     * Create a SQLite file and initialize the table schema.
     */
    private function createIncrementalSqliteTable(string $filepath, string $table): PDO {
        $sqlite = new PDO('sqlite:' . $filepath);
        $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sqlite->exec('PRAGMA journal_mode = WAL');
        $sqlite->exec('PRAGMA synchronous = OFF');

        $create_result = $this->wpdb->get_row("SHOW CREATE TABLE `{$table}`", ARRAY_N);
        if (!$create_result) {
            throw new Exception('Failed to get CREATE TABLE for ' . $table);
        }

        $sqlite->exec(RiseupSqliteSchemaConverter::convert($create_result[1], $table));
        return $sqlite;
    }

    /**
     * Batch export delta rows from MySQL to SQLite.
     */
    private function batchExportDelta(PDO $sqlite, string $table, string $pkColumn, int $lastMaxId): int {
        $columns = $this->wpdb->get_results("DESCRIBE `{$table}`", ARRAY_A);
        $column_names = array_column($columns, 'Field');
        $placeholders = implode(', ', array_fill(0, count($column_names), '?'));
        $column_list = implode(', ', array_map(function($c) { return "`{$c}`"; }, $column_names));

        $stmt = $sqlite->prepare("INSERT OR REPLACE INTO `{$table}` ({$column_list}) VALUES ({$placeholders})");

        $offset = 0;
        $exported = 0;
        $sqlite->beginTransaction();

        while (true) {
            $rows = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT * FROM `{$table}` WHERE `{$pkColumn}` > %d ORDER BY `{$pkColumn}` ASC LIMIT %d OFFSET %d",
                    $lastMaxId, $this->batchSize, $offset
                ),
                ARRAY_N
            );

            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                $stmt->execute($row);
                $exported++;
            }

            $offset += $this->batchSize;
        }

        $sqlite->commit();
        return $exported;
    }
}
