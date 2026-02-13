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

/**
 * Snapshot Scheduler class.
 *
 * Manages WP-Cron hooks for scheduled snapshots and immediate
 * "Snapshot Now" operations.
 */
class RiseupSnapshotScheduler {

    use SchedulerCronTrait;
    use SchedulerExecutorTrait;
    use SchedulerTimingTrait;
    use SchedulerTriggerTrait;

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
     *
     * @param RiseupFileLogger|null $logger Logger instance.
     * @param RiseupDatabase|null    $db     Database instance.
     * @return RiseupSnapshotScheduler
     */
    public static function getInstance($logger = null, $db = null) {
        if (self::$instance === null) {
            self::$instance = new self($logger, $db);
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct($logger = null, $db = null) {
        $this->logger = $logger ?: RiseupFileLogger::get_instance();
        $this->db = $db ?: RiseupDatabase::get_instance();

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

    /**
     * Register custom cron schedules.
     *
     * @param array $schedules Existing schedules.
     * @return array Modified schedules.
     */
    public function registerCronSchedules($schedules) {
        if (!isset($schedules[SNAPSHOT_FREQ_WEEKLY])) {
            $schedules[SNAPSHOT_FREQ_WEEKLY] = array(
                'interval' => WEEK_IN_SECONDS,
                'display' => __('Once Weekly', 'riseup-asia-uploader'),
            );
        }

        if (!isset($schedules[SNAPSHOT_FREQ_MONTHLY])) {
            $schedules[SNAPSHOT_FREQ_MONTHLY] = array(
                'interval' => 30 * DAY_IN_SECONDS,
                'display' => __('Once Monthly', 'riseup-asia-uploader'),
            );
        }

        return $schedules;
    }

    /**
     * Ensure cleanup cron is scheduled.
     */
    private function ensureCleanupScheduled() {
        if (!wp_next_scheduled(CRON_SNAPSHOT_CLEANUP)) {
            $timestamp = strtotime('tomorrow 04:00:00');
            wp_schedule_event($timestamp, 'daily', CRON_SNAPSHOT_CLEANUP);
            $this->logger->info('[SCHEDULER] Cleanup cron scheduled', array('next_run' => date('c', $timestamp)));
        }
    }

    /**
     * Sync cron schedule with user settings.
     */
    public function syncScheduleWithSettings() {
        $settings = $this->detector->getSettings();
        $this->clearScheduledSnapshot();

        if (!$settings['schedule_enabled']) {
            $this->logger->debug('[SCHEDULER] Scheduled snapshots disabled');
            return;
        }

        if ($settings['schedule_frequency'] === SNAPSHOT_FREQ_MANUAL) {
            $this->logger->debug('[SCHEDULER] Frequency set to manual - no cron scheduling');
            return;
        }

        $next_run = $this->calculateNextRunTime($settings['schedule_frequency'], $settings['schedule_time'], $settings['schedule_day']);
        $recurrence = $this->mapFrequencyToRecurrence($settings['schedule_frequency']);
        $result = wp_schedule_event($next_run, $recurrence, CRON_SNAPSHOT_SCHEDULED);

        if ($result) {
            $this->logger->info('[SCHEDULER] Scheduled snapshot cron', array(
                'frequency' => $settings['schedule_frequency'], 'next_run' => date('c', $next_run), 'recurrence' => $recurrence,
            ));
        } else {
            $this->logger->error('[SCHEDULER] Failed to schedule snapshot cron');
        }
    }

    /**
     * Clear scheduled snapshot cron.
     */
    public function clearScheduledSnapshot() {
        $timestamp = wp_next_scheduled(CRON_SNAPSHOT_SCHEDULED);
        if ($timestamp) {
            wp_unschedule_event($timestamp, CRON_SNAPSHOT_SCHEDULED);
            $this->logger->debug('[SCHEDULER] Cleared scheduled snapshot cron');
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
