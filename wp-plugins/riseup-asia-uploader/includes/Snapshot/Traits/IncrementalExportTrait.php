<?php
/**
 * IncrementalExportTrait — Delta row export to SQLite.
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
use RiseupAsia\Helpers\InitHelpers;
use RiseupAsia\Snapshot\SqliteSchemaConverter;

trait IncrementalExportTrait {
    private function exportDeltaRows(
        string $incrementalDir,
        string $table,
        string $pkColumn,
        int $lastMaxId,
        int $expectedCount,
    ): array {
        $filename = $table . '.sqlite';
        $filepath = $incrementalDir . '/' . $filename;

        try {
            $sqlite = $this->createIncrementalSqliteTable($filepath, $table);
            $exported = $this->batchExportDelta($sqlite, $table, $pkColumn, $lastMaxId);
            $sqlite = null;

            return $this->buildDeltaExportSuccess($exported, $filepath);
        } catch (Throwable $e) {
            InitHelpers::errorLog($e, 'IncrementalExportTrait::exportTableFull() failed:');

            return $this->buildDeltaExportFailure($e);
        }
    }

    private function buildDeltaExportSuccess(int $exported, string $filepath): array {
        return array(
            ResponseKeyType::Success->value  => true,
            ResponseKeyType::Rows->value     => $exported,
            ResponseKeyType::FileSize->value => filesize($filepath),
            ResponseKeyType::Checksum->value => md5_file($filepath),
        );
    }

    private function buildDeltaExportFailure(Throwable $e): array {
        return array(
            ResponseKeyType::Success->value  => false,
            ResponseKeyType::Error->value    => $e->getMessage(),
            ResponseKeyType::Rows->value     => 0,
            ResponseKeyType::FileSize->value => 0,
            ResponseKeyType::Checksum->value => '',
        );
    }

    private function createIncrementalSqliteTable(string $filepath, string $table): PDO {
        $sqlite = new PDO('sqlite:' . $filepath);
        $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sqlite->exec('PRAGMA journal_mode = WAL');
        $sqlite->exec('PRAGMA synchronous = OFF');

        $createResult = $this->wpdb->get_row("SHOW CREATE TABLE `{$table}`", ARRAY_N);
        $isCreateResultMissing = ($createResult === null);

        if ($isCreateResultMissing) {
            throw new Exception('Failed to get CREATE TABLE for ' . $table);
        }

        $sqlite->exec(SqliteSchemaConverter::convert($createResult[1], $table));

        return $sqlite;
    }

    private function batchExportDelta(PDO $sqlite, string $table, string $pkColumn, int $lastMaxId): int {
        $prepared = $this->prepareDeltaBatchStatement($sqlite, $table);
        $sqlite->beginTransaction();

        $exported = $this->executeDeltaBatchLoop($prepared[ResponseKeyType::Stmt->value], $table, $pkColumn, $lastMaxId);
        $sqlite->commit();

        return $exported;
    }

    private function prepareDeltaBatchStatement(PDO $sqlite, string $table): array {
        $columns = $this->wpdb->get_results("DESCRIBE `{$table}`", ARRAY_A);
        $columnNames = array_column($columns, 'Field');
        $placeholders = implode(', ', array_fill(0, count($columnNames), '?'));
        $columnList = implode(', ', array_map(function(string $c): string { return "`{$c}`"; }, $columnNames));

        $stmt = $sqlite->prepare("INSERT OR REPLACE INTO `{$table}` ({$columnList}) VALUES ({$placeholders})");

        return array(ResponseKeyType::Stmt->value => $stmt, ResponseKeyType::Columns->value => $columnNames);
    }

    private function executeDeltaBatchLoop(PDOStatement $stmt, string $table, string $pkColumn, int $lastMaxId): int {
        $offset = 0;
        $exported = 0;

        while (true) {
            $rows = $this->fetchDeltaBatchRows($table, $pkColumn, $lastMaxId, $offset);

            if (empty($rows)) {
                break;
            }

            $exported += $this->insertDeltaBatchRows($stmt, $rows);
            $offset += $this->batchSize;
        }

        return $exported;
    }

    private function fetchDeltaBatchRows(string $table, string $pkColumn, int $lastMaxId, int $offset): array {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM `{$table}` WHERE `{$pkColumn}` > %d ORDER BY `{$pkColumn}` ASC LIMIT %d OFFSET %d",
                $lastMaxId, $this->batchSize, $offset
            ), ARRAY_N
        );
    }

    private function insertDeltaBatchRows(PDOStatement $stmt, array $rows): int {
        $count = 0;

        foreach ($rows as $row) {
            $stmt->execute($row);
            $count++;
        }

        return $count;
    }
}
