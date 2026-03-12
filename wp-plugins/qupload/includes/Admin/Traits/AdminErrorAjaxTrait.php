<?php
/**
 * AdminErrorAjaxTrait — AJAX handlers for log file read/clear operations.
 *
 * @package QUpload\Admin\Traits
 * @since   2.1.0
 */

namespace QUpload\Admin\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use QUpload\Enums\CapabilityType;
use QUpload\Enums\NonceType;
use QUpload\Logging\FileLogger;

trait AdminErrorAjaxTrait {

    /** Resolve a log file type to its absolute path. */
    private function resolveLogFilePath(string $type): string|false {
        $logger = FileLogger::getInstance();

        return match ($type) {
            'log'        => $logger->getLogFile(),
            'error'      => $logger->getErrorFile(),
            'stacktrace' => $logger->getStacktraceFile(),
            default      => false,
        };
    }

    /** AJAX handler: Read a log file's contents. */
    public function ajaxReadLogFile(): void {
        check_ajax_referer(NonceType::Admin->value, 'nonce');

        if (!current_user_can(CapabilityType::ManageOptions->value)) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $type = isset($_POST['file_type']) ? sanitize_text_field($_POST['file_type']) : '';
        $path = $this->resolveLogFilePath($type);

        if ($path === false || $path === null) {
            wp_send_json_error(['message' => 'Invalid file type']);
        }

        wp_send_json_success($this->readLogFileContent($path));
    }

    /** Read a log file's content with size-based truncation. */
    private function readLogFileContent(string $path): array {
        $exists = file_exists($path);
        $content = '';
        $size = 0;

        if ($exists) {
            $size = filesize($path);
            $maxBytes = 512 * 1024;

            if ($size > $maxBytes) {
                $fp = @fopen($path, 'r');

                if ($fp !== false) {
                    fseek($fp, -$maxBytes, SEEK_END);
                    fgets($fp);
                    $content = fread($fp, $maxBytes);
                    fclose($fp);
                    $content = '... (truncated, showing last ' . round($maxBytes / 1024) . 'KB) ...' . PHP_EOL . $content;
                } else {
                    $content = '(Failed to open log file for reading)';
                }
            } else {
                $content = file_get_contents($path);
            }
        }

        return [
            'content'  => $content,
            'exists'   => $exists,
            'size'     => $size,
            'filename' => basename($path),
        ];
    }

    /** AJAX handler: Clear (truncate) a log file. */
    public function ajaxClearLogFile(): void {
        check_ajax_referer(NonceType::Admin->value, 'nonce');

        if (!current_user_can(CapabilityType::ManageOptions->value)) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $type = isset($_POST['file_type']) ? sanitize_text_field($_POST['file_type']) : '';
        $path = $this->resolveLogFilePath($type);

        if ($path === false || $path === null) {
            wp_send_json_error(['message' => 'Invalid file type']);
        }

        if (file_exists($path)) {
            $isWriteFailed = (file_put_contents($path, '') === false);

            if ($isWriteFailed) {
                wp_send_json_error(['message' => 'Failed to clear file']);
            }
        }

        wp_send_json_success(['message' => 'File cleared', 'file_type' => $type]);
    }
}
