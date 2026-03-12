<?php
/**
 * PHPStan bootstrap — define WordPress constants and stubs for static analysis.
 *
 * @package QUpload
 */

// WordPress constants
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}

if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', ABSPATH . 'wp-content/plugins/');
}

if (!defined('WPINC')) {
    define('WPINC', 'wp-includes');
}

// Stub WP_User if not available
if (!class_exists('WP_User')) {
    class WP_User {
        public int $ID = 0;
        public string $user_login = '';
        public string $user_email = '';
        public string $display_name = '';
    }
}

// Stub WP_Error if not available
if (!class_exists('WP_Error')) {
    class WP_Error {
        public function __construct(string $code = '', string $message = '', $data = '') {}
        public function get_error_message(): string { return ''; }
        public function get_error_code(): string { return ''; }
        public function get_error_data($code = ''): mixed { return null; }
    }
}

// Stub WP_REST_Request if not available
if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request {
        public function get_param(string $key): mixed { return null; }
        public function get_params(): array { return []; }
        public function get_header(string $key): ?string { return null; }
        public function get_method(): string { return 'GET'; }
        public function get_route(): string { return ''; }
        public function get_body(): string { return ''; }
        public function get_json_params(): array { return []; }
    }
}

// Stub WP_REST_Response if not available
if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        public function __construct($data = null, int $status = 200, array $headers = []) {}
    }
}

// WordPress function stubs for static analysis
if (!function_exists('is_wp_error')) {
    function is_wp_error($thing): bool { return $thing instanceof WP_Error; }
}
