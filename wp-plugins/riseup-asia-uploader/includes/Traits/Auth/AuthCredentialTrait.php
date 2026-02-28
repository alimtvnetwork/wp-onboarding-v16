<?php
/**
 * AuthCredentialTrait — header resolution, Basic auth parsing, capability verification.
 *
 * @package RiseupAsia\Traits\Auth
 * @since   1.57.0
 */

namespace RiseupAsia\Traits\Auth;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_User;
use WP_Error;
use Throwable;
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\ResponseMessageType;
use RiseupAsia\Enums\WpErrorCodeType;
use RiseupAsia\ErrorHandling\ErrorResponse;
use RiseupAsia\Helpers\BooleanHelpers;

trait AuthCredentialTrait
{
    private function resolveAuthHeader(WP_REST_Request $request): ?string {
        $authHeader = $request->get_header('Authorization');
        if (BooleanHelpers::hasValue($authHeader)) {
            return $authHeader;
        }

        if (BooleanHelpers::hasValue($_SERVER['HTTP_AUTHORIZATION'] ?? null)) {
            return $_SERVER['HTTP_AUTHORIZATION'];
        }

        if (BooleanHelpers::hasValue($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null)) {
            return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        return $this->resolveFromGetallheaders();
    }

    private function resolveFromGetallheaders(): ?string {
        if (BooleanHelpers::isFuncMissing('getallheaders')) {
            return null;
        }

        $headers = getallheaders();
        if (isset($headers['Authorization'])) { return $headers['Authorization']; }
        if (isset($headers['authorization'])) { return $headers['authorization']; }

        return null;
    }

    private function authenticateUser(string $authHeader): WP_User|WP_Error {
        $formatError = $this->validateAuthFormat($authHeader);

        if ($formatError) {
            return $formatError;
        }

        $credentials = base64_decode(substr($authHeader, 6));
        $isCredentialsMissing = ($credentials === false);
        $isColonMissing = (!$isCredentialsMissing && strpos($credentials, ':') === false);
        $isFormatInvalid =
            $isCredentialsMissing ||
            $isColonMissing;

        if ($isFormatInvalid) {
            return $this->buildAuthError('Invalid credentials format');
        }

        list($username, $password) = explode(':', $credentials, 2);
        $user = wp_authenticate_application_password(null, $username, $password);

        $isAuthFailed = (is_wp_error($user) || $user === false);

        if ($isAuthFailed) {
            return $this->buildAuthError('Invalid credentials', array('username' => $username));
        }

        wp_set_current_user($user->ID);

        return $user;
    }

    private function validateAuthFormat(string $authHeader): ?WP_Error {
        $isBasicAuth = (strpos($authHeader, 'Basic ') === 0);

        if ($isBasicAuth) {
            return null;
        }

        return $this->buildAuthError('Invalid Authorization header format');
    }

    private function buildAuthError(string $reason, array $context = array()): WP_Error {
        $this->fileLogger->warn($reason, $context);
        $this->logger->logAuthFailure($reason, $context);

        return new WP_Error(WpErrorCodeType::RestForbidden->value, ResponseMessageType::Unauthorized->value, array('status' => HttpStatusType::Unauthorized->value));
    }

    private function buildMissingAuthError(WP_REST_Request $request): WP_Error {
        $this->fileLogger->warn('Missing Authorization header', array(
            'reason' => 'Missing Authorization header', 'method' => $request->get_method(), 'endpoint' => $request->get_route(),
        ));
        $this->logger->logAuthFailure('Missing Authorization header');

        return new WP_Error(WpErrorCodeType::RestForbidden->value, ResponseMessageType::Unauthorized->value, array(
            'status' => HttpStatusType::Unauthorized->value,
            'headers' => array('WWW-Authenticate' => 'Basic realm="WordPress Application Password"'),
        ));
    }

    private function checkAuthenticatedOnly(WP_REST_Request $request): true|WP_Error {
        try {
            return $this->resolveAndAuthenticate($request);
        } catch (Throwable $e) {
            return ErrorResponse::logAndReturnWpError($this->fileLogger, $e, 'Authentication error');
        }
    }

    private function checkAuthenticatedCapability(WP_REST_Request $request, string $capability): true|WP_Error {
        try {
            $authResult = $this->resolveAndAuthenticate($request);

            if (is_wp_error($authResult) || $authResult === true) {
                return $authResult;
            }

            return $this->verifyCapability($authResult, $capability);
        } catch (Throwable $e) {
            return ErrorResponse::logAndReturnWpError($this->fileLogger, $e, 'Authentication error');
        }
    }

    private function resolveAndAuthenticate(WP_REST_Request $request): WP_User|WP_Error {
        $authHeader = $this->resolveAuthHeader($request);

        if (empty($authHeader)) {
            return $this->buildMissingAuthError($request);
        }

        return $this->authenticateUser($authHeader);
    }

    private function verifyCapability(WP_User $user, string $capability): true|WP_Error {
        if (current_user_can($capability)) {
            return true;
        }

        $this->fileLogger->warn('Insufficient permissions', array(
            'username' => $user->user_login, 'required_cap' => $capability,
        ));
        $this->logger->logAuthFailure('Insufficient permissions', array(
            'username' => $user->user_login, 'required_cap' => $capability,
        ));

        return new WP_Error(WpErrorCodeType::RestForbidden->value, ResponseMessageType::Forbidden->value, array('status' => HttpStatusType::Forbidden->value));
    }
}
