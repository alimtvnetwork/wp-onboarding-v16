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

use RiseupAsia\Enums\CapabilityType;

trait AuthPermissionTrait
{
    /** Check if an endpoint is enabled via settings. */
    private function isEndpointEnabled(string $endpoint): bool {
        return RiseupAdmin::is_endpoint_enabled($endpoint);
    }

    /** Check if an endpoint requires authentication via settings. */
    private function isAuthRequired(string $endpoint): bool {
        return RiseupAdmin::is_auth_required($endpoint);
    }

    /** Build permission callback with optional auth bypass. */
    private function buildPermissionCallback(string $endpoint, callable $authCheck): callable {
        return function(WP_REST_Request $request) use ($endpoint, $authCheck) {
            if (!$this->isEndpointEnabled($endpoint)) {
                return new WP_Error('rest_disabled', 'This endpoint is disabled', array('status' => 403));
            }
            if (!$this->isAuthRequired($endpoint)) {
                return true;
            }
            return call_user_func($authCheck, $request);
        };
    }

    /** Check plugin management permission. */
    public function checkPluginPermission(WP_REST_Request $request): bool|WP_Error {
        $this->fileLogger->debug('Checking plugin permission');
        return $this->checkAuthenticatedCapability($request, CapabilityType::ActivatePlugins->value);
    }

    /** Check post management permission. */
    public function checkPostPermission(WP_REST_Request $request): bool|WP_Error {
        $this->fileLogger->debug('Checking post permission');
        return $this->checkAuthenticatedCapability($request, CapabilityType::PublishPosts->value);
    }

    /** Check logs view permission. */
    public function checkLogsPermission(WP_REST_Request $request): bool|WP_Error {
        $this->fileLogger->debug('Checking logs permission');
        return $this->checkAuthenticatedCapability($request, CapabilityType::ManageOptions->value);
    }

    /** Check status/openapi permission (any authenticated user). */
    public function checkStatusPermission(WP_REST_Request $request): bool|WP_Error {
        $this->fileLogger->debug('Checking status permission');
        return $this->checkAuthenticatedOnly($request);
    }
}
