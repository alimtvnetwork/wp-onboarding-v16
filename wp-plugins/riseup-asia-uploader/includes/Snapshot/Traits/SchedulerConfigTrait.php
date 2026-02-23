<?php
/**
 * SchedulerConfigTrait — Cron schedule registration, sync, and cleanup scheduling.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.9.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\HookType;
use RiseupAsia\Enums\SnapshotFrequencyType;
use RiseupAsia\Helpers\BooleanHelpers;

trait SchedulerConfigTrait {
    public function registerCronSchedules(array $schedules): array {
        if (BooleanHelpers::isKeyMissing($schedules, SnapshotFrequencyType::Hourly->value)) {
            $schedules[SnapshotFrequencyType::Hourly->value] = array(
                'interval' => HOUR_IN_SECONDS,
                'display'  => __('Once Hourly', 'riseup-asia-uploader'),
            );
        }

        if (BooleanHelpers::isKeyMissing($schedules, SnapshotFrequencyType::Weekly->value)) {
            $schedules[SnapshotFrequencyType::Weekly->value] = array(
                'interval' => WEEK_IN_SECONDS,
                'display'  => __('Once Weekly', 'riseup-asia-uploader'),
            );
        }

        if (BooleanHelpers::isKeyMissing($schedules, SnapshotFrequencyType::Monthly->value)) {
            $schedules[SnapshotFrequencyType::Monthly->value] = array(
                'interval' => 30 * DAY_IN_SECONDS,
                'display'  => __('Once Monthly', 'riseup-asia-uploader'),
            );
        }

        return $schedules;
    }

    private function ensureCleanupScheduled(): void {
        if (BooleanHelpers::isWpScheduleMissing(HookType::CronSnapshotCleanup->value)) {
            $timestamp = strtotime('tomorrow 04:00:00');
            wp_schedule_event($timestamp, 'daily', HookType::CronSnapshotCleanup->value);
            $this->logger->info('[SCHEDULER] Cleanup cron scheduled', array('nextRun' => date('c', $timestamp)));
        }
    }

    public function syncScheduleWithSettings(): void {
        $settings = $this->detector->getSettings();
        $this->clearScheduledSnapshot();
        $isScheduleDisabled = ($settings['schedule_enabled'] === false || empty($settings['schedule_enabled']));

        if ($isScheduleDisabled) {
            $this->logger->debug('[SCHEDULER] Scheduled snapshots disabled');

            return;
        }

        if ($settings['schedule_frequency'] === SnapshotFrequencyType::Manual->value) {
            $this->logger->debug('[SCHEDULER] Frequency set to manual - no cron scheduling');

            return;
        }

        $next_run = $this->calculateNextRunTime(
            $settings['schedule_frequency'],
            $settings['schedule_time'],
            $settings['schedule_day'],
        );
        $recurrence = $this->mapFrequencyToRecurrence($settings['schedule_frequency']);
        $result = wp_schedule_event(
            $next_run,
            $recurrence,
            HookType::CronSnapshotScheduled->value,
        );

        if ($result) {
            $this->logger->info('[SCHEDULER] Scheduled snapshot cron', array(
                'frequency'  => $settings['schedule_frequency'],
                'nextRun'    => date('c', $next_run),
                'recurrence' => $recurrence,
            ));
        } else {
            $this->logger->error('[SCHEDULER] Failed to schedule snapshot cron');
        }
    }

    public function clearScheduledSnapshot(): void {
        $timestamp = wp_next_scheduled(HookType::CronSnapshotScheduled->value);

        if ($timestamp) {
            wp_unschedule_event($timestamp, HookType::CronSnapshotScheduled->value);
            $this->logger->debug('[SCHEDULER] Cleared scheduled snapshot cron');
        }
    }
}
