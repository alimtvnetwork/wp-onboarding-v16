<?php
/**
 * Riseup Asia Uploader - Restore Engine
 *
 * Dependency-aware restoration from per-table SQLite snapshots.
 * Supports full, incremental, and selective table restore modes.
 * Automatically creates a pre-restore safety backup.
 *
 * @package RiseupAsiaUploader
 * @since   1.15.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Restore Engine class.
 *
 * Reads a-root.db to determine the table dependency graph and restore order,
 * then replays master + incremental SQLite files into MySQL in the correct
 * topological sequence.
 *
 * Restore modes:
 * - 'full'        : Restore all tables from master (+ incrementals if present)
 * - 'incremental' : Apply only incremental deltas on top of current MySQL state
 * - 'selective'   : Restore specific tables chosen by the user
 */
class RiseupRestoreEngine {

    /** @var RiseupFileLogger */
    private $logger;

    /** @var RiseupDatabase */
    private $db;

    /** @var RiseupSnapshotOrchestrator|null */
    private $orchestrator;

    /** @var wpdb */
    private $wpdb;

    /** @var int */
    private $batchSize;

    /** @var RiseupRestoreEngine|null */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @param RiseupFileLogger|null              $logger       Logger.
     * @param RiseupDatabase|null                $db           Plugin database.
     * @param RiseupSnapshotOrchestrator|null     $orchestrator Orchestrator (for pre-restore backups).
     * @return RiseupRestoreEngine
     */
    public static function getInstance($logger = null, $db = null, $orchestrator = null) {
        if (self::$instance === null && $logger && $db) {
            self::$instance = new self($logger, $db, $orchestrator);
        }
        return self::$instance;
    }

