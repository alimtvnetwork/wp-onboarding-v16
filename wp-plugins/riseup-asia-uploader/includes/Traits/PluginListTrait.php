<?php
/**
 * PluginListTrait — Plugin listing, file scanning, and file content retrieval.
 *
 * @package RiseupAsia\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait PluginListTrait
{
    /**
     * Handle list plugins.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function handle_list_plugins($request) {
        $this->file_logger->info('List plugins endpoint called');

        try {
            if (RiseupBooleanHelpers::is_func_missing('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $plugins = $this->collectPluginList();

            return RiseupEnvelopeBuilder::success()
                ->setRequestedAt('/' . API_FULL_NAMESPACE . ENDPOINT_PLUGINS)
                ->setResults($plugins)
                ->toResponse();
        } catch (\Throwable $e) {
            $this->file_logger->log_exception($e, 'List plugins error');

            return $this->error_response('Failed to list plugins: ' . $e->getMessage(), HTTP_SERVER_ERROR, $e);
        }
    }

    /**
     * Collect all installed plugins into a normalized array.
     *
     * @return array Plugin list.
     */
    private function collectPluginList(): array {
        $all_plugins    = get_plugins();
        $active_plugins = get_option('active_plugins', array());
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
    public function handle_plugin_files($request) {
        $body = $request->get_json_params();
        $slug = isset($body['plugin']) ? sanitize_text_field($body['plugin']) : $request->get_param('slug');
        if (empty($slug)) {
            return $this->error_response('Plugin slug is required in JSON body', HTTP_BAD_REQUEST);
        }

        try {
            return $this->scanPluginFilesWithCache($slug);
        } catch (Throwable $e) {
            return $this->error_response('Failed to list plugin files: ' . $e->getMessage(), HTTP_SERVER_ERROR, $e);
        }
    }

    /**
     * Scan plugin files using the file cache and return the response.
     *
     * @param string $slug Plugin slug.
     * @return WP_REST_Response
     */
    private function scanPluginFilesWithCache(string $slug) {
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
            'success'    => true,
            'plugin'     => $slug,
            'totalFiles' => count($result['files']),
            'files'      => $result['files'],
        ), HTTP_OK);
    }

    /**
     * Handle getting content of a single file from a plugin.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function handle_plugin_file_content($request) {
        $json = $request->get_json_params();
        $slug = isset($json['plugin']) ? sanitize_text_field($json['plugin']) : $request->get_param('slug');
        if (empty($slug)) {
            return $this->error_response('Plugin slug is required in JSON body', HTTP_BAD_REQUEST);
        }

        try {
            $file_path = isset($json['path']) ? $json['path'] : null;

            $validation = $this->validateFilePath($file_path, $slug);
            if ($validation instanceof WP_REST_Response) {
                return $validation;
            }

            return $this->readAndReturnFile($validation['real_path'], $validation['file_path']);
        } catch (\Throwable $e) {
            return $this->error_response('Failed to read file: ' . $e->getMessage(), HTTP_SERVER_ERROR, $e);
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
            return $this->error_response('File path is required', HTTP_BAD_REQUEST);
        }

        $file_path = ltrim($file_path, '/\\');
        if (strpos($file_path, '..') !== false) {
            return $this->error_response('Invalid file path', HTTP_BAD_REQUEST);
        }

        $plugin_dir = WP_PLUGIN_DIR . '/' . $slug;
        if (RiseupBooleanHelpers::is_dir_missing($plugin_dir)) {
            return $this->error_response(MSG_PLUGIN_NOT_FOUND . ': ' . $slug, HTTP_NOT_FOUND);
        }

        $real_plugin_dir = realpath($plugin_dir);
        $real_file_path = realpath($plugin_dir . '/' . $file_path);

        if ($real_file_path === false || strpos($real_file_path, $real_plugin_dir) !== 0) {
            return $this->error_response('File not found or invalid path', HTTP_NOT_FOUND);
        }

        if (RiseupBooleanHelpers::is_not_regular_file($real_file_path)) {
            return $this->error_response('File not found', HTTP_NOT_FOUND);
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
            return $this->error_response('Failed to read file', HTTP_SERVER_ERROR);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'path'    => $rel_path,
            'content' => $content,
        ), HTTP_OK);
    }
}
