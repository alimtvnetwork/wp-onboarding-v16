<?php
/**
 * Riseup Asia Uploader - Native SQLite Snapshot Provider
 *
 * Implements database snapshots using MySQL to SQLite export.
 * This is the fallback provider when WP Reset or Updraft is not available.
 *
 * @package RiseupAsiaUploader
 * @since   1.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__FILE__) . '/class-snapshot-provider-interface.php';

/**
 * Native SQLite Snapshot Provider.
 * 
 * Exports MySQL tables to SQLite format for portable database backups.
 * All operations are scheduled via WP-Cron to prevent request timeouts.
 */
class Riseup_Snapshot_Provider_Native extends Riseup_Snapshot_Provider_Interface {

    /**
     * Provider ID.
     *
     * @var string
     */
    protected $provider_id = RISEUP_SNAPSHOT_PROVIDER_NATIVE;

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
     * @param Riseup_File_Logger $logger Logger instance.
     * @param Riseup_Database    $db     Database instance.
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
    public function is_available() {
        return extension_loaded('sqlite3') || extension_loaded('pdo_sqlite');
    }

    /**
     * Get provider capabilities.
     *
     * @return array Capabilities array.
     */
    public function get_capabilities() {
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
    public function create_snapshot($options) {
        $this->log(RISEUP_LOG_LEVEL_INFO, 'Snapshot creation requested', $options);

        // Ensure directory exists
        if (!$this->ensure_snapshots_dir()) {
            return array(
                'success' => false,
                'error' => 'Failed to create snapshots directory',
            );
        }

        // Check for existing lock
        if ($this->is_locked()) {
            $this->log(RISEUP_LOG_LEVEL_WARN, 'Snapshot already in progress (locked)');
            return array(
                'success' => false,
                'error' => 'Another snapshot operation is in progress',
                'code' => RISEUP_ERR_SNAPSHOT_LOCK_EXISTS,
            );
        }

        // Determine tables to export
        $scope = isset($options['scope']) ? $options['scope'] : RISEUP_SNAPSHOT_SCOPE_WORDPRESS;
        $tables = $this->get_tables_for_scope($scope, isset($options['tables']) ? $options['tables'] : array());

        if (empty($tables)) {
            return array(
                'success' => false,
                'error' => 'No tables selected for snapshot',
            );
        }

        // Get next sequence number
        $sequence = $this->get_next_sequence();
        $filename = $this->generate_snapshot_filename($sequence);
        $filepath = $this->get_snapshots_dir() . '/' . $filename . '.sqlite';

        // Create snapshot record
        $trigger = isset($options['trigger']) ? $options['trigger'] : 'api';
        $snapshot_id = $this->create_snapshot_record($sequence, $filename, $filepath, $scope, $tables, $trigger);

        if (!$snapshot_id) {
            return array(
                'success' => false,
                'error' => 'Failed to create snapshot record',
            );
        }

        // Schedule the actual export via cron
        $scheduled = wp_schedule_single_event(
            time() + 5, // 5 seconds from now
            RISEUP_CRON_SNAPSHOT_IMMEDIATE,
            array(array(
                'snapshot_id' => $snapshot_id,
                'tables' => $tables,
            ))
        );

        if ($scheduled === false) {
            // Direct execution as fallback (not recommended)
            $this->log(RISEUP_LOG_LEVEL_WARN, 'Cron scheduling failed, executing directly');
            $result = $this->execute_snapshot($snapshot_id, $tables);
            return $result;
        }

        $this->log(RISEUP_LOG_LEVEL_INFO, 'Snapshot scheduled via cron', array(
            'snapshot_id' => $snapshot_id,
            'filename' => $filename,
            'tables' => count($tables),
        ));

        return array(
            'success' => true,
            'snapshot_id' => $snapshot_id,
            'filename' => $filename . '.sqlite',
            'status' => RISEUP_SNAPSHOT_STATUS_SCHEDULED,
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
    public function execute_snapshot($snapshot_id, $tables) {
        $start_time = microtime(true);

        // Get snapshot record
        $snapshot = $this->get_snapshot($snapshot_id);
        if (!$snapshot) {
            return array('success' => false, 'error' => 'Snapshot record not found');
        }

        $filepath = $snapshot['filepath'];

        // Acquire lock
        if (!$this->acquire_lock()) {
            $this->update_snapshot_status($snapshot_id, RISEUP_SNAPSHOT_STATUS_FAILED, 'Failed to acquire lock');
            return array('success' => false, 'error' => 'Failed to acquire lock');
        }

        try {
            // Update status to running
            $this->update_snapshot_status($snapshot_id, RISEUP_SNAPSHOT_STATUS_RUNNING);

            $this->log(RISEUP_LOG_LEVEL_INFO, 'Starting snapshot export', array(
                'snapshot_id' => $snapshot_id,
                'filepath' => $filepath,
                'tables' => count($tables),
            ));

            // Create SQLite database
            $sqlite = $this->create_sqlite_database($filepath);
            if (!$sqlite) {
                throw new Exception('Failed to create SQLite database');
            }

            $total_rows = 0;
            $table_counts = array();

            // Export each table
            foreach ($tables as $table) {
                $this->log(RISEUP_LOG_LEVEL_DEBUG, 'Exporting table: ' . $table);

                $result = $this->export_table($sqlite, $table, $snapshot_id);

                if ($result['success']) {
                    $total_rows += $result['rows'];
                    $table_counts[$table] = $result['rows'];
                    $this->log(RISEUP_LOG_LEVEL_INFO, sprintf(
                        'Table %s complete (%d rows, %s)',
                        $table,
                        $result['rows'],
                        $this->format_bytes($result['bytes'])
                    ));
                } else {
                    $this->log(RISEUP_LOG_LEVEL_ERROR, 'Failed to export table: ' . $table, array(
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
            $this->finalize_snapshot($snapshot_id, array(
                'status' => RISEUP_SNAPSHOT_STATUS_COMPLETE,
                'file_size' => $file_size,
                'total_rows' => $total_rows,
                'table_counts' => $table_counts,
                'duration_ms' => (int)($duration * 1000),
            ));

            $this->log(RISEUP_LOG_LEVEL_INFO, 'Snapshot complete', array(
                'snapshot_id' => $snapshot_id,
                'filepath' => $filepath,
                'size' => $this->format_bytes($file_size),
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
            $this->log(RISEUP_LOG_LEVEL_ERROR, 'Snapshot failed', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ));

            $this->update_snapshot_status($snapshot_id, RISEUP_SNAPSHOT_STATUS_FAILED, $e->getMessage());

            return array(
                'success' => false,
                'error' => $e->getMessage(),
            );

        } finally {
            $this->release_lock();
        }
    }

    /**
     * Create SQLite database file.
     *
     * @param string $filepath Path to create database.
     * @return PDO|null PDO instance or null on failure.
     */
    private function create_sqlite_database($filepath) {
        try {
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
                'plugin_version' => RISEUP_VERSION,
            );

            $stmt = $pdo->prepare('INSERT INTO _snapshot_meta (key, value) VALUES (?, ?)');
            foreach ($meta as $key => $value) {
                $stmt->execute(array($key, $value));
            }

            return $pdo;

        } catch (Exception $e) {
            $this->log(RISEUP_LOG_LEVEL_ERROR, 'Failed to create SQLite database', array(
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
    private function export_table($sqlite, $table, $snapshot_id) {
        try {
            // Get table structure
            $create_sql = $this->get_create_table_sql($table);
            if (!$create_sql) {
                throw new Exception('Failed to get table structure');
            }

            // Convert MySQL CREATE to SQLite
            $sqlite_create = $this->convert_create_statement($create_sql, $table);

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
            $batch_size = RISEUP_SNAPSHOT_BATCH_SIZE;
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
                    $this->log(RISEUP_LOG_LEVEL_DEBUG, "{$table}: 25% complete");
                } elseif ($progress >= 50 && ($offset - $batch_size) / $count * 100 < 50) {
                    $this->log(RISEUP_LOG_LEVEL_DEBUG, "{$table}: 50% complete");
                } elseif ($progress >= 75 && ($offset - $batch_size) / $count * 100 < 75) {
                    $this->log(RISEUP_LOG_LEVEL_DEBUG, "{$table}: 75% complete");
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
    private function get_create_table_sql($table) {
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
    private function convert_create_statement($mysql_create, $table) {
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
        );

        foreach ($type_map as $pattern => $replacement) {
            $sql = preg_replace($pattern, $replacement, $sql);
        }

        // Remove unsigned
        $sql = preg_replace('/\bUNSIGNED\b/i', '', $sql);

        // Remove character set specifications on columns
        $sql = preg_replace('/\s+CHARACTER\s+SET\s+\w+/i', '', $sql);

        // Remove ON UPDATE CURRENT_TIMESTAMP
        $sql = preg_replace('/\s+ON\s+UPDATE\s+CURRENT_TIMESTAMP/i', '', $sql);

        // Convert CURRENT_TIMESTAMP to SQLite equivalent
        $sql = preg_replace('/\bCURRENT_TIMESTAMP\b/i', "datetime('now')", $sql);

        // Remove KEY definitions (SQLite handles these differently)
        $sql = preg_replace('/,\s*KEY\s+`[^`]+`\s*\([^)]+\)/i', '', $sql);
        $sql = preg_replace('/,\s*UNIQUE\s+KEY\s+`[^`]+`\s*\([^)]+\)/i', '', $sql);
        $sql = preg_replace('/,\s*FULLTEXT\s+KEY\s+`[^`]+`\s*\([^)]+\)/i', '', $sql);

        // Clean up extra commas before closing paren
        $sql = preg_replace('/,\s*\)/', ')', $sql);

        return $sql;
    }

    /**
     * Get tables for a given scope.
     *
     * @param string $scope  Scope type.
     * @param array  $custom Custom tables (for 'custom' scope).
     * @return array Table names.
     */
    private function get_tables_for_scope($scope, $custom = array()) {
        $prefix = $this->wpdb->prefix;

        switch ($scope) {
            case RISEUP_SNAPSHOT_SCOPE_ALL:
                // All tables in database
                $tables = $this->wpdb->get_col("SHOW TABLES");
                break;

            case RISEUP_SNAPSHOT_SCOPE_WORDPRESS:
                // Core WordPress tables
                $core = array(
                    'posts', 'postmeta', 'comments', 'commentmeta',
                    'terms', 'termmeta', 'term_relationships', 'term_taxonomy',
                    'users', 'usermeta', 'options', 'links'
                );
                $tables = array_map(function($t) use ($prefix) {
                    return $prefix . $t;
                }, $core);
                break;

            case RISEUP_SNAPSHOT_SCOPE_CONTENT:
                // Content tables only
                $content = array(
                    'posts', 'postmeta', 'comments', 'commentmeta',
                    'terms', 'termmeta', 'term_relationships', 'term_taxonomy'
                );
                $tables = array_map(function($t) use ($prefix) {
                    return $prefix . $t;
                }, $content);
                break;

            case RISEUP_SNAPSHOT_SCOPE_CUSTOM:
                $tables = $custom;
                break;

            default:
                $tables = array();
        }

        // Filter to only existing tables
        $all_tables = $this->wpdb->get_col("SHOW TABLES");
        return array_intersect($tables, $all_tables);
    }

    /**
     * Create snapshot database record.
     *
     * @param int    $sequence Sequence number.
     * @param string $filename Filename without extension.
     * @param string $filepath Full file path.
     * @param string $scope    Scope type.
     * @param array  $tables   Table names.
     * @param string $trigger  Trigger source.
     * @return int|false Snapshot ID or false on failure.
     */
    private function create_snapshot_record($sequence, $filename, $filepath, $scope, $tables, $trigger) {
        $result = $this->db->execute(
            'INSERT INTO ' . RISEUP_TABLE_SNAPSHOTS . ' 
            (sequence, filename, filepath, created_at, status, provider, scope, tables_json, triggered_by) 
            VALUES (?, ?, ?, datetime("now"), ?, ?, ?, ?, ?)',
            array(
                $sequence,
                $filename . '.sqlite',
                $filepath,
                RISEUP_SNAPSHOT_STATUS_PENDING,
                $this->provider_id,
                $scope,
                json_encode($tables),
                $trigger
            )
        );

        if ($result) {
            return $this->db->last_insert_id();
        }
        return false;
    }

    /**
     * Update snapshot status.
     *
     * @param int    $snapshot_id Snapshot ID.
     * @param string $status      New status.
     * @param string $error       Error message (if failed).
     */
    private function update_snapshot_status($snapshot_id, $status, $error = null) {
        $sql = 'UPDATE ' . RISEUP_TABLE_SNAPSHOTS . ' SET status = ?';
        $params = array($status);

        if ($error !== null) {
            $sql .= ', error_message = ?';
            $params[] = $error;
        }

        if ($status === RISEUP_SNAPSHOT_STATUS_COMPLETE || $status === RISEUP_SNAPSHOT_STATUS_FAILED) {
            $sql .= ', completed_at = datetime("now")';
        }

        $sql .= ' WHERE id = ?';
        $params[] = $snapshot_id;

        $this->db->execute($sql, $params);
    }

    /**
     * Finalize snapshot with results.
     *
     * @param int   $snapshot_id Snapshot ID.
     * @param array $data        Finalization data.
     */
    private function finalize_snapshot($snapshot_id, $data) {
        $this->db->execute(
            'UPDATE ' . RISEUP_TABLE_SNAPSHOTS . ' SET 
                status = ?,
                file_size = ?,
                total_rows = ?,
                table_counts_json = ?,
                duration_ms = ?,
                completed_at = datetime("now")
            WHERE id = ?',
            array(
                $data['status'],
                $data['file_size'],
                $data['total_rows'],
                json_encode($data['table_counts']),
                $data['duration_ms'],
                $snapshot_id
            )
        );
    }

    // =========================================================================
    // Placeholder implementations for remaining interface methods
    // These will be fully implemented in Phase 23 (Import/Export/Restore)
    // =========================================================================

    public function restore_snapshot($snapshot_id, $options) {
        // TODO: Implement in Phase 23
        return array(
            'success' => false,
            'error' => 'Restore not yet implemented',
        );
    }

    public function delete_snapshot($snapshot_id) {
        $snapshot = $this->get_snapshot($snapshot_id);
        if (!$snapshot) {
            return array('success' => false, 'error' => 'Snapshot not found');
        }

        // Delete file
        if (file_exists($snapshot['filepath'])) {
            unlink($snapshot['filepath']);
        }

        // Delete ZIP if exists
        $zip_path = str_replace('.sqlite', '.zip', $snapshot['filepath']);
        if (file_exists($zip_path)) {
            unlink($zip_path);
        }

        // Delete database record
        $this->db->execute(
            'DELETE FROM ' . RISEUP_TABLE_SNAPSHOTS . ' WHERE id = ?',
            array($snapshot_id)
        );

        $this->log(RISEUP_LOG_LEVEL_INFO, 'Snapshot deleted', array('id' => $snapshot_id));

        return array('success' => true);
    }

    public function export_snapshot($snapshot_id) {
        // TODO: Implement in Phase 23
        return array(
            'success' => false,
            'error' => 'Export not yet implemented',
        );
    }

    public function import_snapshot($filepath) {
        // TODO: Implement in Phase 23
        return array(
            'success' => false,
            'error' => 'Import not yet implemented',
        );
    }

    public function get_snapshot($snapshot_id) {
        return $this->db->query_single(
            'SELECT * FROM ' . RISEUP_TABLE_SNAPSHOTS . ' WHERE id = ?',
            array($snapshot_id)
        );
    }

    public function list_snapshots($limit = 50, $offset = 0) {
        $snapshots = $this->db->query_all(
            'SELECT * FROM ' . RISEUP_TABLE_SNAPSHOTS . ' ORDER BY created_at DESC LIMIT ? OFFSET ?',
            array($limit, $offset)
        );

        $total = $this->db->query_single(
            'SELECT COUNT(*) as count FROM ' . RISEUP_TABLE_SNAPSHOTS
        );

        return array(
            'snapshots' => $snapshots ?: array(),
            'total' => $total ? (int)$total['count'] : 0,
        );
    }

    public function get_available_tables() {
        $tables = array();
        $prefix = $this->wpdb->prefix;
        $core_tables = array(
            'posts', 'postmeta', 'comments', 'commentmeta',
            'terms', 'termmeta', 'term_relationships', 'term_taxonomy',
            'users', 'usermeta', 'options', 'links'
        );

        $all_tables = $this->wpdb->get_results("SHOW TABLE STATUS", ARRAY_A);

        foreach ($all_tables as $table_info) {
            $name = $table_info['Name'];
            $is_core = false;

            foreach ($core_tables as $core) {
                if ($name === $prefix . $core) {
                    $is_core = true;
                    break;
                }
            }

            $tables[] = array(
                'name' => $name,
                'rows' => (int)$table_info['Rows'],
                'size' => (int)$table_info['Data_length'] + (int)$table_info['Index_length'],
                'is_core' => $is_core,
            );
        }

        return $tables;
    }
}
