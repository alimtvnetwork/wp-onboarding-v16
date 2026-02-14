<?php
/**
 * Riseup Asia Uploader - Restore Engine
 *
 * Dependency-aware restoration from per-table SQLite snapshots.
 * Shell class — logic delegated to domain-specific traits.
 *
 * @package RiseupAsiaUploader
 * @since   1.15.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;

// Load traits
require_once dirname(__FILE__) . '/Traits/RestoreValidationTrait.php';
require_once dirname(__FILE__) . '/Traits/RestoreTableTrait.php';
require_once dirname(__FILE__) . '/Traits/RestoreIncrementalTrait.php';
require_once dirname(__FILE__) . '/Traits/RestoreGraphTrait.php';
require_once dirname(__FILE__) . '/Traits/RestoreUtilsTrait.php';

/**
 * Restore Engine class.
 *
 * Reads a-root.db to determine the table dependency graph and restore order,
 * then replays master + incremental SQLite files into MySQL.
 */
class RiseupRestoreEngine {

    use RestoreValidationTrait;
    use RestoreTableTrait;
    use RestoreIncrementalTrait;
    use RestoreGraphTrait;
    use RestoreUtilsTrait;

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
     * @param RiseupSnapshotOrchestrator|null     $orchestrator Orchestrator.
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

        $this->log(LogLevelType::Info->value, 'Starting per-table restore', array(
            'directory' => basename($snapshot_dir), 'mode' => $options['mode'] ?? 'full',
        ));

        try {
            return $this->executeRestoreWorkflow($snapshot_dir, $options);
        } catch (Throwable $e) {
            return $this->handleRestoreFailure($e);
        }
    }

    /**
     * Core restore workflow after validation passes.
     *
     * @param string $snapshot_dir Snapshot directory.
     * @param array  $options      Restore options.
     * @return array Result.
     */
    private function executeRestoreWorkflow(string $snapshot_dir, array $options): array {
        $start_time = microtime(true);
        $rootPdo = $this->openRootPdo($snapshot_dir);
        $meta = $this->getSnapshotMeta($rootPdo);
        $restore_order = $this->prepareRestoreOrder($rootPdo, $options);

        if (!$restore_order['success']) {
            $rootPdo = null;
            return $restore_order;
        }

        $backup_id = $this->createSafetyBackup($options);
        $results = $this->runRestoreWithFkDisabled($rootPdo, $snapshot_dir, $restore_order, $options);
        $rootPdo = null;

        $duration = microtime(true) - $start_time;
        $this->logAuditRestore($snapshot_dir, $results['master']['tables_restored'], $results['total_rows'], $duration);

        return $this->buildRestoreResult(
            $results['master'], $results['inc'], $backup_id, $results['errors'], $duration, $meta, $results['total_rows']
        );
    }

    /**
     * Open root PDO connection.
     *
     * @param string $snapshot_dir Snapshot directory.
     * @return PDO
     */
    private function openRootPdo(string $snapshot_dir): PDO {
        $pdo = new PDO('sqlite:' . $snapshot_dir . '/a-root.db');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }

    /**
     * Run master + incremental restore with FK checks disabled.
     *
     * @param PDO    $rootPdo      Root PDO.
     * @param string $snapshot_dir Snapshot directory.
     * @param array  $restoreOrder Restore order result.
     * @param array  $options      Options.
     * @return array Combined results.
     */
    private function runRestoreWithFkDisabled(PDO $rootPdo, string $snapshot_dir, array $restoreOrder, array $options): array {
        $this->wpdb->query("SET FOREIGN_KEY_CHECKS = 0");

        $master = $this->restoreMasterTables($restoreOrder['tables'], $restoreOrder['inventory'], $snapshot_dir, $options);
        $inc = $this->applyIncrementalsPhase($rootPdo, $snapshot_dir, $restoreOrder['tables'], $options['mode'] ?? 'full', $options['apply_incrementals'] ?? true);

        $this->wpdb->query("SET FOREIGN_KEY_CHECKS = 1");

        return array(
            'master' => $master, 'inc' => $inc,
            'total_rows' => $master['total_rows'] + $inc['total_rows'],
            'errors' => array_merge($master['errors'], $inc['errors']),
        );
    }

    /**
     * Handle restore failure with FK cleanup.
     *
     * @param Throwable $e Exception.
     * @return array Failure result.
     */
    private function handleRestoreFailure(Throwable $e): array {
        $this->wpdb->query("SET FOREIGN_KEY_CHECKS = 1");
        $this->log(LogLevelType::Error->value, 'Restore engine failed', array(
            'error' => $e->getMessage(), 'trace' => $e->getTraceAsString(),
        ));
        return array('success' => false, 'error' => $e->getMessage(), 'phase' => 'restore');
    }
}
