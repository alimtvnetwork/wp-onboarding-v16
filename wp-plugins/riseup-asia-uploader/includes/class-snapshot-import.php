<?php
/**
 * Riseup Asia Uploader - Snapshot Import Engine
 *
 * Handles importing per-table snapshot archives (ZIP) with validation,
 * integrity checking, and registration into the snapshot system.
 *
 * Supports both legacy single-file snapshots and the new per-table format
 * with a-root.db, multiple .sqlite files, and optional plugin archives.
 *
 * @package RiseupAsiaUploader
 * @since   1.16.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Snapshot Import Engine.
 *
 * Validates, extracts, and registers imported snapshot ZIP archives.
 */
class RiseupSnapshotImport {

    /** @var RiseupFileLogger */
    private $logger;

    /** @var RiseupDatabase */
    private $db;

    /** @var RiseupSnapshotManager */
    private $manager;

    /** @var string */
    private $baseDir;

    /** @var array Validation errors collected during import */
    private $validationErrors = array();

    /**
     * Constructor.
     *
     * @param RiseupFileLogger      $logger  Logger instance.
     * @param RiseupDatabase        $db      Database instance.
     * @param RiseupSnapshotManager $manager Snapshot manager.
     */
    public function __construct($logger, $db, $manager) {
        $this->logger  = $logger;
        $this->db      = $db;
        $this->manager = $manager;
        $this->baseDir = RiseupPathUtils::getBaseDir();
    }

    /**
     * Import a snapshot from an uploaded ZIP file.
     *
     * Detects format (per-table vs legacy) and delegates accordingly.
     *
     * @param string $uploadedPath Path to uploaded ZIP file.
     * @return array Result with success status and snapshot details.
     */
    public function import($uploadedPath) {
        // Validate file exists
        if (!RiseupPathUtils::fileExists($uploadedPath)) {
            return $this->fail('Uploaded file not found');
        }

        $ext = strtolower(pathinfo($uploadedPath, PATHINFO_EXTENSION));
        if ($ext !== 'zip') {
            return $this->fail('Invalid file type. Expected ZIP file.');
        }

        $fileSize = filesize($uploadedPath);
        $this->log('INFO', 'Starting snapshot import', array(
            'path' => basename($uploadedPath),
            'size' => RiseupPathUtils::formatBytes($fileSize),
        ));

        // Create temp extraction directory
        $tempDir = RiseupPathUtils::join($this->baseDir, RISEUP_TEMP_SUBDIR, 'import_' . uniqid());
        if (!RiseupPathUtils::ensureDir($tempDir, false)) {
            return $this->fail('Failed to create temp directory');
        }

        try {
            // Extract ZIP
            $zip = new ZipArchive();
            if ($zip->open($uploadedPath) !== true) {
                throw new Exception('Failed to open ZIP file');
            }
            $zip->extractTo($tempDir);
            $zip->close();

            // Detect format: per-table (a-root.db) vs legacy (manifest.json)
            $rootDbPath = $this->findFileRecursive($tempDir, 'a-root.db');

            if ($rootDbPath !== null) {
                $result = $this->importPerTable($tempDir, $rootDbPath);
            } else {
                // Delegate to legacy manager import
                $result = $this->manager->importSnapshot($uploadedPath);
            }

            // Cleanup temp directory
            $this->deleteDirectory($tempDir);
            return $result;

        } catch (Exception $e) {
            if (RiseupPathUtils::dirExists($tempDir)) {
                $this->deleteDirectory($tempDir);
            }
            $this->log('ERROR', 'Snapshot import failed', array('error' => $e->getMessage()));
            return $this->fail($e->getMessage());
        }
    }

