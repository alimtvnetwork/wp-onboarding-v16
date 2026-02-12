<?php
/**
 * Riseup Asia Uploader - Snapshot Worker
 *
 * Exports MySQL tables to individual SQLite files using a
 * parallel worker-pool pattern with configurable concurrency.
 * Tables are processed in batches via WP-Cron, allowing the
 * export to survive browser close and avoid request timeouts.
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
 * Manages per-table MySQL → SQLite export with parallel batch
 * processing. Each batch of N tables (worker_pool_size) is
 * scheduled as a single WP-Cron event. Once a batch completes,
 * the next batch is dispatched automatically.
 */
class RiseupSnapshotWorker {

    /** @var RiseupFileLogger */
    private $logger;

    /** @var Riseup_Database */
    private $db;

    /** @var RiseupRootDb */
    private $rootDb;

    /** @var RiseupDependencyAnalyzer */
    private $analyzer;

    /** @var wpdb */
    private $wpdb;

    /** @var int */
    private $batchSize;

    /** @var int Worker pool size (tables per batch). */
    private $poolSize;

    /** @var RiseupSnapshotWorker|null */
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
        if (RiseupBooleanHelpers::is_null(self::$instance) && $logger && $db && $rootDb && $analyzer) {
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
        $this->poolSize  = RISEUP_SNAPSHOT_WORKER_POOL_DEFAULT;
    }

    // =========================================================================
    // PUBLIC API
    // =========================================================================

    /**
     * Set the worker pool size (tables processed per cron batch).
     *
     * @param int $size Pool size (clamped to min/max constants).
     */
    public function setPoolSize($size) {
        $this->poolSize = max(
            RISEUP_SNAPSHOT_WORKER_POOL_MIN,
            min(RISEUP_SNAPSHOT_WORKER_POOL_MAX, (int) $size)
        );
    }

    /**
     * Get the current worker pool size.
     *
     * @return int
     */
    public function getPoolSize() {
        return $this->poolSize;
    }

