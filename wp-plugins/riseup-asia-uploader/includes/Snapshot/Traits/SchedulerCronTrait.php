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
use RiseupAsia\Enums\SnapshotModeType;
use RiseupAsia\Enums\SnapshotScopeType;
use RiseupAsia\Enums\SnapshotTriggerType;
use RiseupAsia\Enums\StatusType;
use RiseupAsia\Enums\TriggerSourceType;

trait SchedulerCronTrait {

    /**
     * Shared cron-job wrapper: try-catch + audit trail.
     *
     * @param string   $label Human label for logging.
     * @param callable $work  Returns a standardized cron result array.
     */
    private function executeCronJob(string $label, callable $work) {
        $this->logger->info("[SCHEDULER] Executing {$label}");
        try {
            $result = $work();
            $this->logCronResult($label, $result);
        } catch (Throwable $e) {
            $this->logger->error("[SCHEDULER] Exception during {$label}", array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ));
        }
    }

    /**
     * Log the outcome of a cron job and optionally write an audit trail.
     *
     * @param string $label  Human label.
     * @param array  $result Standardized cron result.
     */
    private function logCronResult(string $label, array $result) {
        $isSuccess = $result['success'] ?? false;
        $level = $isSuccess ? 'info' : 'error';
        $suffix = $isSuccess ? 'completed' : 'failed';

        $this->logger->{$level}("[SCHEDULER] {$label} {$suffix}", $result['log_data'] ?? array());

        if ($result['skip_audit'] ?? false) {
            return;
        }

        $this->db->logTransaction(
            $result['action'],
            'snapshot', null, '', null, '',
            $result['audit_data'] ?? array(),
            $isSuccess ? \RiseupAsia\Enums\StatusType::Success->value : \RiseupAsia\Enums\StatusType::Failed->value,
            $isSuccess ? null : ($result['error'] ?? 'Unknown'),
            array('triggered_by' => $result['triggered_by'] ?? \RiseupAsia\Enums\TriggerSourceType::Cron->value)
        );
    }

    /**
     * Build a standardized cron result array from a raw operation result.
     *
     * @param array  $result      Raw operation result.
     * @param string $action      Audit action constant.
     * @param string $triggeredBy Trigger source constant.
     * @param array  $auditData   Extra audit data.
     * @return array Standardized result.
     */
    private function buildCronResult(array $result, string $action, string $triggeredBy, array $auditData = array()): array {
        return array(
            'success'      => $result['success'] ?? false,
            'error'        => $result['error'] ?? null,
            'action'       => $action,
            'triggered_by' => $triggeredBy,
            'audit_data'   => $auditData,
            'log_data'     => $result,
            'skip_audit'   => false,
        );
    }

    /**
     * Create manager + orchestrator instances via factory.
     *
     * @return array{0: RiseupSnapshotManager, 1: RiseupSnapshotOrchestrator}
     */
    private function createOrchestrator(): array {
        require_once dirname(__FILE__) . '/../SnapshotFactory.php';
        $manager = RiseupSnapshotFactory::manager($this->logger, $this->db);
        $orchestrator = RiseupSnapshotFactory::orchestrator($this->logger, $this->db, $manager);
        return array($manager, $orchestrator);
    }

    /**
     * Invoke a backup through the orchestrator, branching on snapshot type.
     *
     * @param object $orchestrator Orchestrator instance.
     * @param array  $args         Snapshot arguments including snapshot_type.
     * @return array Raw orchestrator result.
     */
    private function invokeBackup(object $orchestrator, array $args): array {
        $snapshotType = $args['snapshot_type'] ?? SnapshotModeType::Full->value;

        if ($snapshotType === SnapshotModeType::Incremental->value) {
            return $orchestrator->executeIncrementalBackup(array(
                'title'              => $args['title'] ?? 'Incremental Backup ' . date('Y-m-d H:i'),
                'master_snapshot_id' => $args['master_snapshot_id'] ?? null,
            ));
        }

        return $orchestrator->executeFullBackup(array(
            'title'   => $args['title'] ?? 'Manual Backup ' . date('Y-m-d H:i'),
            'scope'   => $args['scope'] ?? SnapshotScopeType::WordPress->value,
            'trigger' => SnapshotTriggerType::Manual->value,
            'async'   => true,
        ));
    }
}
