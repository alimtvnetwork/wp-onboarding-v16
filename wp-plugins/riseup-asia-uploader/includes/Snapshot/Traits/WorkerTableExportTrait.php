<?php
/**
 * WorkerTableExportTrait — Single table MySQL → SQLite export.
 *
 * Handles individual table export, schema conversion, and batch row insertion.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

trait WorkerTableExportTrait {

    /**
     * Export a single MySQL table to its own .sqlite file.
     *
     * @param string $snapshot_dir Snapshot directory path.
     * @param string $table        MySQL table name.
     * @return array Result: success, rows, filename, file_size, checksum.
     */
    private function exportTableToFile($snapshot_dir, $table) {
        $filename = $table . '.sqlite';
        $filepath = $snapshot_dir . '/' . $filename;

        try {
            $sqlite = $this->createSqliteAndSchema($filepath, $table);
            $count = (int) $this->wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");

            if ($count === 0) {
                $sqlite = null;
                return $this->buildExportResult($filename, $filepath, 0);
            }

            $exported = $this->batchExportRows($sqlite, $table, $count);
            $sqlite = null;

            return $this->buildExportResult($filename, $filepath, $exported);
        } catch (Exception $e) {
            return array(
                'success' => false, 'error' => $e->getMessage(),
                'rows' => 0, 'filename' => $filename, 'file_size' => 0, 'checksum' => '',
            );
        }
    }

    /**
     * Create a SQLite file and initialize the table schema.
     *
     * @param string $filepath SQLite file path.
     * @param string $table    Table name.
     * @return PDO SQLite connection.
     */
    private function createSqliteAndSchema(string $filepath, string $table): PDO {
        $sqlite = new PDO('sqlite:' . $filepath);
        $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sqlite->exec('PRAGMA journal_mode = WAL');
        $sqlite->exec('PRAGMA synchronous = OFF');

        $create_sql = $this->getCreateTableSql($table);
        if (!$create_sql) {
            throw new Exception('Failed to get table structure for ' . $table);
        }

        $sqlite->exec(RiseupSqliteSchemaConverter::convert($create_sql, $table));

        return $sqlite;
    }

    /**
     * Batch export all rows from a MySQL table to SQLite.
     *
     * @param PDO    $sqlite SQLite connection.
     * @param string $table  Table name.
     * @param int    $count  Total row count.
     * @return int Number of rows exported.
     */
    private function batchExportRows(PDO $sqlite, string $table, int $count): int {
        $columns = $this->wpdb->get_results("DESCRIBE `{$table}`", ARRAY_A);
        $column_names = array_column($columns, 'Field');
        $placeholders = implode(', ', array_fill(0, count($column_names), '?'));
        $column_list = implode(', ', array_map(function($c) { return "`{$c}`"; }, $column_names));

        $stmt = $sqlite->prepare("INSERT INTO `{$table}` ({$column_list}) VALUES ({$placeholders})");

        $offset = 0;
        $exported = 0;
        $sqlite->beginTransaction();

        while ($offset < $count) {
            $rows = $this->wpdb->get_results(
                $this->wpdb->prepare("SELECT * FROM `{$table}` LIMIT %d OFFSET %d", $this->batchSize, $offset),
                ARRAY_N
            );

            foreach ($rows as $row) {
                $stmt->execute($row);
                $exported++;
            }

            $offset += $this->batchSize;
        }

        $sqlite->commit();
        return $exported;
    }

    /**
     * Build the export result array.
     *
     * @param string $filename Filename.
     * @param string $filepath Full path.
     * @param int    $rows     Rows exported.
     * @return array Result.
     */
    private function buildExportResult(string $filename, string $filepath, int $rows): array {
        return array(
            'success'   => true,
            'rows'      => $rows,
            'filename'  => $filename,
            'file_size' => filesize($filepath),
            'checksum'  => md5_file($filepath),
        );
    }

    /**
     * Get MySQL CREATE TABLE statement.
     *
     * @param string $table Table name.
     * @return string|null CREATE statement or null.
     */
    private function getCreateTableSql($table) {
        $result = $this->wpdb->get_row("SHOW CREATE TABLE `{$table}`", ARRAY_N);
        return $result ? $result[1] : null;
    }
}
