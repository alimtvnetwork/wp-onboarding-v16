<?php
/**
 * SnapshotCrudRestoreTrait — snapshot delete, restore, and routing logic.
 *
 * @package RiseupAsia\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\ActionType;

trait SnapshotCrudRestoreTrait {

    /**
     * Handle deleting a snapshot.
     */
    public function handleDeleteSnapshot($request) {
        return $this->safeExecute(function() use ($request) {
            $body = $request->get_json_params();
            $id = isset($body['id']) ? (int) $body['id'] : (int) $request->get_param('id');
            $this->fileLogger->info('Deleting snapshot', array('id' => $id));

            $this->logger->logPluginAction(
                ActionType::SnapshotDelete->value, 'snapshot', STATUS_SUCCESS,
                array('snapshot_id' => $id, 'trigger' => 'api', 'phase' => 'initiated')
            );

            $manager = RiseupSnapshotManager::getInstance($this->fileLogger, $this->db);
            $result = $manager->deleteSnapshot($id);

            $this->logger->logPluginAction(
                ActionType::SnapshotDelete->value, 'snapshot',
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
     */
    public function handleRestoreSnapshot($request) {
        return $this->safeExecute(function() use ($request) {
            $body = $request->get_json_params();
            $id = isset($body['id']) ? (int) $body['id'] : (int) $request->get_param('id');
            $options = $this->parseRestoreOptions($body);

            $this->logger->logPluginAction(ActionType::SnapshotRestore->value, 'snapshot', STATUS_SUCCESS,
                array('snapshot_id' => $id, 'mode' => $options['mode'], 'phase' => 'initiated'));
            $this->fileLogger->info('Restoring snapshot', array('id' => $id, 'mode' => $options['mode']));

            $manager = RiseupSnapshotManager::getInstance($this->fileLogger, $this->db);
            $result = $this->routeRestoreToEngine($id, $options, $manager);

            $mode = $result['_mode'] ?? 'legacy';
            unset($result['_mode']);
            $this->logSnapshotResult(ActionType::SnapshotRestore->value, '', $mode, $result);

            return new WP_REST_Response($result, $result['success'] ? 200 : 400);
        }, 'restore_snapshot');
    }

    /**
     * Parse restore options from the request body.
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
     */
    private function routeRestoreToEngine(int $id, array $options, $manager): array {
        $snapshot = $manager->getSnapshotById($id);

        if ($snapshot && $this->isPerTableSnapshot($snapshot)) {
            $dir = $this->resolveSnapshotDir($snapshot);
            if ($dir && file_exists($dir . '/a-root.db')) {
                $orchestrator = RiseupSnapshotOrchestrator::getInstance($this->fileLogger, $this->db, $manager);
                $engine = RiseupRestoreEngine::getInstance($this->fileLogger, $this->db, $orchestrator);
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
