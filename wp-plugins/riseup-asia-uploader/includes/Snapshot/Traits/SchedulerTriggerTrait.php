<?php
/**
 * Scheduler Trigger Trait
 *
 * Public trigger methods: triggerSnapshotNow, scheduleRestore,
 * clearAllSchedules, getStatus, getStorageStats, estimateCleanup, runManualCleanup.
 *
 * @package RiseupAsiaUploader
 * @since   1.9.0
 */

use RiseupAsia\Enums\HookType;
use RiseupAsia\Enums\SnapshotModeType;
use RiseupAsia\Enums\SnapshotScopeType;

if (!defined('ABSPATH')) {
    exit;
}

trait SchedulerTriggerTrait {

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

        $scheduled_next = wp_next_scheduled(HookType::CronSnapshotScheduled->value);
        $cleanup_next = wp_next_scheduled(HookType::CronSnapshotCleanup->value);

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
     * Trigger a manual "Snapshot Now" operation via WP-Cron.
     *
     * @param array $options Snapshot options (snapshot_type, title, scope, master_snapshot_id).
     * @return array Result with job status.
     */
    public function triggerSnapshotNow($options = array()) {
        $this->logger->info('[SCHEDULER] Snapshot Now triggered (scheduling cron)', $options);

        try {
            $settings = $this->detector->getSettings();

            $snapshot_type = $options['snapshot_type'] ?? SnapshotModeType::Full->value;
            $title = $options['title'] ?? ($snapshot_type === SnapshotModeType::Incremental->value
                ? 'Incremental Backup ' . date('Y-m-d H:i')
                : 'Manual Backup ' . date('Y-m-d H:i'));

            $cron_args = array(
                'snapshot_type'      => $snapshot_type,
                'title'              => $title,
                'scope'              => $options['scope'] ?? $settings['default_scope'] ?? SnapshotScopeType::WordPress->value,
                'master_snapshot_id' => $options['master_snapshot_id'] ?? null,
            );

            $scheduled = wp_schedule_single_event(
                time() + 5,
                HookType::CronSnapshotImmediate->value,
                array($cron_args)
            );

            if ($scheduled === false) {
                $this->logger->error('[SCHEDULER] Failed to schedule Snapshot Now cron event');
                return array('success' => false, 'error' => 'Failed to schedule background snapshot job');
            }

            $this->logger->info('[SCHEDULER] Snapshot Now scheduled as background cron job', array(
                'type'  => $snapshot_type, 'title' => $title,
            ));

            return array(
                'success'       => true,
                'scheduled'     => true,
                'snapshot_type' => $snapshot_type,
                'title'         => $title,
                'message'       => 'Snapshot has been scheduled and will run in the background.',
            );

        } catch (Throwable $e) {
            $this->logger->error('[SCHEDULER] Snapshot Now scheduling failed', array(
                'error' => $e->getMessage(), 'trace' => $e->getTraceAsString(),
            ));
            return array('success' => false, 'error' => $e->getMessage());
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
        $this->logger->info('[SCHEDULER] Scheduling background restore', array('snapshot_id' => $snapshot_id));

        $cron_args = array('snapshot_id' => $snapshot_id, 'options' => $options);

        $scheduled = wp_schedule_single_event(time() + 5, HookType::CronSnapshotRestore->value, array($cron_args));

        if ($scheduled === false) {
            return array('success' => false, 'error' => 'Failed to schedule background restore');
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
        $this->clearScheduledSnapshot();

        $cleanup = wp_next_scheduled(HookType::CronSnapshotCleanup->value);
        if ($cleanup) {
            wp_unschedule_event($cleanup, HookType::CronSnapshotCleanup->value);
        }

        wp_unschedule_hook(HookType::CronSnapshotImmediate->value);
        wp_unschedule_hook(HookType::CronSnapshotWorkerBatch->value);
        wp_unschedule_hook(HookType::CronSnapshotRestore->value);
        wp_unschedule_hook(HookType::CronSnapshotIncremental->value);

        $this->logger->info('[SCHEDULER] All schedules cleared');
    }
}
