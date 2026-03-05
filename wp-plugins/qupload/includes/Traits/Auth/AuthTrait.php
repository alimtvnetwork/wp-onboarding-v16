<?php
/**
 * AuthTrait — Authentication and permission checks for QUpload.
 *
 * @package QUpload\Traits\Auth
 * @since   1.0.0
 */

namespace QUpload\Traits\Auth;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_User;
use WP_Error;
use Throwable;
use QUpload\Enums\CapabilityType;
use QUpload\Enums\HttpStatusType;
use QUpload\Enums\WpErrorCodeType;

trait AuthTrait
{
    /** Check plugin management permission. */
    public function checkPluginPermission(WP_REST_Request $request): bool|WP_Error {
        $this->fileLogger->debug('Checking plugin permission');

        return $this->checkAuthenticatedCapability($request, CapabilityType::ActivatePlugins->value);
    }

    /** Check status permission (any authenticated user). */
    public function checkStatusPermission(WP_REST_Request $request): bool|WP_Error {
        $this->fileLogger->debug('Checking status permission');

        return $this->checkAuthenticatedOnly($request);
    }

    private function checkAuthenticatedOnly(WP_REST_Request $request): true|WP_Error {
        try {
            $authResult = $this->resolveAndAuthenticate($request);

            if (is_wp_error($authResult)) {
                return $authResult;
            }

            return true;
        } catch (Throwable $e) {
            $this->fileLogger->logException($e, 'Authentication error');

            return new WP_Error(WpErrorCodeType::InternalError->value, $e->getMessage(), ['status' => HttpStatusType::ServerError->value]);
        }
    }

    private function checkAuthenticatedCapability(WP_REST_Request $request, string $capability): true|WP_Error {
        try {
            $authResult = $this->resolveAndAuthenticate($request);

            if (is_wp_error($authResult)) {
                return $authResult;
            }

            if (current_user_can($capability)) {
                return true;
            }

            $this->fileLogger->warn('Insufficient permissions', ['username' => $authResult->user_login, 'required' => $capability]);

            return new WP_Error(WpErrorCodeType::RestForbidden->value, 'Insufficient permissions', ['status' => HttpStatusType::Forbidden->value]);
        } catch (Throwable $e) {
            $this->fileLogger->logException($e, 'Authentication error');

            return new WP_Error(WpErrorCodeType::InternalError->value, $e->getMessage(), ['status' => HttpStatusType::ServerError->value]);
        }
    }

    private function resolveAndAuthenticate(WP_REST_Request $request): WP_User|WP_Error {
        $authHeader = $this->resolveAuthHeader($request);

        if (empty($authHeader)) {
            $this->fileLogger->warn('Missing Authorization header');

            return new WP_Error(WpErrorCodeType::RestForbidden->value, 'Authentication required', [
                'status' => HttpStatusType::Unauthorized->value,
                'headers' => ['WWW-Authenticate' => 'Basic realm="WordPress Application Password"'],
            ]);
        }

        return $this->authenticateUser($authHeader);
    }

    private function resolveAuthHeader(WP_REST_Request $request): ?string {
        $authHeader = $request->get_header('Authorization');

        if (!empty($authHeader)) {
            return $authHeader;
        }

        if (!empty($_SERVER['HTTP_AUTHORIZATION'] ?? null)) {
            return $_SERVER['HTTP_AUTHORIZATION'];
        }

        if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null)) {
            return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        return null;
    }

    private function authenticateUser(string $authHeader): WP_User|WP_Error {
        $isNotBasic = (strpos($authHeader, 'Basic ') !== 0);

        if ($isNotBasic) {
            $this->fileLogger->warn('Invalid Authorization header format');

            return new WP_Error(WpErrorCodeType::RestForbidden->value, 'Authentication required', ['status' => HttpStatusType::Unauthorized->value]);
        }

        $credentials = base64_decode(substr($authHeader, 6));
        $isFormatInvalid = ($credentials === false) || (strpos($credentials, ':') === false);

        if ($isFormatInvalid) {
            return new WP_Error(WpErrorCodeType::RestForbidden->value, 'Invalid credentials format', ['status' => HttpStatusType::Unauthorized->value]);
        }

        [$username, $password] = explode(':', $credentials, 2);
        $user = wp_authenticate_application_password(null, $username, $password);
        $isAuthFailed = (is_wp_error($user) || $user === false);

        if ($isAuthFailed) {
            $this->fileLogger->warn('Invalid credentials', ['username' => $username]);

            return new WP_Error(WpErrorCodeType::RestForbidden->value, 'Authentication required', ['status' => HttpStatusType::Unauthorized->value]);
        }

        wp_set_current_user($user->ID);

        return $user;
    }
}
