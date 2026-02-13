<?php
/**
 * Riseup Asia Uploader - Snapshot Worker
 *
 * Exports MySQL tables to individual SQLite files.
 * Shell class — logic delegated to domain-specific traits.
 *
 * @package RiseupAsiaUploader
 * @since   1.12.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__FILE__) . '/SqliteSchemaConverter.php';

// Load trait files
require_once __DIR__ . '/Traits/WorkerExecuteTrait.php';
require_once __DIR__ . '/Traits/WorkerSetupTrait.php';
require_once __DIR__ . '/Traits/WorkerBatchTrait.php';
require_once __DIR__ . '/Traits/WorkerJobTrait.php';
require_once __DIR__ . '/Traits/WorkerTableExportTrait.php';
require_once __DIR__ . '/Traits/WorkerProgressTrait.php';

/**
 * Snapshot Worker class.
 */
class RiseupSnapshotWorker {

    use WorkerExecuteTrait;
    use WorkerSetupTrait;
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
    /** @var int */
    private $poolSize;
    /** @var RiseupSnapshotWorker|null */
    private static $instance = null;

    /**
     * Get singleton instance.
     */
    public static function getInstance($logger = null, $db = null, $rootDb = null, $analyzer = null) {
        if (self::$instance === null && $logger && $db && $rootDb && $analyzer) {
            self::$instance = new self($logger, $db, $rootDb, $analyzer);
        }
        return self::$instance;
    }

    /** Constructor. */
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
     */
    public function setPoolSize($size) {
        $this->poolSize = max(SNAPSHOT_WORKER_POOL_MIN, min(SNAPSHOT_WORKER_POOL_MAX, (int) $size));
    }

    /** @return int */
    public function getPoolSize() {
        return $this->poolSize;
    }
}
