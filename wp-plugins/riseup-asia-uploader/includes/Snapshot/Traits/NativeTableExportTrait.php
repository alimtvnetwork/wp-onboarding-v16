<?php
/**
 * NativeTableExportTrait — MySQL-to-SQLite table export.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use PDO;
use PDOStatement;
use Throwable;
use Exception;

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\SnapshotConfigType;

trait NativeTableExportTrait {
    use NativeTableExportConvertTrait;

    private function exportTable(
        PDO $sqlite,
        string $table,
        int $snapshotId,
    ): array {
        try {
            $createSql = $this->getCreateTableSql($table);
            $isCreateSqlMissing = ($createSql === null || $createSql === false);

            if ($isCreateSqlMissing) {
                throw new Exception('Failed to get table structure');
            }

            $sqliteCreate = $this->convertCreateStatement($createSql, $table);
            $sqlite->exec($sqliteCreate);

            $count = (int) $this->wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");

            if ($count === 0) {
                return array(
                    ResponseKeyType::Success->value => true,
                    ResponseKeyType::Rows->value    => 0,
                    ResponseKeyType::Bytes->value   => 0,
                );
            }

            return $this->exportTableRows($sqlite, $table, $count);
        } catch (Throwable $e) {
            if ($sqlite->inTransaction()) {
                $sqlite->rollBack();
            }

            return array(
                ResponseKeyType::Success->value => false,
                ResponseKeyType::Error->value   => $e->getMessage(),
                ResponseKeyType::Rows->value    => 0,
                ResponseKeyType::Bytes->value   => 0,
            );
        }
    }

    private function exportTableRows(
        PDO $sqlite,
        string $table,
        int $count,
    ): array {
        $insert = $this->prepareInsertStatement($sqlite, $table);

        $sqlite->beginTransaction();
        $result = $this->executeBatchExport($insert[ResponseKeyType::Stmt->value], $table, $count);
        $sqlite->commit();

        return array(
            ResponseKeyType::Success->value => true,
            ResponseKeyType::Rows->value    => $result[ResponseKeyType::Exported->value],
            ResponseKeyType::Bytes->value   => $result[ResponseKeyType::Bytes->value],
        );
    }

    private function prepareInsertStatement(PDO $sqlite, string $table): array {
        $columns = $this->wpdb->get_results("DESCRIBE `{$table}`", ARRAY_A);
        $columnNames = array_column($columns, 'Field');
        $placeholders = implode(', ', array_fill(0, count($columnNames), '?'));
        $columnList = implode(', ', array_map(function($c) { return "`{$c}`"; }, $columnNames));

        $stmt = $sqlite->prepare("INSERT INTO `{$table}` ({$columnList}) VALUES ({$placeholders})");

        return array(ResponseKeyType::Stmt->value => $stmt);
    }

    private function executeBatchExport(
        PDOStatement $stmt,
        string $table,
        int $count,
    ): array {
        $batchSize = SnapshotConfigType::BatchSize->value;
        $offset = 0;
        $exported = 0;
        $bytes = 0;

        while ($offset < $count) {
            $rows = $this->wpdb->get_results(
                $this->wpdb->prepare("SELECT * FROM `{$table}` LIMIT %d OFFSET %d", $batchSize, $offset),
                ARRAY_N
            );

            foreach ($rows as $row) {
                $stmt->execute($row);
                $exported++;
                $bytes += strlen(implode('', array_map('strval', $row)));
            }

            $offset += $batchSize;
            $this->logExportProgress($table, $offset, $count, $batchSize);
        }

        return array(
            ResponseKeyType::Exported->value => $exported,
            ResponseKeyType::Bytes->value    => $bytes,
        );
    }

    private function logExportProgress(
        string $table,
        int $offset,
        int $count,
        int $batchSize,
    ): void {
        $progress = ($offset / $count) * 100;
        $prev = (($offset - $batchSize) / $count) * 100;

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
