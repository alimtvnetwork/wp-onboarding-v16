<?php
/**
 * Riseup Asia Uploader - Restore Engine
 *
 * Shell class — logic delegated to domain-specific traits.
 *
 * @package RiseupAsia\Snapshot
 * @since   1.15.0
 */

namespace RiseupAsia\Snapshot;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\SnapshotConfigType;
use RiseupAsia\Snapshot\Traits\RestoreValidationTrait;
use RiseupAsia\Snapshot\Traits\RestoreTableTrait;
use RiseupAsia\Snapshot\Traits\RestoreIncrementalTrait;
use RiseupAsia\Snapshot\Traits\RestoreGraphTrait;
use RiseupAsia\Snapshot\Traits\RestoreUtilsTrait;
use RiseupAsia\Database\Database;
use RiseupAsia\Logging\FileLogger;

class RestoreEngine {

    use RestoreValidationTrait;
    use RestoreTableTrait;
    use RestoreIncrementalTrait;
    use RestoreGraphTrait;
    use RestoreUtilsTrait;

    private FileLogger $logger;
    private Database $db;
    private ?SnapshotOrchestrator $orchestrator;
    private \wpdb $wpdb;
    private int $batchSize;
    private static ?self $instance = null;

    public static function getInstance(?FileLogger $logger = null, ?Database $db = null, ?SnapshotOrchestrator $orchestrator = null): self {
        if (self::$instance === null && $logger && $db) {
            self::$instance = new self($logger, $db, $orchestrator);
        }
        return self::$instance;
    }

    private function __construct(FileLogger $logger, Database $db, ?SnapshotOrchestrator $orchestrator = null) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->logger = $logger;
        $this->db = $db;
        $this->orchestrator = $orchestrator;
        $this->batchSize = SnapshotConfigType::BatchSize->value;
    }

    public function execute(string $snapshotDir, array $options = array()): array {
        $prereqError = $this->validateRestorePrereqs($snapshotDir, $options);
        if ($prereqError) {
            return $prereqError;
        }

        $this->log(LogLevelType::Info->value, 'Starting per-table restore', array(
            'directory' => basename($snapshotDir), 'mode' => $options['mode'] ?? 'full',
        ));

        try {
            return $this->executeRestoreWorkflow($snapshotDir, $options);
        } catch (\Throwable $e) {
            return $this->handleRestoreFailure($e);
        }
    }

    private function executeRestoreWorkflow(string $snapshotDir, array $options): array {
        $startTime = microtime(true);
        $rootPdo = $this->openRootPdo($snapshotDir);
        $meta = $this->getSnapshotMeta($rootPdo);
        $restoreOrder = $this->prepareRestoreOrder($rootPdo, $options);

        if (!$restoreOrder['success']) {
            $rootPdo = null;
            return $restoreOrder;
        }

        $backupId = $this->createSafetyBackup($options);
        $results = $this->runRestoreWithFkDisabled($rootPdo, $snapshotDir, $restoreOrder, $options);
        $rootPdo = null;

        $duration = microtime(true) - $startTime;
        $this->logAuditRestore($snapshotDir, $results['master']['tables_restored'], $results['total_rows'], $duration);

        return $this->buildRestoreResult(
            $results['master'], $results['inc'], $backupId, $results['errors'], $duration, $meta, $results['total_rows']
        );
    }

    private function openRootPdo(string $snapshotDir): \PDO {
        $pdo = new \PDO('sqlite:' . $snapshotDir . '/a-root.db');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }

    private function runRestoreWithFkDisabled(\PDO $rootPdo, string $snapshotDir, array $restoreOrder, array $options): array {
        $this->wpdb->query("SET FOREIGN_KEY_CHECKS = 0");
        $master = $this->restoreMasterTables($restoreOrder['tables'], $restoreOrder['inventory'], $snapshotDir, $options);
        $inc = $this->applyIncrementalsPhase($rootPdo, $snapshotDir, $restoreOrder['tables'], $options['mode'] ?? 'full', $options['apply_incrementals'] ?? true);
        $this->wpdb->query("SET FOREIGN_KEY_CHECKS = 1");

        return array(
            'master' => $master, 'inc' => $inc,
            'total_rows' => $master['total_rows'] + $inc['total_rows'],
            'errors' => array_merge($master['errors'], $inc['errors']),
        );
    }

    private function handleRestoreFailure(\Throwable $e): array {
        $this->wpdb->query("SET FOREIGN_KEY_CHECKS = 1");
        $this->log(LogLevelType::Error->value, 'Restore engine failed', array(
            'error' => $e->getMessage(), 'trace' => $e->getTraceAsString(),
        ));
        return array('success' => false, 'error' => $e->getMessage(), 'phase' => 'restore');
    }
}
