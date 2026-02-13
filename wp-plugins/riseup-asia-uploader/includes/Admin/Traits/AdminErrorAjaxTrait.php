<?php
/**
 * Admin Error & Log File AJAX Trait
 *
 * AJAX handlers for error dismissal, clearing, and log file operations.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\CapabilityType;

trait AdminErrorAjaxTrait {

    /**
     * AJAX handler: Dismiss error flash (mark all as seen).
     */
    public function ajax_dismiss_error_flash() {
        check_ajax_referer('riseup_admin_nonce', 'nonce');
        if (!current_user_can(CapabilityType::ManageOptions->value)) {
            wp_send_json_error(array('message' => MSG_UNAUTHORIZED));
        }

        $db = RiseupDatabase::get_instance();
        $pdo = $db->get_pdo();

        $stmt = $pdo->query('SELECT MAX(id) FROM error_sessions');
        $max_id = (int) $stmt->fetchColumn();
        $now = gmdate('Y-m-d\TH:i:s\Z');

        $pdo->exec("INSERT OR REPLACE INTO flash_state (key, value, updated_at) VALUES ('last_seen_error_id', '{$max_id}', '{$now}')");
        $pdo->exec("INSERT OR REPLACE INTO flash_state (key, value, updated_at) VALUES ('has_unseen_errors', '0', '{$now}')");

        wp_send_json_success(array('message' => 'All errors marked as seen', 'last_seen_id' => $max_id));
    }

    /**
     * AJAX handler: Clear all error sessions.
     */
    public function ajax_clear_error_sessions() {
        check_ajax_referer('riseup_admin_nonce', 'nonce');
        if (!current_user_can(CapabilityType::ManageOptions->value)) {
            wp_send_json_error(array('message' => MSG_UNAUTHORIZED));
        }

        $db = RiseupDatabase::get_instance();
        $pdo = $db->get_pdo();

        $pdo->exec('DELETE FROM error_sessions');
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $pdo->exec("INSERT OR REPLACE INTO flash_state (key, value, updated_at) VALUES ('last_seen_error_id', '0', '{$now}')");
        $pdo->exec("INSERT OR REPLACE INTO flash_state (key, value, updated_at) VALUES ('has_unseen_errors', '0', '{$now}')");

        wp_send_json_success(array('message' => 'All error sessions cleared'));
    }

    /**
     * Resolve a log file type to its absolute path.
     *
     * @param string $type One of 'log', 'error', 'stacktrace'.
     * @return string|false File path or false if invalid type.
     */
    private function resolve_log_file_path($type) {
        $logger = RiseupFileLogger::get_instance();
        switch ($type) {
            case 'log':
                return $logger->get_log_file();
            case 'error':
                return $logger->get_error_file();
            case 'stacktrace':
                return $logger->get_stacktrace_file();
            default:
                return false;
        }
    }

    /**
     * AJAX handler: Read a log file's contents.
     */
    public function ajax_read_log_file() {
        check_ajax_referer('riseup_admin_nonce', 'nonce');
        if (!current_user_can(CapabilityType::ManageOptions->value)) {
            wp_send_json_error(array('message' => MSG_UNAUTHORIZED));
        }

        $type = isset($_POST['file_type']) ? sanitize_text_field($_POST['file_type']) : '';
        $path = $this->resolve_log_file_path($type);

        if ($path === false) {
            wp_send_json_error(array('message' => 'Invalid file type'));
        }

        wp_send_json_success($this->readLogFileContent($path));
    }

    /**
     * Read a log file's content with size-based truncation.
     *
     * @param string $path File path.
     * @return array File content data.
     */
    private function readLogFileContent(string $path): array {
        $exists = file_exists($path);
        $content = '';
        $size = 0;

        if ($exists) {
            $size = filesize($path);
            $max_bytes = 512 * 1024;
            if ($size > $max_bytes) {
                $fp = fopen($path, 'r');
                fseek($fp, -$max_bytes, SEEK_END);
                fgets($fp);
                $content = fread($fp, $max_bytes);
                fclose($fp);
                $content = '... (truncated, showing last ' . round($max_bytes / 1024) . 'KB) ...' . PHP_EOL . $content;
            } else {
                $content = file_get_contents($path);
            }
        }

        return array(
            'content'  => $content,
            'exists'   => $exists,
            'size'     => $size,
            'filename' => basename($path),
        );
    }

    /**
     * AJAX handler: Clear (truncate) a log file.
     */
    public function ajax_clear_log_file() {
        check_ajax_referer('riseup_admin_nonce', 'nonce');
        if (!current_user_can(CapabilityType::ManageOptions->value)) {
            wp_send_json_error(array('message' => MSG_UNAUTHORIZED));
        }

        $type = isset($_POST['file_type']) ? sanitize_text_field($_POST['file_type']) : '';
        $path = $this->resolve_log_file_path($type);

        if ($path === false) {
            wp_send_json_error(array('message' => 'Invalid file type'));
        }

        if (file_exists($path)) {
            file_put_contents($path, '');
        }

        wp_send_json_success(array('message' => 'File cleared', 'file_type' => $type));
    }
}
