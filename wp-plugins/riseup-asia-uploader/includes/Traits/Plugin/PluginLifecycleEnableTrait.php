<?php
/**
 * PluginLifecycleEnableTrait — Enable and disable plugin REST handlers.
 *
 * @package RiseupAsia\Traits
 * @since   2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait PluginLifecycleEnableTrait {

    /** Handle enable (activate) plugin request. */
    public function handle_enable_plugin($request) {
        $load_error = $this->load_plugin_functions();
        if ($load_error) {
            return $load_error;
        }

        $resolved = $this->resolve_plugin_from_request($request);
        if ($resolved instanceof WP_REST_Response) {
            return $resolved;
        }

        if (is_plugin_active($resolved['plugin_file'])) {
            return $this->buildAlreadyActiveResponse($resolved['slug']);
        }

        return $this->tryActivatePlugin($resolved['slug'], $resolved['plugin_file']);
    }

    /** Handle disable (deactivate) plugin request. */
    public function handle_disable_plugin($request) {
        $load_error = $this->load_plugin_functions();
        if ($load_error) {
            return $load_error;
        }

        $resolved = $this->resolve_plugin_from_request($request);
        if ($resolved instanceof WP_REST_Response) {
            return $resolved;
        }

        if (!is_plugin_active($resolved['plugin_file'])) {
            return $this->buildAlreadyInactiveResponse($resolved['slug']);
        }

        return $this->tryDeactivatePlugin($resolved['slug'], $resolved['plugin_file']);
    }

    /** Build response for already-active plugin. */
    private function buildAlreadyActiveResponse(string $slug): WP_REST_Response {
        return RiseupEnvelopeBuilder::success('Plugin was already active')
            ->setRequestedAt('/' . API_FULL_NAMESPACE . '/' . ENDPOINT_PLUGIN_ENABLE)
            ->setSingleResult(array('plugin_slug' => $slug, 'activated' => true))
            ->toResponse();
    }

    /** Build response for already-inactive plugin. */
    private function buildAlreadyInactiveResponse(string $slug): WP_REST_Response {
        return RiseupEnvelopeBuilder::success('Plugin was already inactive')
            ->setRequestedAt('/' . API_FULL_NAMESPACE . '/' . ENDPOINT_PLUGIN_DISABLE)
            ->setSingleResult(array('plugin_slug' => $slug, 'deactivated' => true))
            ->toResponse();
    }

    /** Attempt to activate a plugin. */
    private function tryActivatePlugin(string $slug, string $plugin_file) {
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

    /** Attempt to deactivate a plugin. */
    private function tryDeactivatePlugin(string $slug, string $plugin_file) {
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
}
