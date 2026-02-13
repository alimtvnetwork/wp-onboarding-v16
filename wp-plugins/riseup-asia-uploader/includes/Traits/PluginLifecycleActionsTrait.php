<?php
/**
 * PluginLifecycleActionsTrait — enable, disable, delete plugin handlers.
 *
 * @package RiseupAsia\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait PluginLifecycleActionsTrait {

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
