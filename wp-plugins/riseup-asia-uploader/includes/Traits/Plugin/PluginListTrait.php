<?php
/**
 * PluginListTrait — Plugin listing, file scanning, and file content retrieval.
 *
 * @package RiseupAsia\Traits\Plugin
 * @since   1.57.0
 */

namespace RiseupAsia\Traits\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;
use Throwable;
use RiseupAsia\Enums\EndpointType;
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Upload\UploadIgnore;
use RiseupAsia\Database\FileCache;
use RiseupAsia\Enums\ResponseMessageType;
use RiseupAsia\Enums\OptionNameType;
use RiseupAsia\Helpers\BooleanHelpers;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\EnvelopeBuilder;

trait PluginListTrait
{
    /**
     * Handle list plugins.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function handleListPlugins($request) {
        $this->fileLogger->info('List plugins endpoint called');

        try {
            if (BooleanHelpers::isFuncMissing('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $plugins = $this->collectPluginList();

            return EnvelopeBuilder::success()
                ->setRequestedAt('/' . PluginConfigType::apiFullNamespace() . EndpointType::Plugins->route())
                ->setResults($plugins)
                ->toResponse();
        } catch (Throwable $e) {
            $this->fileLogger->logException($e, 'List plugins error');

            return $this->errorResponse('Failed to list plugins: ' . $e->getMessage(), HttpStatusType::ServerError->value, $e);
        }
    }

    /**
     * Collect all installed plugins into a normalized array.
     *
     * @return array Plugin list.
     */
    private function collectPluginList(): array {
        $all_plugins    = get_plugins();
        $active_plugins = get_option(OptionNameType::ActivePlugins->value, array());
        $plugins        = array();

        foreach ($all_plugins as $plugin_file => $plugin_data) {
            $slug = dirname($plugin_file);
            if ($slug === '.') {
                $slug = basename($plugin_file, '.php');
            }

            $plugins[] = array(
                'slug'        => $slug,
                'name'        => $plugin_data['Name'],
                'version'     => $plugin_data['Version'],
                'author'      => $plugin_data['Author'],
                'description' => $plugin_data['Description'],
                'active'      => in_array($plugin_file, $active_plugins, true),
                'plugin_file' => $plugin_file,
            );
        }

        return $plugins;
    }

    /**
     * Handle plugin files listing (for diff preview).
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function handlePluginFiles($request) {
        $body = $request->get_json_params();
        $slug = isset($body['plugin']) ? sanitize_text_field($body['plugin']) : $request->get_param('slug');
        if (empty($slug)) {
            return $this->errorResponse('Plugin slug is required in JSON body', HttpStatusType::BadRequest->value);
        }

        try {
            return $this->scanPluginFilesWithCache($slug);
        } catch (Throwable $e) {
            return $this->errorResponse('Failed to list plugin files: ' . $e->getMessage(), HttpStatusType::ServerError->value, $e);
        }
    }

    /**
     * Scan plugin files using the file cache and return the response.
     *
     * @param string $slug Plugin slug.
     * @return WP_REST_Response
     */
    private function scanPluginFilesWithCache(string $slug) {
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
            'success'    => true,
            'plugin'     => $slug,
            'totalFiles' => count($result['files']),
            'files'      => $result['files'],
        ), HttpStatusType::Ok->value);
    }

    /**
     * Handle getting content of a single file from a plugin.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function handlePluginFileContent($request) {
        $json = $request->get_json_params();
        $slug = isset($json['plugin']) ? sanitize_text_field($json['plugin']) : $request->get_param('slug');
        if (empty($slug)) {
            return $this->errorResponse('Plugin slug is required in JSON body', HttpStatusType::BadRequest->value);
        }

        try {
            $file_path = isset($json['path']) ? $json['path'] : null;

            $validation = $this->validateFilePath($file_path, $slug);
            if ($validation instanceof WP_REST_Response) {
                return $validation;
            }

            return $this->readAndReturnFile($validation['real_path'], $validation['file_path']);
        } catch (Throwable $e) {
            return $this->errorResponse('Failed to read file: ' . $e->getMessage(), HttpStatusType::ServerError->value, $e);
        }
    }

    /**
     * Validate and resolve a plugin file path.
     *
     * @param string|null $file_path Relative file path.
     * @param string      $slug      Plugin slug.
     * @return array{real_path: string, file_path: string}|WP_REST_Response
     */
    private function validateFilePath($file_path, string $slug) {
        if (empty($file_path)) {
            return $this->errorResponse('File path is required', HttpStatusType::BadRequest->value);
        }

        $file_path = ltrim($file_path, '/\\');
        if (strpos($file_path, '..') !== false) {
            return $this->errorResponse('Invalid file path', HttpStatusType::BadRequest->value);
        }

        $plugin_dir = WP_PLUGIN_DIR . '/' . $slug;
        if (PathHelper::isDirMissing($plugin_dir)) {
            return $this->errorResponse(ResponseMessageType::PluginNotFound->value . ': ' . $slug, HttpStatusType::NotFound->value);
        }

        $real_plugin_dir = realpath($plugin_dir);
        $real_file_path = realpath($plugin_dir . '/' . $file_path);

        if ($real_file_path === false || strpos($real_file_path, $real_plugin_dir) !== 0) {
            return $this->errorResponse('File not found or invalid path', HttpStatusType::NotFound->value);
        }

        if (BooleanHelpers::isNotRegularFile($real_file_path)) {
            return $this->errorResponse('File not found', HttpStatusType::NotFound->value);
        }

        return array('real_path' => $real_file_path, 'file_path' => $file_path);
    }

    /**
     * Read a file and return its content as a REST response.
     *
     * @param string $real_path Absolute file path.
     * @param string $rel_path  Relative file path.
     * @return WP_REST_Response
     */
    private function readAndReturnFile(string $real_path, string $rel_path) {
        $content = @file_get_contents($real_path);
        if ($content === false) {
            return $this->errorResponse('Failed to read file', HttpStatusType::ServerError->value);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'path'    => $rel_path,
            'content' => $content,
        ), HttpStatusType::Ok->value);
    }
}
