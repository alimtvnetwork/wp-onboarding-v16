<?php
/**
 * Riseup Asia Uploader - Snapshot Scheduler
 *
 * Handles WP-Cron scheduling for automated database snapshots.
 * Registers cron hooks, manages schedules, and executes snapshot jobs.
 *
 * PHP class naming follows PascalCase convention without underscores.
 *
 * @package RiseupAsiaUploader
 * @since   1.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Snapshot Scheduler class.
 * 
 * Manages WP-Cron hooks for scheduled snapshots and immediate
 * "Snapshot Now" operations.
 *
 * PHP class naming follows PascalCase convention without underscores.
 */
class RiseupSnapshotScheduler {

    /**
     * Logger instance.
     *
     * @var Riseup_File_Logger
     */
    private $logger;

    /**
     * Database instance.
     *
     * @var Riseup_Database
     */
    private $db;

    /**
     * Snapshot detector instance.
     *
     * @var RiseupSnapshotDetector
     */
    private $detector;

    /**
     * Singleton instance.
     *
     * @var RiseupSnapshotScheduler|null
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @param Riseup_File_Logger|null $logger Logger instance.
     * @param Riseup_Database|null    $db     Database instance.
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
     *
     * @param Riseup_File_Logger|null $logger Logger instance.
     * @param Riseup_Database|null    $db     Database instance.
     */
    private function __construct($logger = null, $db = null) {
        $this->logger = $logger ?: Riseup_File_Logger::get_instance();
        $this->db = $db ?: Riseup_Database::get_instance();
        
        require_once dirname(__FILE__) . '/class-snapshot-factory.php';
        $this->detector = RiseupSnapshotFactory::detector($this->logger, $this->db);
    }

    /**
     * Initialize scheduler - register all cron hooks.
     * 
     * This should be called during plugin initialization.
     */
    public function init() {
        $this->logger->info('[SCHEDULER] Initializing snapshot scheduler');

        // Register custom cron schedules
        add_filter('cron_schedules', array($this, 'registerCronSchedules'));

        // Register cron action hooks
        add_action(RISEUP_CRON_SNAPSHOT_SCHEDULED, array($this, 'executeScheduledSnapshot'));
        add_action(RISEUP_CRON_SNAPSHOT_IMMEDIATE, array($this, 'executeImmediateSnapshot'));
        add_action(RISEUP_CRON_SNAPSHOT_CLEANUP, array($this, 'executeCleanup'));

        // Register worker batch cron hook (Phase 2 - parallel worker pool)
        add_action(RISEUP_CRON_SNAPSHOT_WORKER_BATCH, array($this, 'executeWorkerBatch'));

        // Register cron hook for background restore (Phase 3)
        add_action(RISEUP_CRON_SNAPSHOT_RESTORE, array($this, 'executeCronRestore'));

        // Register cron hook for background incremental backup (Phase 3)
        add_action(RISEUP_CRON_SNAPSHOT_INCREMENTAL, array($this, 'executeCronIncremental'));

        // Schedule cleanup if not already scheduled
        $this->ensureCleanupScheduled();

        // Check and update scheduled snapshot based on settings
        $this->syncScheduleWithSettings();

        $this->logger->info('[SCHEDULER] Scheduler initialized');
    }

