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

use LogicException;
use PDO;
use Throwable;
use wpdb;

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\PathDatabaseType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\RestoreModeType;
use RiseupAsia\Enums\SnapshotConfigType;
use RiseupAsia\Snapshot\Traits\RestoreValidationTrait;
use RiseupAsia\Snapshot\Traits\RestoreTableTrait;
use RiseupAsia\Snapshot\Traits\RestoreIncrementalTrait;
use RiseupAsia\Snapshot\Traits\RestoreGraphTrait;
use RiseupAsia\Snapshot\Traits\RestoreHelperTrait;
use RiseupAsia\Database\Database;
use RiseupAsia\Logging\FileLogger;
use RiseupAsia\Helpers\BooleanHelpers;

class RestoreEngine {
    use RestoreValidationTrait;
    use RestoreTableTrait;
    use RestoreIncrementalTrait;
    use RestoreGraphTrait;
    use RestoreHelperTrait;

    private FileLogger $logger;
    private Database $db;
    private ?SnapshotOrchestrator $orchestrator;
    private wpdb $wpdb;
    private int $batchSize;
    private static ?self $instance = null;

    public static function getInstance(
        ?FileLogger $logger = null,
        ?Database $db = null,
        ?SnapshotOrchestrator $orchestrator = null,
    ): self {
        $isReadyToInit = self::$instance === null && $logger && $db;

        if ($isReadyToInit) {
            self::$instance = new self($logger, $db, $orchestrator);
        }

        if (self::$instance === null) {
            throw new LogicException('RestoreEngine::getInstance() called before initialization.');
        }

        return self::$instance;
    }

    private function __construct(
        FileLogger $logger,
        Database $db,
        ?SnapshotOrchestrator $orchestrator = null,
    ) {
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
            ResponseKeyType::Directory->value => basename($snapshotDir),
            'mode' => $options['mode'] ?? RestoreModeType::Full->value,
        ));

        try {
            return $this->executeRestoreWorkflow($snapshotDir, $options);
        } catch (Throwable $e) {
            return $this->handleRestoreFailure($e);
        }
    }

    private function executeRestoreWorkflow(string $snapshotDir, array $options): array {
        $startTime = microtime(true);
        $rootPdo = $this->openRootPdo($snapshotDir);
        $meta = $this->getSnapshotMeta($rootPdo);
        $restoreOrder = $this->prepareRestoreOrder($rootPdo, $options);
        $isRestoreOrderFailed = BooleanHelpers::isResultFailed($restoreOrder);

        if ($isRestoreOrderFailed) {
            $rootPdo = null;

            return $restoreOrder;
        }

        return $this->runAndFinalizeRestore($rootPdo, $snapshotDir, $restoreOrder, $options, $meta, $startTime);
    }

    private function runAndFinalizeRestore(
        PDO $rootPdo,
        string $snapshotDir,
        array $restoreOrder,
        array $options,
        array $meta,
        float $startTime,
    ): array {
        $backupId = $this->createSafetyBackup($options);
        $results = $this->runRestoreWithFkDisabled($rootPdo, $snapshotDir, $restoreOrder, $options);
        $rootPdo = null;
        $duration = microtime(true) - $startTime;

        $this->logAuditRestore($snapshotDir, $results['master'][ResponseKeyType::TablesRestored->value], $results[ResponseKeyType::TotalRows->value], $duration);

        return $this->buildRestoreResult($results['master'], $results['inc'], $backupId, $results[ResponseKeyType::Errors->value], $duration, $meta, $results[ResponseKeyType::TotalRows->value]);
    }

    private function openRootPdo(string $snapshotDir): PDO {
        $pdo = new PDO('sqlite:' . $snapshotDir . PathDatabaseType::Root->value);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }

    private function runRestoreWithFkDisabled(
        PDO $rootPdo,
        string $snapshotDir,
        array $restoreOrder,
        array $options,
    ): array {
        $this->wpdb->query("SET FOREIGN_KEY_CHECKS = 0");

        $master = $this->restoreMasterTables(
            $restoreOrder[ResponseKeyType::Tables->value],
            $restoreOrder[ResponseKeyType::Inventory->value],
            $snapshotDir,
            $options,
        );

        $inc = $this->applyIncrementalsPhase(
            $rootPdo,
            $snapshotDir,
            $restoreOrder[ResponseKeyType::Tables->value],
            $options['mode'] ?? RestoreModeType::Full->value,
            $options['apply_incrementals'] ?? true,
        );

        $this->wpdb->query("SET FOREIGN_KEY_CHECKS = 1");

        return $this->combineRestoreResults($master, $inc);
    }

    private function combineRestoreResults(array $master, array $inc): array {
        return array(
            'master' => $master,
            'inc' => $inc,
            ResponseKeyType::TotalRows->value => $master[ResponseKeyType::TotalRows->value] + $inc[ResponseKeyType::TotalRows->value],
            ResponseKeyType::Errors->value => array_merge($master[ResponseKeyType::Errors->value], $inc[ResponseKeyType::Errors->value]),
        );
    }

    private function handleRestoreFailure(Throwable $e): array {
        $this->wpdb->query("SET FOREIGN_KEY_CHECKS = 1");
        $this->log(LogLevelType::Error->value, 'Restore engine failed', array(
            ResponseKeyType::Error->value => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ));

        return array(
            ResponseKeyType::Success->value => false,
            ResponseKeyType::Error->value => $e->getMessage(),
            ResponseKeyType::Phase->value => 'restore',
        );
    }
}
