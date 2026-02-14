<?php
/**
 * SchedulerConfigTrait — Cron schedule registration, sync, and cleanup scheduling.
 *
 * @package RiseupAsiaUploader
 * @since   1.9.0
 */

use RiseupAsia\Enums\HookType;

trait SchedulerConfigTrait {

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
        if (!wp_next_scheduled(HookType::CronSnapshotCleanup->value)) {
            $timestamp = strtotime('tomorrow 04:00:00');
            wp_schedule_event($timestamp, 'daily', HookType::CronSnapshotCleanup->value);
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
        $result = wp_schedule_event($next_run, $recurrence, HookType::CronSnapshotScheduled->value);

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
        $timestamp = wp_next_scheduled(HookType::CronSnapshotScheduled->value);
        if ($timestamp) {
            wp_unschedule_event($timestamp, HookType::CronSnapshotScheduled->value);
            $this->logger->debug('[SCHEDULER] Cleared scheduled snapshot cron');
        }
    }
}
