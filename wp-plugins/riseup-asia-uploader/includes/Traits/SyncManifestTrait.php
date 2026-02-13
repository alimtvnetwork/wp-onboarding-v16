<?php
/**
 * SyncManifestTrait — sync manifest generation and directory scanning.
 *
 * @package RiseupAsia\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait SyncManifestTrait
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
            'data' => array(
                'plugin' => $slug, 'fileCount' => count($result['files']),
                'generatedAt' => gmdate('c'), 'cached' => $result['cached'] > 0,
                'cacheStats' => array('fromCache' => $result['cached'], 'computed' => $result['computed'], 'removed' => $result['removed']),
                'files' => $result['files'],
            ),
        ), HTTP_OK);
    }

    /**
     * Recursively scan a directory and collect file info with hashes.
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
                $files[] = array(
                    'path' => str_replace('\\', '/', $rel_path),
                    'hash' => @md5_file($full_path) ?: '',
                    'size' => @filesize($full_path) ?: 0,
                    'modifiedAt' => ($mtime = @filemtime($full_path)) ? gmdate('c', $mtime) : null,
                );
            }
        }
    }
}
