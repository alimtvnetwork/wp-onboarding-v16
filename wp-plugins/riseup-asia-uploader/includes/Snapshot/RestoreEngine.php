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

        $start_time = microtime(true);
        $mode = $options['mode'] ?? 'full';
        $apply_incrementals = $options['apply_incrementals'] ?? true;

        $this->log('INFO', 'Starting per-table restore', array(
            'directory' => basename($snapshot_dir), 'mode' => $mode,
        ));

        try {
            $rootPdo = new PDO('sqlite:' . $snapshot_dir . '/a-root.db');
            $rootPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $meta = $this->getSnapshotMeta($rootPdo);
            $restore_order = $this->prepareRestoreOrder($rootPdo, $options);

            if (!$restore_order['success']) {
                $rootPdo = null;
                return $restore_order;
            }

            $ordered_tables = $restore_order['tables'];
            $table_inventory = $restore_order['inventory'];

            $backup_id = $this->createSafetyBackup($options);

            $this->wpdb->query("SET FOREIGN_KEY_CHECKS = 0");

            $master_result = $this->restoreMasterTables($ordered_tables, $table_inventory, $snapshot_dir, $options);
            $inc_result = $this->applyIncrementalsPhase($rootPdo, $snapshot_dir, $ordered_tables, $mode, $apply_incrementals);

            $this->wpdb->query("SET FOREIGN_KEY_CHECKS = 1");
            $rootPdo = null;

            $duration = microtime(true) - $start_time;
            $total_rows = $master_result['total_rows'] + $inc_result['total_rows'];
            $errors = array_merge($master_result['errors'], $inc_result['errors']);

            $this->logAuditRestore($snapshot_dir, $master_result['tables_restored'], $total_rows, $duration);

            return $this->buildRestoreResult(
                $master_result, $inc_result, $backup_id, $errors, $duration, $meta, $total_rows
            );

        } catch (\Throwable $e) {
            $this->wpdb->query("SET FOREIGN_KEY_CHECKS = 1");
            $this->log('ERROR', 'Restore engine failed', array(
                'error' => $e->getMessage(), 'trace' => $e->getTraceAsString(),
            ));
            return array('success' => false, 'error' => $e->getMessage(), 'phase' => 'restore');
        }
    }
}
