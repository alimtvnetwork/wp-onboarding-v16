<?php
/**
 * Riseup Asia Uploader - Native SQLite Snapshot Provider
 *
 * Implements database snapshots using MySQL to SQLite export.
 * This is the fallback provider when WP Reset or Updraft is not available.
 *
 * PHP class naming follows PascalCase convention without underscores.
 *
 * @package RiseupAsiaUploader
 * @since   1.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__FILE__) . '/SnapshotProviderInterface.php';

/**
 * Native SQLite Snapshot Provider.
 * 
 * Exports MySQL tables to SQLite format for portable database backups.
 * All operations are scheduled via WP-Cron to prevent request timeouts.
 *
 * PHP class naming follows PascalCase convention without underscores.
 */
class RiseupSnapshotProviderNative extends RiseupSnapshotProviderInterface {

    /**
     * Provider ID.
     *
     * @var string
     */
    protected $provider_id = SNAPSHOT_PROVIDER_NATIVE;

    /**
     * Provider name.
     *
     * @var string
     */
    protected $provider_name = 'Native SQLite';

    /**
     * WordPress database instance.
     *
     * @var wpdb
     */
    private $wpdb;

    /**
     * Constructor.
     *
     * @param RiseupFileLogger $logger Logger instance.
     * @param RiseupDatabase   $db     Database instance.
     */
    public function __construct($logger, $db) {
        parent::__construct($logger, $db);
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Check if provider is available.
     *
     * @return bool True if SQLite extension is loaded.
     */
    public function isAvailable() {
        return extension_loaded('sqlite3') || extension_loaded('pdo_sqlite');
    }

    /**
     * Get provider capabilities.
     *
     * @return array Capabilities array.
     */
    public function getCapabilities() {
        return array(
            'full_site' => false,
            'database_only' => true,
            'selective' => true,
            'scheduled' => true,
            'restore' => true,
            'export' => true,
            'import' => true,
        );
    }

    /**
     * Create a snapshot (schedules via cron).
     *
     * @param array $options Snapshot options.
     * @return array Snapshot result.
     */
    public function createSnapshot($options) {
        $this->log(LOG_LEVEL_INFO, 'Snapshot creation requested', $options);

        // Ensure directory exists
        if (!$this->ensureSnapshotsDir()) {
            return array(
                'success' => false,
                'error' => 'Failed to create snapshots directory',
            );
        }

        // Check for existing lock
        if ($this->isLocked()) {
            $this->log(LOG_LEVEL_WARN, 'Snapshot already in progress (locked)');
            return array(
                'success' => false,
                'error' => 'Another snapshot operation is in progress',
                'code' => ERR_SNAPSHOT_LOCK_EXISTS,
            );
        }

        // Determine tables to export
        $scope = isset($options['scope']) ? $options['scope'] : SNAPSHOT_SCOPE_WORDPRESS;
        $tables = $this->getTablesForScope($scope, isset($options['tables']) ? $options['tables'] : array());

        if (empty($tables)) {
            return array(
                'success' => false,
                'error' => 'No tables selected for snapshot',
            );
        }

        // Get next sequence number
        $sequence = $this->getNextSequence();
        $filename = $this->generateSnapshotFilename($sequence);
        $filepath = RiseupPathUtils::join($this->getSnapshotsDir(), $filename . '.sqlite');

        // Create snapshot record
        $trigger = isset($options['trigger']) ? $options['trigger'] : 'api';
        $snapshot_id = $this->createSnapshotRecord($sequence, $filename, $filepath, $scope, $tables, $trigger);

        if (!$snapshot_id) {
            return array(
                'success' => false,
                'error' => 'Failed to create snapshot record',
            );
        }

        // Schedule the actual export via cron
        $scheduled = wp_schedule_single_event(
            time() + 5, // 5 seconds from now
            CRON_SNAPSHOT_IMMEDIATE,
            array(array(
                'snapshot_id' => $snapshot_id,
                'tables' => $tables,
            ))
        );

        if ($scheduled === false) {
            // Direct execution as fallback (not recommended)
            $this->log(LOG_LEVEL_WARN, 'Cron scheduling failed, executing directly');
            $result = $this->executeSnapshot($snapshot_id, $tables);
            return $result;
        }

        $this->log(LOG_LEVEL_INFO, 'Snapshot scheduled via cron', array(
            'snapshot_id' => $snapshot_id,
            'filename' => $filename,
            'tables' => count($tables),
        ));

        return array(
            'success' => true,
            'snapshot_id' => $snapshot_id,
            'filename' => $filename . '.sqlite',
            'status' => SNAPSHOT_STATUS_SCHEDULED,
            'tables' => count($tables),
            'scheduled_at' => date('c', time() + 5),
        );
    }

    /**
     * Execute the actual snapshot export (called by cron).
     *
     * @param int   $snapshot_id Snapshot ID.
     * @param array $tables      Tables to export.
     * @return array Result.
     */
    public function executeSnapshot($snapshot_id, $tables) {
        $start_time = microtime(true);

        // Get snapshot record
        $snapshot = $this->getSnapshot($snapshot_id);
        if (!$snapshot) {
            return array('success' => false, 'error' => 'Snapshot record not found');
        }

        $filepath = $snapshot['filepath'];

        // Acquire lock
        if (!$this->acquireLock()) {
            $this->updateSnapshotStatus($snapshot_id, SNAPSHOT_STATUS_FAILED, 'Failed to acquire lock');
            return array('success' => false, 'error' => 'Failed to acquire lock');
        }

        try {
            // Update status to running
            $this->updateSnapshotStatus($snapshot_id, SNAPSHOT_STATUS_RUNNING);

            $this->log(LOG_LEVEL_INFO, 'Starting snapshot export', array(
                'snapshot_id' => $snapshot_id,
                'filepath' => $filepath,
                'tables' => count($tables),
            ));

            // Create SQLite database
            $sqlite = $this->createSqliteDatabase($filepath);
            if (!$sqlite) {
                throw new Exception('Failed to create SQLite database');
            }

            $total_rows = 0;
            $table_counts = array();

            // Export each table
            foreach ($tables as $table) {
                $this->log(LOG_LEVEL_DEBUG, 'Exporting table: ' . $table);

                $result = $this->exportTable($sqlite, $table, $snapshot_id);

                if ($result['success']) {
                    $total_rows += $result['rows'];
                    $table_counts[$table] = $result['rows'];
                    $this->log(LOG_LEVEL_INFO, sprintf(
                        'Table %s complete (%d rows, %s)',
                        $table,
                        $result['rows'],
                        $this->formatBytes($result['bytes'])
                    ));
                } else {
                    $this->log(LOG_LEVEL_ERROR, 'Failed to export table: ' . $table, array(
                        'error' => $result['error']
                    ));
                    // Continue with other tables
                }
            }

            // Close SQLite connection
            $sqlite = null;

            // Get file size
            $file_size = filesize($filepath);
            $duration = microtime(true) - $start_time;

            // Update snapshot record
            $this->finalizeSnapshot($snapshot_id, array(
                'status' => SNAPSHOT_STATUS_COMPLETE,
                'file_size' => $file_size,
                'total_rows' => $total_rows,
                'table_counts' => $table_counts,
                'duration_ms' => (int)($duration * 1000),
            ));

            $this->log(LOG_LEVEL_INFO, 'Snapshot complete', array(
                'snapshot_id' => $snapshot_id,
                'filepath' => $filepath,
                'size' => $this->formatBytes($file_size),
                'tables' => count($tables),
                'rows' => $total_rows,
                'duration' => round($duration, 2) . 's',
            ));

            return array(
                'success' => true,
                'snapshot_id' => $snapshot_id,
                'filename' => basename($filepath),
                'filepath' => $filepath,
                'size' => $file_size,
                'tables' => count($tables),
                'rows' => $total_rows,
                'duration' => $duration,
            );

        } catch (Exception $e) {
            $this->log(LOG_LEVEL_ERROR, 'Snapshot failed', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ));

            $this->updateSnapshotStatus($snapshot_id, SNAPSHOT_STATUS_FAILED, $e->getMessage());

            return array(
                'success' => false,
                'error' => $e->getMessage(),
            );

        } finally {
            $this->releaseLock();
        }
    }

