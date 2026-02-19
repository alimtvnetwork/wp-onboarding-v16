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
use RiseupAsia\Upload\UploadIgnore;
use RiseupAsia\Database\FileCache;
use WP_REST_Response;
use Throwable;
use RiseupAsia\Enums\ActionType;
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\ResponseMessageType;
use RiseupAsia\Enums\StatusType;
use RiseupAsia\Enums\SyncActionType;
use RiseupAsia\Enums\TriggerSourceType;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\BooleanHelpers;

trait SyncPushTrait
{
    /** Handle sync push endpoint. */
    public function handleSyncPush(WP_REST_Request $request): WP_REST_Response {
        $body = $request->get_json_params();
        $slug = isset($body['plugin']) ? sanitize_text_field($body['plugin']) : '';
        $files = isset($body['files']) ? $body['files'] : array();

        if (empty($slug)) {
            return $this->errorResponse('Plugin slug is required in JSON body', HttpStatusType::BadRequest->value);
        }
        $isFilesInvalid = (BooleanHelpers::isValueEmpty($files) || is_array($files) === false);
        if ($isFilesInvalid) {
            return $this->errorResponse('Files array is required', HttpStatusType::BadRequest->value);
        }

        try {
            $plugin_dir = WP_PLUGIN_DIR . '/' . $slug;
            if (PathHelper::isDirMissing($plugin_dir)) {
                return $this->errorResponse(ResponseMessageType::PluginNotFound->value . ': ' . $slug, HttpStatusType::NotFound->value);
            }
            $result = $this->executeSyncPush($slug, $files, $plugin_dir);

            return new WP_REST_Response($result, HttpStatusType::Ok->value);
        } catch (Throwable $e) {
            return $this->errorResponse('Sync push failed: ' . $e->getMessage(), HttpStatusType::ServerError->value, $e);
        }
    }

    /** Execute the sync push operation across all files. */
    private function executeSyncPush(
        string $slug,
        array $files,
        string $plugin_dir,
    ): array {
        $ignore = UploadIgnore::fromDirectory($plugin_dir);
        $counters = array('files_updated' => 0, 'files_deleted' => 0, 'files_ignored' => 0);
        $results = array();
        $ignored_files = array();

        foreach ($files as $file) {
            $entry = $this->processSyncFile($file, $plugin_dir, $slug, $ignore);
            $results[] = $entry;
            $this->updateSyncCounters($entry, $counters, $ignored_files);
        }

        $this->logSyncCompletion($slug, $counters);
        FileCache::getInstance($this->fileLogger, $this->db)->invalidate($slug);

        return array('success' => true) + $counters + array('ignored_files' => $ignored_files, 'results' => $results);
    }

    /** Process a single file in the sync push operation. */
    private function processSyncFile(
        array $file,
        string $plugin_dir,
        string $slug,
        UploadIgnore $ignore,
    ): array {
        $path   = isset($file['path']) ? $file['path'] : '';
        $action = isset($file['action']) ? $file['action'] : '';

        $guardResult = $this->guardSyncFile($path, $action, $plugin_dir, $ignore);
        if ($guardResult !== null) {
            return $guardResult;
        }

        $full_path = $plugin_dir . '/' . $path;

        return $this->dispatchSyncAction($path, $action, $full_path, $plugin_dir, $slug, $file);
    }

    /** Validate sync file prerequisites. */
    private function guardSyncFile(
        string $path,
        string $action,
        string $plugin_dir,
        UploadIgnore $ignore,
    ): ?array {
        if (empty($path) || empty($action)) {
            return array('path' => $path, 'action' => $action, 'status' => 'skipped', 'reason' => 'Missing path or action');
        }
        if ($ignore->shouldIgnore($path)) {
            return array('path' => $path, 'action' => $action, 'status' => 'ignored', 'reason' => ResponseMessageType::FileIgnored->value);
        }

        $full_path = $plugin_dir . '/' . $path;
        if ($this->isSyncPathTraversal($full_path, $plugin_dir, $action)) {
            return array('path' => $path, 'action' => $action, 'status' => 'error', 'reason' => 'Path traversal detected');
        }

        return null;
    }

