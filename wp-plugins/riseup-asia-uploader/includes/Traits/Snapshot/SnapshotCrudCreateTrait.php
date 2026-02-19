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
            $scope = isset($body['scope']) ? sanitize_key($body['scope']) : SnapshotScopeType::All->value;

            $this->logger->logPluginAction(ActionType::SnapshotCreate->value, LogCategoryType::Snapshot->value, StatusType::Success->value,
                array('scope' => $scope, 'trigger' => 'api', 'phase' => 'initiated'));

            $manager = SnapshotManager::getInstance($this->fileLogger, $this->db);
            $isPerTable = (($manager->getSettings()['mode'] ?? SnapshotWorkerModeType::PerTable->value) === SnapshotWorkerModeType::PerTable->value);

            $result = $isPerTable
                ? $this->executePerTableSnapshot($body, $scope, $manager)
                : $this->executeLegacySnapshot($body, $scope, $manager);

            $this->logSnapshotResult(ActionType::SnapshotCreate->value, $scope, $isPerTable ? SnapshotWorkerModeType::PerTable->value : SnapshotWorkerModeType::Legacy->value, $result);

            return new WP_REST_Response($result, $result['success'] ? HttpStatusType::Created->value : HttpStatusType::ServerError->value);
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
            'title'            => $body['title'] ?? null,
            'scope'            => $scope,
            'include_plugins'  => $body['include_plugins'] ?? null,
            'plugin_selection' => $body['plugin_selection'] ?? null,
            'compression'      => $body['compression'] ?? null,
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
            'scope'   => $scope,
            'trigger' => SnapshotTriggerType::Api->value,
            'tables'  => isset($body['tables']) ? array_map('sanitize_text_field', (array) $body['tables']) : array(),
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
            $result['success'] ? StatusType::Success->value : StatusType::Failed->value,
            array('scope' => $scope, 'mode' => $mode, 'phase' => 'complete'),
            $result['success'] ? null : ($result['error'] ?? 'Unknown error')
        );
    }
}