    /**
     * Create SQLite database file.
     *
     * @param string $filepath Path to create database.
     * @return PDO|null PDO instance or null on failure.
     */
    private function createSqliteDatabase($filepath) {
        // Validate path is within snapshots directory
        $snapshots_dir = $this->getSnapshotsDir();
        if (!RiseupPathUtils::isSafePath($filepath, $snapshots_dir)) {
            $this->log(LOG_LEVEL_ERROR, 'Unsafe path detected for SQLite database', array(
                'filepath' => $filepath,
                'base' => $snapshots_dir,
            ));
            return null;
        }

        // Ensure parent directory exists
        $parent_dir = dirname($filepath);
        if (!RiseupPathUtils::ensureDir($parent_dir, true)) {
            $this->log(LOG_LEVEL_ERROR, 'Failed to ensure parent directory for SQLite', array(
                'parent' => $parent_dir,
            ));
            return null;
        }

        try {
            $this->log(LOG_LEVEL_DEBUG, 'Creating SQLite database', array('filepath' => $filepath));

            $pdo = new PDO('sqlite:' . $filepath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Enable WAL mode for better performance
            $pdo->exec('PRAGMA journal_mode = WAL');
            $pdo->exec('PRAGMA synchronous = NORMAL');

            // Create metadata table
            $pdo->exec('CREATE TABLE IF NOT EXISTS _snapshot_meta (
                key TEXT PRIMARY KEY,
                value TEXT
            )');

            // Store metadata
            $meta = array(
                'created_at' => date('c'),
                'wp_version' => get_bloginfo('version'),
                'site_url' => get_site_url(),
                'php_version' => PHP_VERSION,
                'provider' => $this->provider_id,
                'plugin_version' => PLUGIN_VERSION,
            );

            $stmt = $pdo->prepare('INSERT INTO _snapshot_meta (key, value) VALUES (?, ?)');
            foreach ($meta as $key => $value) {
                $stmt->execute(array($key, $value));
            }

            return $pdo;

        } catch (Exception $e) {
            $this->log(LOG_LEVEL_ERROR, 'Failed to create SQLite database', array(
                'filepath' => $filepath,
                'error' => $e->getMessage(),
            ));
            return null;
        }
    }

    /**
     * Export a single MySQL table to SQLite.
     *
     * @param PDO    $sqlite      SQLite PDO instance.
     * @param string $table       Table name.
     * @param int    $snapshot_id Snapshot ID for progress tracking.
     * @return array Export result.
     */
    private function exportTable($sqlite, $table, $snapshot_id) {
        try {
            // Get table structure
            $create_sql = $this->getCreateTableSql($table);
            if (!$create_sql) {
                throw new Exception('Failed to get table structure');
            }

            // Convert MySQL CREATE to SQLite
            $sqlite_create = $this->convertCreateStatement($create_sql, $table);

            // Create table in SQLite
            $sqlite->exec($sqlite_create);

            // Get row count
            $count = $this->wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
            $count = (int)$count;

            if ($count === 0) {
                return array('success' => true, 'rows' => 0, 'bytes' => 0);
            }

            // Get columns
            $columns = $this->wpdb->get_results("DESCRIBE `{$table}`", ARRAY_A);
            $column_names = array_column($columns, 'Field');
            $placeholders = implode(', ', array_fill(0, count($column_names), '?'));
            $column_list = implode(', ', array_map(function($c) { return "`{$c}`"; }, $column_names));

            // Prepare SQLite insert
            $insert_sql = "INSERT INTO `{$table}` ({$column_list}) VALUES ({$placeholders})";
            $stmt = $sqlite->prepare($insert_sql);

            // Export in batches
            $batch_size = SNAPSHOT_BATCH_SIZE;
            $offset = 0;
            $exported = 0;
            $bytes = 0;

            $sqlite->beginTransaction();

            while ($offset < $count) {
                $rows = $this->wpdb->get_results(
                    $this->wpdb->prepare(
                        "SELECT * FROM `{$table}` LIMIT %d OFFSET %d",
                        $batch_size,
                        $offset
                    ),
                    ARRAY_N
                );

                foreach ($rows as $row) {
                    $stmt->execute($row);
                    $exported++;
                    $bytes += strlen(implode('', array_map('strval', $row)));
                }

                $offset += $batch_size;

                // Log progress every 25%
                $progress = ($offset / $count) * 100;
                if ($progress >= 25 && ($offset - $batch_size) / $count * 100 < 25) {
                    $this->log(LOG_LEVEL_DEBUG, "{$table}: 25% complete");
                } elseif ($progress >= 50 && ($offset - $batch_size) / $count * 100 < 50) {
                    $this->log(LOG_LEVEL_DEBUG, "{$table}: 50% complete");
                } elseif ($progress >= 75 && ($offset - $batch_size) / $count * 100 < 75) {
                    $this->log(LOG_LEVEL_DEBUG, "{$table}: 75% complete");
                }
            }

            $sqlite->commit();

            return array('success' => true, 'rows' => $exported, 'bytes' => $bytes);

        } catch (Exception $e) {
            if ($sqlite->inTransaction()) {
                $sqlite->rollBack();
            }
            return array('success' => false, 'error' => $e->getMessage(), 'rows' => 0, 'bytes' => 0);
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
     * Convert MySQL CREATE TABLE to SQLite compatible syntax.
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

        // Convert AUTO_INCREMENT to SQLite AUTOINCREMENT
        $sql = preg_replace('/\bAUTO_INCREMENT\b/i', 'AUTOINCREMENT', $sql);

        // Convert data types
        $type_map = array(
            '/\bTINYINT\s*\(\d+\)/i' => 'INTEGER',
            '/\bSMALLINT\s*\(\d+\)/i' => 'INTEGER',
            '/\bMEDIUMINT\s*\(\d+\)/i' => 'INTEGER',
            '/\bBIGINT\s*\(\d+\)/i' => 'INTEGER',
            '/\bINT\s*\(\d+\)/i' => 'INTEGER',
            '/\bDOUBLE\b/i' => 'REAL',
            '/\bFLOAT\b/i' => 'REAL',
            '/\bDECIMAL\s*\([^)]+\)/i' => 'REAL',
            '/\bVARCHAR\s*\(\d+\)/i' => 'TEXT',
            '/\bCHAR\s*\(\d+\)/i' => 'TEXT',
            '/\bLONGTEXT\b/i' => 'TEXT',
            '/\bMEDIUMTEXT\b/i' => 'TEXT',
            '/\bTINYTEXT\b/i' => 'TEXT',
            '/\bDATETIME\b/i' => 'TEXT',
            '/\bTIMESTAMP\b/i' => 'TEXT',
            '/\bDATE\b/i' => 'TEXT',
            '/\bTIME\b/i' => 'TEXT',
            '/\bLONGBLOB\b/i' => 'BLOB',
            '/\bMEDIUMBLOB\b/i' => 'BLOB',
            '/\bTINYBLOB\b/i' => 'BLOB',
            '/\bENUM\s*\([^)]+\)/i' => 'TEXT',
            '/\bSET\s*\([^)]+\)/i' => 'TEXT',
            '/\bBIT\s*\(\d+\)/i' => 'INTEGER',
            '/\bYEAR\s*\(\d+\)/i' => 'INTEGER',
            '/\bBOOLEAN\b/i' => 'INTEGER',
            '/\bBOOL\b/i' => 'INTEGER',
        );

        foreach ($type_map as $pattern => $replacement) {
            $sql = preg_replace($pattern, $replacement, $sql);
        }

        // Remove inline COLLATE specifications
        $sql = preg_replace('/\s+COLLATE\s+\w+/i', '', $sql);

        // Remove CHARACTER SET specifications
        $sql = preg_replace('/\s+CHARACTER\s+SET\s+\w+/i', '', $sql);

        // Remove UNSIGNED
        $sql = preg_replace('/\s+UNSIGNED\b/i', '', $sql);

        // Remove ZEROFILL
        $sql = preg_replace('/\s+ZEROFILL\b/i', '', $sql);

        // Handle KEY/INDEX definitions - SQLite doesn't support these inline
        $sql = preg_replace('/,\s*KEY\s+[^,]+(?=,|\))/i', '', $sql);
        $sql = preg_replace('/,\s*UNIQUE\s+KEY\s+[^,]+(?=,|\))/i', '', $sql);
        $sql = preg_replace('/,\s*FULLTEXT\s+KEY\s+[^,]+(?=,|\))/i', '', $sql);
        $sql = preg_replace('/,\s*SPATIAL\s+KEY\s+[^,]+(?=,|\))/i', '', $sql);

        // Remove extra commas before closing paren
        $sql = preg_replace('/,\s*\)/', ')', $sql);

        return $sql;
    }

    /**
     * Get tables for a given scope.
     *
     * @param string $scope  Scope type.
     * @param array  $custom Custom table list for 'custom' scope.
     * @return array List of table names.
     */
    private function getTablesForScope($scope, $custom = array()) {
        $all_tables = $this->wpdb->get_col("SHOW TABLES");
        $prefix = $this->wpdb->prefix;

        switch ($scope) {
            case SNAPSHOT_SCOPE_ALL:
                return $all_tables;

            case SNAPSHOT_SCOPE_WORDPRESS:
                // Only tables with WP prefix
                return array_filter($all_tables, function($table) use ($prefix) {
                    return strpos($table, $prefix) === 0;
                });

            case SNAPSHOT_SCOPE_CONTENT:
                // Posts, comments, terms, and related
                $content_tables = array(
                    $prefix . 'posts',
                    $prefix . 'postmeta',
                    $prefix . 'comments',
                    $prefix . 'commentmeta',
                    $prefix . 'terms',
                    $prefix . 'termmeta',
                    $prefix . 'term_taxonomy',
                    $prefix . 'term_relationships',
                );
                return array_filter($all_tables, function($table) use ($content_tables) {
                    return in_array($table, $content_tables);
                });

            case SNAPSHOT_SCOPE_CUSTOM:
                // User-specified tables
                return array_filter($all_tables, function($table) use ($custom) {
                    return in_array($table, $custom);
                });

            default:
                return array();
        }
    }

    /**
     * Create a snapshot record in the database.
     *
     * @param int    $sequence Sequence number.
     * @param string $filename Filename without extension.
     * @param string $filepath Full path to file.
     * @param string $scope    Snapshot scope.
     * @param array  $tables   Tables included.
     * @param string $trigger  Trigger source.
     * @return int|false Snapshot ID or false.
     */
    private function createSnapshotRecord($sequence, $filename, $filepath, $scope, $tables, $trigger) {
        $data = array(
            'sequence' => $sequence,
            'filename' => $filename . '.sqlite',
            'filepath' => $filepath,
            'provider' => $this->provider_id,
            'scope' => $scope,
            'tables_json' => json_encode($tables),
            'trigger_source' => $trigger,
            'status' => SNAPSHOT_STATUS_PENDING,
            'created_at' => date('c'),
        );

        $result = $this->db->insert(TABLE_SNAPSHOTS, $data);

        if ($result) {
            return $this->db->lastInsertId();
        }

        return false;
    }

    /**
     * Update snapshot status.
     *
     * @param int    $snapshot_id Snapshot ID.
     * @param string $status      New status.
     * @param string $error       Error message (optional).
     */
    private function updateSnapshotStatus($snapshot_id, $status, $error = null) {
        $data = array(
            'status' => $status,
            'updated_at' => date('c'),
        );

        if ($error) {
            $data['error_message'] = $error;
        }

        if ($status === SNAPSHOT_STATUS_RUNNING) {
            $data['started_at'] = date('c');
        }

        $this->db->update(TABLE_SNAPSHOTS, $data, array('id' => $snapshot_id));
    }

    /**
     * Finalize a snapshot with completion details.
     *
     * @param int   $snapshot_id Snapshot ID.
     * @param array $details     Completion details.
     */
    private function finalizeSnapshot($snapshot_id, $details) {
        $data = array(
            'status' => $details['status'],
            'file_size' => $details['file_size'],
            'total_rows' => $details['total_rows'],
            'table_counts_json' => json_encode($details['table_counts']),
            'duration_ms' => $details['duration_ms'],
            'completed_at' => date('c'),
            'updated_at' => date('c'),
        );

        $this->db->update(TABLE_SNAPSHOTS, $data, array('id' => $snapshot_id));
    }

    /**
     * Restore from a snapshot.
     *
     * Delegates to RiseupSnapshotManager for full restore functionality.
     *
     * @param int   $snapshot_id Snapshot ID.
     * @param array $options     Restore options.
     * @return array Restore result.
     */
    public function restoreSnapshot($snapshot_id, $options) {
        // Delegate to manager for centralized restore logic
        $manager = RiseupSnapshotManager::getInstance($this->logger, $this->db);
        return $manager->restoreSnapshot($snapshot_id, $options);
    }

    /**
     * Delete a snapshot.
     *
     * @param int $snapshot_id Snapshot ID.
     * @return array Delete result.
     */
    public function deleteSnapshot($snapshot_id) {
        $snapshot = $this->getSnapshot($snapshot_id);
        if (!$snapshot) {
            return array('success' => false, 'error' => 'Snapshot not found');
        }

        // Delete the file
        $filepath = $snapshot['filepath'];
        if (RiseupPathUtils::fileExists($filepath)) {
            if (!RiseupPathUtils::deleteFile($filepath)) {
                $this->log(LOG_LEVEL_ERROR, 'Failed to delete snapshot file', array(
                    'filepath' => $filepath,
                ));
                return array('success' => false, 'error' => 'Failed to delete snapshot file');
            }
        }

        // Delete ZIP if exists
        $zip_path = str_replace('.sqlite', '.zip', $filepath);
        if (RiseupPathUtils::fileExists($zip_path)) {
            RiseupPathUtils::deleteFile($zip_path);
        }

        // Delete database record
        $this->db->delete(TABLE_SNAPSHOTS, array('id' => $snapshot_id));

        $this->log(LOG_LEVEL_INFO, 'Snapshot deleted', array(
            'snapshot_id' => $snapshot_id,
            'filename' => $snapshot['filename'],
        ));

        return array('success' => true);
    }

    /**
     * Export snapshot to ZIP file.
     *
     * @param int $snapshot_id Snapshot ID.
     * @return array Export result.
     */
    public function exportSnapshot($snapshot_id) {
        $snapshot = $this->getSnapshot($snapshot_id);
        if (!$snapshot) {
            return array('success' => false, 'error' => 'Snapshot not found');
        }

        $filepath = $snapshot['filepath'];
        if (!RiseupPathUtils::fileExists($filepath)) {
            return array('success' => false, 'error' => 'Snapshot file not found');
        }

        // Create ZIP
        $zip_path = str_replace('.sqlite', '.zip', $filepath);
        $zip = new ZipArchive();

        if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return array('success' => false, 'error' => 'Failed to create ZIP file');
        }

        $zip->addFile($filepath, basename($filepath));

        // Add manifest
        $manifest = array(
            'version' => PLUGIN_VERSION,
            'created_at' => date('c'),
            'snapshot_id' => $snapshot_id,
            'filename' => $snapshot['filename'],
            'scope' => $snapshot['scope'],
            'tables' => json_decode($snapshot['tables_json'], true),
            'total_rows' => $snapshot['total_rows'],
            'file_size' => $snapshot['file_size'],
        );
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));

