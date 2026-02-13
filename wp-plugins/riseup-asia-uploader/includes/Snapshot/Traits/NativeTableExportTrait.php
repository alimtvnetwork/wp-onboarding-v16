<?php
/**
 * NativeTableExportTrait — MySQL-to-SQLite table export.
 *
 * Shell trait — schema conversion delegated to NativeTableExportConvertTrait.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/NativeTableExportConvertTrait.php';

trait NativeTableExportTrait {

    use NativeTableExportConvertTrait;

    /**
     * Export a single MySQL table to SQLite.
     *
     * @param PDO    $sqlite      SQLite PDO instance.
     * @param string $table       Table name.
     * @param int    $snapshot_id Snapshot ID for progress tracking.
     * @return array Export result.
     */
    private function exportTable($sqlite, $table, $snapshot_id) {
        try {
            $create_sql = $this->getCreateTableSql($table);
            if (!$create_sql) {
                throw new Exception('Failed to get table structure');
            }

            $sqlite_create = $this->convertCreateStatement($create_sql, $table);
            $sqlite->exec($sqlite_create);

            $count = (int) $this->wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
            if ($count === 0) {
                return array('success' => true, 'rows' => 0, 'bytes' => 0);
            }

            return $this->exportTableRows($sqlite, $table, $count);
        } catch (Exception $e) {
            if ($sqlite->inTransaction()) {
                $sqlite->rollBack();
            }
            return array('success' => false, 'error' => $e->getMessage(), 'rows' => 0, 'bytes' => 0);
        }
    }

    /**
     * Export rows from a MySQL table to SQLite in batches.
     *
     * @param PDO    $sqlite SQLite PDO instance.
     * @param string $table  Table name.
     * @param int    $count  Total row count.
     * @return array Export result.
     */
    private function exportTableRows($sqlite, $table, int $count): array {
        $columns = $this->wpdb->get_results("DESCRIBE `{$table}`", ARRAY_A);
        $column_names = array_column($columns, 'Field');
        $placeholders = implode(', ', array_fill(0, count($column_names), '?'));
        $column_list = implode(', ', array_map(function($c) { return "`{$c}`"; }, $column_names));

        $stmt = $sqlite->prepare("INSERT INTO `{$table}` ({$column_list}) VALUES ({$placeholders})");

        $batch_size = SNAPSHOT_BATCH_SIZE;
        $offset = 0;
        $exported = 0;
        $bytes = 0;

        $sqlite->beginTransaction();

        while ($offset < $count) {
            $rows = $this->wpdb->get_results(
                $this->wpdb->prepare("SELECT * FROM `{$table}` LIMIT %d OFFSET %d", $batch_size, $offset),
                ARRAY_N
            );

            foreach ($rows as $row) {
                $stmt->execute($row);
                $exported++;
                $bytes += strlen(implode('', array_map('strval', $row)));
            }

            $offset += $batch_size;
            $this->logExportProgress($table, $offset, $count, $batch_size);
        }

        $sqlite->commit();
        return array('success' => true, 'rows' => $exported, 'bytes' => $bytes);
    }

    /**
     * Log export progress at 25% intervals.
     */
    private function logExportProgress(string $table, int $offset, int $count, int $batch_size) {
        $progress = ($offset / $count) * 100;
        $prev = (($offset - $batch_size) / $count) * 100;

        if ($progress >= 25 && $prev < 25) {
            $this->log(LOG_LEVEL_DEBUG, "{$table}: 25% complete");
        } elseif ($progress >= 50 && $prev < 50) {
            $this->log(LOG_LEVEL_DEBUG, "{$table}: 50% complete");
        } elseif ($progress >= 75 && $prev < 75) {
            $this->log(LOG_LEVEL_DEBUG, "{$table}: 75% complete");
        }
    }

    /**
     * Get MySQL CREATE TABLE statement.
     */
    private function getCreateTableSql($table) {
        $result = $this->wpdb->get_row("SHOW CREATE TABLE `{$table}`", ARRAY_N);
        return $result ? $result[1] : null;
    }
}
