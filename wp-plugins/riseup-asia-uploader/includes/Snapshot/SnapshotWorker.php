<?php
namespace RiseupAsia\Snapshot;

if (!defined('ABSPATH')) { exit; }

use LogicException;
use wpdb;
use RiseupAsia\Snapshot\Traits\WorkerExecuteTrait;
use RiseupAsia\Snapshot\Traits\WorkerSetupTrait;
use RiseupAsia\Snapshot\Traits\WorkerBatchTrait;
use RiseupAsia\Snapshot\Traits\WorkerJobTrait;
use RiseupAsia\Snapshot\Traits\WorkerTableExportTrait;
use RiseupAsia\Snapshot\Traits\WorkerProgressTrait;
use RiseupAsia\Database\Database;
use RiseupAsia\Database\RootDb;
use RiseupAsia\Enums\SnapshotConfigType;
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
    private wpdb $wpdb;
    private int $batchSize;
    private int $poolSize;
    private static ?self $instance = null;

    public static function getInstance(
        ?FileLogger $logger = null,
        ?Database $db = null,
        ?RootDb $rootDb = null,
        ?DependencyAnalyzer $analyzer = null,
    ): self {
        $isReadyToInit = self::$instance === null && $logger && $db && $rootDb && $analyzer;
        if ($isReadyToInit) {
            self::$instance = new self($logger, $db, $rootDb, $analyzer);
        }

        if (self::$instance === null) {
            throw new LogicException('SnapshotWorker::getInstance() called before initialization.');
        }

        return self::$instance;
    }

    private function __construct(
        FileLogger $logger,
        Database $db,
        RootDb $rootDb,
        DependencyAnalyzer $analyzer,
    ) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->logger = $logger;
        $this->db = $db;
        $this->rootDb = $rootDb;
        $this->analyzer = $analyzer;
        $this->batchSize = SnapshotConfigType::BatchSize->value;
        $this->poolSize  = SnapshotConfigType::WorkerPoolDefault->value;
    }

    public function setPoolSize(int $size): void {
        $this->poolSize = max(SnapshotConfigType::WorkerPoolMin->value, min(SnapshotConfigType::WorkerPoolMax->value, $size));
    }

    public function getPoolSize(): int { return $this->poolSize; }
}
