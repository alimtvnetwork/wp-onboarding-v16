<?php
/**
 * Scheduler Executor Trait
 *
 * Private run* work methods for each cron job type.
 *
 * @package RiseupAsiaUploader
 * @since   1.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

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
            'scope'   => $settings['default_scope'] ?? SNAPSHOT_SCOPE_WORDPRESS,
            'trigger' => SNAPSHOT_TRIGGER_CRON,
            'title'   => 'Scheduled Backup ' . date('Y-m-d H:i'),
            'async'   => true,
        ));

        return $this->buildCronResult($result, ACTION_SNAPSHOT_CREATE, TRIGGERED_BY_CRON, array(
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
        $snapshotType = $args['snapshot_type'] ?? SNAPSHOT_TYPE_FULL;
        $result = $this->invokeBackup($orchestrator, $args);

        $action = ($snapshotType === SNAPSHOT_TYPE_INCREMENTAL) ? ACTION_SNAPSHOT_INCREMENTAL : ACTION_SNAPSHOT_FULL_BACKUP;
        return $this->buildCronResult($result, $action, TRIGGERED_BY_DASHBOARD, array(
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

        return $this->buildCronResult($result, ACTION_SNAPSHOT_RESTORE, TRIGGERED_BY_CRON, array(
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

        return $this->buildCronResult($result, ACTION_SNAPSHOT_INCREMENTAL, TRIGGERED_BY_CRON, array(
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

        $cronResult = $this->buildCronResult(array('success' => true), ACTION_SNAPSHOT_CLEANUP, TRIGGERED_BY_CRON, $auditData);
        $cronResult['skip_audit'] = ($totalDeleted === 0);
        $cronResult['log_data'] = $auditData + array(
            'space_freed'  => RiseupPathUtils::format_bytes($result['space_freed_bytes']),
            'errors_count' => count($result['errors']),
        );
        return $cronResult;
    }
}
