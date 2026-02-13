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
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (isset($headers['Authorization'])) { return $headers['Authorization']; }
            if (isset($headers['authorization'])) { return $headers['authorization']; }
        }
        return null;
    }

    /** Parse Basic auth header and authenticate the user. */
    private function authenticate_user($auth_header) {
        if (strpos($auth_header, 'Basic ') !== 0) {
            $this->file_logger->warn('Invalid Authorization header format');
            $this->logger->log_auth_failure('Invalid Authorization header format');
            return new WP_Error('rest_forbidden', MSG_UNAUTHORIZED, array('status' => HTTP_UNAUTHORIZED));
        }

        $credentials = base64_decode(substr($auth_header, 6));
        if (!$credentials || strpos($credentials, ':') === false) {
            $this->file_logger->warn('Invalid credentials format');
            $this->logger->log_auth_failure('Invalid credentials format');
            return new WP_Error('rest_forbidden', MSG_UNAUTHORIZED, array('status' => HTTP_UNAUTHORIZED));
        }

        list($username, $password) = explode(':', $credentials, 2);
        $user = wp_authenticate_application_password(null, $username, $password);

        if (is_wp_error($user) || !$user) {
            $this->file_logger->warn('Invalid credentials', array('username' => $username));
            $this->logger->log_auth_failure('Invalid credentials', array('username' => $username));
            return new WP_Error('rest_forbidden', MSG_UNAUTHORIZED, array('status' => HTTP_UNAUTHORIZED));
        }

        wp_set_current_user($user->ID);
        return $user;
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
    private function check_authenticated_only($request) {
        try {
            $auth_header = $this->resolve_auth_header($request);
            if (empty($auth_header)) {
                return $this->build_missing_auth_error($request);
            }

            $user = $this->authenticate_user($auth_header);
            if (is_wp_error($user)) {
                return $user;
            }

            return true;
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'Authentication error');
            return new WP_Error('rest_forbidden', MSG_UNAUTHORIZED, array('status' => HTTP_UNAUTHORIZED));
        }
    }

    /** Verify authentication and capability. */
    private function check_authenticated_capability($request, $capability) {
        try {
            $auth_header = $this->resolve_auth_header($request);
            if (empty($auth_header)) {
                return $this->build_missing_auth_error($request);
            }

            $user = $this->authenticate_user($auth_header);
            if (is_wp_error($user)) {
                return $user;
            }

            if (!current_user_can($capability)) {
                $this->file_logger->warn('Insufficient permissions', array(
                    'username' => $user->user_login, 'required_cap' => $capability,
                ));
                $this->logger->log_auth_failure('Insufficient permissions', array(
                    'username' => $user->user_login, 'required_cap' => $capability,
                ));
                return new WP_Error('rest_forbidden', MSG_FORBIDDEN, array('status' => HTTP_FORBIDDEN));
            }

            return true;
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'Authentication error');
            return new WP_Error('rest_forbidden', MSG_UNAUTHORIZED, array('status' => HTTP_UNAUTHORIZED));
        }
    }
}
