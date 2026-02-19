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
