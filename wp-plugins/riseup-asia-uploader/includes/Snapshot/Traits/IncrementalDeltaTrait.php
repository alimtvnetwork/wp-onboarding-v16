<?php
/**
 * IncrementalDeltaTrait — Delta detection and max-ID resolution.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   2.0.0
 */

namespace RiseupAsia\Snapshot\Traits;

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Helpers\PathHelper;
use PDO;
use Throwable;

trait IncrementalDeltaTrait {

    private function exportTableDelta(
        string $tableName,
        array $info,
        string $incDir,
        PDO $rootPdo,
        int $sequence,
    ): ?array {
        $lastMaxId = $this->getLastMaxId($tableName, $info, $rootPdo, $sequence);

        if ($lastMaxId === null) {
            $this->log(LogLevelType::Info->value, 'Skipping table (no auto-increment PK): ' . $tableName);

            return null;
        }

        $newCount = (int) $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT COUNT(*) FROM `{$tableName}` WHERE `{$info['pk_column']}` > %d", $lastMaxId)
        );

        if ($newCount === 0) {

            return null;
        }

        $result = $this->exportDeltaRows($incDir, $tableName, $info['pk_column'], $lastMaxId, $newCount);

        if ($result[ResponseKeyType::Success->value]) {
            $this->log(LogLevelType::Info->value, sprintf('Incremental export: %s (+%d rows, %s)', $tableName, $result[ResponseKeyType::Rows->value], $this->formatBytes($result[ResponseKeyType::FileSize->value])));
            $result[ResponseKeyType::Entry->value] = array('table' => $tableName, 'new_rows' => $result[ResponseKeyType::Rows->value], ResponseKeyType::Size->value => $result[ResponseKeyType::FileSize->value]);
        } else {
            $this->log(LogLevelType::Error->value, 'Incremental export failed: ' . $tableName, array(ResponseKeyType::Error->value => $result[ResponseKeyType::Error->value]));
        }