    /**
     * Execute a worker batch (called by WP-Cron from worker pool).
     *
     * Delegates to RiseupSnapshotWorker::processWorkerBatch().
     *
     * @param array $args { job_id: int }
     */
    public function executeWorkerBatch($args) {
        $this->logger->info('[SCHEDULER] Executing worker batch', $args);

        try {
            require_once dirname(__FILE__) . '/class-snapshot-factory.php';
            $worker = RiseupSnapshotFactory::worker($this->logger, $this->db);
            $worker->processWorkerBatch($args);
        } catch (Exception $e) {
            $this->logger->error('[SCHEDULER] Worker batch exception', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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
        // Weekly schedule
        if (!isset($schedules[RISEUP_SNAPSHOT_FREQ_WEEKLY])) {
            $schedules[RISEUP_SNAPSHOT_FREQ_WEEKLY] = array(
                'interval' => WEEK_IN_SECONDS,
                'display' => __('Once Weekly', 'riseup-asia-uploader'),
            );
        }

        // Monthly schedule (30 days)
        if (!isset($schedules[RISEUP_SNAPSHOT_FREQ_MONTHLY])) {
            $schedules[RISEUP_SNAPSHOT_FREQ_MONTHLY] = array(
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
        if (!wp_next_scheduled(RISEUP_CRON_SNAPSHOT_CLEANUP)) {
            // Schedule daily cleanup at 4 AM
            $timestamp = strtotime('tomorrow 04:00:00');
            wp_schedule_event($timestamp, 'daily', RISEUP_CRON_SNAPSHOT_CLEANUP);
            
            $this->logger->info('[SCHEDULER] Cleanup cron scheduled', array(
                'next_run' => date('c', $timestamp),
            ));
        }
    }

    /**
     * Sync cron schedule with user settings.
     * 
     * Call this when settings change or on plugin init.
     */
    public function syncScheduleWithSettings() {
        $settings = $this->detector->getSettings();

        // Clear any existing scheduled snapshot
        $this->clearScheduledSnapshot();

        // If scheduling is disabled, we're done
        if (!$settings['schedule_enabled']) {
            $this->logger->debug('[SCHEDULER] Scheduled snapshots disabled');
            return;
        }

        // Don't schedule for manual-only
        if ($settings['schedule_frequency'] === RISEUP_SNAPSHOT_FREQ_MANUAL) {
            $this->logger->debug('[SCHEDULER] Frequency set to manual - no cron scheduling');
            return;
        }

        // Calculate next run time
        $next_run = $this->calculateNextRunTime(
            $settings['schedule_frequency'],
            $settings['schedule_time'],
            $settings['schedule_day']
        );

        // Map frequency to WP cron recurrence
        $recurrence = $this->mapFrequencyToRecurrence($settings['schedule_frequency']);

        // Schedule the event
        $result = wp_schedule_event($next_run, $recurrence, RISEUP_CRON_SNAPSHOT_SCHEDULED);

        if ($result) {
            $this->logger->info('[SCHEDULER] Scheduled snapshot cron', array(
                'frequency' => $settings['schedule_frequency'],
                'next_run' => date('c', $next_run),
                'recurrence' => $recurrence,
            ));
        } else {
            $this->logger->error('[SCHEDULER] Failed to schedule snapshot cron');
        }
    }

    /**
     * Clear scheduled snapshot cron.
     */
    public function clearScheduledSnapshot() {
        $timestamp = wp_next_scheduled(RISEUP_CRON_SNAPSHOT_SCHEDULED);
        if ($timestamp) {
            wp_unschedule_event($timestamp, RISEUP_CRON_SNAPSHOT_SCHEDULED);
            $this->logger->debug('[SCHEDULER] Cleared scheduled snapshot cron');
        }
    }

    /**
     * Calculate next run timestamp based on settings.
     *
     * @param string $frequency Frequency type.
     * @param string $time      Time in HH:MM format.
     * @param int    $day       Day of week (1-7) or month (1-28).
     * @return int Unix timestamp.
     */
    private function calculateNextRunTime($frequency, $time, $day) {
        $now = current_time('timestamp');
        list($hour, $minute) = explode(':', $time);
        $hour = intval($hour);
        $minute = intval($minute);

        switch ($frequency) {
            case RISEUP_SNAPSHOT_FREQ_DAILY:
                // Next occurrence of the specified time
                $next = strtotime("today {$hour}:{$minute}:00");
                if ($next <= $now) {
                    $next = strtotime("tomorrow {$hour}:{$minute}:00");
                }
                return $next;

            case RISEUP_SNAPSHOT_FREQ_WEEKLY:
                // Day 1 = Monday, 7 = Sunday
                $day_name = $this->getDayName($day);
                $next = strtotime("next {$day_name} {$hour}:{$minute}:00");
                
                // If today is the day and time hasn't passed, use today
                if (date('N') == $day) {
                    $today = strtotime("today {$hour}:{$minute}:00");
                    if ($today > $now) {
                        $next = $today;
                    }
                }
                return $next;

            case RISEUP_SNAPSHOT_FREQ_MONTHLY:
                // Day of month (1-28)
                $day = min(28, max(1, $day)); // Clamp to valid range
                $current_month = date('Y-m');
                $next_month = date('Y-m', strtotime('+1 month'));
                
                $next = strtotime("{$current_month}-{$day} {$hour}:{$minute}:00");
                if ($next <= $now) {
                    $next = strtotime("{$next_month}-{$day} {$hour}:{$minute}:00");
                }
                return $next;

            default:
                // Default to daily
                return strtotime("tomorrow {$hour}:{$minute}:00");
        }
    }

    /**
     * Get day name from ISO day number.
     *
     * @param int $day Day number (1-7, Monday-Sunday).
     * @return string Day name.
     */
    private function getDayName($day) {
        $days = array(
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
        );
        return isset($days[$day]) ? $days[$day] : 'Monday';
    }

    /**
     * Map frequency constant to WP cron recurrence.
     *
     * @param string $frequency Frequency constant.
     * @return string WP cron recurrence name.
     */
    private function mapFrequencyToRecurrence($frequency) {
        switch ($frequency) {
            case RISEUP_SNAPSHOT_FREQ_DAILY:
                return 'daily';
            case RISEUP_SNAPSHOT_FREQ_WEEKLY:
                return RISEUP_SNAPSHOT_FREQ_WEEKLY;
            case RISEUP_SNAPSHOT_FREQ_MONTHLY:
                return RISEUP_SNAPSHOT_FREQ_MONTHLY;
            default:
                return 'daily';
        }
    }

    /**
     * Execute a scheduled snapshot (called by cron).
     *
     * Routes through the orchestrator with async=true so the worker pool
     * handles table export in cron-chained batches.
     */
    public function executeScheduledSnapshot() {
        $this->logger->info('[SCHEDULER] Executing scheduled snapshot via orchestrator');

        try {
            $settings = $this->detector->getSettings();

            require_once dirname(__FILE__) . '/class-snapshot-factory.php';
            $manager = RiseupSnapshotFactory::manager($this->logger, $this->db);
            $orchestrator = RiseupSnapshotFactory::orchestrator($this->logger, $this->db, $manager);

            $result = $orchestrator->executeFullBackup(array(
                'scope'   => $settings['default_scope'] ?? RISEUP_SNAPSHOT_SCOPE_WORDPRESS,
                'trigger' => RISEUP_SNAPSHOT_TRIGGER_CRON,
                'title'   => 'Scheduled Backup ' . date('Y-m-d H:i'),
                'async'   => true,
            ));

            if ($result['success']) {
                $this->logger->info('[SCHEDULER] Scheduled snapshot job created', array(
                    'job_id'      => $result['job_id'] ?? null,
                    'snapshot_id' => $result['snapshot_id'] ?? null,
                    'async'       => $result['async'] ?? false,
                ));
            } else {
                $this->logger->error('[SCHEDULER] Scheduled snapshot failed', array(
                    'error' => $result['error'] ?? 'Unknown',
                ));
            }

        } catch (Exception $e) {
            $this->logger->error('[SCHEDULER] Exception during scheduled snapshot', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ));
        }
    }

    /**
     * Execute an immediate snapshot (called by cron from "Snapshot Now").
     *
     * Routes through the orchestrator with async=true so table export
     * continues in the background via cron-chained batches.
     *
     * @param array $args { snapshot_type, title, scope, options }.
     */
    public function executeImmediateSnapshot($args) {
        $this->logger->info('[SCHEDULER] Executing immediate snapshot via orchestrator', $args);

        try {
            require_once dirname(__FILE__) . '/class-snapshot-factory.php';
            $manager = RiseupSnapshotFactory::manager($this->logger, $this->db);
            $orchestrator = RiseupSnapshotFactory::orchestrator($this->logger, $this->db, $manager);

            $snapshot_type = $args['snapshot_type'] ?? RISEUP_SNAPSHOT_TYPE_FULL;

            if ($snapshot_type === RISEUP_SNAPSHOT_TYPE_INCREMENTAL) {
                $result = $orchestrator->executeIncrementalBackup(array(
                    'title'              => $args['title'] ?? 'Incremental Backup ' . date('Y-m-d H:i'),
                    'master_snapshot_id' => $args['master_snapshot_id'] ?? null,
                ));
            } else {
                $result = $orchestrator->executeFullBackup(array(
                    'title'   => $args['title'] ?? 'Manual Backup ' . date('Y-m-d H:i'),
                    'scope'   => $args['scope'] ?? RISEUP_SNAPSHOT_SCOPE_WORDPRESS,
                    'trigger' => RISEUP_SNAPSHOT_TRIGGER_MANUAL,
                    'async'   => true,
                ));
            }

            if ($result['success']) {
                $this->logger->info('[SCHEDULER] Immediate snapshot job created', array(
                    'job_id'      => $result['job_id'] ?? null,
                    'snapshot_id' => $result['snapshot_id'] ?? null,
                    'type'        => $snapshot_type,
                ));
            } else {
                $this->logger->error('[SCHEDULER] Immediate snapshot failed', array(
                    'error' => $result['error'] ?? 'Unknown',
                ));
            }

        } catch (Exception $e) {
            $this->logger->error('[SCHEDULER] Exception during immediate snapshot', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ));
        }
    }

    /**
     * Execute a background restore operation (called by cron).
     *
     * @param array $args { snapshot_id, options }.
     */
    public function executeCronRestore($args) {
        $this->logger->info('[SCHEDULER] Executing cron-based restore', $args);

        if (empty($args['snapshot_id'])) {
            $this->logger->error('[SCHEDULER] Missing snapshot_id for cron restore');
            return;
        }

        try {
            require_once dirname(__FILE__) . '/class-snapshot-factory.php';
            $manager = RiseupSnapshotFactory::manager($this->logger, $this->db);

            $restore_options = $args['options'] ?? array();
            $restore_options['confirm'] = true; // Already confirmed before scheduling

            $result = $manager->restoreSnapshot($args['snapshot_id'], $restore_options);

            if ($result['success']) {
                $this->logger->info('[SCHEDULER] Cron restore completed', array(
                    'snapshot_id' => $args['snapshot_id'],
                    'tables'      => $result['tables'] ?? 0,
                    'rows'        => $result['rows'] ?? 0,
                    'duration'    => round($result['duration'] ?? 0, 2) . 's',
                ));
            } else {
                $this->logger->error('[SCHEDULER] Cron restore failed', array(
                    'snapshot_id' => $args['snapshot_id'],
                    'error'       => $result['error'] ?? 'Unknown',
                ));
            }

        } catch (Exception $e) {
            $this->logger->error('[SCHEDULER] Exception during cron restore', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ));
        }
    }

    /**
     * Execute a background incremental backup (called by cron).
     *
     * @param array $args { title, master_snapshot_id }.
     */
    public function executeCronIncremental($args) {
        $this->logger->info('[SCHEDULER] Executing cron-based incremental backup', $args);

        try {
            require_once dirname(__FILE__) . '/class-snapshot-factory.php';
            $manager = RiseupSnapshotFactory::manager($this->logger, $this->db);
            $orchestrator = RiseupSnapshotFactory::orchestrator($this->logger, $this->db, $manager);

            $result = $orchestrator->executeIncrementalBackup(array(
                'title'              => $args['title'] ?? 'Incremental Backup ' . date('Y-m-d H:i'),
                'master_snapshot_id' => $args['master_snapshot_id'] ?? null,
            ));

            if ($result['success']) {
                $this->logger->info('[SCHEDULER] Cron incremental backup completed', array(
                    'tables_changed' => $result['tables_changed'] ?? 0,
                    'total_new_rows' => $result['total_new_rows'] ?? 0,
                ));
            } else {
                $this->logger->error('[SCHEDULER] Cron incremental backup failed', array(
                    'error' => $result['error'] ?? 'Unknown',
                ));
            }

        } catch (Exception $e) {
            $this->logger->error('[SCHEDULER] Exception during cron incremental', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ));
        }
    }

