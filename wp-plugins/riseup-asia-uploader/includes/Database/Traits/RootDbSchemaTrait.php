<?php
/**
 * RootDb Schema Trait — schema creation, metadata population, dependency graph.
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
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\SnapshotConfigType;
use RiseupAsia\Enums\SnapshotModeType;
use RiseupAsia\Enums\SnapshotScopeType;
use RiseupAsia\Helpers\DateHelper;

trait RootDbSchemaTrait {

    private function createSchema(PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS SnapshotMeta (
            Id              INTEGER PRIMARY KEY,
            Title           TEXT NOT NULL,
            Type            TEXT NOT NULL,
            CreatedAt       TEXT NOT NULL,
            CreatedBy       TEXT,
            MysqlVersion    TEXT,
            WpVersion       TEXT,
            PluginVersion   TEXT,
            TableCount      INTEGER,
            TotalRows       INTEGER,
            ConfigJson      TEXT
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS SnapshotTables (
            Id              INTEGER PRIMARY KEY,
            TableName       TEXT NOT NULL UNIQUE,
            RowCount        INTEGER NOT NULL,
            SqliteFile      TEXT NOT NULL,
            FileSizeBytes   INTEGER,
            ChecksumMd5     TEXT,
            ExportedAt      TEXT NOT NULL
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS TableDependencies (
            Id              INTEGER PRIMARY KEY,
            ParentTable     TEXT NOT NULL,
            ChildTable      TEXT NOT NULL,
            FkColumn        TEXT NOT NULL,
            RefColumn       TEXT NOT NULL,
            UNIQUE(ChildTable, FkColumn)
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS IncrementalBackups (
            Id              INTEGER PRIMARY KEY,
            SequenceNum     INTEGER NOT NULL,
            FolderName      TEXT NOT NULL,
            CreatedAt       TEXT NOT NULL,
            TablesChanged   INTEGER,
            TotalNewRows    INTEGER,
            RelativePath    TEXT NOT NULL
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS PluginSnapshots (
            Id              INTEGER PRIMARY KEY,
            PluginSlug      TEXT NOT NULL,
            PluginName      TEXT,
            PluginVersion   TEXT,
            ZipFile         TEXT NOT NULL,
            FileSizeBytes   INTEGER,
            ChecksumMd5     TEXT
        )");
    }

    /**
     * Resolve the actual table name in a root DB, handling backward compatibility.
     *
     * Old snapshots use snake_case (e.g. snapshot_meta), new ones use PascalCase (SnapshotMeta).
     * Detects which naming convention exists and returns the correct table name.
     */
    public function resolveRootDbTableName(PDO $pdo, string $pascalName): string {
        $legacyMap = array(
            'SnapshotMeta'       => 'snapshot_meta',
            'SnapshotTables'     => 'snapshot_tables',
            'TableDependencies'  => 'table_dependencies',
            'IncrementalBackups' => 'incremental_backups',
            'PluginSnapshots'    => 'plugin_snapshots',
        );

        $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='{$pascalName}'");

        if ($check->fetch() !== false) {
            return $pascalName;
        }

        $legacy = $legacyMap[$pascalName] ?? null;

        if ($legacy !== null) {
            $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='{$legacy}'");

            if ($check->fetch() !== false) {
                return $legacy;
            }
        }

        return $pascalName;
    }

    /**
     * Resolve a column name for backward compatibility.
     *
     * Old snapshots use snake_case columns; new ones use PascalCase.
     * Returns the legacy column name if the PascalCase column is absent.
     */
    public function resolveRootDbColumnName(PDO $pdo, string $table, string $pascalColumn): string {
        $legacyColumnMap = array(
            'Id'              => 'id',
            'Title'           => 'title',
            'Type'            => 'type',
            'CreatedAt'       => 'created_at',
            'CreatedBy'       => 'created_by',
            'MysqlVersion'    => 'mysql_version',
            'WpVersion'       => 'wp_version',
            'PluginVersion'   => 'plugin_version',
            'TableCount'      => 'table_count',
            'TotalRows'       => 'total_rows',
            'ConfigJson'      => 'config_json',
            'TableName'       => 'table_name',
            'RowCount'        => 'row_count',
            'SqliteFile'      => 'sqlite_file',
            'FileSizeBytes'   => 'file_size_bytes',
            'ChecksumMd5'     => 'checksum_md5',
            'ExportedAt'      => 'exported_at',
            'ParentTable'     => 'parent_table',
            'ChildTable'      => 'child_table',
            'FkColumn'        => 'fk_column',
            'RefColumn'       => 'ref_column',
            'SequenceNum'     => 'sequence_num',
            'FolderName'      => 'folder_name',
            'TablesChanged'   => 'tables_changed',
            'TotalNewRows'    => 'total_new_rows',
            'RelativePath'    => 'relative_path',
            'PluginSlug'      => 'plugin_slug',
            'PluginName'      => 'plugin_name',
            'ZipFile'         => 'zip_file',
        );

        $columns = $pdo->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($columns as $col) {
            if ($col['name'] === $pascalColumn) {
                return $pascalColumn;
            }
        }

        return $legacyColumnMap[$pascalColumn] ?? $pascalColumn;
    }

    /**
     * Detect whether a root DB uses PascalCase naming (new format).
     */
    public function isRootDbPascalCase(PDO $pdo): bool {
        $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='SnapshotMeta'");

        return ($check->fetch() !== false);
    }

    public function populateMetadata(PDO $pdo, array $config): void {
        global $wpdb;
        $mysqlVersion = $wpdb->get_var("SELECT VERSION()");
        $wpVersion = get_bloginfo('version');

        $stmt = $pdo->prepare("INSERT OR REPLACE INTO SnapshotMeta
            (Id, Title, Type, CreatedAt, CreatedBy, MysqlVersion, WpVersion, PluginVersion, TableCount, TotalRows, ConfigJson)
            VALUES (1, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?)");

        $stmt->execute(array(
            $config[ResponseKeyType::Title->value] ?? SnapshotConfigType::UntitledTitle,
            $config[ResponseKeyType::Type->value] ?? SnapshotModeType::Full->value,
            DateHelper::nowIso(),
            gethostname() ?: php_uname('n'),
            $mysqlVersion,
            $wpVersion,
            PluginConfigType::Version->value,
            isset($config[ResponseKeyType::Settings->value]) ? json_encode($config[ResponseKeyType::Settings->value]) : null,
        ));

        $this->log(LogLevelType::Info->value, 'Metadata populated', array(
            ResponseKeyType::Title->value => $config[ResponseKeyType::Title->value] ?? 'Untitled',
            'mysqlVersion' => $mysqlVersion,
            'wpVersion' => $wpVersion,
        ));
    }

    public function populateDependencies(PDO $pdo, string $scope = 'all'): array { // Default 'all' matches SnapshotScopeType::All->value
        $analysis = $this->analyzer->analyze($scope);

        $stmt = $pdo->prepare("INSERT OR IGNORE INTO TableDependencies
            (ParentTable, ChildTable, FkColumn, RefColumn) VALUES (?, ?, ?, ?)");

        $pdo->beginTransaction();

        foreach ($analysis[ResponseKeyType::Dependencies->value] as $dep) {
            $stmt->execute(array(
                $dep['parent_table'],
                $dep['child_table'],
                $dep['fk_column'],
                $dep['ref_column'],
            ));
        }
        $pdo->commit();

        $this->log(LogLevelType::Info->value, 'Dependencies populated', array(
            'edges' => count($analysis[ResponseKeyType::Dependencies->value]),
            ResponseKeyType::Tables->value => count($analysis[ResponseKeyType::Tables->value]),
        ));

        return $analysis;
    }
}