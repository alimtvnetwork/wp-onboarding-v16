<?php
/**
 * SyncManifestTrait — sync manifest generation and directory scanning.
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
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\ResponseMessageType;
use RiseupAsia\Helpers\BooleanHelpers;
use RiseupAsia\Helpers\PathHelper;

trait SyncManifestTrait
{
    /**
     * Handle sync manifest endpoint.
     */
    public function handleSyncManifest(WP_REST_Request $request): WP_REST_Response {
        $body = $request->get_json_params();
        $slug = isset($body['plugin']) ? sanitize_text_field($body['plugin']) : $request->get_param('slug');
        if (empty($slug)) {
            return $this->errorResponse('Plugin slug is required in JSON body', HttpStatusType::BadRequest->value);
        }

        try {
            return $this->generateSyncManifest($slug);
        } catch (Throwable $e) {
            return $this->errorResponse('Failed to generate sync manifest: ' . $e->getMessage(), HttpStatusType::ServerError->value, $e);
        }
    }

    /** Generate a sync manifest for a plugin. */
    private function generateSyncManifest(string $slug): WP_REST_Response {
        if (BooleanHelpers::isFuncMissing('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_dir = WP_PLUGIN_DIR . '/' . $slug;
        if (PathHelper::isDirMissing($plugin_dir)) {
            return $this->errorResponse(ResponseMessageType::PluginNotFound->value . ': ' . $slug, HttpStatusType::NotFound->value);
        }

        $ignore = UploadIgnore::fromDirectory($plugin_dir);
        $fileCache = FileCache::getInstance($this->fileLogger, $this->db);
        $result = $fileCache->getManifest($slug, $plugin_dir, $ignore);

        return new WP_REST_Response(array(
            'success' => true,
            'data' => array(
                'plugin' => $slug, 'fileCount' => count($result['files']),
                'generatedAt' => gmdate('c'), 'cached' => $result['cached'] > 0,
                'cacheStats' => array('fromCache' => $result['cached'], 'computed' => $result['computed'], 'removed' => $result['removed']),
                'files' => $result['files'],
            ),
        ), HttpStatusType::Ok->value);
    }

    /** Recursively scan a directory and collect file info with hashes. */
    private function scanDirectoryForFiles(
        string $baseDir,
        string $dir,
        \RiseupUploadIgnore $ignore,
        array &$files,
    ): void {
        $items = @scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full_path = $dir . '/' . $item;
            $rel_path  = ltrim(str_replace($baseDir, '', $full_path), '/\\');

            if ($ignore->shouldIgnore($rel_path)) {
                continue;
            }

            if (is_dir($full_path)) {
                $this->scanDirectoryForFiles($baseDir, $full_path, $ignore, $files);
            } else {
                $files[] = $this->buildFileEntry($rel_path, $full_path);
            }
        }
    }

    /** Build a file entry for the manifest. */
    private function buildFileEntry(string $rel_path, string $full_path): array {
        return array(
            'path' => str_replace('\\', '/', $rel_path),
            'hash' => @md5_file($full_path) ?: '',
            'size' => @filesize($full_path) ?: 0,
            'modifiedAt' => ($mtime = @filemtime($full_path)) ? gmdate('c', $mtime) : null,
        );
    }
}
