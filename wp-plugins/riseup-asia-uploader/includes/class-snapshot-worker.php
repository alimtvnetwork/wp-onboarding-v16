<?php
/**
 * Riseup Asia Uploader - Snapshot Worker
 *
 * Exports MySQL tables to individual SQLite files using a
 * sequential worker pattern with progress tracking.
 * Integrates with a-root.db for metadata and dependency ordering.
 *
 * @package RiseupAsiaUploader
 * @since   1.12.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Snapshot Worker class.
 *
 * Manages per-table MySQL → SQLite export. Each table gets its own
 * .sqlite file. Progress is tracked in the snapshot_progress table.
 */
class RiseupSnapshotWorker {

    /**
     * Logger instance.
     *
     * @var RiseupFileLogger
     */
    private $logger;

    /**
     * Plugin database instance.
     *
     * @var Riseup_Database
     */
    private $db;

    /**
     * Root DB manager.
     *
     * @var RiseupRootDb
     */
    private $rootDb;

    /**
     * Dependency analyzer.
     *
     * @var RiseupDependencyAnalyzer
     */
    private $analyzer;

    /**
     * WordPress database instance.
     *
     * @var wpdb
     */
    private $wpdb;

    /**
     * Batch size for row export.
     *
     * @var int
     */
    private $batchSize;

    /**
     * Singleton instance.
     *
     * @var RiseupSnapshotWorker|null
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @param RiseupFileLogger|null         $logger   Logger.
     * @param Riseup_Database|null          $db       Plugin database.
     * @param RiseupRootDb|null             $rootDb   Root DB manager.
     * @param RiseupDependencyAnalyzer|null $analyzer Dependency analyzer.
     * @return RiseupSnapshotWorker
     */
    public static function getInstance($logger = null, $db = null, $rootDb = null, $analyzer = null) {
        if (self::$instance === null && $logger && $db && $rootDb && $analyzer) {
            self::$instance = new self($logger, $db, $rootDb, $analyzer);
        }
        return self::$instance;
    }

