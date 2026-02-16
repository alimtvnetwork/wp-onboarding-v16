<?php
namespace RiseupAsia\Snapshot;

if (!defined('ABSPATH')) { exit; }

use RiseupAsia\Snapshot\Traits\WorkerExecuteTrait;
use RiseupAsia\Snapshot\Traits\WorkerSetupTrait;
use RiseupAsia\Snapshot\Traits\WorkerBatchTrait;
use RiseupAsia\Snapshot\Traits\WorkerJobTrait;
use RiseupAsia\Snapshot\Traits\WorkerTableExportTrait;
use RiseupAsia\Snapshot\Traits\WorkerProgressTrait;
use RiseupAsia\Database\Database;
use RiseupAsia\Database\RootDb;
use RiseupAsia\Logging\FileLogger;

class SnapshotWorker {
    use WorkerExecuteTrait;
    use WorkerSetupTrait;
    use WorkerBatchTrait;
    use WorkerJobTrait;
    use WorkerTableExportTrait;
    use WorkerProgressTrait;

    private FileLogger $logger;
    private Database $db;
    private RootDb $rootDb;
    private DependencyAnalyzer $analyzer;
    private \wpdb $wpdb;
    private int $batchSize;
    private int $poolSize;
    private static ?self $instance = null;

    public static function getInstance(?FileLogger $logger = null, ?Database $db = null, ?RootDb $rootDb = null, ?DependencyAnalyzer $analyzer = null): self {
        if (self::$instance === null && $logger && $db && $rootDb && $analyzer) {
            self::$instance = new self($logger, $db, $rootDb, $analyzer);
        }
        return self::$instance;
    }

    private function __construct(FileLogger $logger, Database $db, RootDb $rootDb, DependencyAnalyzer $analyzer) {
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

    public function getPoolSize(): int { return $this->poolSize; }
}

class_alias(SnapshotWorker::class, 'RiseupSnapshotWorker');
