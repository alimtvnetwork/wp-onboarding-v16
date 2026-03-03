<?php
/**
 * ImportValidationTrait — SQLite validation and root DB reading.
 *
 * Supports both old snake_case and new PascalCase root DB schemas.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Helpers\InitHelpers;
use RiseupAsia\Helpers\PathHelper;
use PDO;
use PDOException;
use Exception;

trait ImportValidationTrait {
    use RootDbCompatTrait;

    private function validateSqliteFile(string $path, string $label): void {
        try {
            $pdo = new PDO('sqlite:' . $path);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $result = $pdo->query('PRAGMA integrity_check')->fetchColumn();
            $pdo = null;

            if ($result !== 'ok') {
                throw new Exception("Integrity check failed for {$label}: {$result}");
            }
        } catch (PDOException $e) {
            throw new Exception("Cannot open SQLite file {$label}: " . $e->getMessage());
        }
    }

    private function readRootDbMetadata(string $rootDbPath): ?array {
        try {
            $pdo = new PDO('sqlite:' . $rootDbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $table = $this->resolveRootTable($pdo, 'SnapshotMeta', 'snapshot_meta');
            $row = $pdo->query("SELECT * FROM {$table} LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $pdo = null;

            return $row ?: null;
        } catch (PDOException $e) {
            $this->logError($e, 'Failed to read a-root.db metadata');

            return null;
        }
    }

    private function readRootDbTables(string $rootDbPath): array {
        try {
            $pdo = new PDO('sqlite:' . $rootDbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $table = $this->resolveRootTable($pdo, 'SnapshotTables', 'snapshot_tables');
            $rows = $pdo->query("SELECT * FROM {$table} ORDER BY {$this->resolveRootCol($pdo, $table, 'Id', 'id')}")->fetchAll(PDO::FETCH_ASSOC);
            $pdo = null;

            return $rows ?: array();
        } catch (PDOException $e) {
            InitHelpers::errorLog($e, 'ImportValidationTrait::readRootDbTables() failed:');
            return array();
        }
    }

    private function readRootDbIncrementals(string $rootDbPath): array {
        try {
            $pdo = new PDO('sqlite:' . $rootDbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $table = $this->resolveRootTable($pdo, 'IncrementalBackups', 'incremental_backups');
            $rows = $pdo->query("SELECT * FROM {$table} ORDER BY {$this->resolveRootCol($pdo, $table, 'SequenceNum', 'sequence_num')}")->fetchAll(PDO::FETCH_ASSOC);
            $pdo = null;

            return $rows ?: array();
        } catch (PDOException $e) {
            InitHelpers::errorLog($e, 'ImportValidationTrait::readRootDbIncrementals() failed:');
            return array();
        }
    }

    private function readRootDbPlugins(string $rootDbPath): array {
        try {
            $pdo = new PDO('sqlite:' . $rootDbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $table = $this->resolveRootTable($pdo, 'PluginSnapshots', 'plugin_snapshots');
            $rows = $pdo->query("SELECT * FROM {$table} ORDER BY {$this->resolveRootCol($pdo, $table, 'Id', 'id')}")->fetchAll(PDO::FETCH_ASSOC);
            $pdo = null;

            return $rows ?: array();
        } catch (PDOException $e) {
            InitHelpers::errorLog($e, 'ImportValidationTrait::readRootDbPlugins() failed:');
            return array();
        }
    }

    private function findFileRecursive(string $dir, string $filename): ?string {
        $path = PathHelper::join($dir, $filename);
        if (PathHelper::fileExists($path)) {
            return $path;
        }

        $entries = scandir($dir);

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $subPath = PathHelper::join($dir, $entry, $filename);

            if (PathHelper::fileExists($subPath)) {
                return $subPath;
            }
        }

        return null;
    }
}