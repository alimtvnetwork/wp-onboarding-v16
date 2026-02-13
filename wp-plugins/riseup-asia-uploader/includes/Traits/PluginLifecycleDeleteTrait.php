<?php
/**
 * PluginLifecycleDeleteTrait — Delete plugin REST handler.
 *
 * @package RiseupAsia\Traits
 * @since   2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait PluginLifecycleDeleteTrait {

    /** Handle delete plugin request. */
    public function handle_delete_plugin($request) {
        $load_error = $this->load_plugin_functions(true);
        if ($load_error) {
            return $load_error;
        }

        $resolved = $this->resolve_plugin_from_request($request);
        if ($resolved instanceof WP_REST_Response) {
            return $resolved;
        }

        $deactivation = $this->deactivateBeforeDelete($resolved['slug'], $resolved['plugin_file']);
        if ($deactivation instanceof WP_REST_Response) {
            return $deactivation;
        }

        return $this->tryDeletePlugin($resolved['slug'], $resolved['plugin_file']);
    }

    /** Deactivate a plugin before deletion. */
    private function deactivateBeforeDelete(string $slug, string $plugin_file) {
        try {
            if (is_plugin_active($plugin_file)) {
                deactivate_plugins($plugin_file);
            }
            return true;
        } catch (Throwable $e) {
            return $this->error_response('Failed to deactivate plugin before deletion: ' . $e->getMessage(), HTTP_SERVER_ERROR, $e);
        }
    }

    /** Attempt to delete a plugin. */
    private function tryDeletePlugin(string $slug, string $plugin_file) {
        try {
            $result = delete_plugins(array($plugin_file));
            $error = $this->checkDeleteResult($result);
            if ($error) {
                $this->log_plugin_lifecycle(ACTION_DELETE, $slug, STATUS_FAILED, array('error' => $error));
                return $this->error_response(MSG_DELETE_FAILED . ': ' . $error, HTTP_SERVER_ERROR);
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

    /** Check the result of delete_plugins and return error string or null. */
    private function checkDeleteResult($result): ?string {
        if (is_wp_error($result)) {
            return $result->get_error_message();
        }
        if ($result === false) {
            return 'delete_plugins returned false';
        }
        return null;
    }
}
