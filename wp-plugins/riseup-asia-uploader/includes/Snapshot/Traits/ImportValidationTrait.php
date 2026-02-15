<?php
/**
 * ImportValidationTrait — SQLite validation and root DB reading.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;

trait ImportValidationTrait {

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
            $row = $pdo->query('SELECT * FROM snapshot_meta LIMIT 1')->fetch(PDO::FETCH_ASSOC);
            $pdo = null;
            return $row ?: null;
        } catch (PDOException $e) {
            $this->log(LogLevelType::Error->value, 'Failed to read a-root.db metadata', array('error' => $e->getMessage()));
            return null;
        }
    }

    private function readRootDbTables(string $rootDbPath): array {
        try {
            $pdo = new PDO('sqlite:' . $rootDbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $rows = $pdo->query('SELECT * FROM snapshot_tables ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
            $pdo = null;
            return $rows ?: array();
        } catch (PDOException $e) {
            return array();
        }
    }

    private function readRootDbIncrementals(string $rootDbPath): array {
        try {
            $pdo = new PDO('sqlite:' . $rootDbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $rows = $pdo->query('SELECT * FROM incremental_backups ORDER BY sequence_num')->fetchAll(PDO::FETCH_ASSOC);
            $pdo = null;
            return $rows ?: array();
        } catch (PDOException $e) {
            return array();
        }
    }

    private function readRootDbPlugins(string $rootDbPath): array {
        try {
            $pdo = new PDO('sqlite:' . $rootDbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $rows = $pdo->query('SELECT * FROM plugin_snapshots ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
            $pdo = null;
            return $rows ?: array();
        } catch (PDOException $e) {
            return array();
        }
    }

    private function findFileRecursive(string $dir, string $filename): ?string {
        $path = RiseupPathUtils::join($dir, $filename);
        if (RiseupPathUtils::fileExists($path)) {
            return $path;
        }

        $entries = scandir($dir);
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $subPath = RiseupPathUtils::join($dir, $entry, $filename);
            if (RiseupPathUtils::fileExists($subPath)) {
                return $subPath;
            }
        }
        return null;
    }
}
