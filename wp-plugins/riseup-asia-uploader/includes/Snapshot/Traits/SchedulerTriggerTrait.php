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
use RiseupAsia\Enums\SnapshotModeType;
use RiseupAsia\Enums\SnapshotScopeType;

trait SchedulerTriggerTrait {

    public function getStorageStats(): array {
        $cleaner = RiseupSnapshotFactory::cleaner($this->logger, $this->db);
        return $cleaner->getStorageStats();
    }

    public function estimateCleanup(): array {
        $settings = $this->detector->getSettings();
        $cleaner = RiseupSnapshotFactory::cleaner($this->logger, $this->db);
        return $cleaner->estimateCleanup($settings);
    }

    public function runManualCleanup(): array {
        $settings = $this->detector->getSettings();
        $cleaner = RiseupSnapshotFactory::cleaner($this->logger, $this->db);
        return $cleaner->runCleanup($settings);
    }

    public function getStatus(): array {
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

    public function triggerSnapshotNow(array $options = array()): array {
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

    public function scheduleRestore(int $snapshotId, array $options = array()): array {
        $this->logger->info('[SCHEDULER] Scheduling background restore', array('snapshot_id' => $snapshotId));

        $cron_args = array('snapshot_id' => $snapshotId, 'options' => $options);

        $scheduled = wp_schedule_single_event(time() + 5, HookType::CronSnapshotRestore->value, array($cron_args));

        if ($scheduled === false) {
            return array('success' => false, 'error' => 'Failed to schedule background restore');
        }

        return array(
            'success'     => true,
            'scheduled'   => true,
            'snapshot_id' => $snapshotId,
            'message'     => 'Restore has been scheduled and will run in the background.',
        );
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