    /**
     * Execute a full per-table snapshot export.
     *
     * Creates a snapshot directory, builds a-root.db with dependency graph,
     * then creates a snapshot job and schedules the first worker batch via
     * WP-Cron for background processing.
     *
     * @param array $config Snapshot config: title, scope, tables (for custom), settings, pool_size.
     * @return array Result with success status, snapshot_dir, job_id.
     */
    public function execute($config) {
        $start_time = microtime(true);
        $title = $config['title'] ?? ('Snapshot ' . date('Y-m-d H:i'));
        $scope = $config['scope'] ?? 'wordpress';
        $type  = $config['type'] ?? 'full';

        // Apply pool size from config if provided
        if (!empty($config['settings']['worker_pool_size'])) {
            $this->setPoolSize($config['settings']['worker_pool_size']);
        }

        $this->log('INFO', 'Starting per-table snapshot', array(
            'title'     => $title,
            'scope'     => $scope,
            'type'      => $type,
            'pool_size' => $this->poolSize,
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
            $rootPdo = null; // Close for now; batches reopen as needed

            $this->log('INFO', 'Export order determined', array(
                'tables'    => count($seed_order),
                'pool_size' => $this->poolSize,
            ));

            // 5. Create progress records for all tables
            $this->initProgressRecords($seed_order);

            // 6. Create a snapshot job record
            $job_id = $this->createJob($snapshot_dir, $seed_order, $config);

            if (!$job_id) {
                return array('success' => false, 'error' => 'Failed to create snapshot job');
            }

            // 7. Schedule the first worker batch via WP-Cron
            $this->scheduleNextBatch($job_id);

            $duration = microtime(true) - $start_time;

            $this->log('INFO', 'Snapshot job created, first batch scheduled', array(
                'job_id'       => $job_id,
                'directory'    => $dir_name,
                'total_tables' => count($seed_order),
                'pool_size'    => $this->poolSize,
                'setup_time'   => round($duration, 2) . 's',
            ));

            return array(
                'success'      => true,
                'directory'    => $dir_name,
                'path'         => $snapshot_dir,
                'job_id'       => $job_id,
                'total_tables' => count($seed_order),
                'pool_size'    => $this->poolSize,
                'tables'       => 0, // Will be incremented by batches
                'total_rows'   => 0,
                'errors'       => array(),
                'duration'     => $duration,
                'status'       => RISEUP_SNAPSHOT_JOB_STATUS_QUEUED,
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
     * Execute a synchronous full snapshot (blocks until complete).
     *
     * Used when caller needs immediate results (e.g., CLI).
     *
     * @param array $config Snapshot config.
     * @return array Result with success, tables, total_rows, etc.
     */
    public function executeSynchronous($config) {
        $start_time = microtime(true);
        $title = $config['title'] ?? ('Snapshot ' . date('Y-m-d H:i'));
        $scope = $config['scope'] ?? 'wordpress';
        $type  = $config['type'] ?? 'full';

        if (!empty($config['settings']['worker_pool_size'])) {
            $this->setPoolSize($config['settings']['worker_pool_size']);
        }

        $base_dir = $this->getSnapshotsBaseDir();
        $dir_name = date('Y-m-d') . '_' . $type . '_' . sanitize_title($title);
        $snapshot_dir = $base_dir . '/' . $dir_name;

        if (!RiseupPathUtils::ensureDir($snapshot_dir, true)) {
            return array('success' => false, 'error' => 'Failed to create snapshot directory');
        }

        try {
            $root_path = $snapshot_dir . '/a-root.db';
            $rootPdo = $this->rootDb->create($root_path);

            $this->rootDb->populateMetadata($rootPdo, array(
                'title'    => $title,
                'type'     => $type,
                'settings' => $config['settings'] ?? null,
            ));

            $analysis = $this->rootDb->populateDependencies($rootPdo, $scope);
            $seed_order = $analysis['seed_order'];

            $this->initProgressRecords($seed_order);

            // Process tables in pool-sized batches synchronously
            $total_rows = 0;
            $exported_tables = 0;
            $errors = array();
            $batches = array_chunk($seed_order, $this->poolSize);

            foreach ($batches as $batch_index => $batch_tables) {
                $this->log('INFO', sprintf('Processing batch %d/%d (%d tables)',
                    $batch_index + 1, count($batches), count($batch_tables)
                ));

                foreach ($batch_tables as $table) {
                    $this->updateProgress($table, 'running');
                    $result = $this->exportTableToFile($snapshot_dir, $table);

                    if ($result['success']) {
                        $total_rows += $result['rows'];
                        $exported_tables++;

                        $this->rootDb->registerTable(
                            $rootPdo,
                            $table,
                            $result['rows'],
                            $result['filename'],
                            $result['file_size'],
                            $result['checksum']
                        );

                        $this->updateProgress($table, 'complete', $result['rows']);
                    } else {
                        $errors[] = $table . ': ' . $result['error'];
                        $this->updateProgress($table, 'failed', 0, $result['error']);
                    }
                }
            }

            $this->rootDb->updateStats($rootPdo, $exported_tables, $total_rows);
            $rootPdo = null;

            $duration = microtime(true) - $start_time;

            return array(
                'success'    => true,
                'directory'  => $dir_name,
                'path'       => $snapshot_dir,
                'tables'     => $exported_tables,
                'total_rows' => $total_rows,
                'errors'     => $errors,
                'duration'   => $duration,
            );

        } catch (Exception $e) {
            $this->log('ERROR', 'Synchronous snapshot failed', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ));
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    // =========================================================================
    // CRON BATCH PROCESSING
    // =========================================================================

    /**
     * Process a single worker batch (called by WP-Cron).
     *
     * Exports the next pool_size tables for the given job, updates
     * progress, then schedules the next batch or finalizes the job.
     *
     * @param array $args { job_id: int }
     */
    public function processWorkerBatch($args) {
        $job_id = $args['job_id'] ?? 0;
        if (!$job_id) {
            $this->log('ERROR', 'Worker batch called without job_id');
            return;
        }

        $this->log('INFO', 'Processing worker batch', array('job_id' => $job_id));

        $pdo = $this->db->get_pdo();
        if (RiseupBooleanHelpers::is_falsy($pdo)) {
            $this->log('ERROR', 'No database connection for worker batch');
            return;
        }

        try {
            // Load job
            $job = $this->getJob($pdo, $job_id);
            if (!$job) {
                $this->log('ERROR', 'Job not found', array('job_id' => $job_id));
                return;
            }

            // Mark job as processing
            $this->updateJobStatus($pdo, $job_id, RISEUP_SNAPSHOT_JOB_STATUS_PROCESSING);

            $snapshot_dir = $job['snapshot_dir'];
            $all_tables   = json_decode($job['tables_json'], true);
            $pool_size    = (int) $job['pool_size'];
            $batch_index  = (int) $job['current_batch'];

            $batches = array_chunk($all_tables, $pool_size);

            if ($batch_index >= count($batches)) {
                // All batches done — finalize
                $this->finalizeJob($pdo, $job_id, $snapshot_dir);
                return;
            }

            $batch_tables = $batches[$batch_index];

            $this->log('INFO', sprintf('Batch %d/%d: exporting %d tables',
                $batch_index + 1, count($batches), count($batch_tables)
            ));

            // Open a-root.db for registering tables
            $root_path = $snapshot_dir . '/a-root.db';
            $rootPdo = null;
            if (file_exists($root_path)) {
                $rootPdo = new PDO('sqlite:' . $root_path);
                $rootPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }

            $batch_rows = 0;
            $batch_exported = 0;
            $batch_errors = array();

            foreach ($batch_tables as $table) {
                $this->updateProgress($table, 'running');
                $result = $this->exportTableToFile($snapshot_dir, $table);

                if ($result['success']) {
                    $batch_rows += $result['rows'];
                    $batch_exported++;

                    if ($rootPdo) {
                        $this->rootDb->registerTable(
                            $rootPdo,
                            $table,
                            $result['rows'],
                            $result['filename'],
                            $result['file_size'],
                            $result['checksum']
                        );
                    }

                    $this->updateProgress($table, 'complete', $result['rows']);

                    $this->log('INFO', sprintf('Table exported: %s (%d rows, %s)',
                        $table, $result['rows'], $this->formatBytes($result['file_size'])
                    ));
                } else {
                    $batch_errors[] = $table . ': ' . $result['error'];
                    $this->updateProgress($table, 'failed', 0, $result['error']);
                    $this->log('ERROR', 'Table export failed: ' . $table, array('error' => $result['error']));
                }
            }

            if ($rootPdo) {
                $rootPdo = null; // Close
            }

            // Update job progress
            $this->updateJobBatchProgress($pdo, $job_id, $batch_index + 1, $batch_exported, $batch_rows, $batch_errors);

            // Schedule next batch
            $next_batch = $batch_index + 1;
            if ($next_batch < count($batches)) {
                $this->scheduleNextBatch($job_id);
                $this->log('INFO', sprintf('Next batch scheduled (%d/%d)', $next_batch + 1, count($batches)));
            } else {
                // Last batch done — finalize
                $this->finalizeJob($pdo, $job_id, $snapshot_dir);
            }

        } catch (Exception $e) {
            $this->log('ERROR', 'Worker batch failed', array(
                'job_id' => $job_id,
                'error'  => $e->getMessage(),
                'trace'  => $e->getTraceAsString(),
            ));
            $this->updateJobStatus($pdo, $job_id, RISEUP_SNAPSHOT_JOB_STATUS_FAILED, $e->getMessage());
        }
    }

    // =========================================================================
    // JOB MANAGEMENT
    // =========================================================================

    /**
     * Create a snapshot job record in the jobs table.
     *
     * @param string $snapshot_dir Snapshot directory.
     * @param array  $tables       Ordered table list.
     * @param array  $config       Original config.
     * @return int|false Job ID.
     */
    private function createJob($snapshot_dir, $tables, $config) {
        $pdo = $this->db->get_pdo();
        if (RiseupBooleanHelpers::is_falsy($pdo)) return false;

        try {
            // Ensure jobs table exists
            $pdo->exec("CREATE TABLE IF NOT EXISTS " . RISEUP_TABLE_SNAPSHOT_JOBS . " (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                snapshot_dir TEXT NOT NULL,
                tables_json TEXT NOT NULL,
                pool_size INTEGER NOT NULL DEFAULT " . RISEUP_SNAPSHOT_WORKER_POOL_DEFAULT . ",
                current_batch INTEGER NOT NULL DEFAULT 0,
                tables_exported INTEGER NOT NULL DEFAULT 0,
                total_rows INTEGER NOT NULL DEFAULT 0,
                errors_json TEXT DEFAULT '[]',
                status TEXT NOT NULL DEFAULT '" . RISEUP_SNAPSHOT_JOB_STATUS_QUEUED . "',
                config_json TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                completed_at TEXT
            )");

            $now = gmdate('c');
            $stmt = $pdo->prepare("INSERT INTO " . RISEUP_TABLE_SNAPSHOT_JOBS . "
                (snapshot_dir, tables_json, pool_size, status, config_json, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)");

            $stmt->execute(array(
                $snapshot_dir,
                json_encode($tables),
                $this->poolSize,
                RISEUP_SNAPSHOT_JOB_STATUS_QUEUED,
                json_encode($config),
                $now,
                $now,
            ));

            return (int) $pdo->lastInsertId();

        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to create snapshot job', array('error' => $e->getMessage()));
            return false;
        }
    }

    /**
     * Load a job record.
     *
     * @param PDO $pdo    Database connection.
     * @param int $job_id Job ID.
     * @return array|null Job record.
     */
    private function getJob($pdo, $job_id) {
        $stmt = $pdo->prepare("SELECT * FROM " . RISEUP_TABLE_SNAPSHOT_JOBS . " WHERE id = ?");
        $stmt->execute(array($job_id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Update job status.
     *
     * @param PDO         $pdo    Database connection.
     * @param int         $job_id Job ID.
     * @param string      $status New status.
     * @param string|null $error  Error message (if failed).
     */
    private function updateJobStatus($pdo, $job_id, $status, $error = null) {
        $now = gmdate('c');
        $completed = ($status === RISEUP_SNAPSHOT_JOB_STATUS_COMPLETE || $status === RISEUP_SNAPSHOT_JOB_STATUS_FAILED)
            ? $now : null;

        $stmt = $pdo->prepare("UPDATE " . RISEUP_TABLE_SNAPSHOT_JOBS . "
            SET status = ?, updated_at = ?, completed_at = COALESCE(?, completed_at)
            WHERE id = ?");
        $stmt->execute(array($status, $now, $completed, $job_id));

        if ($error) {
            $job = $this->getJob($pdo, $job_id);
            $errors = json_decode($job['errors_json'] ?? '[]', true);
            $errors[] = $error;
            $stmt2 = $pdo->prepare("UPDATE " . RISEUP_TABLE_SNAPSHOT_JOBS . " SET errors_json = ? WHERE id = ?");
            $stmt2->execute(array(json_encode($errors), $job_id));
        }
    }

    /**
     * Update job after a batch completes.
     *
     * @param PDO    $pdo            Database connection.
     * @param int    $job_id         Job ID.
     * @param int    $next_batch     Next batch index.
     * @param int    $batch_exported Tables exported in this batch.
     * @param int    $batch_rows     Rows exported in this batch.
     * @param array  $batch_errors   Errors from this batch.
     */
    private function updateJobBatchProgress($pdo, $job_id, $next_batch, $batch_exported, $batch_rows, $batch_errors) {
        $now = gmdate('c');

        // Accumulate
        $job = $this->getJob($pdo, $job_id);
        $existing_errors = json_decode($job['errors_json'] ?? '[]', true);
        $all_errors = array_merge($existing_errors, $batch_errors);

        $stmt = $pdo->prepare("UPDATE " . RISEUP_TABLE_SNAPSHOT_JOBS . "
            SET current_batch = ?,
                tables_exported = tables_exported + ?,
                total_rows = total_rows + ?,
                errors_json = ?,
                updated_at = ?
            WHERE id = ?");

        $stmt->execute(array(
            $next_batch,
            $batch_exported,
            $batch_rows,
            json_encode($all_errors),
            $now,
            $job_id,
        ));
    }

    /**
     * Finalize a completed job: update a-root.db stats and mark complete.
     *
     * @param PDO    $pdo          Database connection.
     * @param int    $job_id       Job ID.
     * @param string $snapshot_dir Snapshot directory.
     */
    private function finalizeJob($pdo, $job_id, $snapshot_dir) {
        $job = $this->getJob($pdo, $job_id);

        // Update a-root.db final stats
        $root_path = $snapshot_dir . '/a-root.db';
        if (file_exists($root_path)) {
            try {
                $rootPdo = new PDO('sqlite:' . $root_path);
                $rootPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->rootDb->updateStats(
                    $rootPdo,
                    (int) $job['tables_exported'],
                    (int) $job['total_rows']
                );
                $rootPdo = null;
            } catch (Exception $e) {
                $this->log('WARN', 'Failed to finalize a-root.db stats', array('error' => $e->getMessage()));
            }
        }

        $this->updateJobStatus($pdo, $job_id, RISEUP_SNAPSHOT_JOB_STATUS_COMPLETE);

        $errors = json_decode($job['errors_json'] ?? '[]', true);
        $this->log('INFO', 'Snapshot job complete', array(
            'job_id'          => $job_id,
            'tables_exported' => $job['tables_exported'],
            'total_rows'      => $job['total_rows'],
            'errors'          => count($errors),
        ));
    }

    /**
     * Schedule the next worker batch via WP-Cron.
     *
     * @param int $job_id Job ID.
     */
    private function scheduleNextBatch($job_id) {
        // Schedule 5 seconds from now to allow current request to finish
        wp_schedule_single_event(
            time() + 5,
            RISEUP_CRON_SNAPSHOT_WORKER_BATCH,
            array(array('job_id' => $job_id))
        );
    }

    /**
     * Get the current progress of a snapshot job.
     *
     * @param int $job_id Job ID.
     * @return array|null Progress info or null.
     */
    public function getJobProgress($job_id) {
        $pdo = $this->db->get_pdo();
        if (RiseupBooleanHelpers::is_falsy($pdo)) return null;

        $job = $this->getJob($pdo, $job_id);
        if (!$job) return null;

        $all_tables = json_decode($job['tables_json'], true);
        $total_tables = count($all_tables);
        $pool_size = (int) $job['pool_size'];
        $total_batches = (int) ceil($total_tables / $pool_size);

        // Get per-table progress
        $table_progress = array();
        try {
            $stmt = $pdo->prepare("SELECT table_name, status, rows_total, rows_exported, error_message
                FROM " . RISEUP_TABLE_SNAPSHOT_PROGRESS . " WHERE snapshot_id = 0");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $table_progress[] = $r;
            }
        } catch (Exception $e) {
            // Non-fatal
        }

        return array(
            'job_id'          => (int) $job['id'],
            'status'          => $job['status'],
            'total_tables'    => $total_tables,
            'tables_exported' => (int) $job['tables_exported'],
            'total_rows'      => (int) $job['total_rows'],
            'pool_size'       => $pool_size,
            'total_batches'   => $total_batches,
            'current_batch'   => (int) $job['current_batch'],
            'errors'          => json_decode($job['errors_json'] ?? '[]', true),
            'created_at'      => $job['created_at'],
            'updated_at'      => $job['updated_at'],
            'completed_at'    => $job['completed_at'],
            'table_progress'  => $table_progress,
            'percent'         => $total_tables > 0
                ? round(((int) $job['tables_exported'] / $total_tables) * 100, 1)
                : 0,
        );
    }

    // =========================================================================
    // TABLE EXPORT (unchanged core logic)
    // =========================================================================

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
            $sqlite = new PDO('sqlite:' . $filepath);
            $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $sqlite->exec('PRAGMA journal_mode = WAL');
            $sqlite->exec('PRAGMA synchronous = OFF');

            $create_sql = $this->getCreateTableSql($table);
            if (RiseupBooleanHelpers::is_falsy($create_sql)) {
                throw new Exception('Failed to get table structure for ' . $table);
            }

            $sqlite_create = $this->convertCreateStatement($create_sql, $table);
            $sqlite->exec($sqlite_create);

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

            $columns = $this->wpdb->get_results("DESCRIBE `{$table}`", ARRAY_A);
            $column_names = array_column($columns, 'Field');
            $placeholders = implode(', ', array_fill(0, count($column_names), '?'));
            $column_list = implode(', ', array_map(function($c) { return "`{$c}`"; }, $column_names));

            $insert_sql = "INSERT INTO `{$table}` ({$column_list}) VALUES ({$placeholders})";
            $stmt = $sqlite->prepare($insert_sql);

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
            $sqlite = null;

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

    // =========================================================================
    // SQL CONVERSION
    // =========================================================================

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

        // Remove KEY/INDEX definitions
        $sql = preg_replace('/,\s*KEY\s+[^,]+(?=,|\))/i', '', $sql);
        $sql = preg_replace('/,\s*UNIQUE\s+KEY\s+[^,]+(?=,|\))/i', '', $sql);
        $sql = preg_replace('/,\s*FULLTEXT\s+KEY\s+[^,]+(?=,|\))/i', '', $sql);
        $sql = preg_replace('/,\s*SPATIAL\s+KEY\s+[^,]+(?=,|\))/i', '', $sql);

        // Remove extra commas before closing paren
        $sql = preg_replace('/,\s*\)/', ')', $sql);

        return $sql;
    }

    // =========================================================================
    // PROGRESS TRACKING
    // =========================================================================

    /**
     * Initialize progress records for all tables.
     *
     * @param array $tables Table names.
     */
    private function initProgressRecords($tables) {
        $pdo = $this->db->get_pdo();
        if (RiseupBooleanHelpers::is_falsy($pdo)) return;

        try {
            $stmt = $pdo->prepare("INSERT OR REPLACE INTO " . RISEUP_TABLE_SNAPSHOT_PROGRESS . "
                (snapshot_id, table_name, status, rows_total, rows_exported, started_at)
                VALUES (0, ?, 'pending', 0, 0, ?)");

            $now = gmdate('c');
            foreach ($tables as $table) {
                $count = (int) $this->wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
                $stmt->execute(array($table, $now));

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
        if (RiseupBooleanHelpers::is_falsy($pdo)) return;

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

    // =========================================================================
    // UTILITIES
    // =========================================================================

    /**
     * Get the base snapshots directory.
     *
     * @return string Base snapshots directory path.
     */
    private function getSnapshotsBaseDir() {
        $base = RiseupPathUtils::getSnapshotsDir();
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
