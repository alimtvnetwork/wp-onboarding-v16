<?php
/**
 * PluginLifecycleHelpersTrait — shared helpers for plugin lifecycle operations.
 *
 * @package RiseupAsia\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\EndpointType;

trait PluginLifecycleHelpersTrait {

    /**
     * Handle plugin existence check.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function handlePluginExists($request) {
        $body = $request->get_json_params();
        $slug = isset($body['plugin']) ? sanitize_text_field($body['plugin']) : $request->get_param('slug');
        if (empty($slug)) {
            return $this->errorResponse('Plugin slug is required in JSON body', HTTP_BAD_REQUEST);
        }

        try {
            return $this->buildPluginExistsResponse($slug);
        } catch (Throwable $e) {
            return $this->errorResponse('Failed to check plugin existence: ' . $e->getMessage(), HTTP_SERVER_ERROR, $e);
        }
    }

    /** Build the plugin existence check response. */
    private function buildPluginExistsResponse(string $slug): WP_REST_Response {
        $plugin_file = $this->find_plugin_file($slug);
        $exists = (bool) $plugin_file;
        $status = $exists ? (is_plugin_active($plugin_file) ? 'active' : 'inactive') : 'not_installed';

        return RiseupEnvelopeBuilder::success()
            ->setRequestedAt('/' . API_FULL_NAMESPACE . '/' . EndpointType::PluginExists->value)
            ->setSingleResult(array(
                'plugin_slug' => $slug, 'exists' => $exists, 'status' => $status,
                'plugin_file' => $exists ? $plugin_file : null,
                'requestUrl' => $_SERVER['REQUEST_URI'] ?? '', 'responseUrl' => home_url(),
            ))
            ->toResponse();
    }

    /**
     * Load WordPress plugin admin functions if not already available.
     *
     * @param bool $includeFileFunctions Whether to also load file.php.
     * @return WP_REST_Response|null Error response on failure, null on success.
     */
    private function loadPluginFunctions($includeFileFunctions = false) {
        try {
            if (RiseupBooleanHelpers::is_func_missing('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            if ($includeFileFunctions && RiseupBooleanHelpers::is_func_missing('delete_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }

            return null;
        } catch (Throwable $e) {
            return $this->errorResponse('Failed to load WordPress plugin functions: ' . $e->getMessage(), HTTP_SERVER_ERROR, $e);
        }
    }

    /**
     * Resolve and validate a plugin slug from a REST request body.
     *
     * @param WP_REST_Request $request REST request.
     * @return array|WP_REST_Response Resolved info or error response.
     */
    private function resolvePluginFromRequest($request) {
        $body = $request->get_json_params();
        $slug = isset($body['plugin']) ? sanitize_text_field($body['plugin']) : $request->get_param('slug');

        if (empty($slug)) {
            return $this->errorResponse('Plugin slug is required in JSON body', HTTP_BAD_REQUEST);
        }

        try {
            $plugin_file = $this->find_plugin_file($slug);

            if (!$plugin_file) {
                return $this->errorResponse(MSG_PLUGIN_NOT_FOUND . ': ' . $slug, HTTP_NOT_FOUND);
            }

            return array('slug' => $slug, 'plugin_file' => $plugin_file);
        } catch (Throwable $e) {
            return $this->errorResponse('Failed to locate plugin: ' . $e->getMessage(), HTTP_SERVER_ERROR, $e);
        }
    }

    /**
     * Log a plugin lifecycle action, swallowing failures.
     *
     * @param string $action Action constant.
     * @param string $slug   Plugin slug.
     * @param string $status Status constant.
     * @param array  $extra  Optional extra context.
     */
    private function logPluginLifecycle($action, $slug, $status, $extra = array()) {
        try {
            $this->logger->logPluginAction($action, $slug, $status, $extra);
        } catch (Throwable $e) {
            $this->fileLogger->warn('Failed to log plugin action', array('error' => $e->getMessage()));
        }
    }
}
