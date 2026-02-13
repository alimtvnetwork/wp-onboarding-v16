<?php
/**
 * SyncHandlerTrait — Delta file sync manifest and push handlers.
 *
 * @package RiseupAsia\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait SyncHandlerTrait
{
    /**
     * Handle sync manifest endpoint.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function handle_sync_manifest($request) {
        $body = $request->get_json_params();
        $slug = isset($body['plugin']) ? sanitize_text_field($body['plugin']) : $request->get_param('slug');
        if (empty($slug)) {
            return $this->error_response('Plugin slug is required in JSON body', HTTP_BAD_REQUEST);
        }

        try {
            return $this->generateSyncManifest($slug);
        } catch (Throwable $e) {
            return $this->error_response('Failed to generate sync manifest: ' . $e->getMessage(), HTTP_SERVER_ERROR, $e);
        }
    }

    /**
     * Generate a sync manifest for a plugin.
     *
     * @param string $slug Plugin slug.
     * @return WP_REST_Response
     */
    private function generateSyncManifest(string $slug) {
        if (RiseupBooleanHelpers::is_func_missing('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_dir = WP_PLUGIN_DIR . '/' . $slug;
        if (RiseupBooleanHelpers::is_dir_missing($plugin_dir)) {
            return $this->error_response(MSG_PLUGIN_NOT_FOUND . ': ' . $slug, HTTP_NOT_FOUND);
        }

        $ignore = RiseupUploadIgnore::fromDirectory($plugin_dir);
        $fileCache = RiseupFileCache::getInstance($this->file_logger, $this->db);
        $result = $fileCache->getManifest($slug, $plugin_dir, $ignore);

        return new WP_REST_Response(array(
            'success' => true,
            'data'    => array(
                'plugin'      => $slug,
                'fileCount'   => count($result['files']),
                'generatedAt' => gmdate('c'),
                'cached'      => $result['cached'] > 0,
                'cacheStats'  => array(
                    'fromCache' => $result['cached'],
                    'computed'  => $result['computed'],
                    'removed'   => $result['removed'],
                ),
                'files'       => $result['files'],
            ),
        ), HTTP_OK);
    }

    /**
     * Handle sync push endpoint.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function handle_sync_push($request) {
        $body = $request->get_json_params();
        $slug = isset($body['plugin']) ? sanitize_text_field($body['plugin']) : '';
        $files = isset($body['files']) ? $body['files'] : array();

        if (empty($slug)) {
            return $this->error_response('Plugin slug is required in JSON body', HTTP_BAD_REQUEST);
        }
        if (empty($files) || !is_array($files)) {
            return $this->error_response('Files array is required', HTTP_BAD_REQUEST);
        }

        try {
            $plugin_dir = WP_PLUGIN_DIR . '/' . $slug;
            if (RiseupBooleanHelpers::is_dir_missing($plugin_dir)) {
                return $this->error_response(MSG_PLUGIN_NOT_FOUND . ': ' . $slug, HTTP_NOT_FOUND);
            }

            $result = $this->executeSyncPush($slug, $files, $plugin_dir);
            return new WP_REST_Response($result, HTTP_OK);
        } catch (Throwable $e) {
            return $this->error_response('Sync push failed: ' . $e->getMessage(), HTTP_SERVER_ERROR, $e);
        }
    }

    /**
     * Execute the sync push operation across all files.
     *
     * @param string $slug       Plugin slug.
     * @param array  $files      Files to sync.
     * @param string $plugin_dir Plugin directory path.
     * @return array Sync result payload.
     */
    private function executeSyncPush(string $slug, array $files, string $plugin_dir): array {
        $ignore = RiseupUploadIgnore::fromDirectory($plugin_dir);
        $counters = array('files_updated' => 0, 'files_deleted' => 0, 'files_ignored' => 0);
        $results = array();
        $ignored_files = array();

        foreach ($files as $file) {
            $entry = $this->processSyncFile($file, $plugin_dir, $slug, $ignore);
            $results[] = $entry;
            $this->updateSyncCounters($entry, $counters, $ignored_files);
        }

        $this->logSyncCompletion($slug, $counters);
        RiseupFileCache::getInstance($this->file_logger, $this->db)->invalidate($slug);

        return array('success' => true) + $counters + array('ignored_files' => $ignored_files, 'results' => $results);
    }

    /**
     * Process a single file in the sync push operation.
     *
     * @param array  $file       File entry with path, action, content.
     * @param string $plugin_dir Plugin directory path.
     * @param string $slug       Plugin slug.
     * @param mixed  $ignore     Ignore pattern matcher.
     * @return array Result entry for this file.
     */
    private function processSyncFile(array $file, string $plugin_dir, string $slug, $ignore): array {
        $path   = isset($file['path']) ? $file['path'] : '';
        $action = isset($file['action']) ? $file['action'] : '';

        if (empty($path) || empty($action)) {
            return array('path' => $path, 'action' => $action, 'status' => 'skipped', 'reason' => 'Missing path or action');
        }
        if ($ignore && $ignore->is_ignored($path)) {
            return array('path' => $path, 'action' => $action, 'status' => 'ignored', 'reason' => MSG_FILE_IGNORED);
        }

        $full_path = $plugin_dir . '/' . $path;
        if ($this->isSyncPathTraversal($full_path, $plugin_dir, $action)) {
            return array('path' => $path, 'action' => $action, 'status' => 'error', 'reason' => 'Path traversal detected');
        }

        if ($action === SYNC_ACTION_REPLACE) {
            return $this->syncReplaceFile($path, $action, isset($file['content']) ? $file['content'] : '', $full_path);
        }
        if ($action === SYNC_ACTION_DELETE) {
            return $this->syncDeleteFile($path, $action, $full_path, $plugin_dir, $slug);
        }

        return array('path' => $path, 'action' => $action, 'status' => 'error', 'reason' => 'Unknown action: ' . $action);
    }

    /**
     * Check for path traversal in sync operations.
     *
     * @param string $full_path  Resolved file path.
     * @param string $plugin_dir Plugin directory root.
     * @param string $action     Sync action type.
     * @return bool True if path traversal detected.
     */
    private function isSyncPathTraversal(string $full_path, string $plugin_dir, string $action): bool {
        $real_plugin_dir = realpath($plugin_dir);
        $resolved = realpath(dirname($full_path));
        if ($resolved === false) {
            $resolved = $plugin_dir;
        }
        return (strpos($resolved, $real_plugin_dir) !== 0 && $action !== SYNC_ACTION_DELETE);
    }

    /**
     * Replace (create/update) a file during sync.
     *
     * @param string $path      Relative file path.
     * @param string $action    Action type.
     * @param string $content   Base64-encoded content.
     * @param string $full_path Absolute file path.
     * @return array Result entry.
     */
    private function syncReplaceFile(string $path, string $action, string $content, string $full_path): array {
        $decoded = base64_decode($content, true);
        if ($decoded === false) {
            return array('path' => $path, 'action' => $action, 'status' => 'error', 'reason' => 'Invalid base64 content');
        }

        $dir = dirname($full_path);
        if (RiseupBooleanHelpers::is_dir_missing($dir)) {
            RiseupPathUtils::ensureDir($dir);
        }

        $written = file_put_contents($full_path, $decoded) !== false;
        $status = $written ? 'success' : 'error';
        $result = array('path' => $path, 'action' => $action, 'status' => $status);
        if (!$written) {
            $result['reason'] = 'Failed to write file';
        }
        return $result;
    }

    /**
     * Delete a file during sync with audit trail.
     *
     * @param string $path       Relative file path.
     * @param string $action     Action type.
     * @param string $full_path  Absolute file path.
     * @param string $plugin_dir Plugin directory root.
     * @param string $slug       Plugin slug.
     * @return array Result entry.
     */
    private function syncDeleteFile(string $path, string $action, string $full_path, string $plugin_dir, string $slug): array {
        if (!file_exists($full_path)) {
            return array('path' => $path, 'action' => $action, 'status' => 'success', 'reason' => 'Already absent');
        }

        if ($this->db) {
            $this->db->log_transaction(ACTION_SYNC_DELETE, $slug, STATUS_SUCCESS, 'Deleted via sync: ' . $path, null, null, TRIGGERED_BY_API);
        }

        if (!unlink($full_path)) {
            return array('path' => $path, 'action' => $action, 'status' => 'error', 'reason' => 'Failed to delete file');
        }

        $this->cleanEmptyParentDirs($full_path, $plugin_dir);
        return array('path' => $path, 'action' => $action, 'status' => 'success');
    }

    /**
     * Remove empty parent directories up to the plugin root.
     *
     * @param string $filepath Deleted file path.
     * @param string $stop_dir Directory to stop at.
     */
    private function cleanEmptyParentDirs(string $filepath, string $stop_dir) {
        $parent = dirname($filepath);
        while ($parent !== $stop_dir && is_dir($parent) && count(scandir($parent)) <= 2) {
            rmdir($parent);
            $parent = dirname($parent);
        }
    }

    /**
     * Update sync counters based on a file result entry.
     *
     * @param array $entry     File result entry.
     * @param array &$counters Counters reference.
     * @param array &$ignored  Ignored files list reference.
     */
    private function updateSyncCounters(array $entry, array &$counters, array &$ignored) {
        if ($entry['status'] === 'ignored') {
            $counters['files_ignored']++;
            $ignored[] = $entry['path'];
            return;
        }
        if ($entry['status'] !== 'success') {
            return;
        }
        if ($entry['action'] === SYNC_ACTION_REPLACE) {
            $counters['files_updated']++;
        }
        if ($entry['action'] === SYNC_ACTION_DELETE) {
            $counters['files_deleted']++;
        }
    }

    /**
     * Log the completion of a sync push operation.
     *
     * @param string $slug     Plugin slug.
     * @param array  $counters Sync counters.
     */
    private function logSyncCompletion(string $slug, array $counters) {
        if (!$this->db) {
            return;
        }
        $this->db->log_transaction(
            ACTION_SYNC, $slug, STATUS_SUCCESS,
            sprintf('Sync: %d updated, %d deleted, %d ignored', $counters['files_updated'], $counters['files_deleted'], $counters['files_ignored']),
            null, null, TRIGGERED_BY_API
        );
    }

    /**
     * Recursively scan a directory and collect file info with hashes.
     *
     * @param string             $base_dir Base directory for relative paths.
     * @param string             $dir      Current directory to scan.
     * @param RiseupUploadIgnore $ignore   Ignore patterns.
     * @param array              &$files   Reference to files array.
     */
    private function scan_directory_for_files($base_dir, $dir, $ignore, &$files) {
        $items = @scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full_path = $dir . '/' . $item;
            $rel_path  = ltrim(str_replace($base_dir, '', $full_path), '/\\');

            if ($ignore->shouldIgnore($rel_path)) {
                continue;
            }

            if (is_dir($full_path)) {
                $this->scan_directory_for_files($base_dir, $full_path, $ignore, $files);
            } else {
                $hash = @md5_file($full_path);
                $size = @filesize($full_path);
                $mtime = @filemtime($full_path);

                $files[] = array(
                    'path'       => str_replace('\\', '/', $rel_path),
                    'hash'       => $hash ?: '',
                    'size'       => $size ?: 0,
                    'modifiedAt' => $mtime ? gmdate('c', $mtime) : null,
                );
            }
        }
    }
}
