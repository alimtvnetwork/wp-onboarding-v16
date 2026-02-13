<?php
/**
 * AuthTrait — Permission callbacks and authentication logic.
 *
 * Centralizes endpoint enablement checks, auth header resolution,
 * Basic auth parsing, and capability verification.
 *
 * @package RiseupAsia\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait AuthTrait
{
    /**
     * Check if an endpoint is enabled via settings.
     *
     * @param string $endpoint Endpoint key.
     * @return bool True if enabled.
     */
    private function is_endpoint_enabled($endpoint) {
        return RiseupAdmin::is_endpoint_enabled($endpoint);
    }

    /**
     * Check if an endpoint requires authentication via settings.
     *
     * @param string $endpoint Endpoint key.
     * @return bool True if auth required.
     */
    private function is_auth_required($endpoint) {
        return RiseupAdmin::is_auth_required($endpoint);
    }

    /**
     * Build permission callback with optional auth bypass.
     *
     * @param string   $endpoint   Endpoint key for settings lookup.
     * @param callable $auth_check The actual auth check function.
     * @return callable Permission callback.
     */
    private function build_permission_callback($endpoint, $auth_check) {
        return function($request) use ($endpoint, $auth_check) {
            if (!$this->is_endpoint_enabled($endpoint)) {
                return new WP_Error(
                    'rest_disabled',
                    'This endpoint is disabled',
                    array('status' => 403)
                );
            }

            if (!$this->is_auth_required($endpoint)) {
                return true;
            }

            return call_user_func($auth_check, $request);
        };
    }

    /**
     * Check plugin management permission.
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function check_plugin_permission($request) {
        $this->file_logger->debug('Checking plugin permission');
        return $this->check_authenticated_capability($request, CAP_MANAGE_PLUGINS);
    }

    /**
     * Check post management permission.
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function check_post_permission($request) {
        $this->file_logger->debug('Checking post permission');
        return $this->check_authenticated_capability($request, CAP_MANAGE_POSTS);
    }

    /**
     * Check logs view permission.
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function check_logs_permission($request) {
        $this->file_logger->debug('Checking logs permission');
        return $this->check_authenticated_capability($request, CAP_VIEW_LOGS);
    }

    /**
     * Check status/openapi permission (any authenticated user).
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function check_status_permission($request) {
        $this->file_logger->debug('Checking status permission');
        return $this->check_authenticated_only($request);
    }

    /**
     * Resolve the Authorization header from the request.
     *
     * @param WP_REST_Request $request Request object.
     * @return string|null Authorization header value, or null.
     */
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

        $hasGetAllHeaders = function_exists('getallheaders');
        if (!$hasGetAllHeaders) {
            return null;
        }

        $headers = getallheaders();

        if (isset($headers['Authorization'])) {
            return $headers['Authorization'];
        }

        if (isset($headers['authorization'])) {
            return $headers['authorization'];
        }

        return null;
    }

    /**
     * Parse Basic auth header and authenticate the user.
     *
     * @param string $auth_header Raw Authorization header value.
     * @return WP_User|WP_Error
     */
    private function authenticate_user($auth_header) {
        $isBasicAuth = (strpos($auth_header, 'Basic ') === 0);
        if (!$isBasicAuth) {
            $this->file_logger->warn('Invalid Authorization header format');
            $this->logger->log_auth_failure('Invalid Authorization header format');

            return new WP_Error(
                'rest_forbidden',
                MSG_UNAUTHORIZED,
                array('status' => HTTP_UNAUTHORIZED)
            );
        }

        $credentials = base64_decode(substr($auth_header, 6));
        $hasDelimiter = ($credentials && strpos($credentials, ':') !== false);
        if (!$hasDelimiter) {
            $this->file_logger->warn('Invalid credentials format');
            $this->logger->log_auth_failure('Invalid credentials format');

            return new WP_Error(
                'rest_forbidden',
                MSG_UNAUTHORIZED,
                array('status' => HTTP_UNAUTHORIZED)
            );
        }

        list($username, $password) = explode(':', $credentials, 2);
        $this->file_logger->debug('Authenticating user', array('username' => $username));

        $user = wp_authenticate_application_password(null, $username, $password);

        $isAuthFailed = (is_wp_error($user) || !$user);
        if ($isAuthFailed) {
            $this->file_logger->warn('Invalid credentials', array('username' => $username));
            $this->logger->log_auth_failure(
                'Invalid credentials',
                array('username' => $username)
            );

            return new WP_Error(
                'rest_forbidden',
                MSG_UNAUTHORIZED,
                array('status' => HTTP_UNAUTHORIZED)
            );
        }

        wp_set_current_user($user->ID);

        return $user;
    }

    /**
     * Build a WP_Error for missing Authorization header.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_Error
     */
    private function build_missing_auth_error($request) {
        $this->file_logger->warn('Missing Authorization header', array(
            'reason'          => 'Missing Authorization header',
            'method'          => $request->get_method(),
            'endpoint'        => $request->get_route(),
            'ip'              => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown',
            'user_agent'      => $request->get_header('user-agent') ?: 'unknown',
            'server_software' => isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'unknown',
        ));
        $this->logger->log_auth_failure('Missing Authorization header');

        return new WP_Error(
            'rest_forbidden',
            MSG_UNAUTHORIZED,
            array(
                'status'  => HTTP_UNAUTHORIZED,
                'headers' => array('WWW-Authenticate' => 'Basic realm="WordPress Application Password"'),
            )
        );
    }

    /**
     * Verify authentication only (no capability check).
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    private function check_authenticated_only($request) {
        $this->file_logger->debug('Authenticating request (any user)');

        try {
            $auth_header = $this->resolve_auth_header($request);
            if (empty($auth_header)) {
                return $this->build_missing_auth_error($request);
            }

            $user = $this->authenticate_user($auth_header);
            if (is_wp_error($user)) {
                return $user;
            }

            $this->file_logger->info('Request authorized (status)', array('username' => $user->user_login));

            return true;
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'Authentication error');

            return new WP_Error(
                'rest_forbidden',
                MSG_UNAUTHORIZED,
                array('status' => HTTP_UNAUTHORIZED)
            );
        }
    }

    /**
     * Verify authentication and capability.
     *
     * @param WP_REST_Request $request    Request object.
     * @param string          $capability Required capability.
     * @return bool|WP_Error
     */
    private function check_authenticated_capability($request, $capability) {
        $this->file_logger->debug('Authenticating request', array('capability' => $capability));

        try {
            $auth_header = $this->resolve_auth_header($request);
            if (empty($auth_header)) {
                return $this->build_missing_auth_error($request);
            }

            $user = $this->authenticate_user($auth_header);
            if (is_wp_error($user)) {
                return $user;
            }

            $this->file_logger->debug('User authenticated', array('user_id' => $user->ID));

            if (!current_user_can($capability)) {
                $this->file_logger->warn('Insufficient permissions', array(
                    'username'     => $user->user_login,
                    'required_cap' => $capability,
                ));
                $this->logger->log_auth_failure(
                    'Insufficient permissions',
                    array('username' => $user->user_login, 'required_cap' => $capability)
                );

                return new WP_Error(
                    'rest_forbidden',
                    MSG_FORBIDDEN,
                    array('status' => HTTP_FORBIDDEN)
                );
            }

            $this->file_logger->info('Request authorized', array('username' => $user->user_login));

            return true;
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'Authentication error');

            return new WP_Error(
                'rest_forbidden',
                MSG_UNAUTHORIZED,
                array('status' => HTTP_UNAUTHORIZED)
            );
        }
    }
}