    /**
     * Import a per-table snapshot (with a-root.db).
     *
     * @param string $tempDir    Extracted temp directory.
     * @param string $rootDbPath Full path to a-root.db.
     * @return array Result.
     */
    private function importPerTable($tempDir, $rootDbPath) {
        $this->log('INFO', 'Detected per-table snapshot format');

        // Determine the snapshot root (directory containing a-root.db)
        $snapshotRoot = dirname($rootDbPath);

        // 1. Validate a-root.db integrity
        $this->validateSqliteFile($rootDbPath, 'a-root.db');

        // 2. Read metadata from a-root.db
        $metadata = $this->readRootDbMetadata($rootDbPath);
        if (!$metadata) {
            throw new Exception('Failed to read metadata from a-root.db');
        }

        // 3. Validate all table .sqlite files exist and have integrity
        $tables = $this->readRootDbTables($rootDbPath);
        $this->log('INFO', 'Validating table files', array('count' => count($tables)));

        foreach ($tables as $table) {
            $sqlitePath = RiseupPathUtils::join($snapshotRoot, $table['sqlite_file']);
            if (!RiseupPathUtils::fileExists($sqlitePath)) {
                throw new Exception("Missing table file: {$table['sqlite_file']}");
            }

            // Verify checksum if available
            if (RiseupBooleanHelpers::has_content($table['checksum_md5'])) {
                $actualMd5 = md5_file($sqlitePath);
                if ($actualMd5 !== $table['checksum_md5']) {
                    throw new Exception("Checksum mismatch for {$table['sqlite_file']}: expected {$table['checksum_md5']}, got {$actualMd5}");
                }
            }

            // PRAGMA integrity_check
            $this->validateSqliteFile($sqlitePath, $table['sqlite_file']);
        }

        // 4. Validate incremental backups if present
        $incrementals = $this->readRootDbIncrementals($rootDbPath);
        foreach ($incrementals as $inc) {
            $incDir = RiseupPathUtils::join($snapshotRoot, $inc['relative_path']);
            if (!RiseupPathUtils::dirExists($incDir)) {
                $this->log('WARN', 'Incremental directory missing, skipping', array(
                    'folder' => $inc['folder_name'],
                ));
                continue;
            }
            // Validate each .sqlite file in the incremental
            $incFiles = glob(RiseupPathUtils::join($incDir, '*.sqlite'));
            foreach ($incFiles as $incFile) {
                $this->validateSqliteFile($incFile, basename($incFile));
            }
        }

        // 5. Validate plugin snapshots if present
        $plugins = $this->readRootDbPlugins($rootDbPath);
        foreach ($plugins as $plugin) {
            $zipPath = RiseupPathUtils::join($snapshotRoot, $plugin['zip_file']);
            if (!RiseupPathUtils::fileExists($zipPath)) {
                $this->log('WARN', 'Plugin archive missing, skipping', array(
                    'plugin' => $plugin['plugin_slug'],
                ));
                continue;
            }
            // Verify checksum
            if (RiseupBooleanHelpers::has_content($plugin['checksum_md5'])) {
                $actualMd5 = md5_file($zipPath);
                if ($actualMd5 !== $plugin['checksum_md5']) {
                    throw new Exception("Plugin checksum mismatch for {$plugin['plugin_slug']}");
                }
            }
        }

        // 6. Move entire snapshot to snapshots directory
        $snapshotsDir = RiseupPathUtils::join($this->baseDir, RISEUP_SNAPSHOTS_SUBDIR);
        if (!RiseupPathUtils::ensureDir($snapshotsDir, true)) {
            throw new Exception('Failed to ensure snapshots directory');
        }

        $title = $metadata['title'] ?? 'imported';
        $folderName = date('Y-m-d') . '_imported_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $title);
        $destDir = RiseupPathUtils::join($snapshotsDir, $folderName);

        // Ensure unique folder name
        $counter = 1;
        while (RiseupPathUtils::dirExists($destDir)) {
            $destDir = RiseupPathUtils::join($snapshotsDir, $folderName . '_' . $counter);
            $counter++;
        }

        // Copy entire snapshot directory tree
        $this->copyDirectory($snapshotRoot, $destDir);

        // 7. Register in snapshot database
        $snapshotId = $this->registerImportedSnapshot($metadata, $tables, $incrementals, $plugins, $destDir);

        $this->log('INFO', 'Per-table snapshot imported successfully', array(
            'snapshotId' => $snapshotId,
            'tables'     => count($tables),
            'incrementals' => count($incrementals),
            'plugins'    => count($plugins),
        ));

        return array(
            'success'      => true,
            'snapshot_id'  => $snapshotId,
            'folder'       => basename($destDir),
            'type'         => $metadata['type'] ?? 'full',
            'tables'       => count($tables),
            'total_rows'   => $metadata['total_rows'] ?? 0,
            'incrementals' => count($incrementals),
            'plugins'      => count($plugins),
        );
    }

