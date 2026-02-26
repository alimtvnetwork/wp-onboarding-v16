<?php
/**
 * SnapshotCrudCreateTrait — snapshot creation and scheduling handlers.
 *
 * @package RiseupAsia\Traits\Snapshot
 * @since   1.57.0
 */

namespace RiseupAsia\Traits\Snapshot;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;
use RiseupAsia\Enums\ActionType;
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\LogCategoryType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\SnapshotPhaseType;
use RiseupAsia\Enums\SnapshotScopeType;
use RiseupAsia\Enums\StatusType;
use RiseupAsia\Enums\SnapshotTriggerType;
use RiseupAsia\Enums\SnapshotWorkerModeType;
use RiseupAsia\Snapshot\SnapshotManager;
use RiseupAsia\Snapshot\SnapshotOrchestrator;

trait SnapshotCrudCreateTrait {

    /**
     * Handle scheduling a snapshot (alias).
     */
    public function handleScheduleSnapshot($request) {
        return $this->handleCreateSnapshot($request);
    }

    /**
     * Handle creating/scheduling a snapshot.
     */
    public function handleCreateSnapshot($request) {
        return $this->safeExecute(function() use ($request) {
            $body = $request->get_json_params();
            $scope = isset($body[ResponseKeyType::Scope->value]) ? sanitize_key($body[ResponseKeyType::Scope->value]) : SnapshotScopeType::All->value;

            $this->logger->logPluginAction(ActionType::SnapshotCreate->value, LogCategoryType::Snapshot->value, StatusType::Success->value,
                array(ResponseKeyType::Scope->value => $scope, ResponseKeyType::Trigger->value => 'api', ResponseKeyType::Phase->value => SnapshotPhaseType::Initiated->value));

            $manager = SnapshotManager::getInstance($this->fileLogger, $this->db);
            $isPerTable = (($manager->getSettings()[ResponseKeyType::Mode->value] ?? SnapshotWorkerModeType::PerTable->value) === SnapshotWorkerModeType::PerTable->value);

            $result = $isPerTable
                ? $this->executePerTableSnapshot($body, $scope, $manager)
                : $this->executeLegacySnapshot($body, $scope, $manager);

            $this->logSnapshotResult(ActionType::SnapshotCreate->value, $scope, $isPerTable ? SnapshotWorkerModeType::PerTable->value : SnapshotWorkerModeType::Legacy->value, $result);

            return new WP_REST_Response($result, $result[ResponseKeyType::Success->value] ? HttpStatusType::Created->value : HttpStatusType::ServerError->value);
        }, 'create_snapshot');
    }

    /**
     * Execute a per-table snapshot via the orchestrator.
     */
    private function executePerTableSnapshot(
        array $body,
        string $scope,
        $manager,
    ): array {
        $orchestrator = SnapshotOrchestrator::getInstance($this->fileLogger, $this->db, $manager);

        return $orchestrator->executeFullBackup(array(
            ResponseKeyType::Title->value          => $body[ResponseKeyType::Title->value] ?? null,
            ResponseKeyType::Scope->value          => $scope,
            ResponseKeyType::IncludePlugins->value  => $body[ResponseKeyType::IncludePlugins->value] ?? null,
            ResponseKeyType::PluginSelection->value => $body[ResponseKeyType::PluginSelection->value] ?? null,
            ResponseKeyType::Compression->value     => $body[ResponseKeyType::Compression->value] ?? null,
        ));
    }

    /**
     * Execute a legacy single-db snapshot via the manager.
     */
    private function executeLegacySnapshot(
        array $body,
        string $scope,
        $manager,
    ): array {
        $this->fileLogger->info('Creating snapshot via API (legacy mode)', array('scope' => $scope));

        return $manager->createSnapshot(array(
            ResponseKeyType::Scope->value   => $scope,
            ResponseKeyType::Trigger->value => SnapshotTriggerType::Api->value,
            ResponseKeyType::Tables->value  => isset($body[ResponseKeyType::Tables->value]) ? array_map('sanitize_text_field', (array) $body[ResponseKeyType::Tables->value]) : array(),
        ));
    }

    /**
     * Log a snapshot operation result to the audit trail.
     */
    private function logSnapshotResult(
        string $action,
        string $scope,
        string $mode,
        array $result,
    ) {
        $this->logger->logPluginAction(
            $action, LogCategoryType::Snapshot->value,
            $result[ResponseKeyType::Success->value] ? StatusType::Success->value : StatusType::Failed->value,
            array(ResponseKeyType::Scope->value => $scope, ResponseKeyType::Mode->value => $mode, ResponseKeyType::Phase->value => SnapshotPhaseType::Complete->value),
            $result[ResponseKeyType::Success->value] ? null : ($result[ResponseKeyType::Error->value] ?? 'Unknown error')
        );
    }
}
