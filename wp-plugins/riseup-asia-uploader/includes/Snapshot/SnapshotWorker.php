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

require_once dirname(__FILE__) . '/SqliteSchemaConverter.php';

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

    /** @var RiseupDatabase */
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
     * @param RiseupDatabase|null          $db       Plugin database.
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
     * @param RiseupDatabase          $db       Plugin database.
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
        $this->batchSize = SNAPSHOT_BATCH_SIZE;
        $this->poolSize  = SNAPSHOT_WORKER_POOL_DEFAULT;
    }

    /**
     * Set the worker pool size (tables processed per cron batch).
     *
     * @param int $size Pool size (clamped to min/max constants).
     */
    public function setPoolSize($size) {
        $this->poolSize = max(
            SNAPSHOT_WORKER_POOL_MIN,
            min(SNAPSHOT_WORKER_POOL_MAX, (int) $size)
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

    // =========================================================================
    // PUBLIC API
    // =========================================================================

    /**
     * Execute a full per-table snapshot export (async via WP-Cron).
     *
     * @param array $config Snapshot config: title, scope, tables (for custom), settings, pool_size.
     * @return array Result with success status, snapshot_dir, job_id.
     */
    public function execute($config) {
        $start_time = microtime(true);

        $prepared = $this->prepareSnapshotDir($config);
        if (!$prepared['success']) {
            return $prepared;
        }

        try {
            $rootPdo = $this->initRootDb($prepared['snapshot_dir'], $config);
            $seed_order = $this->populateAndGetSeedOrder($rootPdo, $config);
            $rootPdo = null;

            $this->initProgressRecords($seed_order);
            $job_id = $this->createJob($prepared['snapshot_dir'], $seed_order, $config);

            if (!$job_id) {
                return array('success' => false, 'error' => 'Failed to create snapshot job');
            }

            $this->scheduleNextBatch($job_id);

            return $this->buildAsyncSnapshotResult($prepared, $seed_order, $job_id, $start_time);

        } catch (Exception $e) {
            $this->log('ERROR', 'Per-table snapshot failed', array(
                'error' => $e->getMessage(), 'trace' => $e->getTraceAsString(),
            ));
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * Execute a synchronous full snapshot (blocks until complete).
     *
     * @param array $config Snapshot config.
     * @return array Result with success, tables, total_rows, etc.
     */
    public function executeSynchronous($config) {
        $start_time = microtime(true);

        $prepared = $this->prepareSnapshotDir($config);
        if (!$prepared['success']) {
            return $prepared;
        }

        try {
            $rootPdo = $this->initRootDb($prepared['snapshot_dir'], $config);
            $seed_order = $this->populateAndGetSeedOrder($rootPdo, $config);

            $this->initProgressRecords($seed_order);

            $export = $this->exportBatchesSynchronously($seed_order, $prepared['snapshot_dir'], $rootPdo);

            $this->rootDb->updateStats($rootPdo, $export['exported_tables'], $export['total_rows']);
            $rootPdo = null;

            return $this->buildSyncSnapshotResult($prepared, $export, $start_time);

        } catch (Exception $e) {
            $this->log('ERROR', 'Synchronous snapshot failed', array(
                'error' => $e->getMessage(), 'trace' => $e->getTraceAsString(),
            ));
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * Prepare the snapshot directory and config.
     *
     * @param array $config Snapshot config.
     * @return array Prepared context with success, snapshot_dir, dir_name, title, scope, type.
     */
    private function prepareSnapshotDir(array $config): array {
        $title = $config['title'] ?? ('Snapshot ' . date('Y-m-d H:i'));
        $scope = $config['scope'] ?? 'wordpress';
        $type  = $config['type'] ?? 'full';

        if (!empty($config['settings']['worker_pool_size'])) {
            $this->setPoolSize($config['settings']['worker_pool_size']);
        }

        $this->log('INFO', 'Starting per-table snapshot', array(
            'title' => $title, 'scope' => $scope, 'type' => $type, 'pool_size' => $this->poolSize,
        ));

        $base_dir = $this->getSnapshotsBaseDir();
        $dir_name = date('Y-m-d') . '_' . $type . '_' . sanitize_title($title);
        $snapshot_dir = $base_dir . '/' . $dir_name;

        if (!RiseupPathUtils::ensure_dir($snapshot_dir, true)) {
            return array('success' => false, 'error' => 'Failed to create snapshot directory');
        }

        return array(
            'success'      => true,
            'snapshot_dir' => $snapshot_dir,
            'dir_name'     => $dir_name,
            'title'        => $title,
            'scope'        => $scope,
            'type'         => $type,
        );
    }

    /**
     * Initialize a-root.db with metadata.
     *
     * @param string $snapshotDir Snapshot directory.
     * @param array  $config      Config.
     * @return PDO Root PDO connection.
     */
    private function initRootDb(string $snapshotDir, array $config): PDO {
        $root_path = $snapshotDir . '/a-root.db';
        $rootPdo = $this->rootDb->create($root_path);

        $this->rootDb->populateMetadata($rootPdo, array(
            'title'    => $config['title'] ?? 'Snapshot',
            'type'     => $config['type'] ?? 'full',
            'settings' => $config['settings'] ?? null,
        ));

        return $rootPdo;
    }

    /**
     * Populate dependencies and return seed order.
     *
     * @param PDO   $rootPdo Root PDO.
     * @param array $config  Config.
     * @return array Seed order.
     */
    private function populateAndGetSeedOrder(PDO $rootPdo, array $config): array {
        $scope = $config['scope'] ?? 'wordpress';
        $analysis = $this->rootDb->populateDependencies($rootPdo, $scope);

        $this->log('INFO', 'Export order determined', array(
            'tables' => count($analysis['seed_order']), 'pool_size' => $this->poolSize,
        ));

        return $analysis['seed_order'];
    }

    /**
     * Export all tables in pool-sized batches synchronously.
     *
     * @param array  $seedOrder   Ordered table list.
     * @param string $snapshotDir Snapshot directory.
     * @param PDO    $rootPdo     Root DB connection.
     * @return array Export results.
     */
    private function exportBatchesSynchronously(array $seedOrder, string $snapshotDir, PDO $rootPdo): array {
        $total_rows = 0;
        $exported_tables = 0;
        $errors = array();
        $batches = array_chunk($seedOrder, $this->poolSize);

        foreach ($batches as $batch_index => $batch_tables) {
            $this->log('INFO', sprintf('Processing batch %d/%d (%d tables)',
                $batch_index + 1, count($batches), count($batch_tables)
            ));

            $result = $this->exportBatchTables($batch_tables, $snapshotDir, $rootPdo);
            $total_rows += $result['rows'];
            $exported_tables += $result['exported'];
            $errors = array_merge($errors, $result['errors']);
        }

        return array('total_rows' => $total_rows, 'exported_tables' => $exported_tables, 'errors' => $errors);
    }

    /**
     * Export a batch of tables to SQLite files.
     *
     * @param array    $tables      Table names.
     * @param string   $snapshotDir Snapshot directory.
     * @param PDO|null $rootPdo     Root DB connection for registration.
     * @return array Result with rows, exported, errors.
     */
    private function exportBatchTables(array $tables, string $snapshotDir, ?PDO $rootPdo): array {
        $rows = 0;
        $exported = 0;
        $errors = array();

        foreach ($tables as $table) {
            $this->updateProgress($table, 'running');
            $result = $this->exportTableToFile($snapshotDir, $table);

            if ($result['success']) {
                $rows += $result['rows'];
                $exported++;
                if ($rootPdo) {
                    $this->rootDb->registerTable(
                        $rootPdo, $table, $result['rows'],
                        $result['filename'], $result['file_size'], $result['checksum']
                    );
                }
                $this->updateProgress($table, 'complete', $result['rows']);
            } else {
                $errors[] = $table . ': ' . $result['error'];
                $this->updateProgress($table, 'failed', 0, $result['error']);
            }
        }

        return array('rows' => $rows, 'exported' => $exported, 'errors' => $errors);
    }

    /**
     * Build the async snapshot result.
     *
     * @param array $prepared  Prepared context.
     * @param array $seedOrder Seed order.
     * @param int   $jobId     Job ID.
     * @param float $startTime Start time.
     * @return array Result.
     */
    private function buildAsyncSnapshotResult(array $prepared, array $seedOrder, int $jobId, float $startTime): array {
        $duration = microtime(true) - $startTime;

        $this->log('INFO', 'Snapshot job created, first batch scheduled', array(
            'job_id'       => $jobId,
            'directory'    => $prepared['dir_name'],
            'total_tables' => count($seedOrder),
            'pool_size'    => $this->poolSize,
            'setup_time'   => round($duration, 2) . 's',
        ));

        return array(
            'success'      => true,
            'directory'    => $prepared['dir_name'],
            'path'         => $prepared['snapshot_dir'],
            'job_id'       => $jobId,
            'total_tables' => count($seedOrder),
            'pool_size'    => $this->poolSize,
            'tables'       => 0,
            'total_rows'   => 0,
            'errors'       => array(),
            'duration'     => $duration,
            'status'       => SNAPSHOT_JOB_STATUS_QUEUED,
        );
    }

    /**
     * Build the sync snapshot result.
     *
     * @param array $prepared  Prepared context.
     * @param array $export    Export results.
     * @param float $startTime Start time.
     * @return array Result.
     */
    private function buildSyncSnapshotResult(array $prepared, array $export, float $startTime): array {
        return array(
            'success'    => true,
            'directory'  => $prepared['dir_name'],
            'path'       => $prepared['snapshot_dir'],
            'tables'     => $export['exported_tables'],
            'total_rows' => $export['total_rows'],
            'errors'     => $export['errors'],
            'duration'   => microtime(true) - $startTime,
        );
    }

    // =========================================================================
    // CRON BATCH PROCESSING
    // =========================================================================

    /**
     * Process a single worker batch (called by WP-Cron).
     *
     * @param array $args { job_id: int }
     */
    public function processWorkerBatch($args) {
        $job_id = $args['job_id'] ?? 0;
        if (!$job_id) {
            $this->log('ERROR', 'Worker batch called without job_id');
            return;
        }

        $pdo = $this->db->get_pdo();
        if (!$pdo) {
            $this->log('ERROR', 'No database connection for worker batch');
            return;
        }

        try {
            $job = $this->getJob($pdo, $job_id);
            if (!$job) {
                $this->log('ERROR', 'Job not found', array('job_id' => $job_id));
                return;
            }

            $this->updateJobStatus($pdo, $job_id, SNAPSHOT_JOB_STATUS_PROCESSING);
            $this->processJobBatch($pdo, $job_id, $job);

        } catch (Exception $e) {
            $this->log('ERROR', 'Worker batch failed', array(
                'job_id' => $job_id, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString(),
            ));
            $this->updateJobStatus($pdo, $job_id, SNAPSHOT_JOB_STATUS_FAILED, $e->getMessage());
        }
    }

    /**
     * Process a single batch within a job.
     *
     * @param PDO   $pdo   Database connection.
     * @param int   $jobId Job ID.
     * @param array $job   Job record.
     */
    private function processJobBatch(PDO $pdo, int $jobId, array $job): void {
        $all_tables  = json_decode($job['tables_json'], true);
        $pool_size   = (int) $job['pool_size'];
        $batch_index = (int) $job['current_batch'];
        $batches     = array_chunk($all_tables, $pool_size);

        if ($batch_index >= count($batches)) {
            $this->finalizeJob($pdo, $jobId, $job['snapshot_dir']);
            return;
        }

        $this->log('INFO', sprintf('Batch %d/%d: exporting %d tables',
            $batch_index + 1, count($batches), count($batches[$batch_index])
        ));

        $rootPdo = $this->openRootDbForBatch($job['snapshot_dir']);
        $result = $this->exportBatchTables($batches[$batch_index], $job['snapshot_dir'], $rootPdo);
        $rootPdo = null;

        $this->logBatchExports($batches[$batch_index], $job['snapshot_dir']);
        $this->updateJobBatchProgress($pdo, $jobId, $batch_index + 1, $result['exported'], $result['rows'], $result['errors']);

        $next_batch = $batch_index + 1;
        if ($next_batch < count($batches)) {
            $this->scheduleNextBatch($jobId);
            $this->log('INFO', sprintf('Next batch scheduled (%d/%d)', $next_batch + 1, count($batches)));
        } else {
            $this->finalizeJob($pdo, $jobId, $job['snapshot_dir']);
        }
    }

    /**
     * Open a-root.db for batch registration.
     *
     * @param string $snapshotDir Snapshot directory.
     * @return PDO|null Root PDO or null.
     */
    private function openRootDbForBatch(string $snapshotDir): ?PDO {
        $root_path = $snapshotDir . '/a-root.db';
        if (RiseupBooleanHelpers::is_file_missing($root_path)) {
            return null;
        }

        $rootPdo = new PDO('sqlite:' . $root_path);
        $rootPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $rootPdo;
    }

    /**
     * Log individual table export results for a batch.
     *
     * @param array  $tables      Tables in the batch.
     * @param string $snapshotDir Snapshot directory.
     */
    private function logBatchExports(array $tables, string $snapshotDir): void {
        // Individual table logs are already emitted by exportBatchTables
        // This hook exists for future batch-level logging needs
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
        if (!$pdo) return false;

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS " . TABLE_SNAPSHOT_JOBS . " (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                snapshot_dir TEXT NOT NULL,
                tables_json TEXT NOT NULL,
                pool_size INTEGER NOT NULL DEFAULT " . SNAPSHOT_WORKER_POOL_DEFAULT . ",
                current_batch INTEGER NOT NULL DEFAULT 0,
                tables_exported INTEGER NOT NULL DEFAULT 0,
                total_rows INTEGER NOT NULL DEFAULT 0,
                errors_json TEXT DEFAULT '[]',
                status TEXT NOT NULL DEFAULT '" . SNAPSHOT_JOB_STATUS_QUEUED . "',
                config_json TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                completed_at TEXT
            )");

            $now = gmdate('c');
            $stmt = $pdo->prepare("INSERT INTO " . TABLE_SNAPSHOT_JOBS . "
                (snapshot_dir, tables_json, pool_size, status, config_json, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)");

            $stmt->execute(array(
                $snapshot_dir,
                json_encode($tables),
                $this->poolSize,
                SNAPSHOT_JOB_STATUS_QUEUED,
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
        $stmt = $pdo->prepare("SELECT * FROM " . TABLE_SNAPSHOT_JOBS . " WHERE id = ?");
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
        $completed = ($status === SNAPSHOT_JOB_STATUS_COMPLETE || $status === SNAPSHOT_JOB_STATUS_FAILED)
            ? $now : null;

        $stmt = $pdo->prepare("UPDATE " . TABLE_SNAPSHOT_JOBS . "
            SET status = ?, updated_at = ?, completed_at = COALESCE(?, completed_at)
            WHERE id = ?");
        $stmt->execute(array($status, $now, $completed, $job_id));

        if ($error) {
            $job = $this->getJob($pdo, $job_id);
            $errors = json_decode($job['errors_json'] ?? '[]', true);
            $errors[] = $error;
            $stmt2 = $pdo->prepare("UPDATE " . TABLE_SNAPSHOT_JOBS . " SET errors_json = ? WHERE id = ?");
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
        $job = $this->getJob($pdo, $job_id);
        $existing_errors = json_decode($job['errors_json'] ?? '[]', true);
        $all_errors = array_merge($existing_errors, $batch_errors);

        $stmt = $pdo->prepare("UPDATE " . TABLE_SNAPSHOT_JOBS . "
            SET current_batch = ?,
                tables_exported = tables_exported + ?,
                total_rows = total_rows + ?,
                errors_json = ?,
                updated_at = ?
            WHERE id = ?");

        $stmt->execute(array(
            $next_batch, $batch_exported, $batch_rows,
            json_encode($all_errors), $now, $job_id,
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

        $root_path = $snapshot_dir . '/a-root.db';
        if (file_exists($root_path)) {
            try {
                $rootPdo = new PDO('sqlite:' . $root_path);
                $rootPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->rootDb->updateStats($rootPdo, (int) $job['tables_exported'], (int) $job['total_rows']);
                $rootPdo = null;
            } catch (Exception $e) {
                $this->log('WARN', 'Failed to finalize a-root.db stats', array('error' => $e->getMessage()));
            }
        }

        $this->updateJobStatus($pdo, $job_id, SNAPSHOT_JOB_STATUS_COMPLETE);

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
        wp_schedule_single_event(
            time() + 5,
            CRON_SNAPSHOT_WORKER_BATCH,
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
        if (!$pdo) return null;

        $job = $this->getJob($pdo, $job_id);
        if (!$job) return null;

        $all_tables = json_decode($job['tables_json'], true);
        $total_tables = count($all_tables);
        $pool_size = (int) $job['pool_size'];
        $total_batches = (int) ceil($total_tables / $pool_size);

        $table_progress = array();
        try {
            $stmt = $pdo->prepare("SELECT table_name, status, rows_total, rows_exported, error_message
                FROM " . TABLE_SNAPSHOT_PROGRESS . " WHERE snapshot_id = 0");
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
    // TABLE EXPORT
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
            $sqlite = $this->createSqliteAndSchema($filepath, $table);
            $count = (int) $this->wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");

            if ($count === 0) {
                $sqlite = null;
                return $this->buildExportResult($filename, $filepath, 0);
            }

            $exported = $this->batchExportRows($sqlite, $table, $count);
            $sqlite = null;

            return $this->buildExportResult($filename, $filepath, $exported);

        } catch (Exception $e) {
            return array(
                'success' => false, 'error' => $e->getMessage(),
                'rows' => 0, 'filename' => $filename, 'file_size' => 0, 'checksum' => '',
            );
        }
    }

    /**
     * Create a SQLite file and initialize the table schema.
     *
     * @param string $filepath SQLite file path.
     * @param string $table    Table name.
     * @return PDO SQLite connection.
     */
    private function createSqliteAndSchema(string $filepath, string $table): PDO {
        $sqlite = new PDO('sqlite:' . $filepath);
        $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sqlite->exec('PRAGMA journal_mode = WAL');
        $sqlite->exec('PRAGMA synchronous = OFF');

        $create_sql = $this->getCreateTableSql($table);
        if (!$create_sql) {
            throw new Exception('Failed to get table structure for ' . $table);
        }

        $sqlite->exec(RiseupSqliteSchemaConverter::convert($create_sql, $table));

        return $sqlite;
    }

    /**
     * Batch export all rows from a MySQL table to SQLite.
     *
     * @param PDO    $sqlite SQLite connection.
     * @param string $table  Table name.
     * @param int    $count  Total row count.
     * @return int Number of rows exported.
     */
    private function batchExportRows(PDO $sqlite, string $table, int $count): int {
        $columns = $this->wpdb->get_results("DESCRIBE `{$table}`", ARRAY_A);
        $column_names = array_column($columns, 'Field');
        $placeholders = implode(', ', array_fill(0, count($column_names), '?'));
        $column_list = implode(', ', array_map(function($c) { return "`{$c}`"; }, $column_names));

        $stmt = $sqlite->prepare("INSERT INTO `{$table}` ({$column_list}) VALUES ({$placeholders})");

        $offset = 0;
        $exported = 0;
        $sqlite->beginTransaction();

        while ($offset < $count) {
            $rows = $this->wpdb->get_results(
                $this->wpdb->prepare("SELECT * FROM `{$table}` LIMIT %d OFFSET %d", $this->batchSize, $offset),
                ARRAY_N
            );

            foreach ($rows as $row) {
                $stmt->execute($row);
                $exported++;
            }

            $offset += $this->batchSize;
        }

        $sqlite->commit();
        return $exported;
    }

    /**
     * Build the export result array.
     *
     * @param string $filename Filename.
     * @param string $filepath Full path.
     * @param int    $rows     Rows exported.
     * @return array Result.
     */
    private function buildExportResult(string $filename, string $filepath, int $rows): array {
        return array(
            'success'   => true,
            'rows'      => $rows,
            'filename'  => $filename,
            'file_size' => filesize($filepath),
            'checksum'  => md5_file($filepath),
        );
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
        if (!$pdo) return;

        try {
            $stmt = $pdo->prepare("INSERT OR REPLACE INTO " . TABLE_SNAPSHOT_PROGRESS . "
                (snapshot_id, table_name, status, rows_total, rows_exported, started_at)
                VALUES (0, ?, 'pending', 0, 0, ?)");

            $now = gmdate('c');
            foreach ($tables as $table) {
                $count = (int) $this->wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
                $stmt->execute(array($table, $now));

                $pdo->exec("UPDATE " . TABLE_SNAPSHOT_PROGRESS .
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
            $stmt = $pdo->prepare("UPDATE " . TABLE_SNAPSHOT_PROGRESS . "
                SET status = ?, rows_exported = ?, completed_at = ?, error_message = ?
                WHERE snapshot_id = 0 AND table_name = ?");
            $stmt->execute(array(
                $status, $rows,
                ($status === 'complete' || $status === 'failed') ? $now : null,
                $error, $table,
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
        $base = RiseupPathUtils::get_snapshots_dir();
        RiseupPathUtils::ensure_dir($base, true);
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
        if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
        return round($bytes / 1073741824, 1) . ' GB';
    }

    /**
     * Log a message.
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
