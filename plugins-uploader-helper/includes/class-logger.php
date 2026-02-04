<?php
/**
 * Rise Up Uploader - Transaction Logger
 *
 * Wrapper for logging operations with user context.
 *
 * @package RiseUpUploader
 * @since   1.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class RiseUp_Logger
 *
 * Provides convenient methods for logging transactions.
 */
class RiseUp_Logger {

    /**
     * Database instance.
     *
     * @var RiseUp_Database
     */
    private $db;

    /**
     * Singleton instance.
     *
     * @var RiseUp_Logger|null
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return RiseUp_Logger
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->db = RiseUp_Database::get_instance();
    }

    /**
     * Get client IP address.
     *
     * @return string IP address.
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR');
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs (X-Forwarded-For).
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    /**
     * Get current user info.
     *
     * @return array User info with 'login' and 'id'.
     */
    private function get_user_info() {
        $current_user = wp_get_current_user();
        if ($current_user && $current_user->ID > 0) {
            return array(
                'login' => $current_user->user_login,
                'id'    => $current_user->ID,
            );
        }
        return array(
            'login' => 'anonymous',
            'id'    => 0,
        );
    }

    /**
     * Log a plugin operation.
     *
     * @param string      $action      Action type.
     * @param string      $plugin_slug Plugin slug.
     * @param string      $status      Status.
     * @param array       $details     Additional details.
     * @param string|null $error_msg   Error message if failed.
     *
     * @return int|false Insert ID or false.
     */
    public function log_plugin_action($action, $plugin_slug, $status = RISEUP_STATUS_SUCCESS, $details = array(), $error_msg = null) {
        $user = $this->get_user_info();
        return $this->db->log_transaction(
            $action,
            $plugin_slug,
            null, // post_id
            $user['login'],
            $user['id'],
            $this->get_client_ip(),
            $details,
            $status,
            $error_msg
        );
    }

    /**
     * Log a post operation.
     *
     * @param string      $action    Action type.
     * @param int         $post_id   Post ID.
     * @param string      $status    Status.
     * @param array       $details   Additional details.
     * @param string|null $error_msg Error message if failed.
     *
     * @return int|false Insert ID or false.
     */
    public function log_post_action($action, $post_id, $status = RISEUP_STATUS_SUCCESS, $details = array(), $error_msg = null) {
        $user = $this->get_user_info();
        return $this->db->log_transaction(
            $action,
            null, // plugin_slug
            $post_id,
            $user['login'],
            $user['id'],
            $this->get_client_ip(),
            $details,
            $status,
            $error_msg
        );
    }

    /**
     * Log an authentication failure.
     *
     * @param string $reason Reason for failure.
     * @param array  $details Additional details.
     *
     * @return int|false Insert ID or false.
     */
    public function log_auth_failure($reason, $details = array()) {
        // For auth failures, we may not have a valid user.
        $provided_user = isset($details['username']) ? $details['username'] : 'unknown';
        return $this->db->log_transaction(
            RISEUP_ACTION_AUTH_FAILED,
            null,
            null,
            $provided_user,
            null,
            $this->get_client_ip(),
            $details,
            RISEUP_STATUS_FAILED,
            $reason
        );
    }

    /**
     * Log upload success.
     *
     * @param string $plugin_slug Plugin slug.
     * @param array  $details     Details about the upload.
     *
     * @return int|false Insert ID or false.
     */
    public function log_upload($plugin_slug, $details = array()) {
        return $this->log_plugin_action(RISEUP_ACTION_UPLOAD, $plugin_slug, RISEUP_STATUS_SUCCESS, $details);
    }

    /**
     * Log upload failure.
     *
     * @param string $plugin_slug Plugin slug.
     * @param string $error       Error message.
     * @param array  $details     Additional details.
     *
     * @return int|false Insert ID or false.
     */
    public function log_upload_failed($plugin_slug, $error, $details = array()) {
        return $this->log_plugin_action(RISEUP_ACTION_UPLOAD, $plugin_slug, RISEUP_STATUS_FAILED, $details, $error);
    }

    /**
     * Log plugin enable.
     *
     * @param string $plugin_slug Plugin slug.
     * @param array  $details     Additional details.
     *
     * @return int|false Insert ID or false.
     */
    public function log_enable($plugin_slug, $details = array()) {
        return $this->log_plugin_action(RISEUP_ACTION_ENABLE, $plugin_slug, RISEUP_STATUS_SUCCESS, $details);
    }

    /**
     * Log plugin disable.
     *
     * @param string $plugin_slug Plugin slug.
     * @param array  $details     Additional details.
     *
     * @return int|false Insert ID or false.
     */
    public function log_disable($plugin_slug, $details = array()) {
        return $this->log_plugin_action(RISEUP_ACTION_DISABLE, $plugin_slug, RISEUP_STATUS_SUCCESS, $details);
    }

    /**
     * Log plugin delete.
     *
     * @param string $plugin_slug Plugin slug.
     * @param array  $details     Additional details.
     *
     * @return int|false Insert ID or false.
     */
    public function log_delete($plugin_slug, $details = array()) {
        return $this->log_plugin_action(RISEUP_ACTION_DELETE, $plugin_slug, RISEUP_STATUS_SUCCESS, $details);
    }

    /**
     * Log file replace.
     *
     * @param string $plugin_slug Plugin slug.
     * @param string $file_path   Relative file path.
     * @param array  $details     Additional details.
     *
     * @return int|false Insert ID or false.
     */
    public function log_file_replace($plugin_slug, $file_path, $details = array()) {
        $details['file_path'] = $file_path;
        return $this->log_plugin_action(RISEUP_ACTION_FILE_REPLACE, $plugin_slug, RISEUP_STATUS_SUCCESS, $details);
    }

    /**
     * Log file delete.
     *
     * @param string $plugin_slug Plugin slug.
     * @param string $file_path   Relative file path.
     * @param array  $details     Additional details.
     *
     * @return int|false Insert ID or false.
     */
    public function log_file_delete($plugin_slug, $file_path, $details = array()) {
        $details['file_path'] = $file_path;
        return $this->log_plugin_action(RISEUP_ACTION_FILE_DELETE, $plugin_slug, RISEUP_STATUS_SUCCESS, $details);
    }

    /**
     * Log post creation.
     *
     * @param int   $post_id Post ID.
     * @param array $details Additional details.
     *
     * @return int|false Insert ID or false.
     */
    public function log_post_create($post_id, $details = array()) {
        return $this->log_post_action(RISEUP_ACTION_POST_CREATE, $post_id, RISEUP_STATUS_SUCCESS, $details);
    }

    /**
     * Log post update.
     *
     * @param int   $post_id Post ID.
     * @param array $details Additional details.
     *
     * @return int|false Insert ID or false.
     */
    public function log_post_update($post_id, $details = array()) {
        return $this->log_post_action(RISEUP_ACTION_POST_UPDATE, $post_id, RISEUP_STATUS_SUCCESS, $details);
    }

    /**
     * Log category creation.
     *
     * @param int   $term_id Term ID.
     * @param array $details Additional details.
     *
     * @return int|false Insert ID or false.
     */
    public function log_category_create($term_id, $details = array()) {
        return $this->log_post_action(RISEUP_ACTION_CATEGORY_CREATE, $term_id, RISEUP_STATUS_SUCCESS, $details);
    }
}
