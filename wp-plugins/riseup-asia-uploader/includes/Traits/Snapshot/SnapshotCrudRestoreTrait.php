<?php
/**
 * SnapshotCrudRestoreTrait — snapshot delete, restore, and routing logic.
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
use RiseupAsia\Enums\StatusType;
use RiseupAsia\Snapshot\SnapshotManager;
use RiseupAsia\Snapshot\SnapshotOrchestrator;
use RiseupAsia\Snapshot\RestoreEngine;
use RiseupAsia\Helpers\BooleanHelpers;

trait SnapshotCrudRestoreTrait {

    public function handleDeleteSnapshot(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() use ($request): WP_REST_Response {
            $body = $request->get_json_params();
            $id = isset($body['id']) ? (int) $body['id'] : (int) $request->get_param('id');
            $this->fileLogger->info('Deleting snapshot', array('id' => $id));

            $this->logger->logPluginAction(
                ActionType::SnapshotDelete->value, 'snapshot', StatusType::Success->value,
                array('snapshot_id' => $id, 'trigger' => 'api', 'phase' => 'initiated')
            );

            $manager = SnapshotManager::getInstance($this->fileLogger, $this->db);
            $result = $manager->deleteSnapshot($id);

            $this->logger->logPluginAction(
                ActionType::SnapshotDelete->value, 'snapshot',
                $result['success'] ? StatusType::Success->value : StatusType::Failed->value,
                array('snapshot_id' => $id, 'trigger' => 'api', 'phase' => 'complete'),
                $result['success'] ? null : ($result['error'] ?? 'Delete failed')
            );

            $statusCode = $result['success'] ? HttpStatusType::Ok->value : HttpStatusType::BadRequest->value;
            return new WP_REST_Response($result, $statusCode);
        }, 'delete_snapshot');
    }

    public function handleRestoreSnapshot(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() use ($request): WP_REST_Response {
            $body = $request->get_json_params();
            $id = isset($body['id']) ? (int) $body['id'] : (int) $request->get_param('id');
            $options = $this->parseRestoreOptions($body);

            $this->logger->logPluginAction(ActionType::SnapshotRestore->value, 'snapshot', StatusType::Success->value,
                array('snapshot_id' => $id, 'mode' => $options['mode'], 'phase' => 'initiated'));
            $this->fileLogger->info('Restoring snapshot', array('id' => $id, 'mode' => $options['mode']));

            $manager = SnapshotManager::getInstance($this->fileLogger, $this->db);
            $result = $this->routeRestoreToEngine($id, $options, $manager);

            $mode = $result['_mode'] ?? 'legacy';
            unset($result['_mode']);
            $this->logSnapshotResult(ActionType::SnapshotRestore->value, '', $mode, $result);

            return new WP_REST_Response($result, $result['success'] ? HttpStatusType::Ok->value : HttpStatusType::BadRequest->value);
        }, 'restore_snapshot');
    }

    private function parseRestoreOptions(array $body): array {
        $hasConfirm = BooleanHelpers::hasValue($body['confirm'] ?? null);
        $hasRequireBackup = BooleanHelpers::hasValue($body['requireBackup'] ?? null);
        $hasStrict = BooleanHelpers::hasValue($body['strict'] ?? null);

        return array(
            'confirm'            => $hasConfirm,
            'create_backup'      => isset($body['createBackup']) ? (bool) $body['createBackup'] : true,
            'require_backup'     => $hasRequireBackup,
            'mode'               => isset($body['mode']) ? sanitize_key($body['mode']) : 'full',
            'tables'             => isset($body['tables']) ? array_map('sanitize_text_field', (array) $body['tables']) : array(),
            'strict'             => $hasStrict,
            'apply_incrementals' => isset($body['applyIncrementals']) ? (bool) $body['applyIncrementals'] : true,
        );
    }

    private function routeRestoreToEngine(
        int $id,
        array $options,
        SnapshotManager $manager,
    ): array {
        $snapshot = $manager->getSnapshotById($id);

        if ($snapshot && $this->isPerTableSnapshot($snapshot)) {
            $dir = $this->resolveSnapshotDir($snapshot);
            if ($dir && file_exists($dir . '/a-root.db')) {
                $orchestrator = SnapshotOrchestrator::getInstance($this->fileLogger, $this->db, $manager);
                $engine = RestoreEngine::getInstance($this->fileLogger, $this->db, $orchestrator);
                $result = $engine->execute($dir, $options);
                $result['_mode'] = 'per_table';
                return $result;
            }
        }

        $result = $manager->restoreSnapshot($id, $options);
        $result['_mode'] = 'legacy';
        return $result;
    }

    private function isPerTableSnapshot(array $snapshot): bool {
        $filepath = $snapshot['filepath'] ?? '';
        if (is_dir($filepath)) {
            return file_exists($filepath . '/a-root.db');
        }
        $dir = $snapshot['directory'] ?? '';
        $hasDirWithRootDb = BooleanHelpers::hasValue($dir) && is_dir($dir);
        if ($hasDirWithRootDb) {
            return file_exists($dir . '/a-root.db');
        }
        return false;
    }

    private function resolveSnapshotDir(array $snapshot): ?string {
        $filepath = $snapshot['filepath'] ?? '';
        if (is_dir($filepath)) {
            return $filepath;
        }
        $dir = $snapshot['directory'] ?? '';
        $hasValidDir = BooleanHelpers::hasValue($dir) && is_dir($dir);
        if ($hasValidDir) {
            return $dir;
        }
        $hasFilepathWithRootDb = BooleanHelpers::hasValue($filepath) && file_exists(dirname($filepath) . '/a-root.db');
        if ($hasFilepathWithRootDb) {
            return dirname($filepath);
        }
        return null;
    }
}