    /**
     * Constructor.
     *
     * @param RiseupFileLogger              $logger       Logger.
     * @param RiseupDatabase                $db           Plugin database.
     * @param RiseupSnapshotOrchestrator|null $orchestrator Orchestrator.
     */
    private function __construct($logger, $db, $orchestrator = null) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->logger = $logger;
        $this->db = $db;
        $this->orchestrator = $orchestrator;
        $this->batchSize = RISEUP_SNAPSHOT_BATCH_SIZE;
    }

    /**
     * Execute a per-table restore from a snapshot directory.
     *
     * @param string $snapshot_dir Path to the snapshot directory containing a-root.db.
     * @param array  $options      Options: mode, tables, create_backup, confirm, apply_incrementals.
     * @return array Result with success, tables_restored, total_rows, duration, etc.
     */
    public function execute($snapshot_dir, $options = array()) {
        $start_time = microtime(true);

        $mode = $options['mode'] ?? 'full';
        $selected_tables = $options['tables'] ?? array();
        $create_backup = $options['create_backup'] ?? true;
        $apply_incrementals = $options['apply_incrementals'] ?? true;

        // Validate confirmation
        if (empty($options['confirm']) || $options['confirm'] !== true) {
            return array(
                'success' => false,
                'error'   => 'Restore requires explicit confirmation (confirm=true)',
                'code'    => RISEUP_ERR_RESTORE_NO_CONFIRM,
            );
        }

        $root_path = $snapshot_dir . '/a-root.db';
        if (!file_exists($root_path)) {
            return array(
                'success' => false,
                'error'   => 'Snapshot a-root.db not found at: ' . basename($snapshot_dir),
            );
        }

        $this->log('INFO', 'Starting per-table restore', array(
            'directory'          => basename($snapshot_dir),
            'mode'               => $mode,
            'create_backup'      => $create_backup,
            'apply_incrementals' => $apply_incrementals,
            'selected_tables'    => count($selected_tables) > 0 ? count($selected_tables) : 'all',
        ));

        try {
            // 1. Open a-root.db
            $rootPdo = new PDO('sqlite:' . $root_path);
            $rootPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // 2. Read snapshot metadata
            $meta = $this->getSnapshotMeta($rootPdo);

            // 3. Get table inventory with dependency order
            $table_inventory = $this->getTableInventory($rootPdo);
            $restore_order = $this->getRestoreOrder($rootPdo, $table_inventory);

            // 4. Filter tables for selective mode
            if ($mode === 'selective' && !empty($selected_tables)) {
                $restore_order = array_values(array_filter($restore_order, function($t) use ($selected_tables) {
                    return in_array($t, $selected_tables);
                }));

                if (empty($restore_order)) {
                    $rootPdo = null;
                    return array(
                        'success' => false,
                        'error'   => 'None of the selected tables exist in the snapshot',
                    );
                }
            }

            $this->log('INFO', 'Restore order determined', array(
                'tables' => count($restore_order),
                'order'  => array_slice($restore_order, 0, 10), // Log first 10
            ));

            // 5. Pre-restore safety backup (optional)
            $backup_id = null;
            if ($create_backup && $this->orchestrator) {
                $this->log('INFO', 'Creating pre-restore safety backup');
                $backup_result = $this->orchestrator->executeFullBackup(array(
                    'title' => 'Pre-Restore Safety Backup ' . date('Y-m-d H:i'),
                    'compression' => false, // Skip ZIP for speed
                    'include_plugins' => false,
                ));

                if ($backup_result['success']) {
                    $backup_id = $backup_result['snapshot_id'] ?? null;
                    $this->log('INFO', 'Pre-restore backup complete', array(
                        'backup_id' => $backup_id,
                    ));
                } else {
                    $this->log('WARN', 'Pre-restore backup failed (continuing)', array(
                        'error' => $backup_result['error'] ?? 'Unknown',
                    ));
                    // Continue unless strict mode
                    if (!empty($options['require_backup'])) {
                        $rootPdo = null;
                        return array(
                            'success' => false,
                            'error'   => 'Pre-restore backup failed: ' . ($backup_result['error'] ?? 'Unknown'),
                        );
                    }
                }
            }

            // 6. Restore master tables
            $tables_restored = 0;
            $total_rows = 0;
            $errors = array();

            // Disable FK checks for the entire restore
            $this->wpdb->query("SET FOREIGN_KEY_CHECKS = 0");

            foreach ($restore_order as $table) {
                $table_info = $table_inventory[$table] ?? null;
                if (!$table_info) {
                    $errors[] = $table . ': not found in inventory';
                    continue;
                }

                $sqlite_path = $snapshot_dir . '/' . $table_info['sqlite_file'];
                if (!file_exists($sqlite_path)) {
                    $errors[] = $table . ': SQLite file missing (' . $table_info['sqlite_file'] . ')';
                    $this->log('ERROR', 'SQLite file missing for table', array(
                        'table' => $table,
                        'file'  => $table_info['sqlite_file'],
                    ));
                    continue;
                }

                $result = $this->restoreTableFromFile($sqlite_path, $table, 'truncate');

                if ($result['success']) {
                    $tables_restored++;
                    $total_rows += $result['rows'];
                    $this->log('INFO', sprintf('Restored: %s (%d rows)', $table, $result['rows']));
                } else {
                    $errors[] = $table . ': ' . $result['error'];
                    $this->log('ERROR', 'Restore failed: ' . $table, array(
                        'error' => $result['error'],
                    ));

                    if (!empty($options['strict'])) {
                        $this->wpdb->query("SET FOREIGN_KEY_CHECKS = 1");
                        $rootPdo = null;
                        return array(
                            'success'         => false,
                            'error'           => 'Strict mode: table restore failed for ' . $table,
                            'tables_restored' => $tables_restored,
                            'total_rows'      => $total_rows,
                            'errors'          => $errors,
                        );
                    }
                }
            }

            // 7. Apply incrementals if requested
            $incrementals_applied = 0;
            if ($apply_incrementals && $mode !== 'incremental') {
                $inc_result = $this->applyIncrementals($rootPdo, $snapshot_dir, $restore_order);
                $incrementals_applied = $inc_result['applied'];
                $total_rows += $inc_result['total_rows'];
                if (!empty($inc_result['errors'])) {
                    $errors = array_merge($errors, $inc_result['errors']);
                }
            }

            // 8. If mode is 'incremental' only, skip master and apply latest incremental
            if ($mode === 'incremental') {
                $inc_result = $this->applyIncrementals($rootPdo, $snapshot_dir, $restore_order);
                $incrementals_applied = $inc_result['applied'];
                $total_rows += $inc_result['total_rows'];
                if (!empty($inc_result['errors'])) {
                    $errors = array_merge($errors, $inc_result['errors']);
                }
            }

            // Re-enable FK checks
            $this->wpdb->query("SET FOREIGN_KEY_CHECKS = 1");

            $rootPdo = null; // Close
            $duration = microtime(true) - $start_time;

            // Log audit
            $this->logAuditRestore($snapshot_dir, $tables_restored, $total_rows, $duration);

            $this->log('INFO', 'Per-table restore complete', array(
                'tables_restored'     => $tables_restored,
                'total_rows'          => $total_rows,
                'incrementals_applied' => $incrementals_applied,
                'errors'              => count($errors),
                'backup_id'           => $backup_id,
                'duration'            => round($duration, 2) . 's',
            ));

            return array(
                'success'              => true,
                'tables_restored'      => $tables_restored,
                'total_rows'           => $total_rows,
                'incrementals_applied' => $incrementals_applied,
                'backup_id'            => $backup_id,
                'errors'               => $errors,
                'duration'             => $duration,
                'meta'                 => $meta,
            );

        } catch (Exception $e) {
            $this->wpdb->query("SET FOREIGN_KEY_CHECKS = 1");
            $this->log('ERROR', 'Restore engine failed', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ));
            return array(
                'success' => false,
                'error'   => $e->getMessage(),
                'phase'   => 'restore',
            );
        }
    }

    /**
     * Restore a single table from its individual SQLite file into MySQL.
     *
     * @param string $sqlite_path Path to the table's .sqlite file.
     * @param string $table       MySQL table name.
     * @param string $strategy    'truncate' (replace all) or 'merge' (INSERT OR REPLACE).
     * @return array Result: success, rows, error.
     */
    private function restoreTableFromFile($sqlite_path, $table, $strategy = 'truncate') {
        try {
            $sqlite = new PDO('sqlite:' . $sqlite_path);
            $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Verify table exists in SQLite
            $check = $sqlite->query(
                "SELECT name FROM sqlite_master WHERE type='table' AND name='" .
                str_replace("'", "''", $table) . "'"
            );
            if (!$check->fetch()) {
                $sqlite = null;
                return array('success' => false, 'error' => 'Table not found in SQLite file', 'rows' => 0);
            }

            // Get columns
            $columns_result = $sqlite->query("PRAGMA table_info('" . str_replace("'", "''", $table) . "')");
            $columns = $columns_result->fetchAll(PDO::FETCH_ASSOC);
            $column_names = array_column($columns, 'name');

            if (empty($column_names)) {
                $sqlite = null;
                return array('success' => false, 'error' => 'No columns found in SQLite table', 'rows' => 0);
            }

            // Start MySQL transaction
            $this->wpdb->query("START TRANSACTION");

            try {
                // Truncate if full restore
                if ($strategy === 'truncate') {
                    $this->wpdb->query("TRUNCATE TABLE `{$table}`");
                }

                // Count total rows
                $row_count = (int) $sqlite->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();

                // Batch insert
                $offset = 0;
                $total_rows = 0;
                $columns_sql = '`' . implode('`, `', $column_names) . '`';
                $placeholders = implode(', ', array_fill(0, count($column_names), '%s'));

                $insert_verb = ($strategy === 'merge') ? 'REPLACE' : 'INSERT';
                $sql_template = "{$insert_verb} INTO `{$table}` ({$columns_sql}) VALUES ({$placeholders})";

                while ($offset < $row_count) {
                    $rows = $sqlite->query(
                        "SELECT * FROM `{$table}` LIMIT {$this->batchSize} OFFSET {$offset}"
                    )->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($rows as $row) {
                        $values = array();
                        foreach ($column_names as $col) {
                            $values[] = isset($row[$col]) ? $row[$col] : null;
                        }

                        $prepared = $this->wpdb->prepare($sql_template, $values);
                        $this->wpdb->query($prepared);
                        $total_rows++;
                    }

                    $offset += $this->batchSize;
                }

                $this->wpdb->query("COMMIT");
                $sqlite = null;

                return array('success' => true, 'rows' => $total_rows);

            } catch (Exception $e) {
                $this->wpdb->query("ROLLBACK");
                throw $e;
            }

        } catch (Exception $e) {
            return array(
                'success' => false,
                'error'   => $e->getMessage(),
                'rows'    => 0,
            );
        }
    }

    /**
     * Apply incremental backups in sequence order.
     *
     * @param PDO    $rootPdo       a-root.db PDO connection.
     * @param string $snapshot_dir  Master snapshot directory.
     * @param array  $restore_order Tables to consider for incremental application.
     * @return array Result: applied (count), total_rows, errors.
     */
    private function applyIncrementals($rootPdo, $snapshot_dir, $restore_order) {
        $incrementals = $rootPdo->query(
            "SELECT sequence_num, folder_name, relative_path FROM incremental_backups ORDER BY sequence_num ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        if (empty($incrementals)) {
            return array('applied' => 0, 'total_rows' => 0, 'errors' => array());
        }

        $this->log('INFO', 'Applying incrementals', array('count' => count($incrementals)));

        $applied = 0;
        $total_rows = 0;
        $errors = array();

        foreach ($incrementals as $inc) {
            $inc_dir = $snapshot_dir . '/' . rtrim($inc['relative_path'], '/');
            if (!is_dir($inc_dir)) {
                $errors[] = 'Incremental directory missing: ' . $inc['folder_name'];
                $this->log('WARN', 'Incremental directory missing', array(
                    'folder' => $inc['folder_name'],
                ));
                continue;
            }

            $this->log('INFO', 'Applying incremental: ' . $inc['folder_name']);

            // Find all SQLite files in this incremental
            $sqlite_files = glob($inc_dir . '/*.sqlite');
            $inc_rows = 0;

            foreach ($sqlite_files as $sqlite_file) {
                $table = basename($sqlite_file, '.sqlite');

                // Only restore tables in our restore order
                if (!in_array($table, $restore_order)) {
                    continue;
                }

                // Use REPLACE strategy for incrementals (merge, don't truncate)
                $result = $this->restoreTableFromFile($sqlite_file, $table, 'merge');

                if ($result['success']) {
                    $inc_rows += $result['rows'];
                    $this->log('INFO', sprintf('Incremental %s: %s (+%d rows)',
                        $inc['folder_name'], $table, $result['rows']
                    ));
                } else {
                    $errors[] = sprintf('Incremental %s/%s: %s',
                        $inc['folder_name'], $table, $result['error']
                    );
                }
            }

            $total_rows += $inc_rows;
            $applied++;
        }

        return array(
            'applied'    => $applied,
            'total_rows' => $total_rows,
            'errors'     => $errors,
        );
    }

    /**
     * Get snapshot metadata from a-root.db.
     *
     * @param PDO $rootPdo a-root.db PDO.
     * @return array Metadata.
     */
    private function getSnapshotMeta($rootPdo) {
        $row = $rootPdo->query("SELECT * FROM snapshot_meta WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
        return $row ?: array();
    }

    /**
     * Get table inventory from a-root.db.
     *
     * @param PDO $rootPdo a-root.db PDO.
     * @return array Map of table_name => { sqlite_file, row_count, checksum_md5 }.
     */
    private function getTableInventory($rootPdo) {
        $rows = $rootPdo->query(
            "SELECT table_name, sqlite_file, row_count, checksum_md5 FROM snapshot_tables ORDER BY table_name"
        )->fetchAll(PDO::FETCH_ASSOC);

        $inventory = array();
        foreach ($rows as $row) {
            $inventory[$row['table_name']] = array(
                'sqlite_file'  => $row['sqlite_file'],
                'row_count'    => (int) $row['row_count'],
                'checksum_md5' => $row['checksum_md5'],
            );
        }

        return $inventory;
    }

    /**
     * Determine the restore order using the dependency graph (topological sort).
     *
     * Parent tables (referenced tables) are restored first, then children.
     * Tables not in the dependency graph are appended at the end.
     *
     * @param PDO   $rootPdo        a-root.db PDO.
     * @param array $table_inventory Table inventory map.
     * @return array Ordered list of table names.
     */
    private function getRestoreOrder($rootPdo, $table_inventory) {
        $all_tables = array_keys($table_inventory);

        // Read dependency graph
        $deps = $rootPdo->query(
            "SELECT parent_table, child_table FROM table_dependencies"
        )->fetchAll(PDO::FETCH_ASSOC);

        if (empty($deps)) {
            // No dependencies — return alphabetical order
            sort($all_tables);
            return $all_tables;
        }

        // Build adjacency list (parent → children)
        $graph = array();
        $in_degree = array();

        foreach ($all_tables as $t) {
            $graph[$t] = array();
            $in_degree[$t] = 0;
        }

        foreach ($deps as $dep) {
            $parent = $dep['parent_table'];
            $child = $dep['child_table'];

            // Only include edges for tables in our inventory
            if (!isset($graph[$parent]) || !isset($graph[$child])) {
                continue;
            }

            $graph[$parent][] = $child;
            $in_degree[$child]++;
        }

        // Kahn's algorithm (topological sort)
        $queue = array();
        foreach ($in_degree as $table => $degree) {
            if ($degree === 0) {
                $queue[] = $table;
            }
        }

        $sorted = array();
        while (!empty($queue)) {
            sort($queue); // Deterministic order within same level
            $table = array_shift($queue);
            $sorted[] = $table;

            foreach ($graph[$table] as $child) {
                $in_degree[$child]--;
                if ($in_degree[$child] === 0) {
                    $queue[] = $child;
                }
            }
        }

        // Append any tables not covered by the graph (orphans)
        foreach ($all_tables as $t) {
            if (!in_array($t, $sorted)) {
                $sorted[] = $t;
            }
        }

        return $sorted;
    }

    /**
     * Log an audit trail entry for the restore operation.
     *
     * @param string $snapshot_dir    Snapshot directory.
     * @param int    $tables_restored Number of tables restored.
     * @param int    $total_rows      Total rows restored.
     * @param float  $duration        Duration in seconds.
     */
    private function logAuditRestore($snapshot_dir, $tables_restored, $total_rows, $duration) {
        $pdo = $this->db->get_pdo();
        if (!$pdo) {
            return;
        }

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO " . RISEUP_TABLE_TRANSACTIONS .
                " (plugin, action, status, details, source, created_at) VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute(array(
                RISEUP_SLUG,
                RISEUP_ACTION_SNAPSHOT_RESTORE,
                RISEUP_STATUS_SUCCESS,
                json_encode(array(
                    'directory'       => basename($snapshot_dir),
                    'tables_restored' => $tables_restored,
                    'total_rows'      => $total_rows,
                    'duration'        => round($duration, 2),
                    'type'            => 'per_table',
                )),
                gethostname() ?: php_uname('n'),
                gmdate('Y-m-d H:i:s'),
            ));
        } catch (Exception $e) {
            $this->log('WARN', 'Failed to log audit for restore', array('error' => $e->getMessage()));
        }
    }

    /**
     * Log a message.
     *
     * @param string $level   Log level.
     * @param string $message Message.
     * @param array  $context Context data.
     */
    private function log($level, $message, $context = array()) {
        if ($this->logger) {
            $prefix = '[RestoreEngine] ';
            if (method_exists($this->logger, strtolower($level))) {
                $method = strtolower($level);
                $this->logger->$method($prefix . $message, $context);
            } else {
                $this->logger->info($prefix . $message, $context);
            }
        }
    }
}
