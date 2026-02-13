<?php
/**
 * PluginLifecycleTrait — Plugin exists, enable, disable, delete handlers.
 *
 * @package RiseupAsia\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait PluginLifecycleTrait
{
    /**
     * Handle plugin existence check.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function handle_plugin_exists($request) {
        $body = $request->get_json_params();
        $slug = isset($body['plugin']) ? sanitize_text_field($body['plugin']) : $request->get_param('slug');
        if (empty($slug)) {
            return $this->error_response('Plugin slug is required in JSON body', HTTP_BAD_REQUEST);
        }

        try {
            $plugin_file = $this->find_plugin_file($slug);
            $exists = (bool) $plugin_file;
            $status = $exists ? (is_plugin_active($plugin_file) ? 'active' : 'inactive') : 'not_installed';

            return RiseupEnvelopeBuilder::success()
                ->setRequestedAt('/' . API_FULL_NAMESPACE . '/' . ENDPOINT_PLUGIN_EXISTS)
                ->setSingleResult(array(
                    'plugin_slug'  => $slug,
                    'exists'       => $exists,
                    'status'       => $status,
                    'plugin_file'  => $exists ? $plugin_file : null,
                    'requestUrl'   => $_SERVER['REQUEST_URI'] ?? '',
                    'responseUrl'  => home_url(),
                ))
                ->toResponse();
        } catch (Throwable $e) {
            return $this->error_response('Failed to check plugin existence: ' . $e->getMessage(), HTTP_SERVER_ERROR, $e);
        }
    }

    /**
     * Load WordPress plugin admin functions if not already available.
     *
     * @param bool $include_file_functions Whether to also load file.php.
     * @return WP_REST_Response|null Error response on failure, null on success.
     */
    private function load_plugin_functions($include_file_functions = false) {
        try {
            if (RiseupBooleanHelpers::is_func_missing('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            if ($include_file_functions && RiseupBooleanHelpers::is_func_missing('delete_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }

            return null;
        } catch (Throwable $e) {
            return $this->error_response('Failed to load WordPress plugin functions: ' . $e->getMessage(), HTTP_SERVER_ERROR, $e);
        }
    }

    /**
     * Resolve and validate a plugin slug from a REST request body.
     *
     * @param WP_REST_Request $request REST request.
     * @return array|WP_REST_Response Resolved info or error response.
     */
    private function resolve_plugin_from_request($request) {
        $body = $request->get_json_params();
        $slug = isset($body['plugin']) ? sanitize_text_field($body['plugin']) : $request->get_param('slug');

        if (empty($slug)) {
            return $this->error_response('Plugin slug is required in JSON body', HTTP_BAD_REQUEST);
        }

        try {
            $plugin_file = $this->find_plugin_file($slug);

            if (!$plugin_file) {
                return $this->error_response(MSG_PLUGIN_NOT_FOUND . ': ' . $slug, HTTP_NOT_FOUND);
            }

            return array('slug' => $slug, 'plugin_file' => $plugin_file);
        } catch (Throwable $e) {
            return $this->error_response('Failed to locate plugin: ' . $e->getMessage(), HTTP_SERVER_ERROR, $e);
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
    private function log_plugin_lifecycle($action, $slug, $status, $extra = array()) {
        try {
            $this->logger->log_plugin_action($action, $slug, $status, $extra);
        } catch (Throwable $e) {
            $this->file_logger->warn('Failed to log plugin action', array('error' => $e->getMessage()));
        }
    }

    /**
     * Handle enable (activate) plugin request.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function handle_enable_plugin($request) {
        $load_error = $this->load_plugin_functions();
        if ($load_error) {
            return $load_error;
        }

        $resolved = $this->resolve_plugin_from_request($request);
        if ($resolved instanceof WP_REST_Response) {
            return $resolved;
        }

        $slug        = $resolved['slug'];
        $plugin_file = $resolved['plugin_file'];

        if (is_plugin_active($plugin_file)) {
            return RiseupEnvelopeBuilder::success('Plugin was already active')
                ->setRequestedAt('/' . API_FULL_NAMESPACE . '/' . ENDPOINT_PLUGIN_ENABLE)
                ->setSingleResult(array('plugin_slug' => $slug, 'activated' => true))
                ->toResponse();
        }

        try {
            $result = activate_plugin($plugin_file);

            if (is_wp_error($result)) {
                $this->log_plugin_lifecycle(ACTION_ENABLE, $slug, STATUS_FAILED, array('error' => $result->get_error_message()));

                return $this->error_response(MSG_ACTIVATION_FAILED . ': ' . $result->get_error_message(), HTTP_SERVER_ERROR);
            }
        } catch (Throwable $e) {
            $this->log_plugin_lifecycle(ACTION_ENABLE, $slug, STATUS_FAILED, array('exception' => $e->getMessage()));

            return $this->error_response('Exception during activation: ' . $e->getMessage(), HTTP_SERVER_ERROR, $e);
        }

        $this->log_plugin_lifecycle(ACTION_ENABLE, $slug, STATUS_SUCCESS);

        return RiseupEnvelopeBuilder::success()
            ->setRequestedAt('/' . API_FULL_NAMESPACE . '/' . ENDPOINT_PLUGIN_ENABLE)
            ->setSingleResult(array('plugin_slug' => $slug, 'activated' => true))
            ->toResponse();
    }

    /**
     * Handle disable (deactivate) plugin request.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function handle_disable_plugin($request) {
        $load_error = $this->load_plugin_functions();
        if ($load_error) {
            return $load_error;
        }

        $resolved = $this->resolve_plugin_from_request($request);
        if ($resolved instanceof WP_REST_Response) {
            return $resolved;
        }

        $slug        = $resolved['slug'];
        $plugin_file = $resolved['plugin_file'];

        if (!is_plugin_active($plugin_file)) {
            return RiseupEnvelopeBuilder::success('Plugin was already inactive')
                ->setRequestedAt('/' . API_FULL_NAMESPACE . '/' . ENDPOINT_PLUGIN_DISABLE)
                ->setSingleResult(array('plugin_slug' => $slug, 'deactivated' => true))
                ->toResponse();
        }

        try {
            deactivate_plugins($plugin_file);
        } catch (Throwable $e) {
            $this->log_plugin_lifecycle(ACTION_DISABLE, $slug, STATUS_FAILED, array('exception' => $e->getMessage()));

            return $this->error_response('Exception during deactivation: ' . $e->getMessage(), HTTP_SERVER_ERROR, $e);
        }

        if (is_plugin_active($plugin_file)) {
            $this->log_plugin_lifecycle(ACTION_DISABLE, $slug, STATUS_FAILED, array('error' => 'Plugin remained active'));

            return $this->error_response(MSG_DEACTIVATION_FAILED . ': Plugin remained active', HTTP_SERVER_ERROR);
        }

        $this->log_plugin_lifecycle(ACTION_DISABLE, $slug, STATUS_SUCCESS);

        return RiseupEnvelopeBuilder::success()
            ->setRequestedAt('/' . API_FULL_NAMESPACE . '/' . ENDPOINT_PLUGIN_DISABLE)
            ->setSingleResult(array('plugin_slug' => $slug, 'deactivated' => true))
            ->toResponse();
    }

    /**
     * Handle delete plugin request.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function handle_delete_plugin($request) {
        $load_error = $this->load_plugin_functions(true);
        if ($load_error) {
            return $load_error;
        }

        $resolved = $this->resolve_plugin_from_request($request);
        if ($resolved instanceof WP_REST_Response) {
            return $resolved;
        }

        $slug        = $resolved['slug'];
        $plugin_file = $resolved['plugin_file'];

        try {
            if (is_plugin_active($plugin_file)) {
                deactivate_plugins($plugin_file);
            }
        } catch (Throwable $e) {
            return $this->error_response('Failed to deactivate plugin before deletion: ' . $e->getMessage(), HTTP_SERVER_ERROR, $e);
        }

        try {
            $result = delete_plugins(array($plugin_file));

            if (is_wp_error($result)) {
                $this->log_plugin_lifecycle(ACTION_DELETE, $slug, STATUS_FAILED, array('error' => $result->get_error_message()));

                return $this->error_response(MSG_DELETE_FAILED . ': ' . $result->get_error_message(), HTTP_SERVER_ERROR);
            }

            if ($result === false) {
                $this->log_plugin_lifecycle(ACTION_DELETE, $slug, STATUS_FAILED, array('error' => 'delete_plugins returned false'));

                return $this->error_response(MSG_DELETE_FAILED . ': Unknown error', HTTP_SERVER_ERROR);
            }
        } catch (Throwable $e) {
            $this->log_plugin_lifecycle(ACTION_DELETE, $slug, STATUS_FAILED, array('exception' => $e->getMessage()));

            return $this->error_response('Exception during deletion: ' . $e->getMessage(), HTTP_SERVER_ERROR, $e);
        }

        $this->log_plugin_lifecycle(ACTION_DELETE, $slug, STATUS_SUCCESS);

        return RiseupEnvelopeBuilder::success()
            ->setRequestedAt('/' . API_FULL_NAMESPACE . '/' . ENDPOINT_PLUGIN_DELETE)
            ->setSingleResult(array('plugin_slug' => $slug, 'deleted' => true))
            ->toResponse();
    }
}
