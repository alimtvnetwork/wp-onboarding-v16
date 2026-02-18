<?php
/**
 * ManagerTableRestoreTrait — Low-level MySQL table restore from SQLite.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   2.0.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use PDO;
use Throwable;
use Exception;
use RiseupAsia\Enums\SnapshotConfigType;
use RiseupAsia\Enums\SnapshotScopeType;
use RiseupAsia\Enums\SnapshotTriggerType;

trait ManagerTableRestoreTrait {

    private function restoreTable(PDO $sqlite, string $table): array {
        try {
            $check = $sqlite->query("SELECT name FROM sqlite_master WHERE type='table' AND name='{$table}'");
            $isTableAbsent = ($check->fetch() === false);
            if ($isTableAbsent) {

                return array('success' => false, 'error' => 'Table not found in snapshot', 'rows' => 0);
            }

            $columnsResult = $sqlite->query("PRAGMA table_info('{$table}')");
            $columns = $columnsResult->fetchAll(PDO::FETCH_ASSOC);
            $columnNames = array_column($columns, 'name');

            return $this->truncateAndInsert($sqlite, $table, $columnNames);
        } catch (Throwable $e) {

            return array('success' => false, 'error' => $e->getMessage(), 'rows' => 0);
        }
    }

    private function truncateAndInsert(
        PDO $sqlite,
        string $table,
        array $columnNames,
    ): array {
        $this->wpdb->query("START TRANSACTION");

        try {
            $this->wpdb->query("SET FOREIGN_KEY_CHECKS = 0");
            $this->wpdb->query("TRUNCATE TABLE `{$table}`");

            $countStmt = $sqlite->query("SELECT COUNT(*) FROM `{$table}`");
            $rowCount = (int) $countStmt->fetchColumn();

            $totalRows = $this->insertBatchFromSqlite($sqlite, $table, $columnNames, $rowCount);

            $this->wpdb->query("SET FOREIGN_KEY_CHECKS = 1");
            $this->wpdb->query("COMMIT");

            return array('success' => true, 'rows' => $totalRows);
        } catch (Throwable $e) {
            $this->wpdb->query("ROLLBACK");
            $this->wpdb->query("SET FOREIGN_KEY_CHECKS = 1");

            throw $e;
        }
    }

    private function insertBatchFromSqlite(
        PDO $sqlite,
        string $table,
        array $columnNames,
        int $rowCount,
    ): int {
        $batchSize = SnapshotConfigType::BatchSize->value;
        $offset = 0;
        $totalRows = 0;
        $columnsSql = '`' . implode('`, `', $columnNames) . '`';
        $placeholdersSql = implode(', ', array_fill(0, count($columnNames), '%s'));

        while ($offset < $rowCount) {
            $rows = $sqlite->query("SELECT * FROM `{$table}` LIMIT {$batchSize} OFFSET {$offset}")->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $values = array();
                foreach ($columnNames as $col) {
                    $values[] = $row[$col] ?? null;
                }

                $sql = "INSERT INTO `{$table}` ({$columnsSql}) VALUES ({$placeholdersSql})";
                $this->wpdb->query($this->wpdb->prepare($sql, $values));
                $totalRows++;
            }

            $offset += $batchSize;
        }

        return $totalRows;
    }

    private function createPreRestoreBackup(int $originalSnapshotId): array {
        $provider = $this->getProvider();
        $isProviderMissing = ($provider === null);
        if ($isProviderMissing) {

            return array('success' => false, 'error' => 'No provider available');
        }

        return $provider->createSnapshot(array(
            'scope' => SnapshotScopeType::WordPress->value,
            'trigger' => SnapshotTriggerType::Api->value,
            'pre_restore_of' => $originalSnapshotId,
        ));
    }
}
