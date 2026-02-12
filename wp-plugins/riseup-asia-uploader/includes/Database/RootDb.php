<?php
/**
 * Riseup Asia Uploader - Root Database Manager
 *
 * Creates and manages the a-root.db SQLite file that stores
 * snapshot metadata, table inventories, and dependency graphs.
 *
 * @package RiseupAsiaUploader
 * @since   1.12.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Root Database Manager class.
 *
 * Responsible for creating a-root.db with the standard schema,
 * populating it with dependency graph data and table inventories.
 */
class RiseupRootDb {

    /**
     * Logger instance.
     *
     * @var RiseupFileLogger
     */
    private $logger;

    /**
     * Dependency analyzer instance.
     *
     * @var RiseupDependencyAnalyzer
     */
    private $analyzer;

    /**
     * Singleton instance.
     *
     * @var RiseupRootDb|null
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @param RiseupFileLogger|null         $logger   Logger.
     * @param RiseupDependencyAnalyzer|null $analyzer Dependency analyzer.
     * @return RiseupRootDb
     */
    public static function getInstance($logger = null, $analyzer = null) {
        if (self::$instance === null && $logger && $analyzer) {
            self::$instance = new self($logger, $analyzer);
        }
        return self::$instance;
    }

    /**
     * Constructor.
     *
     * @param RiseupFileLogger         $logger   Logger.
     * @param RiseupDependencyAnalyzer $analyzer Dependency analyzer.
     */
    private function __construct($logger, $analyzer) {
        $this->logger = $logger;
        $this->analyzer = $analyzer;
    }

