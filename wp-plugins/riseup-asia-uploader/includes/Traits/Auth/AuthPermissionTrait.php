<?php
/**
 * AuthPermissionTrait — permission callbacks and endpoint checks.
 *
 * @package RiseupAsia\Traits\Auth
 * @since   1.57.0
 */

namespace RiseupAsia\Traits\Auth;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_Error;
use RiseupAsia\Enums\CapabilityType;
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\WpErrorCodeType;
use RiseupAsia\Admin\Admin;

trait AuthPermissionTrait
{
    /** Check if an endpoint is enabled via settings. */
    private function isEndpointEnabled(string $endpoint): bool {
        return Admin::isEndpointEnabled($endpoint);
    }

    /** Check if an endpoint requires authentication via settings. */
    private function isAuthRequired(string $endpoint): bool {
        return Admin::isAuthRequired($endpoint);
    }

    /** Build permission callback with optional auth bypass. */
    private function buildPermissionCallback(string $endpoint, callable $authCheck): callable {
        return function(WP_REST_Request $request) use ($endpoint, $authCheck) {
            $isEndpointDisabled = ($this->isEndpointEnabled($endpoint) === false);

            if ($isEndpointDisabled) {
                return new WP_Error(WpErrorCodeType::RestDisabled->value, 'This endpoint is disabled', array('status' => HttpStatusType::Forbidden->value));
            }

            $isAuthOptional = ($this->isAuthRequired($endpoint) === false);

            if ($isAuthOptional) {
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
        $authResult = $this->checkAuthenticatedOnly($request);
        $isAuthError = is_wp_error($authResult);

        if ($isAuthError) {
            return $authResult;
        }

        return true;
    }

    /** Check user management permission. */
    public function checkUserPermission(WP_REST_Request $request): bool|WP_Error {
        $this->fileLogger->debug('Checking user management permission');
        return $this->checkAuthenticatedCapability($request, CapabilityType::EditUsers->value);
    }
}
