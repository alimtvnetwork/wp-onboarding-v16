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
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\ResponseMessageType;
use RiseupAsia\Helpers\BooleanHelpers;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\DateHelper;

trait SyncManifestTrait
{
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

    private function generateSyncManifest(string $slug): WP_REST_Response {
        if (BooleanHelpers::isFuncMissing('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $pluginDir = WP_PLUGIN_DIR . '/' . $slug;

        if (PathHelper::isDirMissing($pluginDir)) {
            return $this->errorResponse(ResponseMessageType::PluginNotFound->value . ': ' . $slug, HttpStatusType::NotFound->value);
        }

        $ignore = UploadIgnore::fromDirectory($pluginDir);
        $fileCache = FileCache::getInstance($this->fileLogger, $this->db);
        $result = $fileCache->getManifest($slug, $pluginDir, $ignore);

        return new WP_REST_Response(array(
            ResponseKeyType::Success->value => true,
            ResponseKeyType::Data->value => array(
                ResponseKeyType::Plugin->value    => $slug,
                ResponseKeyType::FileCount->value => count($result[ResponseKeyType::Files->value]),
                ResponseKeyType::GeneratedAt->value => DateHelper::nowIso(),
                ResponseKeyType::Cached->value    => $result[ResponseKeyType::Cached->value] > 0,
                ResponseKeyType::CacheStats->value => array(
                    ResponseKeyType::FromCache->value => $result[ResponseKeyType::Cached->value],
                    ResponseKeyType::Computed->value  => $result[ResponseKeyType::Computed->value],
                    ResponseKeyType::Removed->value   => $result[ResponseKeyType::Removed->value],
                ),
                ResponseKeyType::Files->value => $result[ResponseKeyType::Files->value],
            ),
        ), HttpStatusType::Ok->value);
    }

    private function scanDirectoryForFiles(
        string $baseDir,
        string $dir,
        UploadIgnore $ignore,
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

            $fullPath = $dir . '/' . $item;
            $relPath  = ltrim(str_replace($baseDir, '', $fullPath), '/\\');

            if ($ignore->shouldIgnore($relPath)) {
                continue;
            }

            if (is_dir($fullPath)) {
                $this->scanDirectoryForFiles($baseDir, $fullPath, $ignore, $files);
            } else {
                $files[] = $this->buildFileEntry($relPath, $fullPath);
            }
        }
    }

    private function buildFileEntry(string $relPath, string $fullPath): array {
        return array(
            'path' => str_replace('\\', '/', $relPath),
            'hash' => @md5_file($fullPath) ?: '',
            'size' => @filesize($fullPath) ?: 0,
            'modifiedAt' => ($mtime = @filemtime($fullPath)) ? DateHelper::formatIso($mtime) : null,
        );
    }
}
