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

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\SnapshotModeType;
use RiseupAsia\Enums\SnapshotScopeType;
trait RootDbSchemaTrait {

    private function createSchema(PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS snapshot_meta (
            id              INTEGER PRIMARY KEY,
            title           TEXT NOT NULL,
            type            TEXT NOT NULL,
            created_at      TEXT NOT NULL,
            created_by      TEXT,
            mysql_version   TEXT,
            wp_version      TEXT,
            plugin_version  TEXT,
            table_count     INTEGER,
            total_rows      INTEGER,
            config_json     TEXT
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS snapshot_tables (
            id              INTEGER PRIMARY KEY,
            table_name      TEXT NOT NULL UNIQUE,
            row_count       INTEGER NOT NULL,
            sqlite_file     TEXT NOT NULL,
            file_size_bytes INTEGER,
            checksum_md5    TEXT,
            exported_at     TEXT NOT NULL
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS table_dependencies (
            id              INTEGER PRIMARY KEY,
            parent_table    TEXT NOT NULL,
            child_table     TEXT NOT NULL,
            fk_column       TEXT NOT NULL,
            ref_column      TEXT NOT NULL,
            UNIQUE(child_table, fk_column)
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS incremental_backups (
            id              INTEGER PRIMARY KEY,
            sequence_num    INTEGER NOT NULL,
            folder_name     TEXT NOT NULL,
            created_at      TEXT NOT NULL,
            tables_changed  INTEGER,
            total_new_rows  INTEGER,
            relative_path   TEXT NOT NULL
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS plugin_snapshots (
            id              INTEGER PRIMARY KEY,
            plugin_slug     TEXT NOT NULL,
            plugin_name     TEXT,
            plugin_version  TEXT,
            zip_file        TEXT NOT NULL,
            file_size_bytes INTEGER,
            checksum_md5    TEXT
        )");
    }

    public function populateMetadata(PDO $pdo, array $config): void {
        global $wpdb;
        $mysqlVersion = $wpdb->get_var("SELECT VERSION()");
        $wpVersion = get_bloginfo('version');

        $stmt = $pdo->prepare("INSERT OR REPLACE INTO snapshot_meta
            (id, title, type, created_at, created_by, mysql_version, wp_version, plugin_version, table_count, total_rows, config_json)
            VALUES (1, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?)");

        $stmt->execute(array(
            $config['title'] ?? 'Untitled Snapshot', $config['type'] ?? SnapshotModeType::Full->value,
            gmdate('c'), gethostname() ?: php_uname('n'),
            $mysqlVersion, $wpVersion, PluginConfigType::Version->value,
            isset($config['settings']) ? json_encode($config['settings']) : null,
        ));

        $this->log(LogLevelType::Info->value, 'Metadata populated', array(
            'title' => $config['title'] ?? 'Untitled', 'mysql_version' => $mysqlVersion, 'wp_version' => $wpVersion,
        ));
    }

    public function populateDependencies(PDO $pdo, string $scope = 'all'): array { // Default matches SnapshotScopeType::All->value
        $analysis = $this->analyzer->analyze($scope);

        $stmt = $pdo->prepare("INSERT OR IGNORE INTO table_dependencies
            (parent_table, child_table, fk_column, ref_column) VALUES (?, ?, ?, ?)");

        $pdo->beginTransaction();
        foreach ($analysis['dependencies'] as $dep) {
            $stmt->execute(array($dep['parent_table'], $dep['child_table'], $dep['fk_column'], $dep['ref_column']));
        }
        $pdo->commit();

        $this->log(LogLevelType::Info->value, 'Dependencies populated', array(
            'edges' => count($analysis['dependencies']), 'tables' => count($analysis['tables']),
        ));

        return $analysis;
    }
}
