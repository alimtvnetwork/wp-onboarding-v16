<?php
namespace RiseupAsia\Snapshot;

if (!defined('ABSPATH')) { exit; }

use LogicException;
use wpdb;
use RiseupAsia\Snapshot\Traits\OrchestratorBackupTrait;
use RiseupAsia\Snapshot\Traits\OrchestratorHelpersTrait;
use RiseupAsia\Snapshot\Traits\OrchestratorPluginTrait;
use RiseupAsia\Snapshot\Traits\OrchestratorZipTrait;
use RiseupAsia\Snapshot\Traits\OrchestratorRegistrationTrait;
use RiseupAsia\Database\Database;
use RiseupAsia\Database\RootDb;
use RiseupAsia\Logging\FileLogger;

class SnapshotOrchestrator {
    use OrchestratorBackupTrait;
    use OrchestratorHelpersTrait;
    use OrchestratorPluginTrait;
    use OrchestratorZipTrait;
    use OrchestratorRegistrationTrait;

    private FileLogger $logger;
    private Database $db;
    private SnapshotManager $manager;
    private SnapshotWorker $worker;
    private RootDb $rootDb;
    private DependencyAnalyzer $analyzer;
    private wpdb $wpdb;
    private static ?self $instance = null;

    public static function getInstance(
        ?FileLogger $logger = null,
        ?Database $db = null,
        ?SnapshotManager $manager = null,
    ): self {
        $isReadyToInit = self::$instance === null && $logger && $db && $manager;
        if ($isReadyToInit) {
            self::$instance = new self($logger, $db, $manager);
        }

        if (self::$instance === null) {
            throw new LogicException('SnapshotOrchestrator::getInstance() called before initialization.');
        }
        return self::$instance;
    }

    private function __construct(
        FileLogger $logger,
        Database $db,
        SnapshotManager $manager,
    ) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->logger = $logger;
        $this->db = $db;
        $this->manager = $manager;
        $this->analyzer = DependencyAnalyzer::getInstance($logger);
        $this->rootDb = RootDb::getInstance($logger, $this->analyzer);
        $this->worker = SnapshotWorker::getInstance($logger, $db, $this->rootDb, $this->analyzer);
    }
}
