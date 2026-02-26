<?php
/**
 * RootDb Registration Trait — table registration, stats, incrementals, plugins, metadata reading.
 *
 * @package RiseupAsia\Database\Traits
 * @since   1.12.0
 */

namespace RiseupAsia\Database\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use PDO;
use Throwable;
use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\DateHelper;

trait RootDbRegistrationTrait {

    /** Register a table export in a-root.db. */
    public function registerTable(
        PDO $pdo,
        string $tableName,
        int $rowCount,
        string $sqliteFile,
        int $fileSize = 0,
        string $checksum = '',
    ): void {
        $stmt = $pdo->prepare("INSERT OR REPLACE INTO SnapshotTables
            (TableName, RowCount, SqliteFile, FileSizeBytes, ChecksumMd5, ExportedAt) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute(array(
            $tableName,
            $rowCount,
            $sqliteFile,
            $fileSize,
            $checksum,
            DateHelper::nowIso(),
        ));
    }

    /** Update final stats in SnapshotMeta. */
    public function updateStats(
        PDO $pdo,
        int $tableCount,
        int $totalRows,
    ): void {
        $stmt = $pdo->prepare("UPDATE SnapshotMeta SET TableCount = ?, TotalRows = ? WHERE Id = 1");
        $stmt->execute(array($tableCount, $totalRows));
    }

    /** Register an incremental backup in a-root.db. */
    public function registerIncremental(PDO $pdo, array $info): void {
        $stmt = $pdo->prepare("INSERT INTO IncrementalBackups
            (SequenceNum, FolderName, CreatedAt, TablesChanged, TotalNewRows, RelativePath) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute(array(
            $info['sequenceNum'],
            $info['folderName'],
            DateHelper::nowIso(),
            $info[ResponseKeyType::TablesChanged->value] ?? 0,
            $info[ResponseKeyType::TotalNewRows->value] ?? 0,
            $info['relativePath'],
        ));
    }

    /** Register a plugin snapshot in a-root.db. */
    public function registerPluginSnapshot(PDO $pdo, array $info): void {
        $stmt = $pdo->prepare("INSERT INTO PluginSnapshots
            (PluginSlug, PluginName, PluginVersion, ZipFile, FileSizeBytes, ChecksumMd5) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute(array(
            $info[ResponseKeyType::PluginSlug->value],
            $info['pluginName'] ?? '',
            $info[ResponseKeyType::PluginVersion->value] ?? '',
            $info['zipFile'],
            $info['fileSizeBytes'] ?? 0,
            $info['checksumMd5'] ?? '',
        ));
    }

    /** Read metadata from an existing a-root.db (supports both old snake_case and new PascalCase schemas). */
    public function readMetadata(string $filepath): ?array {
        if (PathHelper::isFileMissing($filepath)) {
            return null;
        }

        try {
            $pdo = new PDO('sqlite:' . $filepath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $metaTable = $this->resolveRootDbTableName($pdo, 'SnapshotMeta');
            $tablesTable = $this->resolveRootDbTableName($pdo, 'SnapshotTables');
            $depsTable = $this->resolveRootDbTableName($pdo, 'TableDependencies');
            $incTable = $this->resolveRootDbTableName($pdo, 'IncrementalBackups');
            $pluginsTable = $this->resolveRootDbTableName($pdo, 'PluginSnapshots');

            $meta = $pdo->query("SELECT * FROM {$metaTable} WHERE {$this->resolveCol($pdo, $metaTable, 'Id')} = 1")->fetch(PDO::FETCH_ASSOC);
            $tables = $pdo->query("SELECT * FROM {$tablesTable} ORDER BY {$this->resolveCol($pdo, $tablesTable, 'TableName')}")->fetchAll(PDO::FETCH_ASSOC);
            $deps = $pdo->query("SELECT * FROM {$depsTable} ORDER BY {$this->resolveCol($pdo, $depsTable, 'ParentTable')}, {$this->resolveCol($pdo, $depsTable, 'ChildTable')}")->fetchAll(PDO::FETCH_ASSOC);
            $incrementals = $pdo->query("SELECT * FROM {$incTable} ORDER BY {$this->resolveCol($pdo, $incTable, 'SequenceNum')}")->fetchAll(PDO::FETCH_ASSOC);
            $plugins = $pdo->query("SELECT * FROM {$pluginsTable} ORDER BY {$this->resolveCol($pdo, $pluginsTable, 'PluginSlug')}")->fetchAll(PDO::FETCH_ASSOC);
            $pdo = null;

            return array(
                ResponseKeyType::Meta->value => $meta,
                ResponseKeyType::Tables->value => $tables,
                ResponseKeyType::Dependencies->value => $deps,
                ResponseKeyType::Incrementals->value => $incrementals,
                ResponseKeyType::Plugins->value => $plugins,
            );
        } catch (Throwable $e) {
            $this->log(LogLevelType::Error->value, 'Failed to read a-root.db', array('path' => $filepath, 'error' => $e->getMessage()));

            return null;
        }
    }

    /** Shorthand for resolveRootDbColumnName. */
    private function resolveCol(PDO $pdo, string $table, string $pascalColumn): string {
        return $this->resolveRootDbColumnName($pdo, $table, $pascalColumn);
    }
}