    /**
     * Execute cleanup of old snapshots based on retention policy.
     * 
     * Uses RiseupSnapshotCleaner for comprehensive cleanup including:
     * - Retention policy enforcement (days or count based)
     * - Orphan file cleanup (files without database records)
     * - Failed/stuck snapshot cleanup (older than 24 hours)
     */
    public function executeCleanup() {
        $this->logger->info('[SCHEDULER] Executing snapshot cleanup');

        try {
            $settings = $this->detector->getSettings();
            $cleaner = RiseupSnapshotFactory::cleaner($this->logger, $this->db);
            
            $result = $cleaner->runCleanup($settings);

            $this->logger->info('[SCHEDULER] Cleanup complete', array(
                'deleted_by_policy' => $result['deleted_by_policy'],
                'deleted_orphans' => $result['deleted_orphans'],
                'deleted_failed' => $result['deleted_failed'],
                'space_freed' => RiseupPathUtils::formatBytes($result['space_freed_bytes']),
                'errors_count' => count($result['errors']),
            ));

        } catch (Exception $e) {
            $this->logger->error('[SCHEDULER] Exception during cleanup', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ));
        }
    }

    /**
     * Get storage statistics for the scheduler status panel.
     *
     * @return array Storage stats from cleaner.
     */
    public function getStorageStats() {
        $cleaner = RiseupSnapshotFactory::cleaner($this->logger, $this->db);
        return $cleaner->getStorageStats();
    }

    /**
     * Estimate what cleanup would remove without actually deleting.
     *
     * @return array Cleanup estimate.
     */
    public function estimateCleanup() {
        $settings = $this->detector->getSettings();
        $cleaner = RiseupSnapshotFactory::cleaner($this->logger, $this->db);
        return $cleaner->estimateCleanup($settings);
    }

    /**
     * Run cleanup manually (not via cron).
     *
     * @return array Cleanup result.
     */
    public function runManualCleanup() {
        $settings = $this->detector->getSettings();
        $cleaner = RiseupSnapshotFactory::cleaner($this->logger, $this->db);
        return $cleaner->runCleanup($settings);
    }

    /**
     * Get scheduler status information.
     *
     * @return array Status info.
     */
    public function getStatus() {
        $settings = $this->detector->getSettings();
        
        $scheduled_next = wp_next_scheduled(RISEUP_CRON_SNAPSHOT_SCHEDULED);
        $cleanup_next = wp_next_scheduled(RISEUP_CRON_SNAPSHOT_CLEANUP);

        return array(
            'schedule_enabled' => $settings['schedule_enabled'],
            'frequency' => $settings['schedule_frequency'],
            'time' => $settings['schedule_time'],
            'day' => $settings['schedule_day'],
            'next_scheduled_snapshot' => $scheduled_next ? date('c', $scheduled_next) : null,
            'next_cleanup' => $cleanup_next ? date('c', $cleanup_next) : null,
            'retention_type' => $settings['retention_type'],
            'retention_days' => $settings['retention_days'],
            'retention_count' => $settings['retention_count'],
        );
    }

    /**
     * Trigger a manual "Snapshot Now" operation.
     *
     * Instead of executing synchronously, schedules a WP-Cron event
     * that will be picked up within seconds. This ensures the backup
     * runs in the background and completes even if the browser tab is closed.
     *
     * @param array $options Snapshot options (snapshot_type, title, scope, master_snapshot_id).
     * @return array Result with job status (always returns immediately).
     */
    public function triggerSnapshotNow($options = array()) {
        $this->logger->info('[SCHEDULER] Snapshot Now triggered (scheduling cron)', $options);

        try {
            $settings = $this->detector->getSettings();

            $snapshot_type = $options['snapshot_type'] ?? RISEUP_SNAPSHOT_TYPE_FULL;
            $title = $options['title'] ?? ($snapshot_type === RISEUP_SNAPSHOT_TYPE_INCREMENTAL
                ? 'Incremental Backup ' . date('Y-m-d H:i')
                : 'Manual Backup ' . date('Y-m-d H:i'));

            $cron_args = array(
                'snapshot_type'      => $snapshot_type,
                'title'              => $title,
                'scope'              => $options['scope'] ?? $settings['default_scope'] ?? RISEUP_SNAPSHOT_SCOPE_WORDPRESS,
                'master_snapshot_id' => $options['master_snapshot_id'] ?? null,
            );

            // Schedule the cron event to fire ASAP (within next 5 seconds)
            $scheduled = wp_schedule_single_event(
                time() + 5,
                RISEUP_CRON_SNAPSHOT_IMMEDIATE,
                array($cron_args)
            );

            if ($scheduled === false) {
                $this->logger->error('[SCHEDULER] Failed to schedule Snapshot Now cron event');
                return array(
                    'success' => false,
                    'error'   => 'Failed to schedule background snapshot job',
                );
            }

            $this->logger->info('[SCHEDULER] Snapshot Now scheduled as background cron job', array(
                'type'  => $snapshot_type,
                'title' => $title,
            ));

            return array(
                'success'       => true,
                'scheduled'     => true,
                'snapshot_type' => $snapshot_type,
                'title'         => $title,
                'message'       => 'Snapshot has been scheduled and will run in the background.',
            );

        } catch (Exception $e) {
            $this->logger->error('[SCHEDULER] Snapshot Now scheduling failed', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ));

            return array(
                'success' => false,
                'error' => $e->getMessage(),
            );
        }
    }

    /**
     * Schedule a restore operation to run in the background via WP-Cron.
     *
     * @param int   $snapshot_id Snapshot ID to restore.
     * @param array $options     Restore options.
     * @return array Result with scheduling status.
     */
    public function scheduleRestore($snapshot_id, $options = array()) {
        $this->logger->info('[SCHEDULER] Scheduling background restore', array(
            'snapshot_id' => $snapshot_id,
        ));

        $cron_args = array(
            'snapshot_id' => $snapshot_id,
            'options'     => $options,
        );

        $scheduled = wp_schedule_single_event(
            time() + 5,
            RISEUP_CRON_SNAPSHOT_RESTORE,
            array($cron_args)
        );

        if ($scheduled === false) {
            return array(
                'success' => false,
                'error'   => 'Failed to schedule background restore',
            );
        }

        return array(
            'success'     => true,
            'scheduled'   => true,
            'snapshot_id' => $snapshot_id,
            'message'     => 'Restore has been scheduled and will run in the background.',
        );
    }

    /**
     * Clear all scheduled events on plugin deactivation.
     */
    public function clearAllSchedules() {
        // Clear scheduled snapshot
        $this->clearScheduledSnapshot();

        // Clear cleanup
        $cleanup = wp_next_scheduled(RISEUP_CRON_SNAPSHOT_CLEANUP);
        if ($cleanup) {
            wp_unschedule_event($cleanup, RISEUP_CRON_SNAPSHOT_CLEANUP);
        }

        // Clear any pending immediate snapshots
        wp_unschedule_hook(RISEUP_CRON_SNAPSHOT_IMMEDIATE);

        // Clear any pending worker batches
        wp_unschedule_hook(RISEUP_CRON_SNAPSHOT_WORKER_BATCH);

        // Clear any pending restore operations
        wp_unschedule_hook(RISEUP_CRON_SNAPSHOT_RESTORE);

        // Clear any pending incremental backups
        wp_unschedule_hook(RISEUP_CRON_SNAPSHOT_INCREMENTAL);

        $this->logger->info('[SCHEDULER] All schedules cleared');
    }
}