    /** Dispatch the sync action to the appropriate handler. */
    private function dispatchSyncAction(
        string $path,
        string $action,
        string $full_path,
        string $plugin_dir,
        string $slug,
        array $file,
    ): array {
        if ($action === SyncActionType::Replace->value) {
            return $this->syncReplaceFile($path, $action, isset($file['content']) ? $file['content'] : '', $full_path);
        }
        if ($action === SyncActionType::Delete->value) {
            return $this->syncDeleteFile($path, $action, $full_path, $plugin_dir, $slug);
        }

        return array('path' => $path, 'action' => $action, 'status' => 'error', 'reason' => 'Unknown action: ' . $action);
    }

    /** Check for path traversal in sync operations. */
    private function isSyncPathTraversal(
        string $full_path,
        string $plugin_dir,
        string $action,
    ): bool {
        $real_plugin_dir = realpath($plugin_dir);
        $resolved = realpath(dirname($full_path));
        if ($resolved === false) {
            $resolved = $plugin_dir;
        }

        $syncAction = SyncActionType::tryFrom($action);
        $isActionOtherThanDelete = ($syncAction === null || $syncAction->isOtherThan(SyncActionType::Delete));

        return (strpos($resolved, $real_plugin_dir) !== 0 && $isActionOtherThanDelete);
    }

    /** Replace (create/update) a file during sync. */
    private function syncReplaceFile(
        string $path,
        string $action,
        string $content,
        string $full_path,
    ): array {
        $decoded = base64_decode($content, true);
        if ($decoded === false) {
            return array('path' => $path, 'action' => $action, 'status' => 'error', 'reason' => 'Invalid base64 content');
        }

        $dir = dirname($full_path);
        if (PathHelper::isDirMissing($dir)) {
            PathHelper::makeDirectory($dir);
        }

        $written = file_put_contents($full_path, $decoded) !== false;
        $result = array('path' => $path, 'action' => $action, 'status' => $written ? 'success' : 'error');
        $isWriteFailed = ($written === false);
        if ($isWriteFailed) {
            $result['reason'] = 'Failed to write file';
        }

        return $result;
    }

    /** Delete a file during sync with audit trail. */
    private function syncDeleteFile(
        string $path,
        string $action,
        string $full_path,
        string $plugin_dir,
        string $slug,
    ): array {
        if (PathHelper::isFileMissing($full_path)) {
            return array('path' => $path, 'action' => $action, 'status' => 'success', 'reason' => 'Already absent');
        }

        if ($this->db) {
            $this->db->logTransaction(ActionType::SyncDelete->value, $slug, StatusType::Success->value, 'Deleted via sync: ' . $path, null, null, TriggerSourceType::Api->value);
        }

        $isDeleteFailed = (unlink($full_path) === false);
        if ($isDeleteFailed) {
            return array('path' => $path, 'action' => $action, 'status' => 'error', 'reason' => 'Failed to delete file');
        }

        $this->cleanEmptyParentDirs($full_path, $plugin_dir);

        return array('path' => $path, 'action' => $action, 'status' => 'success');
    }

    /** Remove empty parent directories up to the plugin root. */
    private function cleanEmptyParentDirs(string $filepath, string $stop_dir): void {
        $parent = dirname($filepath);
        while ($parent !== $stop_dir && is_dir($parent) && count(scandir($parent)) <= 2) {
            rmdir($parent);
            $parent = dirname($parent);
        }
    }

    /** Update sync counters based on a file result entry. */
    private function updateSyncCounters(
        array $entry,
        array &$counters,
        array &$ignored,
    ): void {
        $isIgnored = ($entry['status'] === 'ignored'); // Sync-specific status; no enum yet — candidate for SyncEntryStatusType
        if ($isIgnored) {
            $counters['files_ignored']++;
            $ignored[] = $entry['path'];

            return;
        }
        $isStatusSuccess = ($entry['status'] === StatusType::Success->value);
        if ($isStatusSuccess) {
            if ($entry['action'] === SyncActionType::Replace->value) { $counters['files_updated']++; }
            if ($entry['action'] === SyncActionType::Delete->value)  { $counters['files_deleted']++; }
        }
    }

    /** Log the completion of a sync push operation. */
    private function logSyncCompletion(string $slug, array $counters): void {
        $isDbMissing = ($this->db === null);
        if ($isDbMissing) {
            return;
        }
        $this->db->logTransaction(
            ActionType::Sync->value, $slug, StatusType::Success->value,
            sprintf('Sync: %d updated, %d deleted, %d ignored', $counters['files_updated'], $counters['files_deleted'], $counters['files_ignored']),
            null, null, TriggerSourceType::Api->value
        );
    }
}
