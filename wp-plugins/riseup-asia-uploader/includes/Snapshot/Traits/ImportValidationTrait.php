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

trait ImportValidationTrait {

    /**
     * Validate a SQLite file using PRAGMA integrity_check.
     *
     * @param string $path  Full path to .sqlite file.
     * @param string $label Label for error messages.
     * @throws Exception On integrity failure.
     */
    private function validateSqliteFile($path, $label) {
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

    /**
     * Read snapshot metadata from a-root.db.
     *
     * @param string $rootDbPath Path to a-root.db.
     * @return array|null Metadata row.
     */
    private function readRootDbMetadata($rootDbPath) {
        try {
            $pdo = new PDO('sqlite:' . $rootDbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $row = $pdo->query('SELECT * FROM snapshot_meta LIMIT 1')->fetch(PDO::FETCH_ASSOC);
            $pdo = null;
            return $row ?: null;
        } catch (PDOException $e) {
            $this->log('ERROR', 'Failed to read a-root.db metadata', array('error' => $e->getMessage()));
            return null;
        }
    }

    /**
     * Read table inventory from a-root.db.
     *
     * @param string $rootDbPath Path to a-root.db.
     * @return array List of table records.
     */
    private function readRootDbTables($rootDbPath) {
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

    /**
     * Read incremental backup registry from a-root.db.
     *
     * @param string $rootDbPath Path to a-root.db.
     * @return array List of incremental records.
     */
    private function readRootDbIncrementals($rootDbPath) {
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

    /**
     * Read plugin snapshots from a-root.db.
     *
     * @param string $rootDbPath Path to a-root.db.
     * @return array List of plugin snapshot records.
     */
    private function readRootDbPlugins($rootDbPath) {
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

    /**
     * Find a file recursively in a directory (one level deep).
     *
     * @param string $dir      Directory to search.
     * @param string $filename Filename to find.
     * @return string|null Full path or null.
     */
    private function findFileRecursive($dir, $filename) {
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