    /**
     * Constructor.
     *
     * @param RiseupFileLogger         $logger   Logger.
     * @param Riseup_Database          $db       Plugin database.
     * @param RiseupRootDb             $rootDb   Root DB manager.
     * @param RiseupDependencyAnalyzer $analyzer Dependency analyzer.
     */
    private function __construct($logger, $db, $rootDb, $analyzer) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->logger = $logger;
        $this->db = $db;
        $this->rootDb = $rootDb;
        $this->analyzer = $analyzer;
        $this->batchSize = RISEUP_SNAPSHOT_BATCH_SIZE;
    }

    /**
     * Execute a full per-table snapshot export.
     *
     * Creates a snapshot directory, builds a-root.db with dependency graph,
     * exports each table to an individual .sqlite file, and finalizes metadata.
     *
     * @param array $config Snapshot config: title, scope, tables (for custom), settings.
     * @return array Result with success status and snapshot path.
     */
    public function execute($config) {
        $start_time = microtime(true);
        $title = $config['title'] ?? ('Snapshot ' . date('Y-m-d H:i'));
        $scope = $config['scope'] ?? 'wordpress';
        $type = $config['type'] ?? 'full';

        $this->log('INFO', 'Starting per-table snapshot', array(
            'title' => $title,
            'scope' => $scope,
            'type'  => $type,
        ));

        // 1. Determine snapshot directory
        $base_dir = $this->getSnapshotsBaseDir();
        $dir_name = date('Y-m-d') . '_' . $type . '_' . sanitize_title($title);
        $snapshot_dir = $base_dir . '/' . $dir_name;

        if (!RiseupPathUtils::ensureDir($snapshot_dir, true)) {
            return array('success' => false, 'error' => 'Failed to create snapshot directory');
        }

        try {
            // 2. Create a-root.db
            $root_path = $snapshot_dir . '/a-root.db';
            $rootPdo = $this->rootDb->create($root_path);

            // 3. Populate metadata
            $this->rootDb->populateMetadata($rootPdo, array(
                'title'    => $title,
                'type'     => $type,
                'settings' => $config['settings'] ?? null,
            ));

            // 4. Analyze dependencies and populate graph
            $analysis = $this->rootDb->populateDependencies($rootPdo, $scope);
            $seed_order = $analysis['seed_order'];

            $this->log('INFO', 'Export order determined', array(
                'tables' => count($seed_order),
            ));

            // 5. Create progress records
            $this->initProgressRecords($seed_order);

            // 6. Export each table in dependency order
            $total_rows = 0;
            $exported_tables = 0;
            $errors = array();

            foreach ($seed_order as $table) {
                $this->updateProgress($table, 'running');

                $result = $this->exportTableToFile($snapshot_dir, $table);

                if ($result['success']) {
                    $total_rows += $result['rows'];
                    $exported_tables++;

                    // Register in a-root.db
                    $this->rootDb->registerTable(
                        $rootPdo,
                        $table,
                        $result['rows'],
                        $result['filename'],
                        $result['file_size'],
                        $result['checksum']
                    );

                    $this->updateProgress($table, 'complete', $result['rows']);

                    $this->log('INFO', sprintf('Table exported: %s (%d rows, %s)',
                        $table, $result['rows'], $this->formatBytes($result['file_size'])
                    ));
                } else {
                    $errors[] = $table . ': ' . $result['error'];
                    $this->updateProgress($table, 'failed', 0, $result['error']);
                    $this->log('ERROR', 'Table export failed: ' . $table, array('error' => $result['error']));
                }
            }

            // 7. Update final stats in a-root.db
            $this->rootDb->updateStats($rootPdo, $exported_tables, $total_rows);
            $rootPdo = null; // Close

            $duration = microtime(true) - $start_time;

            $this->log('INFO', 'Per-table snapshot complete', array(
                'directory'   => $dir_name,
                'tables'      => $exported_tables,
                'total_rows'  => $total_rows,
                'errors'      => count($errors),
                'duration'    => round($duration, 2) . 's',
            ));

            return array(
                'success'   => true,
                'directory' => $dir_name,
                'path'      => $snapshot_dir,
                'tables'    => $exported_tables,
                'total_rows' => $total_rows,
                'errors'    => $errors,
                'duration'  => $duration,
            );

        } catch (Exception $e) {
            $this->log('ERROR', 'Per-table snapshot failed', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ));
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * Export a single MySQL table to its own .sqlite file.
     *
     * @param string $snapshot_dir Snapshot directory path.
     * @param string $table        MySQL table name.
     * @return array Result: success, rows, filename, file_size, checksum.
     */
    private function exportTableToFile($snapshot_dir, $table) {
        $filename = $table . '.sqlite';
        $filepath = $snapshot_dir . '/' . $filename;

        try {
            // Create SQLite file for this table
            $sqlite = new PDO('sqlite:' . $filepath);
            $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $sqlite->exec('PRAGMA journal_mode = WAL');
            $sqlite->exec('PRAGMA synchronous = OFF');

            // Get MySQL CREATE TABLE and convert
            $create_sql = $this->getCreateTableSql($table);
            if (!$create_sql) {
                throw new Exception('Failed to get table structure for ' . $table);
            }

            $sqlite_create = $this->convertCreateStatement($create_sql, $table);
            $sqlite->exec($sqlite_create);

            // Get row count
            $count = (int) $this->wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");

            if ($count === 0) {
                $sqlite = null;
                $file_size = filesize($filepath);
                return array(
                    'success'   => true,
                    'rows'      => 0,
                    'filename'  => $filename,
                    'file_size' => $file_size,
                    'checksum'  => md5_file($filepath),
                );
            }

            // Get columns
            $columns = $this->wpdb->get_results("DESCRIBE `{$table}`", ARRAY_A);
            $column_names = array_column($columns, 'Field');
            $placeholders = implode(', ', array_fill(0, count($column_names), '?'));
            $column_list = implode(', ', array_map(function($c) { return "`{$c}`"; }, $column_names));

            // Prepare insert
            $insert_sql = "INSERT INTO `{$table}` ({$column_list}) VALUES ({$placeholders})";
            $stmt = $sqlite->prepare($insert_sql);

            // Export in batches
            $offset = 0;
            $exported = 0;

            $sqlite->beginTransaction();

            while ($offset < $count) {
                $rows = $this->wpdb->get_results(
                    $this->wpdb->prepare(
                        "SELECT * FROM `{$table}` LIMIT %d OFFSET %d",
                        $this->batchSize,
                        $offset
                    ),
                    ARRAY_N
                );

                foreach ($rows as $row) {
                    $stmt->execute($row);
                    $exported++;
                }

                $offset += $this->batchSize;
            }

            $sqlite->commit();
            $sqlite = null; // Close

            $file_size = filesize($filepath);
            $checksum = md5_file($filepath);

            return array(
                'success'   => true,
                'rows'      => $exported,
                'filename'  => $filename,
                'file_size' => $file_size,
                'checksum'  => $checksum,
            );

        } catch (Exception $e) {
            return array(
                'success'   => false,
                'error'     => $e->getMessage(),
                'rows'      => 0,
                'filename'  => $filename,
                'file_size' => 0,
                'checksum'  => '',
            );
        }
    }

    /**
     * Get MySQL CREATE TABLE statement.
     *
     * @param string $table Table name.
     * @return string|null CREATE statement or null.
     */
    private function getCreateTableSql($table) {
        $result = $this->wpdb->get_row("SHOW CREATE TABLE `{$table}`", ARRAY_N);
        return $result ? $result[1] : null;
    }

    /**
     * Convert MySQL CREATE TABLE to SQLite syntax.
     *
     * @param string $mysql_create MySQL CREATE statement.
     * @param string $table        Table name.
     * @return string SQLite CREATE statement.
     */
    private function convertCreateStatement($mysql_create, $table) {
        $sql = $mysql_create;

        // Remove MySQL-specific clauses
        $sql = preg_replace('/\s+ENGINE\s*=\s*\w+/i', '', $sql);
        $sql = preg_replace('/\s+DEFAULT\s+CHARSET\s*=\s*\w+/i', '', $sql);
        $sql = preg_replace('/\s+COLLATE\s*=?\s*\w+/i', '', $sql);
        $sql = preg_replace('/\s+AUTO_INCREMENT\s*=\s*\d+/i', '', $sql);
        $sql = preg_replace('/\s+ROW_FORMAT\s*=\s*\w+/i', '', $sql);

        // AUTO_INCREMENT → AUTOINCREMENT
        $sql = preg_replace('/\bAUTO_INCREMENT\b/i', 'AUTOINCREMENT', $sql);

        // Convert data types
        $type_map = array(
            '/\bTINYINT\s*\(\d+\)/i'    => 'INTEGER',
            '/\bSMALLINT\s*\(\d+\)/i'   => 'INTEGER',
            '/\bMEDIUMINT\s*\(\d+\)/i'  => 'INTEGER',
            '/\bBIGINT\s*\(\d+\)/i'     => 'INTEGER',
            '/\bINT\s*\(\d+\)/i'        => 'INTEGER',
            '/\bDOUBLE\b/i'             => 'REAL',
            '/\bFLOAT\b/i'             => 'REAL',
            '/\bDECIMAL\s*\([^)]+\)/i'  => 'REAL',
            '/\bVARCHAR\s*\(\d+\)/i'    => 'TEXT',
            '/\bCHAR\s*\(\d+\)/i'       => 'TEXT',
            '/\bLONGTEXT\b/i'          => 'TEXT',
            '/\bMEDIUMTEXT\b/i'        => 'TEXT',
            '/\bTINYTEXT\b/i'          => 'TEXT',
            '/\bDATETIME\b/i'          => 'TEXT',
            '/\bTIMESTAMP\b/i'         => 'TEXT',
            '/\bDATE\b/i'              => 'TEXT',
            '/\bTIME\b/i'              => 'TEXT',
            '/\bLONGBLOB\b/i'          => 'BLOB',
            '/\bMEDIUMBLOB\b/i'        => 'BLOB',
            '/\bTINYBLOB\b/i'          => 'BLOB',
            '/\bENUM\s*\([^)]+\)/i'     => 'TEXT',
            '/\bSET\s*\([^)]+\)/i'      => 'TEXT',
            '/\bBIT\s*\(\d+\)/i'        => 'INTEGER',
            '/\bYEAR\s*\(\d+\)/i'       => 'INTEGER',
            '/\bBOOLEAN\b/i'           => 'INTEGER',
            '/\bBOOL\b/i'              => 'INTEGER',
        );

        foreach ($type_map as $pattern => $replacement) {
            $sql = preg_replace($pattern, $replacement, $sql);
        }

        // Remove inline collation/charset/unsigned/zerofill
        $sql = preg_replace('/\s+COLLATE\s+\w+/i', '', $sql);
        $sql = preg_replace('/\s+CHARACTER\s+SET\s+\w+/i', '', $sql);
        $sql = preg_replace('/\s+UNSIGNED\b/i', '', $sql);
        $sql = preg_replace('/\s+ZEROFILL\b/i', '', $sql);

        // Remove KEY/INDEX definitions (not supported inline in SQLite)
        $sql = preg_replace('/,\s*KEY\s+[^,]+(?=,|\))/i', '', $sql);
        $sql = preg_replace('/,\s*UNIQUE\s+KEY\s+[^,]+(?=,|\))/i', '', $sql);
        $sql = preg_replace('/,\s*FULLTEXT\s+KEY\s+[^,]+(?=,|\))/i', '', $sql);
        $sql = preg_replace('/,\s*SPATIAL\s+KEY\s+[^,]+(?=,|\))/i', '', $sql);

        // Remove extra commas before closing paren
        $sql = preg_replace('/,\s*\)/', ')', $sql);

        return $sql;
    }

    /**
     * Initialize progress records for all tables.
     *
     * @param array $tables Table names.
     */
    private function initProgressRecords($tables) {
        $pdo = $this->db->get_pdo();
        if (!$pdo) return;

        try {
            // Use a temporary progress tracking (reuse snapshot_progress with snapshot_id = 0 for per-table mode)
            $stmt = $pdo->prepare("INSERT OR REPLACE INTO " . RISEUP_TABLE_SNAPSHOT_PROGRESS . "
                (snapshot_id, table_name, status, rows_total, rows_exported, started_at)
                VALUES (0, ?, 'pending', 0, 0, ?)");

            $now = gmdate('c');
            foreach ($tables as $table) {
                $count = (int) $this->wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
                $stmt->execute(array($table, $now));

                // Update rows_total separately
                $pdo->exec("UPDATE " . RISEUP_TABLE_SNAPSHOT_PROGRESS .
                    " SET rows_total = {$count} WHERE snapshot_id = 0 AND table_name = '{$table}'");
            }
        } catch (Exception $e) {
            $this->log('WARN', 'Failed to init progress records', array('error' => $e->getMessage()));
        }
    }

    /**
     * Update progress for a table.
     *
     * @param string      $table  Table name.
     * @param string      $status Status: pending, running, complete, failed.
     * @param int         $rows   Rows exported.
     * @param string|null $error  Error message if failed.
     */
    private function updateProgress($table, $status, $rows = 0, $error = null) {
        $pdo = $this->db->get_pdo();
        if (!$pdo) return;

        try {
            $now = gmdate('c');
            $stmt = $pdo->prepare("UPDATE " . RISEUP_TABLE_SNAPSHOT_PROGRESS . "
                SET status = ?, rows_exported = ?, completed_at = ?, error_message = ?
                WHERE snapshot_id = 0 AND table_name = ?");
            $stmt->execute(array(
                $status,
                $rows,
                ($status === 'complete' || $status === 'failed') ? $now : null,
                $error,
                $table,
            ));
        } catch (Exception $e) {
            // Non-fatal
        }
    }

    /**
     * Get the base snapshots directory.
     *
     * @return string Base snapshots directory path.
     */
    private function getSnapshotsBaseDir() {
        $upload_dir = wp_upload_dir();
        $base = $upload_dir['basedir'] . '/' . RISEUP_UPLOADS_SUBDIR . '/' . RISEUP_SNAPSHOTS_SUBDIR;
        RiseupPathUtils::ensureDir($base, true);
        return $base;
    }

    /**
     * Format bytes to human-readable string.
     *
     * @param int $bytes Byte count.
     * @return string Formatted string.
     */
    private function formatBytes($bytes) {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }

    /**
     * Log a message with worker context.
     *
     * @param string $level   Log level.
     * @param string $message Message.
     * @param array  $context Context data.
     */
    private function log($level, $message, $context = array()) {
        $prefix = '[SNAPSHOT] [WORKER]';
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
