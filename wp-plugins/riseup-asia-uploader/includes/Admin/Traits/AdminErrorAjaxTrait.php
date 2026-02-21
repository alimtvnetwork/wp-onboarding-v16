<?php
/**
 * Admin Error & Log File AJAX Trait
 *
 * AJAX handlers for error dismissal, clearing, and log file operations.
 *
 * @package RiseupAsia\Admin\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Admin\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\CapabilityType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\ResponseMessageType;
use RiseupAsia\Database\Database;
use RiseupAsia\Logging\FileLogger;
use RiseupAsia\Helpers\BooleanHelpers;
use RiseupAsia\Helpers\DateHelper;

trait AdminErrorAjaxTrait {

    /** AJAX handler: Dismiss error flash (mark all as seen). */
    public function ajaxDismissErrorFlash() {
        check_ajax_referer('riseup_admin_nonce', 'nonce');
        if (BooleanHelpers::isCapabilityMissing(CapabilityType::ManageOptions->value)) {
            wp_send_json_error(array(ResponseKeyType::Message->value => ResponseMessageType::Unauthorized->value));
        }

        $db = Database::getInstance();
        $pdo = $db->getPdo();

        $stmt = $pdo->query('SELECT MAX(id) FROM error_sessions');
        $maxId = (int) $stmt->fetchColumn();
        $now = DateHelper::nowUtc();

        $pdo->exec("INSERT OR REPLACE INTO flash_state (key, value, updated_at) VALUES ('last_seen_error_id', '{$maxId}', '{$now}')");
        $pdo->exec("INSERT OR REPLACE INTO flash_state (key, value, updated_at) VALUES ('has_unseen_errors', '0', '{$now}')");

        wp_send_json_success(array(ResponseKeyType::Message->value => 'All errors marked as seen', 'last_seen_id' => $maxId));
    }

    /** AJAX handler: Clear all error sessions. */
    public function ajaxClearErrorSessions() {
        check_ajax_referer('riseup_admin_nonce', 'nonce');
        if (BooleanHelpers::isCapabilityMissing(CapabilityType::ManageOptions->value)) {
            wp_send_json_error(array(ResponseKeyType::Message->value => ResponseMessageType::Unauthorized->value));
        }

        $db = Database::getInstance();
        $pdo = $db->getPdo();

        $pdo->exec('DELETE FROM error_sessions');
        $now = DateHelper::nowUtc();
        $pdo->exec("INSERT OR REPLACE INTO flash_state (key, value, updated_at) VALUES ('last_seen_error_id', '0', '{$now}')");
        $pdo->exec("INSERT OR REPLACE INTO flash_state (key, value, updated_at) VALUES ('has_unseen_errors', '0', '{$now}')");

        wp_send_json_success(array(ResponseKeyType::Message->value => 'All error sessions cleared'));
    }

    /** Resolve a log file type to its absolute path. */
    private function resolveLogFilePath(string $type): string|false {
        $logger = FileLogger::getInstance();
        switch ($type) {
            case 'log':
                return $logger->getLogFile();
            case 'error':
                return $logger->getErrorFile();
            case 'stacktrace':
                return $logger->getStacktraceFile();
            default:
                return false;
        }
    }

    /** AJAX handler: Read a log file's contents. */
    public function ajaxReadLogFile() {
        check_ajax_referer('riseup_admin_nonce', 'nonce');
        if (BooleanHelpers::isCapabilityMissing(CapabilityType::ManageOptions->value)) {
            wp_send_json_error(array(ResponseKeyType::Message->value => ResponseMessageType::Unauthorized->value));
        }

        $type = isset($_POST['file_type']) ? sanitize_text_field($_POST['file_type']) : '';
        $path = $this->resolveLogFilePath($type);

        if ($path === false) {
            wp_send_json_error(array(ResponseKeyType::Message->value => 'Invalid file type'));
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
                $fp = fopen($path, 'r');
                fseek($fp, -$maxBytes, SEEK_END);
                fgets($fp);
                $content = fread($fp, $maxBytes);
                fclose($fp);
                $content = '... (truncated, showing last ' . round($maxBytes / 1024) . 'KB) ...' . PHP_EOL . $content;
            } else {
                $content = file_get_contents($path);
            }
        }

        return array(
            ResponseKeyType::Content->value  => $content,
            ResponseKeyType::Exists->value   => $exists,
            ResponseKeyType::Size->value     => $size,
            ResponseKeyType::Filename->value => basename($path),
        );
    }

    /** AJAX handler: Clear (truncate) a log file. */
    public function ajaxClearLogFile() {
        check_ajax_referer('riseup_admin_nonce', 'nonce');
        if (BooleanHelpers::isCapabilityMissing(CapabilityType::ManageOptions->value)) {
            wp_send_json_error(array(ResponseKeyType::Message->value => ResponseMessageType::Unauthorized->value));
        }

        $type = isset($_POST['file_type']) ? sanitize_text_field($_POST['file_type']) : '';
        $path = $this->resolveLogFilePath($type);

        if ($path === false) {
            wp_send_json_error(array(ResponseKeyType::Message->value => 'Invalid file type'));
        }

        if (file_exists($path)) {
            file_put_contents($path, '');
        }

        wp_send_json_success(array(ResponseKeyType::Message->value => 'File cleared', 'file_type' => $type));
    }
}