    /**
     * Validate a SQLite file using PRAGMA integrity_check.
     *
     * @param string $path     Full path to .sqlite file.
     * @param string $label    Label for error messages.
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
            $stmt = $pdo->query('SELECT * FROM snapshot_meta LIMIT 1');
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
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
            $stmt = $pdo->query('SELECT * FROM snapshot_tables ORDER BY id');
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
            $stmt = $pdo->query('SELECT * FROM incremental_backups ORDER BY sequence_num');
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
            $stmt = $pdo->query('SELECT * FROM plugin_snapshots ORDER BY id');
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $pdo = null;
            return $rows ?: array();
        } catch (PDOException $e) {
            return array();
        }
    }

    /**
     * Register the imported snapshot in the database.
     *
     * @param array  $metadata     Metadata from a-root.db.
     * @param array  $tables       Table inventory.
     * @param array  $incrementals Incremental backup records.
     * @param array  $plugins      Plugin snapshot records.
     * @param string $destDir      Final destination directory.
     * @return int Snapshot ID.
     */
    private function registerImportedSnapshot($metadata, $tables, $incrementals, $plugins, $destDir) {
        $tableNames = array_map(function($t) { return $t['table_name']; }, $tables);

        $data = array(
            'sequence'       => $this->manager->getNextSequence(),
            'filename'       => basename($destDir),
            'filepath'       => $destDir,
            'provider'       => RISEUP_SNAPSHOT_PROVIDER_NATIVE,
            'scope'          => RISEUP_SNAPSHOT_SCOPE_ALL,
            'tables_json'    => json_encode($tableNames),
            'total_rows'     => $metadata['total_rows'] ?? 0,
            'file_size'      => $this->getDirectorySize($destDir),
            'trigger_source' => 'import',
            'status'         => RISEUP_SNAPSHOT_STATUS_COMPLETE,
            'created_at'     => date('c'),
            'completed_at'   => date('c'),
            'import_source'  => json_encode(array(
                'original_title'      => $metadata['title'] ?? null,
                'original_type'       => $metadata['type'] ?? null,
                'original_created_at' => $metadata['created_at'] ?? null,
                'wp_version'          => $metadata['wp_version'] ?? null,
                'mysql_version'       => $metadata['mysql_version'] ?? null,
                'table_count'         => count($tables),
                'incremental_count'   => count($incrementals),
                'plugin_count'        => count($plugins),
                'format'              => 'per_table',
            )),
        );

        $result = $this->db->insert(RISEUP_TABLE_SNAPSHOTS, $data);
        if ($result) {
            return $this->db->lastInsertId();
        }

        throw new Exception('Failed to create snapshot record in database');
    }

    /**
     * Find a file recursively in a directory.
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

        // Check one level deep (ZIP may have a root folder)
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

    /**
     * Copy a directory recursively.
     *
     * @param string $src  Source directory.
     * @param string $dest Destination directory.
     */
    private function copyDirectory($src, $dest) {
        if (!RiseupPathUtils::ensureDir($dest, false)) {
            throw new Exception("Failed to create directory: {$dest}");
        }

        $entries = scandir($src);
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;

            $srcPath  = RiseupPathUtils::join($src, $entry);
            $destPath = RiseupPathUtils::join($dest, $entry);

            if (is_dir($srcPath)) {
                $this->copyDirectory($srcPath, $destPath);
            } else {
                if (!copy($srcPath, $destPath)) {
                    throw new Exception("Failed to copy file: {$entry}");
                }
            }
        }
    }

    /**
     * Delete a directory recursively.
     *
     * @param string $dir Directory path.
     */
    private function deleteDirectory($dir) {
        if (RiseupBooleanHelpers::is_dir_missing($dir)) return;

        $entries = scandir($dir);
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;

            $path = RiseupPathUtils::join($dir, $entry);
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    /**
     * Get total size of a directory recursively.
     *
     * @param string $dir Directory path.
     * @return int Total size in bytes.
     */
    private function getDirectorySize($dir) {
        $size = 0;
        $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($entries as $entry) {
            $size += $entry->getSize();
        }
        return $size;
    }

    /**
     * Log a message.
     *
     * @param string $level   Log level.
     * @param string $message Message.
     * @param array  $context Context data.
     */
    private function log($level, $message, $context = array()) {
        $method = strtolower($level);
        if (method_exists($this->logger, $method)) {
            $this->logger->$method('[SnapshotImport] ' . $message, $context);
        }
    }

    /**
     * Return a failure result.
     *
     * @param string $message Error message.
     * @return array
     */
    private function fail($message) {
        return array(
            'success' => false,
            'error'   => $message,
        );
    }
}
