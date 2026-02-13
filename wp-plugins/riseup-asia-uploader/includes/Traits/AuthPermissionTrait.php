<?php
/**
 * AuthPermissionTrait — permission callbacks and endpoint checks.
 *
 * @package RiseupAsia\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait AuthPermissionTrait
{
    /** Check if an endpoint is enabled via settings. */
    private function is_endpoint_enabled($endpoint) {
        return RiseupAdmin::is_endpoint_enabled($endpoint);
    }

    /** Check if an endpoint requires authentication via settings. */
    private function is_auth_required($endpoint) {
        return RiseupAdmin::is_auth_required($endpoint);
    }

    /** Build permission callback with optional auth bypass. */
    private function build_permission_callback($endpoint, $auth_check) {
        return function($request) use ($endpoint, $auth_check) {
            if (!$this->is_endpoint_enabled($endpoint)) {
                return new WP_Error('rest_disabled', 'This endpoint is disabled', array('status' => 403));
            }
            if (!$this->is_auth_required($endpoint)) {
                return true;
            }
            return call_user_func($auth_check, $request);
        };
    }

    /** Check plugin management permission. */
    public function check_plugin_permission($request) {
        $this->file_logger->debug('Checking plugin permission');
        return $this->check_authenticated_capability($request, CAP_MANAGE_PLUGINS);
    }

    /** Check post management permission. */
    public function check_post_permission($request) {
        $this->file_logger->debug('Checking post permission');
        return $this->check_authenticated_capability($request, CAP_MANAGE_POSTS);
    }

    /** Check logs view permission. */
    public function check_logs_permission($request) {
        $this->file_logger->debug('Checking logs permission');
        return $this->check_authenticated_capability($request, CAP_VIEW_LOGS);
    }

    /** Check status/openapi permission (any authenticated user). */
    public function check_status_permission($request) {
        $this->file_logger->debug('Checking status permission');
        return $this->check_authenticated_only($request);
    }
}
