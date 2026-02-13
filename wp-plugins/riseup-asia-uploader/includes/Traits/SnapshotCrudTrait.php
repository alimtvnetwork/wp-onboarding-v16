<?php
/**
 * SnapshotCrudTrait — snapshot list, create, get, delete, and restore handlers.
 *
 * Extracted from riseup-asia-uploader.php (lines 4532–4827).
 *
 * @package RiseupAsiaUploader
 */

trait SnapshotCrudTrait {

    /**
     * Handle listing snapshots.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_list_snapshots($request) {
        return $this->safe_execute(function() use ($request) {
            $limit = (int) ($request->get_param('limit') ?: 50);
            $offset = (int) ($request->get_param('offset') ?: 0);

            $manager = RiseupSnapshotManager::getInstance($this->file_logger, $this->db);
            $snapshots = $manager->listSnapshots($limit, $offset);

            return new WP_REST_Response(array(
                'success'   => true,
                'snapshots' => $snapshots['snapshots'],
                'total'     => $snapshots['total'],
            ), 200);
        }, 'list_snapshots');
    }

    /**
     * Handle scheduling a snapshot (alias for handle_create_snapshot).
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_schedule_snapshot($request) {
        return $this->handle_create_snapshot($request);
    }

    /**
     * Handle creating/scheduling a snapshot.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
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
     *
     * @param array  $body    Request body.
     * @param string $scope   Snapshot scope.
     * @param object $manager Snapshot manager instance.
     * @return array Result array.
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
     *
     * @param array  $body    Request body.
     * @param string $scope   Snapshot scope.
     * @param object $manager Snapshot manager instance.
     * @return array Result array.
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
     *
     * @param string $action Action constant.
     * @param string $scope  Snapshot scope.
     * @param string $mode   Execution mode.
     * @param array  $result Operation result.
     */
    private function logSnapshotResult(string $action, string $scope, string $mode, array $result) {
        $this->logger->log_plugin_action(
            $action, 'snapshot',
            $result['success'] ? STATUS_SUCCESS : STATUS_FAILED,
            array('scope' => $scope, 'mode' => $mode, 'phase' => 'complete'),
            $result['success'] ? null : ($result['error'] ?? 'Unknown error')
        );
    }

