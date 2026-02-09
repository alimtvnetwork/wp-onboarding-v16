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

        // Schedule cleanup if not already scheduled
        $this->ensureCleanupScheduled();

        // Check and update scheduled snapshot based on settings
        $this->syncScheduleWithSettings();

        $this->logger->info('[SCHEDULER] Scheduler initialized');
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
     */
    public function executeScheduledSnapshot() {
        $this->logger->info('[SCHEDULER] Executing scheduled snapshot');

        try {
            $settings = $this->detector->getSettings();
            $provider = $this->detector->getProviderInstance();

            $options = array(
                'scope' => $settings['default_scope'],
                'tables' => $settings['custom_tables'],
                'trigger' => RISEUP_SNAPSHOT_TRIGGER_CRON,
            );

            $result = $provider->createSnapshot($options);

            if ($result['success']) {
                $this->logger->info('[SCHEDULER] Scheduled snapshot created successfully', array(
                    'snapshot_id' => $result['snapshot_id'],
                    'filename' => $result['filename'],
                ));
            } else {
                $this->logger->error('[SCHEDULER] Scheduled snapshot failed', array(
                    'error' => $result['error'],
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
     * @param array $args Snapshot arguments (snapshot_id, tables).
     */
    public function executeImmediateSnapshot($args) {
        $this->logger->info('[SCHEDULER] Executing immediate snapshot', $args);

        if (empty($args['snapshot_id']) || empty($args['tables'])) {
            $this->logger->error('[SCHEDULER] Invalid arguments for immediate snapshot');
            return;
        }

        try {
            $provider = $this->detector->getProviderInstance();

            // The provider's executeSnapshot method handles the actual work
            $result = $provider->executeSnapshot($args['snapshot_id'], $args['tables']);

            if ($result['success']) {
                $this->logger->info('[SCHEDULER] Immediate snapshot completed', array(
                    'snapshot_id' => $result['snapshot_id'],
                    'size' => RiseupPathUtils::formatBytes($result['size']),
                    'duration' => round($result['duration'], 2) . 's',
                ));
            } else {
                $this->logger->error('[SCHEDULER] Immediate snapshot failed', array(
                    'error' => $result['error'],
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
     * @param array $options Snapshot options.
     * @return array Result with snapshot_id or error.
     */
    public function triggerSnapshotNow($options = array()) {
        $this->logger->info('[SCHEDULER] Snapshot Now triggered', $options);

        try {
            $settings = $this->detector->getSettings();
            $provider = $this->detector->getProviderInstance();

            $defaults = array(
                'scope' => $settings['default_scope'],
                'tables' => $settings['custom_tables'],
                'trigger' => RISEUP_SNAPSHOT_TRIGGER_MANUAL,
            );

            $options = array_merge($defaults, $options);

            return $provider->createSnapshot($options);

        } catch (Exception $e) {
            $this->logger->error('[SCHEDULER] Snapshot Now failed', array(
                'error' => $e->getMessage(),
            ));

            return array(
                'success' => false,
                'error' => $e->getMessage(),
            );
        }
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

        $this->logger->info('[SCHEDULER] All schedules cleared');
    }
}
