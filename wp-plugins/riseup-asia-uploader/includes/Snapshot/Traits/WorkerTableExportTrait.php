<?php
/**
 * WorkerTableExportTrait — Single table MySQL → SQLite export.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   2.0.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use PDO;
use PDOStatement;
use Throwable;
use Exception;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Snapshot\SqliteSchemaConverter;

trait WorkerTableExportTrait {
    private function exportTableToFile(string $snapshotDir, string $table): array {
        $filename = $table . '.sqlite';
        $filepath = $snapshotDir . '/' . $filename;

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
        } catch (Throwable $e) {

            return array(
                ResponseKeyType::Success->value  => false,
                ResponseKeyType::Error->value    => $e->getMessage(),
                ResponseKeyType::Rows->value     => 0,
                ResponseKeyType::Filename->value => $filename,
                ResponseKeyType::FileSize->value => 0,
                ResponseKeyType::Checksum->value => '',
            );
        }
    }

    private function createSqliteAndSchema(string $filepath, string $table): PDO {
        $sqlite = new PDO('sqlite:' . $filepath);
        $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sqlite->exec('PRAGMA journal_mode = WAL');
        $sqlite->exec('PRAGMA synchronous = OFF');

        $create_sql = $this->getCreateTableSql($table);
        $isCreateSqlMissing = ($create_sql === null);

        if ($isCreateSqlMissing) {

            throw new Exception('Failed to get table structure for ' . $table);
        }

        $sqlite->exec(SqliteSchemaConverter::convert($create_sql, $table));

        return $sqlite;
    }

    private function batchExportRows(
        PDO $sqlite,
        string $table,
        int $count,
    ): int {
        $columns = $this->wpdb->get_results("DESCRIBE `{$table}`", ARRAY_A);
        $column_names = array_column($columns, 'Field');
        $stmt = $this->prepareExportInsert($sqlite, $table, $column_names);

        $sqlite->beginTransaction();
        $exported = $this->exportRowsInBatches($stmt, $table, $count);
        $sqlite->commit();

        return $exported;
    }

    private function prepareExportInsert(
        PDO $sqlite,
        string $table,
        array $columnNames,
    ): PDOStatement {
        $placeholders = implode(', ', array_fill(0, count($columnNames), '?'));
        $columnList = implode(', ', array_map(function($c) { return "`{$c}`"; }, $columnNames));

        return $sqlite->prepare("INSERT INTO `{$table}` ({$columnList}) VALUES ({$placeholders})");
    }

    private function exportRowsInBatches(
        PDOStatement $stmt,
        string $table,
        int $count,
    ): int {
        $offset = 0;
        $exported = 0;

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

        return $exported;
    }

    private function buildExportResult(
        string $filename,
        string $filepath,
        int $rows,
    ): array {
        return array(
            ResponseKeyType::Success->value  => true,
            ResponseKeyType::Rows->value     => $rows,
            ResponseKeyType::Filename->value => $filename,
            ResponseKeyType::FileSize->value => filesize($filepath),
            ResponseKeyType::Checksum->value => md5_file($filepath),
        );
    }

    private function getCreateTableSql(string $table): ?string {
        $result = $this->wpdb->get_row("SHOW CREATE TABLE `{$table}`", ARRAY_N);

        return $result ? $result[1] : null;
    }
}
