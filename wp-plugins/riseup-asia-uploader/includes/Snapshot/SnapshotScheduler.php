<?php
/**
 * Riseup Asia Uploader - Snapshot Scheduler
 *
 * Handles WP-Cron scheduling for automated database snapshots.
 * Shell class — logic delegated to domain-specific traits.
 *
 * @package RiseupAsiaUploader
 * @since   1.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\HookType;

// Load traits
require_once dirname(__FILE__) . '/Traits/SchedulerCronTrait.php';
require_once dirname(__FILE__) . '/Traits/SchedulerExecutorTrait.php';
require_once dirname(__FILE__) . '/Traits/SchedulerTimingTrait.php';
require_once dirname(__FILE__) . '/Traits/SchedulerTriggerTrait.php';
require_once dirname(__FILE__) . '/Traits/SchedulerConfigTrait.php';

/**
 * Snapshot Scheduler class.
 */
class RiseupSnapshotScheduler {

    use SchedulerCronTrait;
    use SchedulerExecutorTrait;
    use SchedulerTimingTrait;
    use SchedulerTriggerTrait;
    use SchedulerConfigTrait;

    /** @var RiseupFileLogger */
    private $logger;

    /** @var RiseupDatabase */
    private $db;

    /** @var RiseupSnapshotDetector */
    private $detector;

    /** @var RiseupSnapshotScheduler|null */
    private static $instance = null;

    /**
     * Get singleton instance.
     */
    public static function getInstance($logger = null, $db = null) {
        if (self::$instance === null) {
            self::$instance = new self($logger, $db);
        }

        return self::$instance;
    }

    /** Constructor. */
    private function __construct($logger = null, $db = null) {
        $this->logger = $logger ?: RiseupFileLogger::getInstance();
        $this->db = $db ?: RiseupDatabase::getInstance();

        require_once dirname(__FILE__) . '/SnapshotFactory.php';
        $this->detector = RiseupSnapshotFactory::detector($this->logger, $this->db);
    }

    /**
     * Initialize scheduler - register all cron hooks.
     */
    public function init() {
        $this->logger->info('[SCHEDULER] Initializing snapshot scheduler');

        add_filter(HookType::CronSchedules->value, array($this, 'registerCronSchedules'));

        add_action(CRON_SNAPSHOT_SCHEDULED, array($this, 'executeScheduledSnapshot'));
        add_action(CRON_SNAPSHOT_IMMEDIATE, array($this, 'executeImmediateSnapshot'));
        add_action(CRON_SNAPSHOT_CLEANUP, array($this, 'executeCleanup'));
        add_action(CRON_SNAPSHOT_WORKER_BATCH, array($this, 'executeWorkerBatch'));
        add_action(CRON_SNAPSHOT_RESTORE, array($this, 'executeCronRestore'));
        add_action(CRON_SNAPSHOT_INCREMENTAL, array($this, 'executeCronIncremental'));

        $this->ensureCleanupScheduled();
        $this->syncScheduleWithSettings();

        $this->logger->info('[SCHEDULER] Scheduler initialized');
    }

    /**
     * Execute a worker batch (called by WP-Cron from worker pool).
     *
     * @param array $args { job_id: int }
     */
    public function executeWorkerBatch($args) {
        $this->logger->info('[SCHEDULER] Executing worker batch', $args);

        try {
            require_once dirname(__FILE__) . '/SnapshotFactory.php';
            $worker = RiseupSnapshotFactory::worker($this->logger, $this->db);
            $worker->processWorkerBatch($args);
        } catch (\Throwable $e) {
            $this->logger->error('[SCHEDULER] Worker batch exception', array(
                'error' => $e->getMessage(), 'trace' => $e->getTraceAsString(),
            ));
        }
    }

    // ── Public cron executors (thin delegates) ──

    /** Execute a scheduled snapshot (called by cron). */
    public function executeScheduledSnapshot() {
        $this->executeCronJob('scheduled snapshot', fn() => $this->runScheduledSnapshot());
    }

    /** Execute an immediate snapshot (called by cron from "Snapshot Now"). */
    public function executeImmediateSnapshot($args) {
        $this->executeCronJob('immediate snapshot', fn() => $this->runImmediateSnapshot($args));
    }

    /** Execute a background restore operation (called by cron). */
    public function executeCronRestore($args) {
        $this->executeCronJob('cron restore', fn() => $this->runCronRestore($args));
    }

    /** Execute a background incremental backup (called by cron). */
    public function executeCronIncremental($args) {
        $this->executeCronJob('cron incremental backup', fn() => $this->runCronIncremental($args));
    }

    /** Execute cleanup of old snapshots based on retention policy. */
    public function executeCleanup() {
        $this->executeCronJob('snapshot cleanup', fn() => $this->runCleanup());
    }
}
