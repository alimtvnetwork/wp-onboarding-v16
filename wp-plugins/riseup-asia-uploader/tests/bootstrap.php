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

// Stub WordPress string/path functions.
if (!function_exists('trailingslashit')) {
    function trailingslashit(string $value): string {
        return rtrim($value, '/\\') . '/';
    }
}

if (!function_exists('untrailingslashit')) {
    function untrailingslashit(string $value): string {
        return rtrim($value, '/\\');
    }
}

if (!function_exists('absint')) {
    function absint(mixed $value): int {
        return abs((int) $value);
    }
}

if (!function_exists('esc_html')) {
    function esc_html(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url(string $url): string {
        return filter_var($url, FILTER_SANITIZE_URL) ?: '';
    }
}

if (!function_exists('wp_slash')) {
    function wp_slash(mixed $value): mixed {
        if (gettype($value) === 'string') {
            return addslashes($value);
        }
        return $value;
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash(mixed $value): mixed {
        if (gettype($value) === 'string') {
            return stripslashes($value);
        }
        return $value;
    }
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

// Minimal WP_REST_Response stub.
if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        public mixed $data;
        public int $status;
        public function __construct(mixed $data = null, int $status = 200) {
            $this->data = $data;
            $this->status = $status;
        }
        public function get_data(): mixed { return $this->data; }
        public function get_status(): int { return $this->status; }
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

// Stub WordPress hook functions.
if (!function_exists('add_action')) {
    function add_action(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): true {
        return true;
    }
}

if (!function_exists('remove_action')) {
    function remove_action(string $hook, callable $callback, int $priority = 10): bool {
        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): true {
        return true;
    }
}

if (!function_exists('remove_filter')) {
    function remove_filter(string $hook, callable $callback, int $priority = 10): bool {
        return true;
    }
}

if (!function_exists('do_action')) {
    function do_action(string $hook, mixed ...$args): void {}
}

if (!function_exists('apply_filters')) {
    function apply_filters(string $hook, mixed $value, mixed ...$args): mixed {
        return $value;
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

// --- WP-Cron stubs ---
global $_wp_test_scheduled_events;
$_wp_test_scheduled_events = [];

if (!function_exists('wp_next_scheduled')) {
    function wp_next_scheduled(string $hook): int|false {
        global $_wp_test_scheduled_events;
        return $_wp_test_scheduled_events[$hook] ?? false;
    }
}

if (!function_exists('wp_schedule_event')) {
    function wp_schedule_event(int $timestamp, string $recurrence, string $hook): bool {
        global $_wp_test_scheduled_events;
        $_wp_test_scheduled_events[$hook] = $timestamp;
        return true;
    }
}

if (!function_exists('wp_clear_scheduled_hook')) {
    function wp_clear_scheduled_hook(string $hook): int {
        global $_wp_test_scheduled_events;
        unset($_wp_test_scheduled_events[$hook]);
        return 1;
    }
}

if (!function_exists('wp_unschedule_event')) {
    function wp_unschedule_event(int $timestamp, string $hook): bool {
        global $_wp_test_scheduled_events;
        unset($_wp_test_scheduled_events[$hook]);
        return true;
    }
}

// --- AJAX stubs (throw to simulate wp_die) ---
class WpAjaxTestException extends \RuntimeException {
    public bool $success;
    public mixed $data;

    public function __construct(bool $success, mixed $data) {
        $this->success = $success;
        $this->data    = $data;
        parent::__construct('wp_send_json called');
    }
}

if (!function_exists('check_ajax_referer')) {
    function check_ajax_referer(string $action, string $queryArg = ''): bool {
        return true; // always pass in tests
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success(mixed $data = null): void {
        throw new WpAjaxTestException(true, $data);
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error(mixed $data = null): void {
        throw new WpAjaxTestException(false, $data);
    }
}

// Stub is_plugin_active / is_plugin_active_for_network.
if (!function_exists('is_plugin_active')) {
    function is_plugin_active(string $plugin): bool {
        return false;
    }
}

if (!function_exists('is_plugin_active_for_network')) {
    function is_plugin_active_for_network(string $plugin): bool {
        return false;
    }
}

// Stub current_time.
if (!function_exists('current_time')) {
    function current_time(string $type, bool $gmt = false): int|string {
        if ($type === 'timestamp') {
            return time();
        }
        return date('Y-m-d H:i:s');
    }
}

// Stub __ (i18n).
if (!function_exists('__')) {
    function __(string $text, string $domain = 'default'): string {
        return $text;
    }
}

// Stub sanitize_key / sanitize_user / esc_url_raw.
if (!function_exists('sanitize_key')) {
    function sanitize_key(string $key): string {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower($key));
    }
}

if (!function_exists('sanitize_user')) {
    function sanitize_user(string $username): string {
        return trim($username);
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw(string $url): string {
        return filter_var($url, FILTER_SANITIZE_URL) ?: '';
    }
}

// Time constants.
if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

if (!defined('WEEK_IN_SECONDS')) {
    define('WEEK_IN_SECONDS', 604800);
}

// Auth key constants for AgentManager encryption tests.
if (!defined('AUTH_KEY')) {
    define('AUTH_KEY', 'test-auth-key-for-unit-tests-only');
}

if (!defined('SECURE_AUTH_KEY')) {
    define('SECURE_AUTH_KEY', 'test-secure-auth-key-for-unit-tests-only');
}

// Minimal wpdb stub.
if (!class_exists('wpdb')) {
    class wpdb {
        public string $prefix = 'wp_';
        public function prepare(string $query, ...$args): string {
            return vsprintf(str_replace('%s', "'%s'", $query), $args);
        }
    }
}

// Global $wpdb for classes that reference it.
if (!isset($GLOBALS['wpdb'])) {
    $GLOBALS['wpdb'] = new wpdb();
}

// PSR-4 autoloader via Composer.
require_once __DIR__ . '/../vendor/autoload.php';
