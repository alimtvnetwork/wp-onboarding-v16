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
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\SnapshotFrequencyType;
use RiseupAsia\Helpers\BooleanHelpers;

trait SchedulerConfigTrait {
    public function registerCronSchedules(array $schedules): array {
        $pluginSlug = PluginConfigType::Slug->value;

        if (BooleanHelpers::isKeyMissing($schedules, SnapshotFrequencyType::Hourly->value)) {
            $schedules[SnapshotFrequencyType::Hourly->value] = array(
                'interval' => HOUR_IN_SECONDS,
                'display'  => __('Once Hourly', $pluginSlug),
            );
        }

        if (BooleanHelpers::isKeyMissing($schedules, SnapshotFrequencyType::Weekly->value)) {
            $schedules[SnapshotFrequencyType::Weekly->value] = array(
                'interval' => WEEK_IN_SECONDS,
                'display'  => __('Once Weekly', $pluginSlug),
            );
        }

        if (BooleanHelpers::isKeyMissing($schedules, SnapshotFrequencyType::Monthly->value)) {
            $schedules[SnapshotFrequencyType::Monthly->value] = array(
                'interval' => 30 * DAY_IN_SECONDS,
                'display'  => __('Once Monthly', $pluginSlug),
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
        $isScheduleEnabled = !empty($settings[SettingsKeyType::ScheduleEnabled->value]);

        if (!$isScheduleEnabled) {
            $this->logger->debug('[SCHEDULER] Scheduled snapshots disabled');

            return;
        }

        $frequency = $settings[SettingsKeyType::ScheduleFrequency->value] ?? SnapshotFrequencyType::Manual->value;
        $isManualFrequency = ($frequency === SnapshotFrequencyType::Manual->value);

        if ($isManualFrequency) {
            $this->logger->debug('[SCHEDULER] Frequency set to manual - no cron scheduling');

            return;
        }

        $scheduleTime = $settings[SettingsKeyType::ScheduleTime->value] ?? '04:00';
        $scheduleDay = $settings[SettingsKeyType::ScheduleDay->value] ?? 'monday';
        $nextRun = $this->calculateNextRunTime($frequency, $scheduleTime, $scheduleDay);
        $recurrence = $this->mapFrequencyToRecurrence($frequency);
        $result = wp_schedule_event(
            $nextRun,
            $recurrence,
            HookType::CronSnapshotScheduled->value,
        );

        if ($result) {
            $this->logger->info('[SCHEDULER] Scheduled snapshot cron', array(
                'frequency'  => $frequency,
                'nextRun'    => date('c', $nextRun),
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
