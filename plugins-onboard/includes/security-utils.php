<?php
/**
 * Security utilities.
 *
 * @package PluginsOnboard
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('onboard_get_client_ip')) {
/**
 * Get client IP address.
 *
 * @return string
 */
function onboard_get_client_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = $_SERVER['HTTP_X_REAL_IP'];
    } else {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
    }
    return trim($ip);
}
}

if (!function_exists('onboard_is_https')) {
/**
 * Check if request is over HTTPS.
 *
 * @return bool
 */
function onboard_is_https() {
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        return true;
    }
    if (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
        return true;
    }
    if (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
        return true;
    }
    return false;
}
}

if (!function_exists('onboard_is_valid_ip')) {
/**
 * Validate IP address format.
 *
 * @param string $ip IP address.
 * @return bool
 */
function onboard_is_valid_ip($ip) {
    return filter_var($ip, FILTER_VALIDATE_IP) !== false;
}
}

if (!function_exists('onboard_ip_in_cidr')) {
/**
 * Check if IP is in CIDR range.
 *
 * @param string $ip   IP address.
 * @param string $cidr CIDR notation (e.g., 192.168.1.0/24).
 * @return bool
 */
function onboard_ip_in_cidr($ip, $cidr) {
    list($subnet, $bits) = explode('/', $cidr);
    $subnet_long = ip2long($subnet);
    $ip_long = ip2long($ip);
    $mask = -1 << (32 - $bits);
    $subnet_long &= $mask;
    return ($ip_long & $mask) == $subnet_long;
}
}

if (!function_exists('onboard_sanitize_path')) {
/**
 * Sanitize file path to prevent directory traversal.
 *
 * @param string $path File path.
 * @return string|false Sanitized path or false if invalid.
 */
function onboard_sanitize_path($path) {
    // Remove null bytes.
    $path = str_replace(chr(0), '', $path);
    
    // Normalize slashes.
    $path = str_replace('\\', '/', $path);
    
    // Remove double slashes.
    $path = preg_replace('#/+#', '/', $path);
    
    // Check for directory traversal.
    if (strpos($path, '..') !== false) {
        return false;
    }
    
    return $path;
}
}

if (!function_exists('onboard_create_nonce')) {
/**
 * Generate CSRF token.
 *
 * @param string $action Action name.
 * @return string
 */
function onboard_create_nonce($action) {
    return wp_create_nonce('onboard_' . $action);
}
}

if (!function_exists('onboard_verify_nonce')) {
/**
 * Verify CSRF token.
 *
 * @param string $nonce  Nonce value.
 * @param string $action Action name.
 * @return bool
 */
function onboard_verify_nonce($nonce, $action) {
    return wp_verify_nonce($nonce, 'onboard_' . $action) !== false;
}
}

if (!function_exists('onboard_user_can')) {
/**
 * Check if user has required capability.
 *
 * @param string $capability Capability to check.
 * @return bool
 */
function onboard_user_can($capability = 'manage_options') {
    return current_user_can($capability);
}
}

if (!function_exists('onboard_log_security_event')) {
/**
 * Log security event.
 *
 * @param string $event   Event name.
 * @param array  $details Event details.
 */
function onboard_log_security_event($event, $details = array()) {
    $log_path = OnboardPaths::get(OnboardPaths::DIR_SECURITY_LOGS);
    
    if (empty($log_path)) {
        return;
    }
    
    $log_file = $log_path . 'security.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = onboard_get_client_ip();
    
    $message = sprintf(
        "[%s] [%s] %s: %s\n",
        $timestamp,
        $ip,
        $event,
        json_encode($details)
    );
    
    // Ensure directory exists.
    OnboardPaths::ensure_directory_exists(OnboardPaths::DIR_SECURITY_LOGS);
    
    error_log($message, 3, $log_file);
}
}

if (!function_exists('onboard_rate_limit')) {
/**
 * Rate limit check using transients.
 *
 * @param string $key     Unique key.
 * @param int    $limit   Maximum requests.
 * @param int    $window  Time window in seconds.
 * @return bool True if allowed, false if rate limited.
 */
function onboard_rate_limit($key, $limit = 10, $window = 60) {
    $transient_key = 'onboard_rl_' . md5($key);
    $current = get_transient($transient_key);
    
    if ($current === false) {
        set_transient($transient_key, 1, $window);
        return true;
    }
    
    if ($current >= $limit) {
        return false;
    }
    
    set_transient($transient_key, $current + 1, $window);
    return true;
}
}

if (!function_exists('onboard_esc')) {
/**
 * Escape output for HTML.
 *
 * @param string $text Text to escape.
 * @return string
 */
function onboard_esc($text) {
    return esc_html($text);
}
}

if (!function_exists('onboard_esc_attr')) {
/**
 * Escape output for attributes.
 *
 * @param string $text Text to escape.
 * @return string
 */
function onboard_esc_attr($text) {
    return esc_attr($text);
}
}

if (!function_exists('onboard_format_size')) {
/**
 * Format file size.
 *
 * @param int $bytes Size in bytes.
 * @return string
 */
function onboard_format_size($bytes) {
    return size_format($bytes);
}
}

if (!function_exists('onboard_format_date')) {
/**
 * Format timestamp.
 *
 * @param string $timestamp Timestamp.
 * @param string $format    Date format.
 * @return string
 */
function onboard_format_date($timestamp, $format = 'Y-m-d H:i:s') {
    return date_i18n($format, strtotime($timestamp));
}
}
