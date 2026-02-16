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
use RiseupAsia\Enums\SnapshotModeType;
use RiseupAsia\Enums\SnapshotScopeType;
use RiseupAsia\Enums\SnapshotTriggerType;
use RiseupAsia\Enums\TriggerSourceType;

trait SchedulerExecutorTrait {

    /**
     * Run a scheduled backup through the orchestrator.
     *
     * @return array Standardized cron result.
     */
    private function runScheduledSnapshot(): array {
        $settings = $this->detector->getSettings();
        list(, $orchestrator) = $this->createOrchestrator();

        $result = $orchestrator->executeFullBackup(array(
            'scope'   => $settings['default_scope'] ?? SnapshotScopeType::WordPress->value,
            'trigger' => SnapshotTriggerType::Cron->value,
            'title'   => 'Scheduled Backup ' . date('Y-m-d H:i'),
            'async'   => true,
        ));

        return $this->buildCronResult($result, ActionType::SnapshotCreate->value, TriggerSourceType::Cron->value, array(
            'trigger' => 'cron', 'snapshot_id' => $result['snapshot_id'] ?? null, 'job_id' => $result['job_id'] ?? null,
        ));
    }

    /**
     * Run an immediate backup, branching on full vs incremental.
     *
     * @param array $args Snapshot arguments.
     * @return array Standardized cron result.
     */
    private function runImmediateSnapshot(array $args): array {
        list(, $orchestrator) = $this->createOrchestrator();
        $snapshotType = $args['snapshot_type'] ?? SnapshotModeType::Full->value;
        $result = $this->invokeBackup($orchestrator, $args);

        $action = ($snapshotType === SnapshotModeType::Incremental->value) ? ActionType::SnapshotIncremental->value : ActionType::SnapshotFullBackup->value;
        return $this->buildCronResult($result, $action, TriggerSourceType::Dashboard->value, array(
            'trigger' => 'manual', 'snapshot_id' => $result['snapshot_id'] ?? null, 'type' => $snapshotType,
        ));
    }

    /**
     * Run a cron-based restore operation.
     *
     * @param array $args Restore arguments.
     * @return array Standardized cron result.
     */
    private function runCronRestore(array $args): array {
        if (empty($args['snapshot_id'])) {
            $this->logger->error('[SCHEDULER] Missing snapshot_id for cron restore');
            return array('success' => false, 'error' => 'Missing snapshot_id', 'skip_audit' => true);
        }

        require_once dirname(__FILE__) . '/../SnapshotFactory.php';
        $manager = RiseupSnapshotFactory::manager($this->logger, $this->db);
        $restoreOptions = $args['options'] ?? array();
        $restoreOptions['confirm'] = true;

        $result = $manager->restoreSnapshot($args['snapshot_id'], $restoreOptions);

        return $this->buildCronResult($result, ActionType::SnapshotRestore->value, TriggerSourceType::Cron->value, array(
            'snapshot_id' => $args['snapshot_id'], 'tables' => $result['tables'] ?? 0, 'rows' => $result['rows'] ?? 0,
        ));
    }

    /**
     * Run a cron-based incremental backup.
     *
     * @param array $args Incremental arguments.
     * @return array Standardized cron result.
     */
    private function runCronIncremental(array $args): array {
        list(, $orchestrator) = $this->createOrchestrator();

        $result = $orchestrator->executeIncrementalBackup(array(
            'title'              => $args['title'] ?? 'Incremental Backup ' . date('Y-m-d H:i'),
            'master_snapshot_id' => $args['master_snapshot_id'] ?? null,
        ));

        return $this->buildCronResult($result, ActionType::SnapshotIncremental->value, TriggerSourceType::Cron->value, array(
            'tables_changed' => $result['tables_changed'] ?? 0, 'total_new_rows' => $result['total_new_rows'] ?? 0,
        ));
    }

    /**
     * Run snapshot cleanup with conditional audit trail.
     *
     * @return array Standardized cron result.
     */
    private function runCleanup(): array {
        $settings = $this->detector->getSettings();
        $result = RiseupSnapshotFactory::cleaner($this->logger, $this->db)->runCleanup($settings);

        $auditData = array(
            'deleted_by_policy' => $result['deleted_by_policy'] ?? 0,
            'deleted_orphans'   => $result['deleted_orphans'] ?? 0,
            'deleted_failed'    => $result['deleted_failed'] ?? 0,
            'space_freed_bytes' => $result['space_freed_bytes'] ?? 0,
        );
        $totalDeleted = array_sum(array_slice($auditData, 0, 3));

        $cronResult = $this->buildCronResult(array('success' => true), ActionType::SnapshotCleanup->value, TriggerSourceType::Cron->value, $auditData);
        $cronResult['skip_audit'] = ($totalDeleted === 0);
        $cronResult['log_data'] = $auditData + array(
            'space_freed'  => RiseupPathUtils::formatBytes($result['space_freed_bytes']),
            'errors_count' => count($result['errors']),
        );
        return $cronResult;
    }
}
