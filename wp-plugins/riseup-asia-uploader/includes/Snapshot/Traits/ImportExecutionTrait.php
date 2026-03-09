<?php
/**
 * ImportExecutionTrait — per-table import execution and registration.
 *
 * Shell trait — file validation delegated to ImportExecutionFileTrait.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use Exception;

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\SnapshotConfigType;
use RiseupAsia\Enums\SnapshotModeType;
use RiseupAsia\Enums\SnapshotProviderType;
use RiseupAsia\Enums\SnapshotScopeType;
use RiseupAsia\Enums\SnapshotStatusType;
use RiseupAsia\Enums\SnapshotTriggerType;
use RiseupAsia\Enums\SnapshotWorkerModeType;
use RiseupAsia\Enums\TableType;
use RiseupAsia\Helpers\DateHelper;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\ResultHelper;

trait ImportExecutionTrait {
    use ImportExecutionFileTrait;

    /** Import a per-table snapshot (with a-root.db). */
    private function importPerTable(string $tempDir, string $rootDbPath): array {
        $this->log(LogLevelType::Info->value, 'Detected per-table snapshot format');
        $snapshotRoot = dirname($rootDbPath);

        $metadata = $this->extractAndValidateRootDb($rootDbPath, $snapshotRoot);
        $inventories = $this->validateAllImportFiles($rootDbPath, $snapshotRoot);

        $destDir = $this->moveSnapshotToFinalLocation($snapshotRoot, $metadata);
        $snapshotId = $this->registerImportedSnapshot($metadata, $inventories, $destDir);

        return $this->buildImportResult($snapshotId, $destDir, $metadata, $inventories);
    }

    /** Extract and validate the root database file and metadata. */
    private function extractAndValidateRootDb(string $rootDbPath, string $snapshotRoot): array {
        $this->validateSqliteFile($rootDbPath, SnapshotConfigType::RootDbFilename);
        $metadata = $this->readRootDbMetadata($rootDbPath);

        $isMetadataMissing = ($metadata === null || $metadata === false);

        if ($isMetadataMissing) {
            throw new Exception('Failed to read metadata from ' . SnapshotConfigType::RootDbFilename);
        }

        return $metadata;
    }

    /** Validate all import file inventories (tables, incrementals, plugins). */
    private function validateAllImportFiles(string $rootDbPath, string $snapshotRoot): array {
        $tables = $this->readRootDbTables($rootDbPath);
        $this->log(LogLevelType::Info->value, 'Validating table files', array(ResponseKeyType::Count->value => count($tables)));
        $this->validateTableFiles($snapshotRoot, $tables);

        $incrementals = $this->readRootDbIncrementals($rootDbPath);
        $this->validateIncrementalFiles($snapshotRoot, $incrementals);

        $plugins = $this->readRootDbPlugins($rootDbPath);
        $this->validatePluginFiles($snapshotRoot, $plugins);

        return array(
            ResponseKeyType::Tables->value       => $tables,
            ResponseKeyType::Incrementals->value  => $incrementals,
            ResponseKeyType::Plugins->value       => $plugins,
        );
    }

    /** Build the final import result array. */
    private function buildImportResult(int $snapshotId, string $destDir, array $metadata, array $inventories): array {
        $this->logImportComplete($snapshotId, $inventories);

        return ResultHelper::ok(array(
            ResponseKeyType::SnapshotId->value   => $snapshotId,
            ResponseKeyType::Folder->value       => basename($destDir),
            ResponseKeyType::Tables->value       => count($inventories[ResponseKeyType::Tables->value]),
            ResponseKeyType::TotalRows->value    => $metadata['total_rows'] ?? 0,
            ResponseKeyType::Incrementals->value => count($inventories[ResponseKeyType::Incrementals->value]),
            ResponseKeyType::Plugins->value      => count($inventories[ResponseKeyType::Plugins->value]),
        ));
    }

    private function logImportComplete(int $snapshotId, array $inventories): void {
        $this->log(LogLevelType::Info->value, 'Per-table snapshot imported successfully', array(
            ResponseKeyType::SnapshotId->value    => $snapshotId,
            ResponseKeyType::Tables->value        => count($inventories[ResponseKeyType::Tables->value]),
            ResponseKeyType::Incrementals->value  => count($inventories[ResponseKeyType::Incrementals->value]),
            ResponseKeyType::Plugins->value       => count($inventories[ResponseKeyType::Plugins->value]),
        ));
    }

    /** Move snapshot to final location in snapshots directory. */
    private function moveSnapshotToFinalLocation(string $snapshotRoot, array $metadata): string {
        $snapshotsDir = PathHelper::getSnapshotsDir();
        $isDirCreationFailed = (PathHelper::makeDirectory($snapshotsDir, true) === false);

        if ($isDirCreationFailed) {
            throw new Exception('Failed to create snapshots directory');
        }

        $title = $metadata[ResponseKeyType::Title->value] ?? 'imported';
        $folderName = DateHelper::nowDateOnly() . '_imported_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $title);
        $destDir = $this->resolveUniqueDest(PathHelper::join($snapshotsDir, $folderName), $snapshotsDir, $folderName);

        $this->copyDirectory($snapshotRoot, $destDir);

        return $destDir;
    }

    /** Resolve a unique destination directory path. */
    private function resolveUniqueDest(string $destDir, string $snapshotsDir, string $folderName): string {
        $counter = 1;
        while (PathHelper::dirExists($destDir)) {
            $destDir = PathHelper::join($snapshotsDir, $folderName . '_' . $counter);
            $counter++;
        }

        return $destDir;
    }

    /** Register the imported snapshot in the database. */
    private function registerImportedSnapshot(array $metadata, array $inventories, string $destDir): int {
        $tableNames = array_map(function($t) { return $t['table_name']; }, $inventories[ResponseKeyType::Tables->value]);
        $record = $this->buildSnapshotRecord($metadata, $inventories, $destDir, $tableNames);

        $result = $this->db->insert(TableType::Snapshots->value, $record);

        if ($result) {
            return $this->db->lastInsertId();
        }

        throw new Exception('Failed to create snapshot record in database');
    }

    /** Build the snapshot database record for import. */
    private function buildSnapshotRecord(array $metadata, array $inventories, string $destDir, array $tableNames): array {
        return array(
            'Sequence'      => $this->manager->getNextSequence(),
            'Filename'      => basename($destDir),
            'Filepath'      => $destDir,
            'Provider'      => SnapshotProviderType::Native->value,
            'Scope'         => SnapshotScopeType::All->value,
            'TablesJson'    => json_encode($tableNames),
            'TotalRows'     => $metadata['total_rows'] ?? 0,
            'FileSize'      => $this->getDirectorySize($destDir),
            'TriggerSource' => SnapshotTriggerType::Api->value,
            'Status'        => SnapshotStatusType::Complete->value,
            'CreatedAt'     => DateHelper::nowIso(),
            'CompletedAt'   => DateHelper::nowIso(),
            'ImportSource'  => json_encode($this->buildImportSourceMeta($metadata, $inventories)),
        );
    }

    /** Build the import_source metadata. */
    private function buildImportSourceMeta(array $metadata, array $inventories): array {
        return array(
            ResponseKeyType::OriginalTitle->value     => $metadata['title'] ?? null,
            ResponseKeyType::OriginalType->value      => $metadata['type'] ?? null,
            ResponseKeyType::OriginalCreatedAt->value => $metadata['created_at'] ?? null,
            ResponseKeyType::WpVersion->value         => $metadata['wp_version'] ?? null,
            ResponseKeyType::MysqlVersion->value      => $metadata['mysql_version'] ?? null,
            ResponseKeyType::TableCount->value        => count($inventories[ResponseKeyType::Tables->value]),
            ResponseKeyType::IncrementalCount->value  => count($inventories[ResponseKeyType::Incrementals->value]),
            ResponseKeyType::PluginCount->value       => count($inventories[ResponseKeyType::Plugins->value]),
            ResponseKeyType::Format->value            => SnapshotWorkerModeType::PerTable->value,
        );
    }
}
