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

    private RiseupFileLogger $logger;
    private RiseupDatabase $db;
    private RiseupRootDb $rootDb;
    private RiseupDependencyAnalyzer $analyzer;
    private \wpdb $wpdb;
    private int $batchSize;
    private int $poolSize;
    private static ?self $instance = null;

    public static function getInstance(
        ?RiseupFileLogger $logger = null,
        ?RiseupDatabase $db = null,
        ?RiseupRootDb $rootDb = null,
        ?RiseupDependencyAnalyzer $analyzer = null
    ): self {
        if (self::$instance === null && $logger && $db && $rootDb && $analyzer) {
            self::$instance = new self($logger, $db, $rootDb, $analyzer);
        }
        return self::$instance;
    }

    private function __construct(
        RiseupFileLogger $logger,
        RiseupDatabase $db,
        RiseupRootDb $rootDb,
        RiseupDependencyAnalyzer $analyzer
    ) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->logger = $logger;
        $this->db = $db;
        $this->rootDb = $rootDb;
        $this->analyzer = $analyzer;
        $this->batchSize = SNAPSHOT_BATCH_SIZE;
        $this->poolSize  = SNAPSHOT_WORKER_POOL_DEFAULT;
    }

    public function setPoolSize(int $size): void {
        $this->poolSize = max(SNAPSHOT_WORKER_POOL_MIN, min(SNAPSHOT_WORKER_POOL_MAX, $size));
    }

    public function getPoolSize(): int {
        return $this->poolSize;
    }
}