        return $result;
    }

    private function exportDeltaRows(
        string $incDir,
        string $table,
        string $pkColumn,
        int $lastMaxId,
        int $newCount,
    ): array {
        $filename = $table . '.sqlite';
        $filepath = $incDir . '/' . $filename;

        try {
            $sqlite = new PDO('sqlite:' . $filepath);
            $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $sqlite->exec('PRAGMA journal_mode = WAL');
            $sqlite->exec('PRAGMA synchronous = OFF');

            $create_sql = $this->getCreateTableSql($table);
            $sqlite->exec($create_sql);

            $offset = 0;
            $exported = 0;
            $batchSize = 250;

            while ($offset < $newCount) {
                $rows = $this->wpdb->get_results(
                    $this->wpdb->prepare("SELECT * FROM `{$table}` WHERE `{$pkColumn}` > %d ORDER BY `{$pkColumn}` ASC LIMIT %d OFFSET %d", $lastMaxId, $batchSize, $offset),
                    ARRAY_A
                );
                foreach ($rows as $row) {
                    $columns = array_keys($row);
                    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
                    $columnList = implode(', ', array_map(function($c) { return "`{$c}`"; }, $columns));
                    $stmt = $sqlite->prepare("INSERT INTO `{$table}` ({$columnList}) VALUES ({$placeholders})");
                    $stmt->execute(array_values($row));
                    $exported++;
                }
                $offset += $batchSize;
            }
            $sqlite = null;

            return array(
                ResponseKeyType::Success->value => true,
                ResponseKeyType::Rows->value => $exported,
                ResponseKeyType::Filename->value => $filename,
                ResponseKeyType::FileSize->value => filesize($filepath),
                ResponseKeyType::Checksum->value => md5_file($filepath),
            );
        } catch (Throwable $e) {

            return array(
                ResponseKeyType::Success->value => false, ResponseKeyType::Error->value => $e->getMessage(),
                ResponseKeyType::Rows->value => 0, ResponseKeyType::Filename->value => $filename, ResponseKeyType::FileSize->value => 0, ResponseKeyType::Checksum->value => '',
            );
        }
    }

    private function getLastMaxId(
        string $tableName,
        array $info,
        PDO $rootPdo,
        int $sequence,
    ): ?int {
        if ($info['pk_column'] === null) {

            return null;
        }

        if ($sequence === 1) {

            return $this->getMaxIdFromMasterSqlite($rootPdo, $tableName, $info['pk_column'], $info);
        }

        return $this->getMaxIdFromPreviousIncremental($rootPdo, $tableName, $info['pk_column'], $sequence, $info);
    }

    private function getMaxIdFromMasterSqlite(
        PDO $rootPdo,
        string $tableName,
        string $pk,
        array $info,
    ): int {
        $sqliteFile = $this->findMasterSqliteFile($rootPdo, $tableName);
        $isSqliteFileMissing = ($sqliteFile === null);
        if ($isSqliteFileMissing) {

            return (int) $info['row_count'];
        }

        try {
            $tablePdo = new PDO('sqlite:' . $sqliteFile);
            $tablePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $maxId = $tablePdo->query("SELECT MAX(`{$pk}`) FROM `{$tableName}`")->fetchColumn();
            $tablePdo = null;

            return ($maxId !== false && $maxId !== null) ? (int) $maxId : 0;
        } catch (Throwable $e) {
            $this->log(LogLevelType::Warn->value, 'Could not read master SQLite for max ID', array('table' => $tableName, ResponseKeyType::Error->value => $e->getMessage()));

            return (int) $info['row_count'];
        }
    }

    private function getMaxIdFromPreviousIncremental(
        PDO $rootPdo,
        string $tableName,
        string $pk,
        int $sequence,
        array $info,
    ): int {
        $prevSeq = $sequence - 1;
        $prevFolder = $rootPdo->query("SELECT folder_name FROM incremental_backups WHERE sequence_num = {$prevSeq}")->fetchColumn();

        if ($prevFolder) {
            $rootDir = $this->getRootDirFromPdo($rootPdo);
            $prevSqlite = $rootDir . '/incremental/' . $prevFolder . '/' . $tableName . '.sqlite';
            $maxId = $this->readMaxIdFromSqlite($prevSqlite, $tableName, $pk);
            if ($maxId !== null) {

                return $maxId;
            }
        }

        return $this->getMaxIdFromMasterSqlite($rootPdo, $tableName, $pk, $info);
    }

    private function readMaxIdFromSqlite(
        string $sqlitePath,
        string $tableName,
        string $pk,
    ): ?int {
        if (PathHelper::isFileMissing($sqlitePath)) {

            return null;
        }

        try {
            $pdo = new PDO('sqlite:' . $sqlitePath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $maxId = $pdo->query("SELECT MAX(`{$pk}`) FROM `{$tableName}`")->fetchColumn();
            $pdo = null;

            return ($maxId !== false && $maxId !== null) ? (int) $maxId : null;
        } catch (Throwable $e) {

            return null;
        }
    }

    private function findMasterSqliteFile(PDO $rootPdo, string $tableName): ?string {
        $stmt = $rootPdo->prepare("SELECT sqlite_file FROM snapshot_tables WHERE table_name = ?");
        $stmt->execute(array($tableName));
        $filename = $stmt->fetchColumn();

        $isFilenameAbsent = ($filename === false || $filename === null);
        if ($isFilenameAbsent) {

            return null;
        }

        $rootDir = $this->getRootDirFromPdo($rootPdo);
        $fullPath = $rootDir . '/' . $filename;

        return file_exists($fullPath) ? $fullPath : null;
    }

    private function getRootDirFromPdo(PDO $rootPdo): string {
        $result = $rootPdo->query("PRAGMA database_list")->fetch(PDO::FETCH_ASSOC);
        if ($result && isset($result['file'])) {

            return dirname($result['file']);
        }

        return '';
    }

    private function getNextSequence(PDO $rootPdo): int {
        $max = $rootPdo->query("SELECT MAX(sequence_num) FROM incremental_backups")->fetchColumn();

        return ($max !== false && $max !== null) ? (int) $max + 1 : 1;
    }
}
