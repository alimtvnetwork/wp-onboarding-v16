<?php
/**
 * IncrementalDeltaTrait — Delta detection and max-ID resolution.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   2.0.0
 */

namespace RiseupAsia\Snapshot\Traits;

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Helpers\BooleanHelpers;
use PDO;
use Throwable;

trait IncrementalDeltaTrait {

    private function exportTableDelta(string $tableName, array $info, string $incDir, PDO $rootPdo, int $sequence): ?array {
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

        if ($result['success']) {
            $this->log(LogLevelType::Info->value, sprintf('Incremental export: %s (+%d rows, %s)', $tableName, $result['rows'], $this->formatBytes($result['file_size'])));
            $result['entry'] = array('table' => $tableName, 'new_rows' => $result['rows'], 'size' => $result['file_size']);
        } else {
            $this->log(LogLevelType::Error->value, 'Incremental export failed: ' . $tableName, array('error' => $result['error']));
        }

        return $result;
    }

    private function getLastMaxId(string $tableName, array $info, PDO $rootPdo, int $sequence): ?int {
        if ($info['pk_column'] === null) {
            return null;
        }

        if ($sequence === 1) {
            return $this->getMaxIdFromMasterSqlite($rootPdo, $tableName, $info['pk_column'], $info);
        }

        return $this->getMaxIdFromPreviousIncremental($rootPdo, $tableName, $info['pk_column'], $sequence, $info);
    }

    private function getMaxIdFromMasterSqlite(PDO $rootPdo, string $tableName, string $pk, array $info): int {
        $sqliteFile = $this->findMasterSqliteFile($rootPdo, $tableName);
        if (!$sqliteFile) {
            return (int) $info['row_count'];
        }

        try {
            $tablePdo = new PDO('sqlite:' . $sqliteFile);
            $tablePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $maxId = $tablePdo->query("SELECT MAX(`{$pk}`) FROM `{$tableName}`")->fetchColumn();
            $tablePdo = null;
            return ($maxId !== false && $maxId !== null) ? (int) $maxId : 0;
        } catch (Throwable $e) {
            $this->log(LogLevelType::Warn->value, 'Could not read master SQLite for max ID', array('table' => $tableName, 'error' => $e->getMessage()));
            return (int) $info['row_count'];
        }
    }

    private function getMaxIdFromPreviousIncremental(PDO $rootPdo, string $tableName, string $pk, int $sequence, array $info): int {
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

    private function readMaxIdFromSqlite(string $sqlitePath, string $tableName, string $pk): ?int {
        if (BooleanHelpers::isFileMissing($sqlitePath)) {
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

        if (!$filename) {
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
