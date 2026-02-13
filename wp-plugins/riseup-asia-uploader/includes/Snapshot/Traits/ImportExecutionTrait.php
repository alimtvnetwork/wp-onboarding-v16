<?php
/**
 * ImportExecutionTrait — per-table import execution and registration.
 *
 * Shell trait — file validation delegated to ImportExecutionFileTrait.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;

require_once __DIR__ . '/ImportExecutionFileTrait.php';

trait ImportExecutionTrait {

    use ImportExecutionFileTrait;

    /** Import a per-table snapshot (with a-root.db). */
    private function importPerTable($tempDir, $rootDbPath) {
        $this->log(LogLevelType::Info->value, 'Detected per-table snapshot format');
        $snapshotRoot = dirname($rootDbPath);

        $metadata = $this->extractAndValidateRootDb($rootDbPath, $snapshotRoot);
        $inventories = $this->validateAllImportFiles($rootDbPath, $snapshotRoot);

        $destDir = $this->moveSnapshotToFinalLocation($snapshotRoot, $metadata);
        $snapshotId = $this->registerImportedSnapshot($metadata, $inventories['tables'], $inventories['incrementals'], $inventories['plugins'], $destDir);

        return $this->buildImportResult($snapshotId, $destDir, $metadata, $inventories);
    }

    /** Extract and validate the root database file and metadata. */
    private function extractAndValidateRootDb(string $rootDbPath, string $snapshotRoot): array {
        $this->validateSqliteFile($rootDbPath, 'a-root.db');
        $metadata = $this->readRootDbMetadata($rootDbPath);

        if (!$metadata) {
            throw new Exception('Failed to read metadata from a-root.db');
        }

        return $metadata;
    }

    /** Validate all import file inventories (tables, incrementals, plugins). */
    private function validateAllImportFiles(string $rootDbPath, string $snapshotRoot): array {
        $tables = $this->readRootDbTables($rootDbPath);
        $this->log(LogLevelType::Info->value, 'Validating table files', array('count' => count($tables)));
        $this->validateTableFiles($snapshotRoot, $tables);

        $incrementals = $this->readRootDbIncrementals($rootDbPath);
        $this->validateIncrementalFiles($snapshotRoot, $incrementals);

        $plugins = $this->readRootDbPlugins($rootDbPath);
        $this->validatePluginFiles($snapshotRoot, $plugins);

        return array('tables' => $tables, 'incrementals' => $incrementals, 'plugins' => $plugins);
    }

    /** Build the final import result array. */
    private function buildImportResult(int $snapshotId, string $destDir, array $metadata, array $inventories): array {
        $this->log(LogLevelType::Info->value, 'Per-table snapshot imported successfully', array(
            'snapshotId' => $snapshotId, 'tables' => count($inventories['tables']),
            'incrementals' => count($inventories['incrementals']), 'plugins' => count($inventories['plugins']),
        ));

        return array(
            'success' => true, 'snapshot_id' => $snapshotId, 'folder' => basename($destDir),
            'type' => $metadata['type'] ?? 'full', 'tables' => count($inventories['tables']),
            'total_rows' => $metadata['total_rows'] ?? 0,
            'incrementals' => count($inventories['incrementals']), 'plugins' => count($inventories['plugins']),
        );
    }

    /** Move snapshot to final location in snapshots directory. */
    private function moveSnapshotToFinalLocation(string $snapshotRoot, array $metadata): string {
        $snapshotsDir = RiseupPathUtils::getSnapshotsDir();
        if (!RiseupPathUtils::ensureDir($snapshotsDir, true)) {
            throw new Exception('Failed to ensure snapshots directory');
        }

        $title = $metadata['title'] ?? 'imported';
        $folderName = date('Y-m-d') . '_imported_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $title);
        $destDir = $this->resolveUniqueDest(RiseupPathUtils::join($snapshotsDir, $folderName), $snapshotsDir, $folderName);

        $this->copyDirectory($snapshotRoot, $destDir);
        return $destDir;
    }

    /** Resolve a unique destination directory path. */
    private function resolveUniqueDest(string $destDir, string $snapshotsDir, string $folderName): string {
        $counter = 1;
        while (RiseupPathUtils::dirExists($destDir)) {
            $destDir = RiseupPathUtils::join($snapshotsDir, $folderName . '_' . $counter);
            $counter++;
        }
        return $destDir;
    }

    /** Register the imported snapshot in the database. */
    private function registerImportedSnapshot($metadata, $tables, $incrementals, $plugins, $destDir) {
        $tableNames = array_map(function($t) { return $t['table_name']; }, $tables);
        $record = $this->buildSnapshotRecord($metadata, $tables, $incrementals, $plugins, $destDir, $tableNames);

        $result = $this->db->insert(TABLE_SNAPSHOTS, $record);
        if ($result) {
            return $this->db->lastInsertId();
        }
        throw new Exception('Failed to create snapshot record in database');
    }

    /** Build the snapshot database record for import. */
    private function buildSnapshotRecord(array $metadata, array $tables, array $incrementals, array $plugins, string $destDir, array $tableNames): array {
        return array(
            'sequence' => $this->manager->getNextSequence(), 'filename' => basename($destDir),
            'filepath' => $destDir, 'provider' => SNAPSHOT_PROVIDER_NATIVE,
            'scope' => SNAPSHOT_SCOPE_ALL, 'tables_json' => json_encode($tableNames),
            'total_rows' => $metadata['total_rows'] ?? 0, 'file_size' => $this->getDirectorySize($destDir),
            'trigger_source' => 'import', 'status' => SNAPSHOT_STATUS_COMPLETE,
            'created_at' => date('c'), 'completed_at' => date('c'),
            'import_source' => json_encode($this->buildImportSourceMeta($metadata, $tables, $incrementals, $plugins)),
        );
    }

    /** Build the import_source metadata. */
    private function buildImportSourceMeta(array $metadata, array $tables, array $incrementals, array $plugins): array {
        return array(
            'original_title' => $metadata['title'] ?? null, 'original_type' => $metadata['type'] ?? null,
            'original_created_at' => $metadata['created_at'] ?? null,
            'wp_version' => $metadata['wp_version'] ?? null, 'mysql_version' => $metadata['mysql_version'] ?? null,
            'table_count' => count($tables), 'incremental_count' => count($incrementals),
            'plugin_count' => count($plugins), 'format' => 'per_table',
        );
    }
}
