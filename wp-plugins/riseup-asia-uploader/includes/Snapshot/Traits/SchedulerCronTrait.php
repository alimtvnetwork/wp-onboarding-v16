<?php
/**
 * Scheduler Cron Infrastructure Trait
 *
 * Shared cron-job wrapper, result building, and factory helpers.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.9.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use Throwable;

use RiseupAsia\Enums\LogCategoryType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\SnapshotModeType;
use RiseupAsia\Enums\SnapshotScopeType;
use RiseupAsia\Enums\SnapshotTriggerType;
use RiseupAsia\Helpers\DateHelper;
use RiseupAsia\Snapshot\SnapshotFactory;
use RiseupAsia\Snapshot\SnapshotManager;
use RiseupAsia\Snapshot\SnapshotOrchestrator;
use RiseupAsia\Enums\StatusType;
use RiseupAsia\Enums\TriggerSourceType;

trait SchedulerCronTrait {
    private function executeCronJob(string $label, callable $work) {
        $this->logger->info("[SCHEDULER] Executing {$label}");

        try {
            $result = $work();
            $this->logCronResult($label, $result);
        } catch (Throwable $e) {
            $this->logger->error("[SCHEDULER] Exception during {$label}", array(
                ResponseKeyType::Error->value => $e->getMessage(),
                ResponseKeyType::Trace->value => $e->getTraceAsString(),
            ));
        }
    }

    private function logCronResult(string $label, array $result) {
        $isSuccess = $result[ResponseKeyType::Success->value] ?? false;
        $suffix = $isSuccess ? 'completed' : 'failed';
        $level = $isSuccess ? 'info' : 'error';

        $this->logger->{$level}("[SCHEDULER] {$label} {$suffix}", $result[ResponseKeyType::LogDataKey->value] ?? array());
        $this->writeCronAuditTrail($result, $isSuccess);
    }

    private function writeCronAuditTrail(array $result, bool $isSuccess): void {
        $shouldSkip = $result[ResponseKeyType::SkipAudit->value] ?? false;

        if ($shouldSkip) {
            return;
        }

        $this->db->logTransaction(
            $result[ResponseKeyType::Action->value],
            LogCategoryType::Snapshot->value,
            null,
            '',
            null,
            '',
            $result[ResponseKeyType::AuditData->value] ?? array(),
            $isSuccess ? StatusType::Success->value : StatusType::Failed->value,
            $isSuccess ? null : ($result[ResponseKeyType::Error->value] ?? 'Unknown'),
            array(
                ResponseKeyType::TriggeredBy->value => $result[ResponseKeyType::TriggeredBy->value] ?? TriggerSourceType::Cron->value,
            ),
        );
    }

    private function buildCronResult(
        array $result,
        string $action,
        string $triggeredBy,
        array $auditData = array(),
    ): array {
        return array(
            ResponseKeyType::Success->value     => $result[ResponseKeyType::Success->value] ?? false,
            ResponseKeyType::Error->value       => $result[ResponseKeyType::Error->value] ?? null,
            ResponseKeyType::Action->value      => $action,
            ResponseKeyType::TriggeredBy->value => $triggeredBy,
            ResponseKeyType::AuditData->value   => $auditData,
            ResponseKeyType::LogDataKey->value  => $result,
            ResponseKeyType::SkipAudit->value   => false,
        );
    }

    private function createOrchestrator(): array {
        $manager = SnapshotFactory::manager($this->logger, $this->db);
        $orchestrator = SnapshotFactory::orchestrator($this->logger, $this->db, $manager);

        return array($manager, $orchestrator);
    }

    private function invokeBackup(object $orchestrator, array $args): array {
        $snapshotType = $args[ResponseKeyType::SnapshotType->value] ?? SnapshotModeType::Full->value;

        if ($snapshotType === SnapshotModeType::Incremental->value) {
            return $this->invokeIncrementalBackup($orchestrator, $args);
        }

        return $this->invokeFullBackup($orchestrator, $args);
    }

    private function invokeIncrementalBackup(object $orchestrator, array $args): array {
        return $orchestrator->executeIncrementalBackup(array(
            ResponseKeyType::Title->value            => $args[ResponseKeyType::Title->value] ?? 'Incremental Backup ' . DateHelper::nowCompactDatetime(),
            ResponseKeyType::MasterSnapshotId->value => $args[ResponseKeyType::MasterSnapshotId->value] ?? null,
        ));
    }

    private function invokeFullBackup(object $orchestrator, array $args): array {
        return $orchestrator->executeFullBackup(array(
            ResponseKeyType::Title->value   => $args[ResponseKeyType::Title->value] ?? 'Manual Backup ' . DateHelper::nowCompactDatetime(),
            ResponseKeyType::Scope->value   => $args[ResponseKeyType::Scope->value] ?? SnapshotScopeType::WordPress->value,
            ResponseKeyType::Trigger->value => SnapshotTriggerType::Manual->value,
            ResponseKeyType::Async->value   => true,
        ));
    }
}
