<?php
/**
 * PHPStan bootstrap — define WordPress constants and stubs for static analysis.
 *
 * @package PluginsOnboard
 */

// WordPress constants
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}

if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', ABSPATH . 'wp-content/plugins/');
}

if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', ABSPATH . 'wp-content/');
}

if (!defined('WPINC')) {
    define('WPINC', 'wp-includes');
}

// Plugin-specific constants
if (!defined('ONBOARD_PLUGIN_VERSION')) {
    define('ONBOARD_PLUGIN_VERSION', '1.0.8');
}

if (!defined('ONBOARD_PLUGIN_FILE')) {
    define('ONBOARD_PLUGIN_FILE', __FILE__);
}

if (!defined('ONBOARD_PLUGIN_DIR')) {
    define('ONBOARD_PLUGIN_DIR', __DIR__ . '/');
}

if (!defined('ONBOARD_PLUGIN_URL')) {
    define('ONBOARD_PLUGIN_URL', '');
}

if (!defined('ONBOARD_PLUGIN_BASENAME')) {
    define('ONBOARD_PLUGIN_BASENAME', 'plugins-onboard/plugins-onboard.php');
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
        public function get_status(): int { return 200; }
        public function get_data(): mixed { return null; }
        public function set_data($data): void {}
    }
}

// Stub WP_REST_Server if not available
if (!class_exists('WP_REST_Server')) {
    class WP_REST_Server {
        public const ALLMETHODS = 'GET, POST, PUT, PATCH, DELETE';
    }
}

// WordPress function stubs for static analysis
if (!function_exists('is_wp_error')) {
    function is_wp_error($thing): bool { return $thing instanceof WP_Error; }
}