    /**
     * Handle getting a single snapshot.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_get_snapshot($request) {
        return $this->safe_execute(function() use ($request) {
            $body = $request->get_json_params();
            $id = isset($body['id']) ? (int) $body['id'] : (int) $request->get_param('id');

            $manager = RiseupSnapshotManager::getInstance($this->file_logger, $this->db);
            $provider = $manager->getProvider();
            if (!$provider) {
                return $this->error_response('No snapshot provider available', 500);
            }

            $snapshot = $provider->getSnapshot($id);
            if (!$snapshot) {
                return $this->error_response('Snapshot not found', 404);
            }

            return new WP_REST_Response(array(
                'success'  => true,
                'snapshot' => $snapshot,
            ), 200);
        }, 'get_snapshot');
    }

    /**
     * Alias for handle_get_snapshot.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_snapshot_info($request) {
        return $this->handle_get_snapshot($request);
    }

    /**
     * Handle deleting a snapshot.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_delete_snapshot($request) {
        return $this->safe_execute(function() use ($request) {
            $body = $request->get_json_params();
            $id = isset($body['id']) ? (int) $body['id'] : (int) $request->get_param('id');
            $this->file_logger->info('Deleting snapshot', array('id' => $id));

            $this->logger->log_plugin_action(
                ACTION_SNAPSHOT_DELETE, 'snapshot', STATUS_SUCCESS,
                array('snapshot_id' => $id, 'trigger' => 'api', 'phase' => 'initiated')
            );

            $manager = RiseupSnapshotManager::getInstance($this->file_logger, $this->db);
            $result = $manager->deleteSnapshot($id);

            $this->logger->log_plugin_action(
                ACTION_SNAPSHOT_DELETE, 'snapshot',
                $result['success'] ? STATUS_SUCCESS : STATUS_FAILED,
                array('snapshot_id' => $id, 'trigger' => 'api', 'phase' => 'complete'),
                $result['success'] ? null : ($result['error'] ?? 'Delete failed')
            );

            $status_code = $result['success'] ? 200 : 400;
            return new WP_REST_Response($result, $status_code);
        }, 'delete_snapshot');
    }

    /**
     * Handle restoring a snapshot.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_restore_snapshot($request) {
        return $this->safe_execute(function() use ($request) {
            $body = $request->get_json_params();
            $id = isset($body['id']) ? (int) $body['id'] : (int) $request->get_param('id');
            $options = $this->parseRestoreOptions($body);

            $this->logger->log_plugin_action(ACTION_SNAPSHOT_RESTORE, 'snapshot', STATUS_SUCCESS,
                array('snapshot_id' => $id, 'mode' => $options['mode'], 'phase' => 'initiated'));
            $this->file_logger->info('Restoring snapshot', array('id' => $id, 'mode' => $options['mode']));

            $manager = RiseupSnapshotManager::getInstance($this->file_logger, $this->db);
            $result = $this->routeRestoreToEngine($id, $options, $manager);

            $mode = $result['_mode'] ?? 'legacy';
            unset($result['_mode']);
            $this->logSnapshotResult(ACTION_SNAPSHOT_RESTORE, '', $mode, $result);

            return new WP_REST_Response($result, $result['success'] ? 200 : 400);
        }, 'restore_snapshot');
    }

    /**
     * Parse restore options from the request body.
     *
     * @param array $body Request body.
     * @return array Parsed restore options.
     */
    private function parseRestoreOptions(array $body): array {
        return array(
            'confirm'            => !empty($body['confirm']),
            'create_backup'      => isset($body['createBackup']) ? (bool) $body['createBackup'] : true,
            'require_backup'     => !empty($body['requireBackup']),
            'mode'               => isset($body['mode']) ? sanitize_key($body['mode']) : 'full',
            'tables'             => isset($body['tables']) ? array_map('sanitize_text_field', (array) $body['tables']) : array(),
            'strict'             => !empty($body['strict']),
            'apply_incrementals' => isset($body['applyIncrementals']) ? (bool) $body['applyIncrementals'] : true,
        );
    }

    /**
     * Route a restore operation to the appropriate engine.
     *
     * @param int    $id      Snapshot ID.
     * @param array  $options Restore options.
     * @param object $manager Snapshot manager instance.
     * @return array Result with _mode metadata.
     */
    private function routeRestoreToEngine(int $id, array $options, $manager): array {
        $snapshot = $manager->getSnapshotById($id);

        if ($snapshot && $this->isPerTableSnapshot($snapshot)) {
            $dir = $this->resolveSnapshotDir($snapshot);
            if ($dir && file_exists($dir . '/a-root.db')) {
                $orchestrator = RiseupSnapshotOrchestrator::getInstance($this->file_logger, $this->db, $manager);
                $engine = RiseupRestoreEngine::getInstance($this->file_logger, $this->db, $orchestrator);
                $result = $engine->execute($dir, $options);
                $result['_mode'] = 'per_table';
                return $result;
            }
        }

        $result = $manager->restoreSnapshot($id, $options);
        $result['_mode'] = 'legacy';
        return $result;
    }

    /**
     * Check if a snapshot is a per-table snapshot.
     *
     * @param array $snapshot Snapshot record.
     * @return bool True if per-table snapshot.
     */
    private function isPerTableSnapshot($snapshot) {
        $filepath = $snapshot['filepath'] ?? '';
        if (is_dir($filepath)) {
            return file_exists($filepath . '/a-root.db');
        }
        $dir = $snapshot['directory'] ?? '';
        if (!empty($dir) && is_dir($dir)) {
            return file_exists($dir . '/a-root.db');
        }
        return false;
    }

    /**
     * Resolve the snapshot directory path from a snapshot record.
     *
     * @param array $snapshot Snapshot record.
     * @return string|null Directory path or null.
     */
    private function resolveSnapshotDir($snapshot) {
        $filepath = $snapshot['filepath'] ?? '';
        if (is_dir($filepath)) {
            return $filepath;
        }
        $dir = $snapshot['directory'] ?? '';
        if (!empty($dir) && is_dir($dir)) {
            return $dir;
        }
        if (!empty($filepath) && file_exists(dirname($filepath) . '/a-root.db')) {
            return dirname($filepath);
        }
        return null;
    }
}