    /**
     * Create a new a-root.db at the given path with full schema.
     *
     * @param string $filepath Full path to the a-root.db file.
     * @return PDO The opened PDO connection.
     */
    public function create($filepath) {
        $this->log('INFO', 'Creating a-root.db', array('path' => $filepath));

        // Ensure parent directory exists
        $dir = dirname($filepath);
        if (!file_exists($dir)) {
            RiseupPathUtils::ensure_dir($dir);
        }

        $pdo = new PDO('sqlite:' . $filepath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA journal_mode = WAL');

        $this->createSchema($pdo);
        $this->log('INFO', 'a-root.db schema created');

        return $pdo;
    }

    /**
     * Create the standard a-root.db schema.
     *
     * @param PDO $pdo SQLite PDO connection.
     */
    private function createSchema($pdo) {
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

    /**
     * Populate metadata in a-root.db.
     *
     * @param PDO   $pdo    SQLite PDO connection.
     * @param array $config Metadata config with keys: title, type, settings.
     */
    public function populateMetadata($pdo, $config) {
        global $wpdb;

        // Get MySQL version
        $mysql_version = $wpdb->get_var("SELECT VERSION()");

        // Get WordPress version
        $wp_version = get_bloginfo('version');

        $stmt = $pdo->prepare("INSERT OR REPLACE INTO snapshot_meta
            (id, title, type, created_at, created_by, mysql_version, wp_version, plugin_version, table_count, total_rows, config_json)
            VALUES (1, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?)");

        $stmt->execute(array(
            $config['title'] ?? 'Untitled Snapshot',
            $config['type'] ?? 'full',
            gmdate('c'),
            gethostname() ?: php_uname('n'),
            $mysql_version,
            $wp_version,
            RISEUP_VERSION,
            isset($config['settings']) ? json_encode($config['settings']) : null,
        ));

        $this->log('INFO', 'Metadata populated', array(
            'title'         => $config['title'] ?? 'Untitled',
            'mysql_version' => $mysql_version,
            'wp_version'    => $wp_version,
        ));
    }

    /**
     * Populate dependency graph in a-root.db.
     *
     * @param PDO    $pdo   SQLite PDO connection.
     * @param string $scope Table scope for analysis.
     * @return array Analysis result (tables, dependencies, seed_order).
     */
    public function populateDependencies($pdo, $scope = 'all') {
        $analysis = $this->analyzer->analyze($scope);

        $stmt = $pdo->prepare("INSERT OR IGNORE INTO table_dependencies
            (parent_table, child_table, fk_column, ref_column)
            VALUES (?, ?, ?, ?)");

        $pdo->beginTransaction();
        foreach ($analysis['dependencies'] as $dep) {
            $stmt->execute(array(
                $dep['parent_table'],
                $dep['child_table'],
                $dep['fk_column'],
                $dep['ref_column'],
            ));
        }
        $pdo->commit();

        $this->log('INFO', 'Dependencies populated', array(
            'edges'      => count($analysis['dependencies']),
            'tables'     => count($analysis['tables']),
            'seed_order' => count($analysis['seed_order']),
        ));

        return $analysis;
    }

    /**
     * Register a table export in a-root.db.
     *
     * @param PDO    $pdo        SQLite PDO connection.
     * @param string $table_name MySQL table name.
     * @param int    $row_count  Number of rows exported.
     * @param string $sqlite_file Relative path to the .sqlite file.
     * @param int    $file_size  File size in bytes.
     * @param string $checksum   MD5 checksum of the file.
     */
    public function registerTable($pdo, $table_name, $row_count, $sqlite_file, $file_size = 0, $checksum = '') {
        $stmt = $pdo->prepare("INSERT OR REPLACE INTO snapshot_tables
            (table_name, row_count, sqlite_file, file_size_bytes, checksum_md5, exported_at)
            VALUES (?, ?, ?, ?, ?, ?)");

        $stmt->execute(array(
            $table_name,
            $row_count,
            $sqlite_file,
            $file_size,
            $checksum,
            gmdate('c'),
        ));
    }

    /**
     * Update final stats in snapshot_meta.
     *
     * @param PDO $pdo         SQLite PDO connection.
     * @param int $table_count Total tables exported.
     * @param int $total_rows  Total rows across all tables.
     */
    public function updateStats($pdo, $table_count, $total_rows) {
        $stmt = $pdo->prepare("UPDATE snapshot_meta SET table_count = ?, total_rows = ? WHERE id = 1");
        $stmt->execute(array($table_count, $total_rows));
    }

    /**
     * Register an incremental backup in a-root.db.
     *
     * @param PDO   $pdo  SQLite PDO connection.
     * @param array $info Incremental info: sequence_num, folder_name, tables_changed, total_new_rows, relative_path.
     */
    public function registerIncremental($pdo, $info) {
        $stmt = $pdo->prepare("INSERT INTO incremental_backups
            (sequence_num, folder_name, created_at, tables_changed, total_new_rows, relative_path)
            VALUES (?, ?, ?, ?, ?, ?)");

        $stmt->execute(array(
            $info['sequence_num'],
            $info['folder_name'],
            gmdate('c'),
            $info['tables_changed'] ?? 0,
            $info['total_new_rows'] ?? 0,
            $info['relative_path'],
        ));
    }

    /**
     * Register a plugin snapshot in a-root.db.
     *
     * @param PDO   $pdo  SQLite PDO connection.
     * @param array $info Plugin info: plugin_slug, plugin_name, plugin_version, zip_file, file_size_bytes, checksum_md5.
     */
    public function registerPluginSnapshot($pdo, $info) {
        $stmt = $pdo->prepare("INSERT INTO plugin_snapshots
            (plugin_slug, plugin_name, plugin_version, zip_file, file_size_bytes, checksum_md5)
            VALUES (?, ?, ?, ?, ?, ?)");

        $stmt->execute(array(
            $info['plugin_slug'],
            $info['plugin_name'] ?? '',
            $info['plugin_version'] ?? '',
            $info['zip_file'],
            $info['file_size_bytes'] ?? 0,
            $info['checksum_md5'] ?? '',
        ));
    }

    /**
     * Read metadata from an existing a-root.db.
     *
     * @param string $filepath Path to a-root.db file.
     * @return array|null Metadata or null if invalid.
     */
    public function readMetadata($filepath) {
        if (!RiseupPathUtils::file_exists($filepath)) {
            return null;
        }

        try {
            $pdo = new PDO('sqlite:' . $filepath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $meta = $pdo->query("SELECT * FROM snapshot_meta WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
            $tables = $pdo->query("SELECT * FROM snapshot_tables ORDER BY table_name")->fetchAll(PDO::FETCH_ASSOC);
            $deps = $pdo->query("SELECT * FROM table_dependencies ORDER BY parent_table, child_table")->fetchAll(PDO::FETCH_ASSOC);
            $incrementals = $pdo->query("SELECT * FROM incremental_backups ORDER BY sequence_num")->fetchAll(PDO::FETCH_ASSOC);
            $plugins = $pdo->query("SELECT * FROM plugin_snapshots ORDER BY plugin_slug")->fetchAll(PDO::FETCH_ASSOC);

            $pdo = null;

            return array(
                'meta'          => $meta,
                'tables'        => $tables,
                'dependencies'  => $deps,
                'incrementals'  => $incrementals,
                'plugins'       => $plugins,
            );
        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to read a-root.db', array(
                'path'  => $filepath,
                'error' => $e->getMessage(),
            ));
            return null;
        }
    }

    /**
     * Log a message with root-db context.
     *
     * @param string $level   Log level.
     * @param string $message Message.
     * @param array  $context Context data.
     */
    private function log($level, $message, $context = array()) {
        $prefix = '[SNAPSHOT] [ROOT-DB]';
        $full = $prefix . ' ' . $message;
        if (!empty($context)) {
            $full .= ' ' . json_encode($context);
        }

        if ($this->logger) {
            switch ($level) {
                case 'WARN':
                    $this->logger->warn($full);
                    break;
                case 'ERROR':
                    $this->logger->error($full);
                    break;
                default:
                    $this->logger->info($full);
            }
        }
    }
}
