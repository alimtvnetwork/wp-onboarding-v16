<?php
/**
 * Rate Limiter class.
 *
 * @package PluginsOnboard
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class OnboardRateLimiter
 *
 * Handles rate limiting for API requests.
 */
class OnboardRateLimiter {

    /**
     * Check if request is allowed.
     *
     * @param string $identifier Unique identifier (IP, app_id, etc.).
     * @param string $action     Action type (auth, mutation, etc.).
     * @param int    $limit      Maximum requests allowed.
     * @param int    $window     Time window in seconds (default 3600).
     * @return bool True if allowed, false if rate limited.
     */
    public static function is_allowed($identifier, $action, $limit = null, $window = 3600) {
        if ($limit === null) {
            $limit = self::get_limit_for_action($action);
        }

        $key = 'onboard_ratelimit_' . md5($identifier . '_' . $action);
        $current = get_transient($key);

        if ($current === false) {
            $current = 0;
        }

        if ($current >= $limit) {
            return false;
        }

        set_transient($key, $current + 1, $window);
        return true;
    }

    /**
     * Get remaining requests.
     *
     * @param string $identifier Unique identifier.
     * @param string $action     Action type.
     * @param int    $limit      Maximum requests allowed.
     * @return int Remaining requests.
     */
    public static function get_remaining($identifier, $action, $limit = null) {
        if ($limit === null) {
            $limit = self::get_limit_for_action($action);
        }

        $key = 'onboard_ratelimit_' . md5($identifier . '_' . $action);
        $current = get_transient($key);

        if ($current === false) {
            return $limit;
        }

        return max(0, $limit - $current);
    }

    /**
     * Reset rate limit for identifier.
     *
     * @param string $identifier Unique identifier.
     * @param string $action     Action type.
     */
    public static function reset($identifier, $action) {
        $key = 'onboard_ratelimit_' . md5($identifier . '_' . $action);
        delete_transient($key);
    }

    /**
     * Get default limit for action type.
     *
     * @param string $action Action type.
     * @return int Limit.
     */
    private static function get_limit_for_action($action) {
        // Use constants with safe defaults (config may not be fully initialized).
        $auth_limit = defined('ONBOARD_RATE_LIMIT_AUTH_REQUESTS') ? ONBOARD_RATE_LIMIT_AUTH_REQUESTS : 5;
        $mutation_limit = defined('ONBOARD_RATE_LIMIT_MUTATION_REQUESTS') ? ONBOARD_RATE_LIMIT_MUTATION_REQUESTS : 10;
        
        $limits = array(
            'auth' => $auth_limit,
            'mutation' => $mutation_limit,
            'default' => 60,
        );

        return isset($limits[$action]) ? $limits[$action] : $limits['default'];
    }

    /**
     * Add rate limit headers to response.
     *
     * @param string $identifier Unique identifier.
     * @param string $action     Action type.
     * @param int    $limit      Maximum requests allowed.
     * @param int    $window     Time window in seconds.
     * @return array Headers to add.
     */
    public static function get_headers($identifier, $action, $limit = null, $window = 3600) {
        if ($limit === null) {
            $limit = self::get_limit_for_action($action);
        }

        $remaining = self::get_remaining($identifier, $action, $limit);
        $key = 'onboard_ratelimit_' . md5($identifier . '_' . $action);
        
        // Get expiration time.
        $expiration = get_option('_transient_timeout_' . $key);
        $reset = $expiration ? $expiration : time() + $window;

        return array(
            'X-RateLimit-Limit' => $limit,
            'X-RateLimit-Remaining' => $remaining,
            'X-RateLimit-Reset' => $reset,
        );
    }
}
