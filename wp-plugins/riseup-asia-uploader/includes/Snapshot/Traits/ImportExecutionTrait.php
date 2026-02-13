<?php
/**
 * ImportExecutionTrait — per-table import execution, registration, and file operations.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait ImportExecutionTrait {

    /**
     * Import a per-table snapshot (with a-root.db).
     *
     * @param string $tempDir    Extracted temp directory.
     * @param string $rootDbPath Full path to a-root.db.
     * @return array Result.
     */
    private function importPerTable($tempDir, $rootDbPath) {
        $this->log('INFO', 'Detected per-table snapshot format');
        $snapshotRoot = dirname($rootDbPath);

        $this->validateSqliteFile($rootDbPath, 'a-root.db');

        $metadata = $this->readRootDbMetadata($rootDbPath);
        if (!$metadata) {
            throw new Exception('Failed to read metadata from a-root.db');
        }

        $tables = $this->readRootDbTables($rootDbPath);
        $this->log('INFO', 'Validating table files', array('count' => count($tables)));
        $this->validateTableFiles($snapshotRoot, $tables);

        $incrementals = $this->readRootDbIncrementals($rootDbPath);
        $this->validateIncrementalFiles($snapshotRoot, $incrementals);

        $plugins = $this->readRootDbPlugins($rootDbPath);
        $this->validatePluginFiles($snapshotRoot, $plugins);

        $destDir = $this->moveSnapshotToFinalLocation($snapshotRoot, $metadata);
        $snapshotId = $this->registerImportedSnapshot($metadata, $tables, $incrementals, $plugins, $destDir);

        $this->log('INFO', 'Per-table snapshot imported successfully', array(
            'snapshotId' => $snapshotId, 'tables' => count($tables),
            'incrementals' => count($incrementals), 'plugins' => count($plugins),
        ));

        return array(
            'success' => true, 'snapshot_id' => $snapshotId, 'folder' => basename($destDir),
            'type' => $metadata['type'] ?? 'full', 'tables' => count($tables),
            'total_rows' => $metadata['total_rows'] ?? 0,
            'incrementals' => count($incrementals), 'plugins' => count($plugins),
        );
    }

    /**
     * Validate table .sqlite files exist with checksum verification.
     *
     * @param string $snapshotRoot Root directory.
     * @param array  $tables       Table records.
     */
    private function validateTableFiles(string $snapshotRoot, array $tables) {
        foreach ($tables as $table) {
            $sqlitePath = RiseupPathUtils::join($snapshotRoot, $table['sqlite_file']);
            if (!RiseupPathUtils::fileExists($sqlitePath)) {
                throw new Exception("Missing table file: {$table['sqlite_file']}");
            }
            if (!empty($table['checksum_md5'])) {
                $actualMd5 = md5_file($sqlitePath);
                if ($actualMd5 !== $table['checksum_md5']) {
                    throw new Exception("Checksum mismatch for {$table['sqlite_file']}: expected {$table['checksum_md5']}, got {$actualMd5}");
                }
            }
            $this->validateSqliteFile($sqlitePath, $table['sqlite_file']);
        }
    }

    /**
     * Validate incremental backup files.
     *
     * @param string $snapshotRoot Root directory.
     * @param array  $incrementals Incremental records.
     */
    private function validateIncrementalFiles(string $snapshotRoot, array $incrementals) {
        foreach ($incrementals as $inc) {
            $incDir = RiseupPathUtils::join($snapshotRoot, $inc['relative_path']);
            if (!RiseupPathUtils::dirExists($incDir)) {
                $this->log('WARN', 'Incremental directory missing, skipping', array('folder' => $inc['folder_name']));
                continue;
            }
            $incFiles = glob(RiseupPathUtils::join($incDir, '*.sqlite'));
            foreach ($incFiles as $incFile) {
                $this->validateSqliteFile($incFile, basename($incFile));
            }
        }
    }

    /**
     * Validate plugin archive files.
     *
     * @param string $snapshotRoot Root directory.
     * @param array  $plugins      Plugin records.
     */
    private function validatePluginFiles(string $snapshotRoot, array $plugins) {
        foreach ($plugins as $plugin) {
            $zipPath = RiseupPathUtils::join($snapshotRoot, $plugin['zip_file']);
            if (!RiseupPathUtils::fileExists($zipPath)) {
                $this->log('WARN', 'Plugin archive missing, skipping', array('plugin' => $plugin['plugin_slug']));
                continue;
            }
            if (!empty($plugin['checksum_md5'])) {
                $actualMd5 = md5_file($zipPath);
                if ($actualMd5 !== $plugin['checksum_md5']) {
                    throw new Exception("Plugin checksum mismatch for {$plugin['plugin_slug']}");
                }
            }
        }
    }

    /**
     * Move snapshot to final location in snapshots directory.
     *
     * @param string $snapshotRoot Source directory.
     * @param array  $metadata     Snapshot metadata.
     * @return string Destination directory path.
     */
    private function moveSnapshotToFinalLocation(string $snapshotRoot, array $metadata): string {
        $snapshotsDir = RiseupPathUtils::getSnapshotsDir();
        if (!RiseupPathUtils::ensureDir($snapshotsDir, true)) {
            throw new Exception('Failed to ensure snapshots directory');
        }

        $title = $metadata['title'] ?? 'imported';
        $folderName = date('Y-m-d') . '_imported_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $title);
        $destDir = RiseupPathUtils::join($snapshotsDir, $folderName);

        $counter = 1;
        while (RiseupPathUtils::dirExists($destDir)) {
            $destDir = RiseupPathUtils::join($snapshotsDir, $folderName . '_' . $counter);
            $counter++;
        }

        $this->copyDirectory($snapshotRoot, $destDir);
        return $destDir;
    }

    /**
     * Register the imported snapshot in the database.
     *
     * @param array  $metadata     Metadata from a-root.db.
     * @param array  $tables       Table inventory.
     * @param array  $incrementals Incremental records.
     * @param array  $plugins      Plugin records.
     * @param string $destDir      Final destination directory.
     * @return int Snapshot ID.
     */
    private function registerImportedSnapshot($metadata, $tables, $incrementals, $plugins, $destDir) {
        $tableNames = array_map(function($t) { return $t['table_name']; }, $tables);

        $result = $this->db->insert(TABLE_SNAPSHOTS, array(
            'sequence' => $this->manager->getNextSequence(), 'filename' => basename($destDir),
            'filepath' => $destDir, 'provider' => SNAPSHOT_PROVIDER_NATIVE,
            'scope' => SNAPSHOT_SCOPE_ALL, 'tables_json' => json_encode($tableNames),
            'total_rows' => $metadata['total_rows'] ?? 0, 'file_size' => $this->getDirectorySize($destDir),
            'trigger_source' => 'import', 'status' => SNAPSHOT_STATUS_COMPLETE,
            'created_at' => date('c'), 'completed_at' => date('c'),
            'import_source' => json_encode(array(
                'original_title' => $metadata['title'] ?? null, 'original_type' => $metadata['type'] ?? null,
                'original_created_at' => $metadata['created_at'] ?? null,
                'wp_version' => $metadata['wp_version'] ?? null, 'mysql_version' => $metadata['mysql_version'] ?? null,
                'table_count' => count($tables), 'incremental_count' => count($incrementals),
                'plugin_count' => count($plugins), 'format' => 'per_table',
            )),
        ));

        if ($result) {
            return $this->db->lastInsertId();
        }
        throw new Exception('Failed to create snapshot record in database');
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
            $srcPath = RiseupPathUtils::join($src, $entry);
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
}
