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
        $this->batchSize = SNAPSHOT_BATCH_SIZE;
    }

    /**
     * Execute a per-table restore from a snapshot directory.
     *
     * @param string $snapshot_dir Path to the snapshot directory containing a-root.db.
     * @param array  $options      Options: mode, tables, create_backup, confirm, apply_incrementals.
     * @return array Result with success, tables_restored, total_rows, duration, etc.
     */
    public function execute($snapshot_dir, $options = array()) {
        $prereqError = $this->validateRestorePrereqs($snapshot_dir, $options);
        if ($prereqError) {
            return $prereqError;
        }

        $start_time = microtime(true);
        $mode = $options['mode'] ?? 'full';
        $apply_incrementals = $options['apply_incrementals'] ?? true;

        $this->log('INFO', 'Starting per-table restore', array(
            'directory' => basename($snapshot_dir),
            'mode'      => $mode,
        ));

        try {
            $rootPdo = new PDO('sqlite:' . $snapshot_dir . '/a-root.db');
            $rootPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $meta = $this->getSnapshotMeta($rootPdo);
            $restore_order = $this->prepareRestoreOrder($rootPdo, $options);

            if (!$restore_order['success']) {
                $rootPdo = null;
                return $restore_order;
            }

            $ordered_tables = $restore_order['tables'];
            $table_inventory = $restore_order['inventory'];

            $backup_id = $this->createSafetyBackup($options);

            $this->wpdb->query("SET FOREIGN_KEY_CHECKS = 0");

            $master_result = $this->restoreMasterTables($ordered_tables, $table_inventory, $snapshot_dir, $options);
            $inc_result = $this->applyIncrementalsPhase($rootPdo, $snapshot_dir, $ordered_tables, $mode, $apply_incrementals);

            $this->wpdb->query("SET FOREIGN_KEY_CHECKS = 1");
            $rootPdo = null;

            $duration = microtime(true) - $start_time;
            $total_rows = $master_result['total_rows'] + $inc_result['total_rows'];
            $errors = array_merge($master_result['errors'], $inc_result['errors']);

            $this->logAuditRestore($snapshot_dir, $master_result['tables_restored'], $total_rows, $duration);

            return $this->buildRestoreResult(
                $master_result, $inc_result, $backup_id, $errors, $duration, $meta, $total_rows
            );

        } catch (Exception $e) {
            $this->wpdb->query("SET FOREIGN_KEY_CHECKS = 1");
            $this->log('ERROR', 'Restore engine failed', array(
                'error' => $e->getMessage(), 'trace' => $e->getTraceAsString(),
            ));
            return array('success' => false, 'error' => $e->getMessage(), 'phase' => 'restore');
        }
    }

    /**
     * Validate restore prerequisites (confirmation and root db existence).
     *
     * @param string $snapshotDir Snapshot directory.
     * @param array  $options     Options.
     * @return array|null Error result or null if valid.
     */
    private function validateRestorePrereqs(string $snapshotDir, array $options): ?array {
        if (empty($options['confirm']) || $options['confirm'] !== true) {
            return array(
                'success' => false,
                'error'   => 'Restore requires explicit confirmation (confirm=true)',
                'code'    => ERR_RESTORE_NO_CONFIRM,
            );
        }

        $root_path = $snapshotDir . '/a-root.db';
        if (RiseupBooleanHelpers::is_file_missing($root_path)) {
            return array(
                'success' => false,
                'error'   => 'Snapshot a-root.db not found at: ' . basename($snapshotDir),
            );
        }

        return null;
    }

    /**
     * Prepare the restore order from inventory and dependency graph.
     *
     * @param PDO   $rootPdo Root DB connection.
     * @param array $options Restore options.
     * @return array Result with success, tables, inventory.
     */
    private function prepareRestoreOrder(PDO $rootPdo, array $options): array {
        $mode = $options['mode'] ?? 'full';
        $selected_tables = $options['tables'] ?? array();

        $table_inventory = $this->getTableInventory($rootPdo);
        $restore_order = $this->getRestoreOrder($rootPdo, $table_inventory);

        if ($mode === 'selective' && !empty($selected_tables)) {
            $restore_order = array_values(array_filter($restore_order, function($t) use ($selected_tables) {
                return in_array($t, $selected_tables);
            }));

            if (empty($restore_order)) {
                return array('success' => false, 'error' => 'None of the selected tables exist in the snapshot');
            }
        }

        $this->log('INFO', 'Restore order determined', array(
            'tables' => count($restore_order),
            'order'  => array_slice($restore_order, 0, 10),
        ));

        return array('success' => true, 'tables' => $restore_order, 'inventory' => $table_inventory);
    }

    /**
     * Create a pre-restore safety backup if requested.
     *
     * @param array $options Restore options.
     * @return int|null Backup ID or null.
     */
    private function createSafetyBackup(array $options): ?int {
        $create_backup = $options['create_backup'] ?? true;

        if (!$create_backup || !$this->orchestrator) {
            return null;
        }

        $this->log('INFO', 'Creating pre-restore safety backup');
        $result = $this->orchestrator->executeFullBackup(array(
            'title'           => 'Pre-Restore Safety Backup ' . date('Y-m-d H:i'),
            'compression'     => false,
            'include_plugins' => false,
        ));

        if ($result['success']) {
            $this->log('INFO', 'Pre-restore backup complete', array('backup_id' => $result['snapshot_id'] ?? null));
            return $result['snapshot_id'] ?? null;
        }

        $this->log('WARN', 'Pre-restore backup failed (continuing)', array('error' => $result['error'] ?? 'Unknown'));

        if (!empty($options['require_backup'])) {
            throw new Exception('Pre-restore backup failed: ' . ($result['error'] ?? 'Unknown'));
        }

        return null;
    }

    /**
     * Restore master tables from SQLite files.
     *
     * @param array  $restoreOrder   Tables in restore order.
     * @param array  $tableInventory Table inventory map.
     * @param string $snapshotDir    Snapshot directory.
     * @param array  $options        Restore options.
     * @return array Result with tables_restored, total_rows, errors.
     */
    private function restoreMasterTables(array $restoreOrder, array $tableInventory, string $snapshotDir, array $options): array {
        $tables_restored = 0;
        $total_rows = 0;
        $errors = array();

        foreach ($restoreOrder as $table) {
            $result = $this->restoreSingleMasterTable($table, $tableInventory, $snapshotDir);

            if ($result === null) {
                $errors[] = $result['error'] ?? $table . ': skipped';
                continue;
            }

            if ($result['success']) {
                $tables_restored++;
                $total_rows += $result['rows'];
                $this->log('INFO', sprintf('Restored: %s (%d rows)', $table, $result['rows']));
                continue;
            }

            $errors[] = $table . ': ' . $result['error'];
            $this->log('ERROR', 'Restore failed: ' . $table, array('error' => $result['error']));

            if (!empty($options['strict'])) {
                throw new Exception('Strict mode: table restore failed for ' . $table);
            }
        }

        return array('tables_restored' => $tables_restored, 'total_rows' => $total_rows, 'errors' => $errors);
    }

    /**
     * Restore a single master table.
     *
     * @param string $table          Table name.
     * @param array  $tableInventory Table inventory.
     * @param string $snapshotDir    Snapshot directory.
     * @return array|null Result or null if missing.
     */
    private function restoreSingleMasterTable(string $table, array $tableInventory, string $snapshotDir): ?array {
        $table_info = $tableInventory[$table] ?? null;
        if (!$table_info) {
            return array('success' => false, 'error' => $table . ': not found in inventory', 'rows' => 0);
        }

        $sqlite_path = $snapshotDir . '/' . $table_info['sqlite_file'];
        if (RiseupBooleanHelpers::is_file_missing($sqlite_path)) {
            $this->log('ERROR', 'SQLite file missing for table', array('table' => $table, 'file' => $table_info['sqlite_file']));
            return array('success' => false, 'error' => 'SQLite file missing (' . $table_info['sqlite_file'] . ')', 'rows' => 0);
        }

        return $this->restoreTableFromFile($sqlite_path, $table, 'truncate');
    }

    /**
     * Apply incrementals phase based on mode.
     *
     * @param PDO    $rootPdo         Root DB connection.
     * @param string $snapshotDir     Snapshot directory.
     * @param array  $restoreOrder    Restore order.
     * @param string $mode            Restore mode.
     * @param bool   $applyIncrementals Whether to apply incrementals.
     * @return array Result with applied, total_rows, errors.
     */
    private function applyIncrementalsPhase(PDO $rootPdo, string $snapshotDir, array $restoreOrder, string $mode, bool $applyIncrementals): array {
        $shouldApply = ($applyIncrementals && $mode !== 'incremental') || $mode === 'incremental';

        if (!$shouldApply) {
            return array('applied' => 0, 'total_rows' => 0, 'errors' => array());
        }

        return $this->applyIncrementals($rootPdo, $snapshotDir, $restoreOrder);
    }

    /**
     * Build the final restore result.
     *
     * @param array    $masterResult Master restore result.
     * @param array    $incResult    Incremental result.
     * @param int|null $backupId     Pre-restore backup ID.
     * @param array    $errors       All errors.
     * @param float    $duration     Duration.
     * @param array    $meta         Snapshot metadata.
     * @param int      $totalRows    Total rows restored.
     * @return array Final result.
     */
    private function buildRestoreResult(array $masterResult, array $incResult, ?int $backupId, array $errors, float $duration, array $meta, int $totalRows): array {
        $this->log('INFO', 'Per-table restore complete', array(
            'tables_restored'      => $masterResult['tables_restored'],
            'total_rows'           => $totalRows,
            'incrementals_applied' => $incResult['applied'],
            'errors'               => count($errors),
            'backup_id'            => $backupId,
            'duration'             => round($duration, 2) . 's',
        ));

        return array(
            'success'              => true,
            'tables_restored'      => $masterResult['tables_restored'],
            'total_rows'           => $totalRows,
            'incrementals_applied' => $incResult['applied'],
            'backup_id'            => $backupId,
            'errors'               => $errors,
            'duration'             => $duration,
            'meta'                 => $meta,
        );
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
            $validated = $this->openAndValidateSqliteTable($sqlite_path, $table);
            if (!$validated['success']) {
                return $validated;
            }

            return $this->batchInsertToMysql(
                $validated['sqlite'], $table, $validated['columns'],
                $strategy, $validated['row_count']
            );

        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage(), 'rows' => 0);
        }
    }

    /**
     * Open a SQLite file and validate the table exists with columns.
     *
     * @param string $sqlitePath Path to SQLite file.
     * @param string $table      Table name.
     * @return array Result with sqlite, columns, row_count, or error.
     */
    private function openAndValidateSqliteTable(string $sqlitePath, string $table): array {
        $sqlite = new PDO('sqlite:' . $sqlitePath);
        $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $check = $sqlite->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name='" .
            str_replace("'", "''", $table) . "'"
        );
        if (!$check->fetch()) {
            $sqlite = null;
            return array('success' => false, 'error' => 'Table not found in SQLite file', 'rows' => 0);
        }

        $columns = $sqlite->query("PRAGMA table_info('" . str_replace("'", "''", $table) . "')")
            ->fetchAll(PDO::FETCH_ASSOC);
        $column_names = array_column($columns, 'name');

        if (empty($column_names)) {
            $sqlite = null;
            return array('success' => false, 'error' => 'No columns found in SQLite table', 'rows' => 0);
        }

        $row_count = (int) $sqlite->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();

        return array(
            'success'   => true,
            'sqlite'    => $sqlite,
            'columns'   => $column_names,
            'row_count' => $row_count,
        );
    }

    /**
     * Batch insert rows from SQLite into MySQL.
     *
     * @param PDO    $sqlite   SQLite connection.
     * @param string $table    Table name.
     * @param array  $columns  Column names.
     * @param string $strategy Insert strategy (truncate or merge).
     * @param int    $rowCount Total row count.
     * @return array Result with success, rows.
     */
    private function batchInsertToMysql(PDO $sqlite, string $table, array $columns, string $strategy, int $rowCount): array {
        $this->wpdb->query("START TRANSACTION");

        try {
            if ($strategy === 'truncate') {
                $this->wpdb->query("TRUNCATE TABLE `{$table}`");
            }

            $columns_sql = '`' . implode('`, `', $columns) . '`';
            $placeholders = implode(', ', array_fill(0, count($columns), '%s'));
            $insert_verb = ($strategy === 'merge') ? 'REPLACE' : 'INSERT';
            $sql_template = "{$insert_verb} INTO `{$table}` ({$columns_sql}) VALUES ({$placeholders})";

            $total_rows = 0;
            $offset = 0;

            while ($offset < $rowCount) {
                $rows = $sqlite->query("SELECT * FROM `{$table}` LIMIT {$this->batchSize} OFFSET {$offset}")
                    ->fetchAll(PDO::FETCH_ASSOC);

                foreach ($rows as $row) {
                    $values = array();
                    foreach ($columns as $col) {
                        $values[] = isset($row[$col]) ? $row[$col] : null;
                    }
                    $this->wpdb->query($this->wpdb->prepare($sql_template, $values));
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
            $result = $this->applySingleIncremental($inc, $snapshot_dir, $restore_order);
            $total_rows += $result['rows'];
            $applied++;
            if (!empty($result['errors'])) {
                $errors = array_merge($errors, $result['errors']);
            }
        }

        return array('applied' => $applied, 'total_rows' => $total_rows, 'errors' => $errors);
    }

    /**
     * Apply a single incremental backup.
     *
     * @param array  $inc          Incremental record.
     * @param string $snapshotDir  Snapshot directory.
     * @param array  $restoreOrder Restore order.
     * @return array Result with rows, errors.
     */
    private function applySingleIncremental(array $inc, string $snapshotDir, array $restoreOrder): array {
        $inc_dir = $snapshotDir . '/' . rtrim($inc['relative_path'], '/');
        if (RiseupBooleanHelpers::is_dir_missing($inc_dir)) {
            $this->log('WARN', 'Incremental directory missing', array('folder' => $inc['folder_name']));
            return array('rows' => 0, 'errors' => array('Incremental directory missing: ' . $inc['folder_name']));
        }

        $this->log('INFO', 'Applying incremental: ' . $inc['folder_name']);

        $sqlite_files = glob($inc_dir . '/*.sqlite');
        $inc_rows = 0;
        $errors = array();

        foreach ($sqlite_files as $sqlite_file) {
            $table = basename($sqlite_file, '.sqlite');
            if (!in_array($table, $restoreOrder)) {
                continue;
            }

            $result = $this->restoreTableFromFile($sqlite_file, $table, 'merge');
            if ($result['success']) {
                $inc_rows += $result['rows'];
                $this->log('INFO', sprintf('Incremental %s: %s (+%d rows)', $inc['folder_name'], $table, $result['rows']));
            } else {
                $errors[] = sprintf('Incremental %s/%s: %s', $inc['folder_name'], $table, $result['error']);
            }
        }

        return array('rows' => $inc_rows, 'errors' => $errors);
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
     * @param PDO   $rootPdo        a-root.db PDO.
     * @param array $table_inventory Table inventory map.
     * @return array Ordered list of table names.
     */
    private function getRestoreOrder($rootPdo, $table_inventory) {
        $all_tables = array_keys($table_inventory);

        $deps = $rootPdo->query(
            "SELECT parent_table, child_table FROM table_dependencies"
        )->fetchAll(PDO::FETCH_ASSOC);

        if (empty($deps)) {
            sort($all_tables);
            return $all_tables;
        }

        $graph = $this->buildDependencyGraph($all_tables, $deps);

        return $this->topologicalSort($graph['adjacency'], $graph['in_degree'], $all_tables);
    }

    /**
     * Build an adjacency list and in-degree map from dependencies.
     *
     * @param array $allTables All table names.
     * @param array $deps      Dependency records.
     * @return array Graph with adjacency and in_degree.
     */
    private function buildDependencyGraph(array $allTables, array $deps): array {
        $graph = array();
        $in_degree = array();

        foreach ($allTables as $t) {
            $graph[$t] = array();
            $in_degree[$t] = 0;
        }

        foreach ($deps as $dep) {
            $parent = $dep['parent_table'];
            $child = $dep['child_table'];

            if (!isset($graph[$parent]) || !isset($graph[$child])) {
                continue;
            }

            $graph[$parent][] = $child;
            $in_degree[$child]++;
        }

        return array('adjacency' => $graph, 'in_degree' => $in_degree);
    }

    /**
     * Perform Kahn's topological sort.
     *
     * @param array $graph    Adjacency list.
     * @param array $inDegree In-degree map.
     * @param array $allTables All table names.
     * @return array Sorted table names.
     */
    private function topologicalSort(array $graph, array $inDegree, array $allTables): array {
        $queue = array();
        foreach ($inDegree as $table => $degree) {
            if ($degree === 0) {
                $queue[] = $table;
            }
        }

        $sorted = array();
        while (!empty($queue)) {
            sort($queue);
            $table = array_shift($queue);
            $sorted[] = $table;

            foreach ($graph[$table] as $child) {
                $inDegree[$child]--;
                if ($inDegree[$child] === 0) {
                    $queue[] = $child;
                }
            }
        }

        foreach ($allTables as $t) {
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
                "INSERT INTO " . TABLE_TRANSACTIONS .
                " (plugin, action, status, details, source, created_at) VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute(array(
                PLUGIN_SLUG,
                ACTION_SNAPSHOT_RESTORE,
                STATUS_SUCCESS,
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
