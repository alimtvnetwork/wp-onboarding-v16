<?php
/**
 * NativeTableExportTrait — MySQL-to-SQLite table export.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;

require_once __DIR__ . '/NativeTableExportConvertTrait.php';

trait NativeTableExportTrait {

    use NativeTableExportConvertTrait;

    private function exportTable(PDO $sqlite, string $table, int $snapshotId): array {
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
        } catch (Throwable $e) {
            if ($sqlite->inTransaction()) {
                $sqlite->rollBack();
            }
            return array('success' => false, 'error' => $e->getMessage(), 'rows' => 0, 'bytes' => 0);
        }
    }

    private function exportTableRows(PDO $sqlite, string $table, int $count): array {
        $insert = $this->prepareInsertStatement($sqlite, $table);

        $sqlite->beginTransaction();
        $result = $this->executeBatchExport($insert['stmt'], $table, $count);
        $sqlite->commit();

        return array('success' => true, 'rows' => $result['exported'], 'bytes' => $result['bytes']);
    }

    private function prepareInsertStatement(PDO $sqlite, string $table): array {
        $columns = $this->wpdb->get_results("DESCRIBE `{$table}`", ARRAY_A);
        $column_names = array_column($columns, 'Field');
        $placeholders = implode(', ', array_fill(0, count($column_names), '?'));
        $column_list = implode(', ', array_map(function($c) { return "`{$c}`"; }, $column_names));

        $stmt = $sqlite->prepare("INSERT INTO `{$table}` ({$column_list}) VALUES ({$placeholders})");

        return array('stmt' => $stmt);
    }

    private function executeBatchExport(PDOStatement $stmt, string $table, int $count): array {
        $batch_size = SNAPSHOT_BATCH_SIZE;
        $offset = 0;
        $exported = 0;
        $bytes = 0;

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

        return array('exported' => $exported, 'bytes' => $bytes);
    }

    private function logExportProgress(string $table, int $offset, int $count, int $batchSize): void {
        $progress = ($offset / $count) * 100;
        $prev = (($offset - $batch_size) / $count) * 100;

        if ($progress >= 25 && $prev < 25) {
            $this->log(LogLevelType::Debug->value, "{$table}: 25% complete");
        } elseif ($progress >= 50 && $prev < 50) {
            $this->log(LogLevelType::Debug->value, "{$table}: 50% complete");
        } elseif ($progress >= 75 && $prev < 75) {
            $this->log(LogLevelType::Debug->value, "{$table}: 75% complete");
        }
    }

    private function getCreateTableSql(string $table): ?string {
        $result = $this->wpdb->get_row("SHOW CREATE TABLE `{$table}`", ARRAY_N);
        return $result ? $result[1] : null;
    }
}
