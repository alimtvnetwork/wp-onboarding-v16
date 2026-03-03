<?php
/**
 * PHPUnit bootstrap — defines WordPress stubs so unit tests
 * can run without a full WordPress installation.
 */

// Satisfy ABSPATH guards in source files.
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}

if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', sys_get_temp_dir() . '/wp-plugins-test');
}

if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', sys_get_temp_dir() . '/wp-content-test');
}

// Stub WordPress filesystem functions.
if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p(string $target): bool {
        if (is_dir($target)) {
            return true;
        }
        return mkdir($target, 0755, true);
    }
}

if (!function_exists('wp_cache_delete')) {
    function wp_cache_delete(string $key, string $group = ''): bool {
        return true;
    }
}

// Minimal WP_Error stub.
if (!class_exists('WP_Error')) {
    class WP_Error {
        private string $code;
        private string $message;
        private mixed $data;

        public function __construct(
            string $code = '',
            string $message = '',
            mixed $data = '',
        ) {
            $this->code    = $code;
            $this->message = $message;
            $this->data    = $data;
        }

        public function get_error_code(): string {
            return $this->code;
        }

        public function get_error_message(): string {
            return $this->message;
        }

        public function get_error_data(): mixed {
            return $this->data;
        }
    }
}

// Stub WordPress functions used by the traits.
if (!function_exists('is_wp_error')) {
    function is_wp_error($thing): bool {
        return $thing instanceof WP_Error;
    }
}

// --- In-memory option store for testing ---
global $_wp_test_options;
$_wp_test_options = [];

if (!function_exists('get_option')) {
    function get_option(string $key, mixed $default = false): mixed {
        global $_wp_test_options;
        return $_wp_test_options[$key] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option(string $key, mixed $value): bool {
        global $_wp_test_options;
        $_wp_test_options[$key] = $value;
        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option(string $key): bool {
        global $_wp_test_options;
        unset($_wp_test_options[$key]);
        return true;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $str): string {
        return trim(strip_tags($str));
    }
}

if (!function_exists('get_site_url')) {
    function get_site_url(): string {
        return 'https://example.com';
    }
}

if (!function_exists('wp_parse_url')) {
    function wp_parse_url(string $url): array|false {
        return parse_url($url);
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode(mixed $data): string|false {
        return json_encode($data);
    }
}

// Remote request stubs — overridable via global callback.
global $_wp_test_remote_handler;
$_wp_test_remote_handler = null;

if (!function_exists('wp_remote_request')) {
    function wp_remote_request(string $url, array $args = []): array|WP_Error {
        global $_wp_test_remote_handler;
        if (is_callable($_wp_test_remote_handler)) {
            return ($_wp_test_remote_handler)($url, $args);
        }
        return new WP_Error('no_handler', 'No test handler configured');
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code(array $response): int {
        return $response['response']['code'] ?? 0;
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body(array $response): string {
        return $response['body'] ?? '';
    }
}

// PSR-4 autoloader via Composer.
require_once __DIR__ . '/../vendor/autoload.php';
