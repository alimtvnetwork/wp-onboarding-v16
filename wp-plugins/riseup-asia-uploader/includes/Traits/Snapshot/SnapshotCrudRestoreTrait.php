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
use RiseupAsia\Enums\LogCategoryType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\RestoreModeType;
use RiseupAsia\Enums\SnapshotConfigType;
use RiseupAsia\Enums\SnapshotPhaseType;
use RiseupAsia\Enums\SnapshotWorkerModeType;
use RiseupAsia\Enums\StatusType;
use RiseupAsia\Snapshot\SnapshotManager;
use RiseupAsia\Snapshot\SnapshotOrchestrator;
use RiseupAsia\Snapshot\RestoreEngine;


trait SnapshotCrudRestoreTrait {

    public function handleDeleteSnapshot(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() use ($request): WP_REST_Response {
            $body = $request->get_json_params();
            $id = isset($body[ResponseKeyType::Id->value]) ? (int) $body[ResponseKeyType::Id->value] : (int) $request->get_param('id');
            $this->fileLogger->info('Deleting snapshot', array('id' => $id));

            $this->logger->logPluginAction(
                ActionType::SnapshotDelete->value, LogCategoryType::Snapshot->value, StatusType::Success->value,
                array(ResponseKeyType::SnapshotId->value => $id, ResponseKeyType::Trigger->value => 'api', ResponseKeyType::Phase->value => SnapshotPhaseType::Initiated->value)
            );

            $manager = SnapshotManager::getInstance($this->fileLogger, $this->db);
            $result = $manager->deleteSnapshot($id);

            $this->logger->logPluginAction(
                ActionType::SnapshotDelete->value, LogCategoryType::Snapshot->value,
                $result[ResponseKeyType::Success->value] ? StatusType::Success->value : StatusType::Failed->value,
                array(ResponseKeyType::SnapshotId->value => $id, ResponseKeyType::Trigger->value => 'api', ResponseKeyType::Phase->value => SnapshotPhaseType::Complete->value),
                $result[ResponseKeyType::Success->value] ? null : ($result[ResponseKeyType::Error->value] ?? 'Delete failed')
            );

            $statusCode = $result[ResponseKeyType::Success->value] ? HttpStatusType::Ok->value : HttpStatusType::BadRequest->value;

            return new WP_REST_Response($result, $statusCode);
        }, 'delete_snapshot');
    }

    public function handleRestoreSnapshot(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() use ($request): WP_REST_Response {
            $body = $request->get_json_params();
            $id = isset($body[ResponseKeyType::Id->value]) ? (int) $body[ResponseKeyType::Id->value] : (int) $request->get_param('id');
            $options = $this->parseRestoreOptions($body);

            $this->logger->logPluginAction(ActionType::SnapshotRestore->value, LogCategoryType::Snapshot->value, StatusType::Success->value,
                array(ResponseKeyType::SnapshotId->value => $id, ResponseKeyType::Mode->value => $options[ResponseKeyType::Mode->value], ResponseKeyType::Phase->value => SnapshotPhaseType::Initiated->value));
            $this->fileLogger->info('Restoring snapshot', array('id' => $id, 'mode' => $options[ResponseKeyType::Mode->value]));

            $manager = SnapshotManager::getInstance($this->fileLogger, $this->db);
            $result = $this->routeRestoreToEngine($id, $options, $manager);

            $mode = $result[ResponseKeyType::InternalMode->value] ?? SnapshotWorkerModeType::Legacy->value;
            unset($result[ResponseKeyType::InternalMode->value]);
            $this->logSnapshotResult(ActionType::SnapshotRestore->value, '', $mode, $result);

            return new WP_REST_Response($result, $result[ResponseKeyType::Success->value] ? HttpStatusType::Ok->value : HttpStatusType::BadRequest->value);
        }, 'restore_snapshot');
    }

    private function parseRestoreOptions(array $body): array {
        $hasConfirm = !empty($body[ResponseKeyType::Confirm->value] ?? null);
        $hasRequireBackup = !empty($body[ResponseKeyType::RequireBackup->value] ?? null);
        $hasStrict = !empty($body[ResponseKeyType::Strict->value] ?? null);

        return array(
            ResponseKeyType::Confirm->value            => $hasConfirm,
            ResponseKeyType::CreateBackup->value       => isset($body[ResponseKeyType::CreateBackup->value]) ? (bool) $body[ResponseKeyType::CreateBackup->value] : true,
            ResponseKeyType::RequireBackup->value      => $hasRequireBackup,
            ResponseKeyType::Mode->value               => isset($body[ResponseKeyType::Mode->value]) ? sanitize_key($body[ResponseKeyType::Mode->value]) : RestoreModeType::Full->value,
            ResponseKeyType::Tables->value             => isset($body[ResponseKeyType::Tables->value]) ? array_map('sanitize_text_field', (array) $body[ResponseKeyType::Tables->value]) : array(),
            ResponseKeyType::Strict->value             => $hasStrict,
            ResponseKeyType::ApplyIncrementals->value  => isset($body[ResponseKeyType::ApplyIncrementals->value]) ? (bool) $body[ResponseKeyType::ApplyIncrementals->value] : true,
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

            if ($dir && file_exists($dir . '/' . SnapshotConfigType::RootDbFilename)) {
                $orchestrator = SnapshotOrchestrator::getInstance($this->fileLogger, $this->db, $manager);
                $engine = RestoreEngine::getInstance($this->fileLogger, $this->db, $orchestrator);
                $result = $engine->execute($dir, $options);
                $result[ResponseKeyType::InternalMode->value] = SnapshotWorkerModeType::PerTable->value;

                return $result;
            }
        }

        $result = $manager->restoreSnapshot($id, $options);
        $result[ResponseKeyType::InternalMode->value] = SnapshotWorkerModeType::Legacy->value;

        return $result;
    }

    private function isPerTableSnapshot(array $snapshot): bool {
        $filepath = $snapshot['Filepath'] ?? '';

        if (is_dir($filepath)) {
            return file_exists($filepath . '/' . SnapshotConfigType::RootDbFilename);
        }

        $dir = $snapshot[ResponseKeyType::Directory->value] ?? '';
        $hasDirWithRootDb = !empty($dir) && is_dir($dir);

        if ($hasDirWithRootDb) {
            return file_exists($dir . '/' . SnapshotConfigType::RootDbFilename);
        }

        return false;
    }

    private function resolveSnapshotDir(array $snapshot): ?string {
        $filepath = $snapshot['Filepath'] ?? '';

        if (is_dir($filepath)) {
            return $filepath;
        }

        $dir = $snapshot[ResponseKeyType::Directory->value] ?? '';
        $hasValidDir = !empty($dir) && is_dir($dir);

        if ($hasValidDir) {
            return $dir;
        }

        $hasFilepathWithRootDb = !empty($filepath) && file_exists(dirname($filepath) . '/' . SnapshotConfigType::RootDbFilename);

        if ($hasFilepathWithRootDb) {
            return dirname($filepath);
        }

        return null;
    }
}
