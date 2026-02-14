<?php
/**
 * AuthCredentialTrait — header resolution, Basic auth parsing, capability verification.
 *
 * @package RiseupAsia\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait AuthCredentialTrait
{
    /** Resolve the Authorization header from the request. */
    private function resolve_auth_header($request) {
        $auth_header = $request->get_header('Authorization');
        if (!empty($auth_header)) {
            return $auth_header;
        }
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            return $_SERVER['HTTP_AUTHORIZATION'];
        }
        if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        return $this->resolveFromGetallheaders();
    }

    /** Attempt to resolve Authorization from getallheaders(). */
    private function resolveFromGetallheaders() {
        if (RiseupBooleanHelpers::is_func_missing('getallheaders')) {
            return null;
        }

        $headers = getallheaders();
        if (isset($headers['Authorization'])) { return $headers['Authorization']; }
        if (isset($headers['authorization'])) { return $headers['authorization']; }

        return null;
    }

    /** Parse Basic auth header and authenticate the user. */
    private function authenticate_user($auth_header) {
        $formatError = $this->validateAuthFormat($auth_header);
        if ($formatError) {
            return $formatError;
        }

        $credentials = base64_decode(substr($auth_header, 6));
        $isInvalidFormat = (!$credentials || strpos($credentials, ':') === false);
        if ($isInvalidFormat) {
            return $this->buildAuthError('Invalid credentials format');
        }

        list($username, $password) = explode(':', $credentials, 2);
        $user = wp_authenticate_application_password(null, $username, $password);

        if (is_wp_error($user) || !$user) {
            return $this->buildAuthError('Invalid credentials', array('username' => $username));
        }

        wp_set_current_user($user->ID);
        return $user;
    }

    /** Validate the Basic auth header format prefix. */
    private function validateAuthFormat(string $auth_header) {
        if (strpos($auth_header, 'Basic ') === 0) {
            return null;
        }

        return $this->buildAuthError('Invalid Authorization header format');
    }

    /** Build a WP_Error for auth failure with logging. */
    private function buildAuthError(string $reason, array $context = array()) {
        $this->file_logger->warn($reason, $context);
        $this->logger->log_auth_failure($reason, $context);

        return new WP_Error('rest_forbidden', MSG_UNAUTHORIZED, array('status' => HTTP_UNAUTHORIZED));
    }

    /** Build a WP_Error for missing Authorization header. */
    private function build_missing_auth_error($request) {
        $this->file_logger->warn('Missing Authorization header', array(
            'reason' => 'Missing Authorization header', 'method' => $request->get_method(), 'endpoint' => $request->get_route(),
        ));
        $this->logger->log_auth_failure('Missing Authorization header');

        return new WP_Error('rest_forbidden', MSG_UNAUTHORIZED, array(
            'status' => HTTP_UNAUTHORIZED,
            'headers' => array('WWW-Authenticate' => 'Basic realm="WordPress Application Password"'),
        ));
    }

    /** Verify authentication only (no capability check). */
    private function checkAuthenticatedOnly($request) {
        try {
            return $this->resolveAndAuthenticate($request);
        } catch (Throwable $e) {
            $this->fileLogger->logException($e, 'Authentication error');
            return new WP_Error('rest_forbidden', MSG_UNAUTHORIZED, array('status' => HTTP_UNAUTHORIZED));
        }
    }

    /** Verify authentication and capability. */
    private function checkAuthenticatedCapability($request, $capability) {
        try {
            $authResult = $this->resolveAndAuthenticate($request);
            if (is_wp_error($authResult) || $authResult === true) {
                return $authResult;
            }

            return $this->verifyCapability($authResult, $capability);
        } catch (Throwable $e) {
            $this->fileLogger->logException($e, 'Authentication error');
            return new WP_Error('rest_forbidden', MSG_UNAUTHORIZED, array('status' => HTTP_UNAUTHORIZED));
        }
    }

    /** Resolve auth header and authenticate, returning user or error. */
    private function resolveAndAuthenticate($request) {
        $auth_header = $this->resolve_auth_header($request);
        if (empty($auth_header)) {
            return $this->build_missing_auth_error($request);
        }

        $user = $this->authenticate_user($auth_header);
        if (is_wp_error($user)) {
            return $user;
        }

        return $user;
    }

    /** Verify the current user has the required capability. */
    private function verifyCapability($user, string $capability) {
        if (current_user_can($capability)) {
            return true;
        }

        $this->file_logger->warn('Insufficient permissions', array(
            'username' => $user->user_login, 'required_cap' => $capability,
        ));
        $this->logger->log_auth_failure('Insufficient permissions', array(
            'username' => $user->user_login, 'required_cap' => $capability,
        ));

        return new WP_Error('rest_forbidden', MSG_FORBIDDEN, array('status' => HTTP_FORBIDDEN));
    }
}
