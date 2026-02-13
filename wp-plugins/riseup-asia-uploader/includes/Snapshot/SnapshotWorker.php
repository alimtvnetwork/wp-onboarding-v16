<?php
/**
 * Riseup Asia Uploader - Snapshot Worker
 *
 * Exports MySQL tables to individual SQLite files using a
 * parallel worker-pool pattern with configurable concurrency.
 *
 * @package RiseupAsiaUploader
 * @since   1.12.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__FILE__) . '/SqliteSchemaConverter.php';

// Load trait files
require_once __DIR__ . '/Traits/WorkerBatchTrait.php';
require_once __DIR__ . '/Traits/WorkerJobTrait.php';
require_once __DIR__ . '/Traits/WorkerTableExportTrait.php';
require_once __DIR__ . '/Traits/WorkerProgressTrait.php';

/**
 * Snapshot Worker class.
 *
 * Manages per-table MySQL → SQLite export with parallel batch processing.
 */
class RiseupSnapshotWorker {

    use WorkerBatchTrait;
    use WorkerJobTrait;
    use WorkerTableExportTrait;
    use WorkerProgressTrait;

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
     * Set the worker pool size.
     *
     * @param int $size Pool size (clamped to min/max).
     */
    public function setPoolSize($size) {
        $this->poolSize = max(SNAPSHOT_WORKER_POOL_MIN, min(SNAPSHOT_WORKER_POOL_MAX, (int) $size));
    }

    /** @return int */
    public function getPoolSize() {
        return $this->poolSize;
    }

    /**
     * Execute a full per-table snapshot export (async via WP-Cron).
     *
     * @param array $config Snapshot config.
     * @return array Result.
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
            $this->log('ERROR', 'Per-table snapshot failed', array('error' => $e->getMessage(), 'trace' => $e->getTraceAsString()));
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * Execute a synchronous full snapshot (blocks until complete).
     *
     * @param array $config Snapshot config.
     * @return array Result.
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
            $this->log('ERROR', 'Synchronous snapshot failed', array('error' => $e->getMessage(), 'trace' => $e->getTraceAsString()));
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * Prepare the snapshot directory.
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

        return array('success' => true, 'snapshot_dir' => $snapshot_dir, 'dir_name' => $dir_name, 'title' => $title, 'scope' => $scope, 'type' => $type);
    }

    /** Initialize a-root.db. */
    private function initRootDb(string $snapshotDir, array $config): PDO {
        $rootPdo = $this->rootDb->create($snapshotDir . '/a-root.db');
        $this->rootDb->populateMetadata($rootPdo, array(
            'title' => $config['title'] ?? 'Snapshot', 'type' => $config['type'] ?? 'full', 'settings' => $config['settings'] ?? null,
        ));
        return $rootPdo;
    }

    /** Populate dependencies and return seed order. */
    private function populateAndGetSeedOrder(PDO $rootPdo, array $config): array {
        $analysis = $this->rootDb->populateDependencies($rootPdo, $config['scope'] ?? 'wordpress');
        $this->log('INFO', 'Export order determined', array('tables' => count($analysis['seed_order']), 'pool_size' => $this->poolSize));
        return $analysis['seed_order'];
    }

    /** Get the base snapshots directory. */
    private function getSnapshotsBaseDir() {
        $base = RiseupPathUtils::get_snapshots_dir();
        RiseupPathUtils::ensure_dir($base, true);
        return $base;
    }

    /**
     * Log a message.
     */
    private function log($level, $message, $context = array()) {
        $full = '[SNAPSHOT] [WORKER] ' . $message;
        if (!empty($context)) {
            $full .= ' ' . json_encode($context);
        }

        if (!$this->logger) return;

        switch ($level) {
            case 'WARN':  $this->logger->warn($full); break;
            case 'ERROR': $this->logger->error($full); break;
            default:      $this->logger->info($full);
        }
    }
}
