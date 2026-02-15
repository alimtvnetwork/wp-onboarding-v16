<?php
/**
 * Riseup Asia Uploader - Snapshot Orchestrator
 *
 * End-to-end full backup flow.
 * Shell class — logic delegated to domain-specific traits.
 *
 * @package RiseupAsiaUploader
 * @since   1.13.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load trait files
require_once __DIR__ . '/Traits/OrchestratorBackupTrait.php';
require_once __DIR__ . '/Traits/OrchestratorHelpersTrait.php';
require_once __DIR__ . '/Traits/OrchestratorPluginTrait.php';
require_once __DIR__ . '/Traits/OrchestratorZipTrait.php';
require_once __DIR__ . '/Traits/OrchestratorRegistrationTrait.php';

/**
 * Snapshot Orchestrator class.
 */
class RiseupSnapshotOrchestrator {

    use OrchestratorBackupTrait;
    use OrchestratorHelpersTrait;
    use OrchestratorPluginTrait;
    use OrchestratorZipTrait;
    use OrchestratorRegistrationTrait;

    private RiseupFileLogger $logger;
    private RiseupDatabase $db;
    private RiseupSnapshotManager $manager;
    private RiseupSnapshotWorker $worker;
    private RiseupRootDb $rootDb;
    private RiseupDependencyAnalyzer $analyzer;
    private \wpdb $wpdb;
    private static ?self $instance = null;

    public static function getInstance(
        ?RiseupFileLogger $logger = null,
        ?RiseupDatabase $db = null,
        ?RiseupSnapshotManager $manager = null
    ): self {
        if (self::$instance === null && $logger && $db && $manager) {
            self::$instance = new self($logger, $db, $manager);
        }
        return self::$instance;
    }

    private function __construct(RiseupFileLogger $logger, RiseupDatabase $db, RiseupSnapshotManager $manager) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->logger = $logger;
        $this->db = $db;
        $this->manager = $manager;
        $this->analyzer = RiseupDependencyAnalyzer::getInstance($logger);
        $this->rootDb = RiseupRootDb::getInstance($logger, $this->analyzer);
        $this->worker = RiseupSnapshotWorker::getInstance($logger, $db, $this->rootDb, $this->analyzer);
    }
}
