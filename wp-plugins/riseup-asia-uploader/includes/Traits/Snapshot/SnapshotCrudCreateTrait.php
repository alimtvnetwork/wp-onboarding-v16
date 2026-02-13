<?php
/**
 * SnapshotCrudCreateTrait — snapshot creation and scheduling handlers.
 *
 * @package RiseupAsia\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait SnapshotCrudCreateTrait {

    /**
     * Handle scheduling a snapshot (alias).
     */
    public function handle_schedule_snapshot($request) {
        return $this->handle_create_snapshot($request);
    }

    /**
     * Handle creating/scheduling a snapshot.
     */
    public function handle_create_snapshot($request) {
        return $this->safe_execute(function() use ($request) {
            $body = $request->get_json_params();
            $scope = isset($body['scope']) ? sanitize_key($body['scope']) : 'all';

            $this->logger->log_plugin_action(ACTION_SNAPSHOT_CREATE, 'snapshot', STATUS_SUCCESS,
                array('scope' => $scope, 'trigger' => 'api', 'phase' => 'initiated'));

            $manager = RiseupSnapshotManager::getInstance($this->file_logger, $this->db);
            $isPerTable = (($manager->getSettings()['mode'] ?? 'per_table') === 'per_table');

            $result = $isPerTable
                ? $this->executePerTableSnapshot($body, $scope, $manager)
                : $this->executeLegacySnapshot($body, $scope, $manager);

            $this->logSnapshotResult(ACTION_SNAPSHOT_CREATE, $scope, $isPerTable ? 'per_table' : 'legacy', $result);
            return new WP_REST_Response($result, $result['success'] ? 201 : 500);
        }, 'create_snapshot');
    }

    /**
     * Execute a per-table snapshot via the orchestrator.
     */
    private function executePerTableSnapshot(array $body, string $scope, $manager): array {
        $orchestrator = RiseupSnapshotOrchestrator::getInstance($this->file_logger, $this->db, $manager);
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
    private function executeLegacySnapshot(array $body, string $scope, $manager): array {
        $this->file_logger->info('Creating snapshot via API (legacy mode)', array('scope' => $scope));
        return $manager->createSnapshot(array(
            'scope'   => $scope,
            'trigger' => SNAPSHOT_TRIGGER_API,
            'tables'  => isset($body['tables']) ? array_map('sanitize_text_field', (array) $body['tables']) : array(),
        ));
    }

    /**
     * Log a snapshot operation result to the audit trail.
     */
    private function logSnapshotResult(string $action, string $scope, string $mode, array $result) {
        $this->logger->log_plugin_action(
            $action, 'snapshot',
            $result['success'] ? STATUS_SUCCESS : STATUS_FAILED,
            array('scope' => $scope, 'mode' => $mode, 'phase' => 'complete'),
            $result['success'] ? null : ($result['error'] ?? 'Unknown error')
        );
    }
}
