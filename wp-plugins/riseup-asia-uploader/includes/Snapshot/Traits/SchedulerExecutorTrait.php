<?php
/**
 * Scheduler Executor Trait
 *
 * Private run* work methods for each cron job type.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.9.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use Throwable;
use RiseupAsia\Enums\ActionType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\SnapshotModeType;
use RiseupAsia\Enums\SnapshotScopeType;
use RiseupAsia\Enums\SnapshotTriggerType;
use RiseupAsia\Enums\TriggerSourceType;
use RiseupAsia\Helpers\DateHelper;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\ResultHelper;
use RiseupAsia\Snapshot\SnapshotFactory;

trait SchedulerExecutorTrait {
    private function runScheduledSnapshot(): array {
        $settings = $this->detector->getSettings();
        list(, $orchestrator) = $this->createOrchestrator();

        $result = $orchestrator->executeFullBackup(array(
            ResponseKeyType::Scope->value => $settings['default_scope'] ?? SnapshotScopeType::WordPress->value,
            'trigger'                     => SnapshotTriggerType::Cron->value,
            ResponseKeyType::Title->value => 'Scheduled Backup ' . DateHelper::nowCompactDatetime(),
            'async'                       => true,
        ));

        return $this->buildCronResult(
            $result,
            ActionType::SnapshotCreate->value,
            TriggerSourceType::Cron->value,
            array(
                'trigger'                          => 'cron',
                ResponseKeyType::SnapshotId->value => $result[ResponseKeyType::SnapshotId->value] ?? null,
                ResponseKeyType::JobId->value      => $result[ResponseKeyType::JobId->value] ?? null,
            ),
        );
    }

    private function runImmediateSnapshot(array $args): array {
        list(, $orchestrator) = $this->createOrchestrator();
        $snapshotType = $args[ResponseKeyType::SnapshotType->value] ?? SnapshotModeType::Full->value;
        $result = $this->invokeBackup($orchestrator, $args);
        $action = ($snapshotType === SnapshotModeType::Incremental->value) ? ActionType::SnapshotIncremental->value : ActionType::SnapshotFullBackup->value;

        return $this->buildCronResult(
            $result,
            $action,
            TriggerSourceType::Dashboard->value,
            array(
                'trigger'                          => SnapshotTriggerType::Manual->value,
                ResponseKeyType::SnapshotId->value => $result[ResponseKeyType::SnapshotId->value] ?? null,
                ResponseKeyType::Type->value       => $snapshotType,
            ),
        );
    }

    private function runCronRestore(array $args): array {
        if (empty($args[ResponseKeyType::SnapshotId->value])) {
            $this->logger->error('[SCHEDULER] Missing snapshot_id for cron restore');

            return ResultHelper::error(
                'Missing snapshot_id',
                array(ResponseKeyType::SkipAudit->value => true),
            );
        }

        $manager = SnapshotFactory::manager($this->logger, $this->db);
        $restoreOptions = $args['options'] ?? array();
        $restoreOptions['confirm'] = true;
        $result = $manager->restoreSnapshot($args[ResponseKeyType::SnapshotId->value], $restoreOptions);

        return $this->buildCronResult(
            $result,
            ActionType::SnapshotRestore->value,
            TriggerSourceType::Cron->value,
            array(
                ResponseKeyType::SnapshotId->value => $args[ResponseKeyType::SnapshotId->value],
                ResponseKeyType::Tables->value     => $result[ResponseKeyType::Tables->value] ?? 0,
                ResponseKeyType::Rows->value       => $result[ResponseKeyType::Rows->value] ?? 0,
            ),
        );
    }

    private function runCronIncremental(array $args): array {
        list(, $orchestrator) = $this->createOrchestrator();

        $result = $orchestrator->executeIncrementalBackup(array(
            ResponseKeyType::Title->value => $args[ResponseKeyType::Title->value] ?? 'Incremental Backup ' . DateHelper::nowCompactDatetime(),
            'master_snapshot_id'          => $args['master_snapshot_id'] ?? null,
        ));

        return $this->buildCronResult(
            $result,
            ActionType::SnapshotIncremental->value,
            TriggerSourceType::Cron->value,
            array(
                ResponseKeyType::TablesChanged->value => $result[ResponseKeyType::TablesChanged->value] ?? 0,
                ResponseKeyType::TotalNewRows->value  => $result[ResponseKeyType::TotalNewRows->value] ?? 0,
            ),
        );
    }

    private function runCleanup(): array {
        $settings = $this->detector->getSettings();
        $result = SnapshotFactory::cleaner($this->logger, $this->db)->runCleanup($settings);

        $auditData = array(
            ResponseKeyType::DeletedByPolicy->value => $result[ResponseKeyType::DeletedByPolicy->value] ?? 0,
            ResponseKeyType::DeletedOrphans->value  => $result[ResponseKeyType::DeletedOrphans->value] ?? 0,
            ResponseKeyType::DeletedFailed->value   => $result[ResponseKeyType::DeletedFailed->value] ?? 0,
            ResponseKeyType::SpaceFreedBytes->value => $result[ResponseKeyType::SpaceFreedBytes->value] ?? 0,
        );
        $totalDeleted = array_sum(array_slice($auditData, 0, 3));

        $cronResult = $this->buildCronResult(
            ResultHelper::ok(),
            ActionType::SnapshotCleanup->value,
            TriggerSourceType::Cron->value,
            $auditData,
        );

        $cronResult[ResponseKeyType::SkipAudit->value] = ($totalDeleted === 0);
        $cronResult[ResponseKeyType::LogDataKey->value] = $auditData + array(
            'spaceFreed'  => PathHelper::formatBytes($result[ResponseKeyType::SpaceFreedBytes->value]),
            'errorsCount' => count($result[ResponseKeyType::Errors->value]),
        );

        return $cronResult;
    }
}