        $zip->close();

        return array(
            'success' => true,
            'filepath' => $zip_path,
            'filename' => basename($zip_path),
            'size' => filesize($zip_path),
        );
    }

    /**
     * Import snapshot from uploaded file.
     *
     * Delegates to RiseupSnapshotManager for full import functionality.
     *
     * @param string $filepath Path to uploaded file.
     * @return array Import result.
     */
    public function importSnapshot($filepath) {
        // Delegate to manager for centralized import logic
        $manager = RiseupSnapshotManager::getInstance($this->logger, $this->db);
        return $manager->importSnapshot($filepath);
    }

    /**
     * Get snapshot details.
     *
     * @param int $snapshot_id Snapshot ID.
     * @return array|null Snapshot or null.
     */
    public function getSnapshot($snapshot_id) {
        return $this->db->query_single(
            'SELECT * FROM ' . TABLE_SNAPSHOTS . ' WHERE id = ?',
            array($snapshot_id)
        );
    }

    /**
     * List snapshots.
     *
     * @param int $limit  Limit.
     * @param int $offset Offset.
     * @return array List result.
     */
    public function listSnapshots($limit = 50, $offset = 0) {
        $snapshots = $this->db->query_all(
            'SELECT * FROM ' . TABLE_SNAPSHOTS . ' WHERE provider = ? ORDER BY created_at DESC LIMIT ? OFFSET ?',
            array($this->provider_id, $limit, $offset)
        );

        $total = $this->db->query_single(
            'SELECT COUNT(*) as count FROM ' . TABLE_SNAPSHOTS . ' WHERE provider = ?',
            array($this->provider_id)
        );

        return array(
            'snapshots' => $snapshots ?: array(),
            'total' => $total ? (int)$total['count'] : 0,
        );
    }

    /**
     * Get available tables.
     *
     * @return array Tables list.
     */
    public function getAvailableTables() {
        $tables = array();
        $all_tables = $this->wpdb->get_results("SHOW TABLE STATUS", ARRAY_A);

        foreach ($all_tables as $table_info) {
            $tables[] = array(
                'name' => $table_info['Name'],
                'rows' => (int)$table_info['Rows'],
                'size' => (int)$table_info['Data_length'] + (int)$table_info['Index_length'],
                'is_core' => strpos($table_info['Name'], $this->wpdb->prefix) === 0,
            );
        }

        return $tables;
    }
}
