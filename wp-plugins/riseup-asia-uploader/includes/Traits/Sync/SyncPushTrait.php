<?php
/**
 * SyncPushTrait — sync push execution, file processing, and helpers.
 *
 * @package RiseupAsia\Traits\Sync
 * @since   1.57.0
 */

namespace RiseupAsia\Traits\Sync;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;
use Throwable;

use RiseupAsia\Database\FileCache;
use RiseupAsia\Enums\ActionType;
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\ResponseMessageType;
use RiseupAsia\Enums\StatusType;
use RiseupAsia\Enums\SyncActionType;
use RiseupAsia\Enums\SyncEntryStatusType;
use RiseupAsia\Enums\TriggerSourceType;
use RiseupAsia\Helpers\BooleanHelpers;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\ResultHelper;
use RiseupAsia\Upload\UploadIgnore;

trait SyncPushTrait
{
    /** Handle sync push endpoint. */
    public function handleSyncPush(WP_REST_Request $request): WP_REST_Response {
        $body = $request->get_json_params();
        $slug = isset($body['plugin']) ? sanitize_text_field($body['plugin']) : '';
        $files = isset($body[ResponseKeyType::Files->value]) ? $body[ResponseKeyType::Files->value] : array();

        if (empty($slug)) {
            return $this->errorResponse('Plugin slug is required in JSON body', HttpStatusType::BadRequest->value);
        }

        $isFilesInvalid = (BooleanHelpers::isValueEmpty($files) || is_array($files) === false);

        if ($isFilesInvalid) {
            return $this->errorResponse('Files array is required', HttpStatusType::BadRequest->value);
        }

        try {
            $pluginDir = WP_PLUGIN_DIR . '/' . $slug;

            if (PathHelper::isDirMissing($pluginDir)) {
                return $this->errorResponse(ResponseMessageType::PluginNotFound->value . ': ' . $slug, HttpStatusType::NotFound->value);
            }

            $result = $this->executeSyncPush($slug, $files, $pluginDir);

            return new WP_REST_Response($result, HttpStatusType::Ok->value);
        } catch (Throwable $e) {
            return $this->errorResponse('Sync push failed: ' . $e->getMessage(), HttpStatusType::ServerError->value, $e);
        }
    }

    private function executeSyncPush(
        string $slug,
        array $files,
        string $pluginDir,
    ): array {
        $ignore = UploadIgnore::fromDirectory($pluginDir);
        $counters = array(ResponseKeyType::FilesUpdated->value => 0, ResponseKeyType::FilesDeleted->value => 0, ResponseKeyType::FilesIgnored->value => 0);
        $results = array();
        $ignoredFiles = array();

        foreach ($files as $file) {
            $entry = $this->processSyncFile($file, $pluginDir, $slug, $ignore);
            $results[] = $entry;
            $this->updateSyncCounters($entry, $counters, $ignoredFiles);
        }

        $this->logSyncCompletion($slug, $counters);
        FileCache::getInstance($this->fileLogger, $this->db)->invalidate($slug);

        return ResultHelper::ok($counters + array(
            ResponseKeyType::IgnoredFiles->value => $ignoredFiles,
            ResponseKeyType::Results->value => $results,
        ));
    }

    /** Process a single file in the sync push operation. */
    private function processSyncFile(
        array $file,
        string $pluginDir,
        string $slug,
        UploadIgnore $ignore,
    ): array {
        $path   = isset($file[ResponseKeyType::Path->value]) ? $file[ResponseKeyType::Path->value] : '';
        $action = isset($file['action']) ? $file['action'] : '';

        $guardResult = $this->guardSyncFile($path, $action, $pluginDir, $ignore);

        if ($guardResult !== null) {
            return $guardResult;
        }

        $fullPath = $pluginDir . '/' . $path;

        return $this->dispatchSyncAction($path, $action, $fullPath, $pluginDir, $slug, $file);
    }

    /** Validate sync file prerequisites. */
    private function guardSyncFile(
        string $path,
        string $action,
        string $pluginDir,
        UploadIgnore $ignore,
    ): ?array {
        if (empty($path) || empty($action)) {
            return array(ResponseKeyType::Path->value => $path, ResponseKeyType::Action->value => $action, ResponseKeyType::Status->value => SyncEntryStatusType::Skipped->value, ResponseKeyType::Reason->value => 'Missing path or action');
        }
        if ($ignore->shouldIgnore($path)) {
            return array(ResponseKeyType::Path->value => $path, ResponseKeyType::Action->value => $action, ResponseKeyType::Status->value => SyncEntryStatusType::Ignored->value, ResponseKeyType::Reason->value => ResponseMessageType::FileIgnored->value);
        }
        $fullPath = $pluginDir . '/' . $path;

        if ($this->isSyncPathTraversal($fullPath, $pluginDir, $action)) {
            return array(ResponseKeyType::Path->value => $path, ResponseKeyType::Action->value => $action, ResponseKeyType::Status->value => SyncEntryStatusType::Error->value, ResponseKeyType::Reason->value => 'Path traversal detected');
        }

        return null;
    }

