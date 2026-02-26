<?php
/**
 * Scheduler Trigger Trait
 *
 * Public trigger methods.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.9.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use Throwable;
use RiseupAsia\Enums\HookType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\SettingsKeyType;
use RiseupAsia\Enums\SnapshotModeType;
use RiseupAsia\Enums\SnapshotScopeType;
use RiseupAsia\Helpers\DateHelper;
use RiseupAsia\Helpers\ResultHelper;
use RiseupAsia\Snapshot\SnapshotFactory;

trait SchedulerTriggerTrait {
    public function getStorageStats(): array {
        $cleaner = SnapshotFactory::cleaner($this->logger, $this->db);

        return $cleaner->getStorageStats();
    }

    public function estimateCleanup(): array {
        $settings = $this->detector->getSettings();
        $cleaner = SnapshotFactory::cleaner($this->logger, $this->db);

        return $cleaner->estimateCleanup($settings);
    }

    public function runManualCleanup(): array {
        $settings = $this->detector->getSettings();
        $cleaner = SnapshotFactory::cleaner($this->logger, $this->db);

        return $cleaner->runCleanup($settings);
    }

    public function getStatus(): array {
        $settings = $this->detector->getSettings();
        $scheduledNext = wp_next_scheduled(HookType::CronSnapshotScheduled->value);
        $cleanupNext = wp_next_scheduled(HookType::CronSnapshotCleanup->value);

        return array(
            ResponseKeyType::ScheduleEnabled->value       => $settings[SettingsKeyType::ScheduleEnabled->value],
            ResponseKeyType::Frequency->value              => $settings[SettingsKeyType::ScheduleFrequency->value],
            ResponseKeyType::Time->value                   => $settings[SettingsKeyType::ScheduleTime->value],
            ResponseKeyType::Day->value                    => $settings[SettingsKeyType::ScheduleDay->value],
            ResponseKeyType::NextScheduledSnapshot->value => $scheduledNext ? DateHelper::formatIso($scheduledNext) : null,
            ResponseKeyType::NextCleanup->value           => $cleanupNext ? DateHelper::formatIso($cleanupNext) : null,
            ResponseKeyType::RetentionType->value         => $settings[SettingsKeyType::RetentionType->value],
            ResponseKeyType::RetentionDays->value         => $settings[SettingsKeyType::RetentionDays->value],
            ResponseKeyType::RetentionCount->value        => $settings[SettingsKeyType::RetentionCount->value],
        );
    }

    public function triggerSnapshotNow(array $options = array()): array {
        $this->logger->info('[SCHEDULER] Snapshot Now triggered (scheduling cron)', $options);

        try {
            $settings = $this->detector->getSettings();
            $snapshotType = $options[ResponseKeyType::SnapshotType->value] ?? SnapshotModeType::Full->value;
            $title = $options[ResponseKeyType::Title->value] ?? ($snapshotType === SnapshotModeType::Incremental->value
                ? 'Incremental Backup ' . DateHelper::nowCompactDatetime()
                : 'Manual Backup ' . DateHelper::nowCompactDatetime());

            $cronArgs = array(
                ResponseKeyType::SnapshotType->value      => $snapshotType,
                ResponseKeyType::Title->value             => $title,
                ResponseKeyType::Scope->value             => $options[ResponseKeyType::Scope->value] ?? $settings[SettingsKeyType::DefaultScope->value] ?? SnapshotScopeType::WordPress->value,
                ResponseKeyType::MasterSnapshotId->value  => $options[ResponseKeyType::MasterSnapshotId->value] ?? null,
            );

            $scheduled = wp_schedule_single_event(
                time() + 5,
                HookType::CronSnapshotImmediate->value,
                array($cronArgs),
            );

            if ($scheduled === false) {
                $this->logger->error('[SCHEDULER] Failed to schedule Snapshot Now cron event');

                return ResultHelper::error('Failed to schedule background snapshot job');
            }

            $this->logger->info('[SCHEDULER] Snapshot Now scheduled as background cron job', array(
                ResponseKeyType::Type->value  => $snapshotType,
                ResponseKeyType::Title->value => $title,
            ));

            return ResultHelper::ok(array(
                ResponseKeyType::Scheduled->value        => true,
                ResponseKeyType::SnapshotType->value     => $snapshotType,
                ResponseKeyType::Title->value            => $title,
                ResponseKeyType::Message->value          => 'Snapshot has been scheduled and will run in the background.',
            ));
        } catch (Throwable $e) {
            $this->logger->error('[SCHEDULER] Snapshot Now scheduling failed', array(
                ResponseKeyType::Error->value => $e->getMessage(),
                ResponseKeyType::Trace->value => $e->getTraceAsString(),
            ));

            return ResultHelper::errorFromException($e);
        }
    }

    public function scheduleRestore(int $snapshotId, array $options = array()): array {
        $this->logger->info('[SCHEDULER] Scheduling background restore', array(ResponseKeyType::SnapshotId->value => $snapshotId));

        $cronArgs = array(
            ResponseKeyType::SnapshotId->value => $snapshotId,
            ResponseKeyType::Options->value    => $options,
        );

        $scheduled = wp_schedule_single_event(
            time() + 5,
            HookType::CronSnapshotRestore->value,
            array($cronArgs),
        );

        if ($scheduled === false) {
            return ResultHelper::error('Failed to schedule background restore');
        }

        return ResultHelper::ok(array(
            ResponseKeyType::Scheduled->value      => true,
            ResponseKeyType::SnapshotId->value     => $snapshotId,
            ResponseKeyType::Message->value        => 'Restore has been scheduled and will run in the background.',
        ));
    }

    public function clearAllSchedules(): void {
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
