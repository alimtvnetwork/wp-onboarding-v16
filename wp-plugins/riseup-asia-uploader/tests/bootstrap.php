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

// PSR-4 autoloader via Composer.
require_once __DIR__ . '/../vendor/autoload.php';