    /** Dispatch the sync action to the appropriate handler. */
    private function dispatchSyncAction(
        string $path,
        string $action,
        string $fullPath,
        string $pluginDir,
        string $slug,
        array $file,
    ): array {
        if ($action === SyncActionType::Replace->value) {
            return $this->syncReplaceFile($path, $action, isset($file['content']) ? $file['content'] : '', $fullPath);
        }
        if ($action === SyncActionType::Delete->value) {
            return $this->syncDeleteFile($path, $action, $fullPath, $pluginDir, $slug);
        }

        return array(ResponseKeyType::Path->value => $path, ResponseKeyType::Action->value => $action, ResponseKeyType::Status->value => SyncEntryStatusType::Error->value, ResponseKeyType::Reason->value => 'Unknown action: ' . $action);
    }

    /** Check for path traversal in sync operations. */
    private function isSyncPathTraversal(string $fullPath, string $pluginDir, string $action): bool {
        $realPluginDir = realpath($pluginDir);
        $resolved = realpath(dirname($fullPath));
        if ($resolved === false) { $resolved = $pluginDir; }
        $syncAction = SyncActionType::tryFrom($action);
        $isActionOtherThanDelete = ($syncAction === null || $syncAction->isOtherThan(SyncActionType::Delete));
        $isOutsidePluginDir = (strpos($resolved, $realPluginDir) !== 0);
        $isTraversal =
            $isOutsidePluginDir &&
            $isActionOtherThanDelete;

        return $isTraversal;
    }

    /** Replace (create/update) a file during sync. */
    private function syncReplaceFile(string $path, string $action, string $content, string $fullPath): array {
        $decoded = base64_decode($content, true);

        if ($decoded === false) {
            return array(ResponseKeyType::Path->value => $path, ResponseKeyType::Action->value => $action, ResponseKeyType::Status->value => SyncEntryStatusType::Error->value, ResponseKeyType::Reason->value => 'Invalid base64 content');
        }
        $dir = dirname($fullPath);
        if (PathHelper::isDirMissing($dir)) { PathHelper::makeDirectory($dir); }
        $written = file_put_contents($fullPath, $decoded) !== false;
        $result = array(ResponseKeyType::Path->value => $path, ResponseKeyType::Action->value => $action, ResponseKeyType::Status->value => $written ? SyncEntryStatusType::Success->value : SyncEntryStatusType::Error->value);
        $isWriteFailed = ($written === false);

        if ($isWriteFailed) { $result[ResponseKeyType::Reason->value] = 'Failed to write file'; }

        return $result;
    }

    /** Delete a file during sync with audit trail. */
    private function syncDeleteFile(string $path, string $action, string $fullPath, string $pluginDir, string $slug): array {
        if (PathHelper::isFileMissing($fullPath)) {
            return array(ResponseKeyType::Path->value => $path, ResponseKeyType::Action->value => $action, ResponseKeyType::Status->value => SyncEntryStatusType::Success->value, ResponseKeyType::Reason->value => 'Already absent');
        }
        if ($this->db) {
            $this->db->logTransaction(ActionType::SyncDelete->value, $slug, StatusType::Success->value, 'Deleted via sync: ' . $path, null, null, TriggerSourceType::Api->value);
        }
        $isDeleteFailed = (unlink($fullPath) === false);

        if ($isDeleteFailed) {
            return array(ResponseKeyType::Path->value => $path, ResponseKeyType::Action->value => $action, ResponseKeyType::Status->value => SyncEntryStatusType::Error->value, ResponseKeyType::Reason->value => 'Failed to delete file');
        }
        $this->cleanEmptyParentDirs($fullPath, $pluginDir);

        return array(ResponseKeyType::Path->value => $path, ResponseKeyType::Action->value => $action, ResponseKeyType::Status->value => SyncEntryStatusType::Success->value);
    }

    /** Remove empty parent directories up to the plugin root. */
    private function cleanEmptyParentDirs(string $filepath, string $stopDir): void {
        $parent = dirname($filepath);

        while ($parent !== $stopDir && is_dir($parent) && count(scandir($parent)) <= 2) {
            if (!@rmdir($parent)) {
                break;
            }

            $parent = dirname($parent);
        }
    }

    /** Update sync counters based on a file result entry. */
    private function updateSyncCounters(array $entry, array &$counters, array &$ignored): void {
        $isIgnored = ($entry['status'] === SyncEntryStatusType::Ignored->value);

        if ($isIgnored) { $counters[ResponseKeyType::FilesIgnored->value]++; $ignored[] = $entry[ResponseKeyType::Path->value]; return; }
        $isStatusSuccess = ($entry['status'] === SyncEntryStatusType::Success->value);

        if ($isStatusSuccess) {
            if ($entry['action'] === SyncActionType::Replace->value) { $counters[ResponseKeyType::FilesUpdated->value]++; }
            if ($entry['action'] === SyncActionType::Delete->value)  { $counters[ResponseKeyType::FilesDeleted->value]++; }
        }
    }

    /** Log the completion of a sync push operation. */
    private function logSyncCompletion(string $slug, array $counters): void {
        $isDbMissing = ($this->db === null);

        if ($isDbMissing) { return; }
        $this->db->logTransaction(
            ActionType::Sync->value, $slug, StatusType::Success->value,
            sprintf('Sync: %d updated, %d deleted, %d ignored', $counters[ResponseKeyType::FilesUpdated->value], $counters[ResponseKeyType::FilesDeleted->value], $counters[ResponseKeyType::FilesIgnored->value]),
            null, null, TriggerSourceType::Api->value
        );
    }
}
