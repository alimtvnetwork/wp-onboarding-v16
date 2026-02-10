<?php
/**
 * Riseup Asia Uploader - Transaction Logger
 *
 * Wrapper for logging operations with user context.
 *
 * @package RiseupAsiaUploader
 * @since   1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Riseup_Logger
 *
 * Provides convenient methods for logging transactions.
 */
class Riseup_Logger {

    /**
     * Database instance.
     *
     * @var Riseup_Database|null
     */
    private $db = null;

    /**
     * File logger instance.
     *
     * @var Riseup_File_Logger
     */
    private $file_logger;

    /**
     * Singleton instance.
     *
     * @var Riseup_Logger|null
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return Riseup_Logger
     */
    public static function get_instance() {
        if (RiseupBooleanHelpers::is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->file_logger = Riseup_File_Logger::get_instance();
        // NOTE: We get the database instance lazily to avoid circular dependency
        $this->file_logger->info('Transaction logger initialized');
    }

    /**
     * Get database instance (lazy loading).
     *
     * @return Riseup_Database
     */
    private function get_db() {
        if (RiseupBooleanHelpers::is_null($this->db)) {
            $this->db = Riseup_Database::get_instance();
        }
        return $this->db;
    }

    /**
     * Get client IP address.
     *
     * @return string IP address.
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR');
        foreach ($ip_keys as $key) {
            if (RiseupBooleanHelpers::has_content($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $parts = explode(',', $ip);
                    $ip = trim($parts[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    /**
     * Get source machine hostname from request header.
     * This identifies which management server triggered the API request.
     *
     * @return string|null Source machine hostname or null if not provided.
     */
    private function get_source_machine() {
        $header_key = 'HTTP_X_RISEUP_SOURCE_MACHINE';
        if (RiseupBooleanHelpers::has_content($_SERVER[$header_key])) {
            // Sanitize: allow alphanumeric, dots, hyphens, underscores
            $machine = preg_replace('/[^a-zA-Z0-9.\-_]/', '', $_SERVER[$header_key]);
            return RiseupBooleanHelpers::has_content($machine) ? $machine : null;
        }
        return null;
    }

    /**
     * Get current user info.
     *
     * @return array User info with 'login' and 'id'.
     */
    private function get_user_info() {
        // Check if WordPress user functions are available
        if (RiseupBooleanHelpers::is_func_missing('wp_get_current_user')) {
            return array(
                'login' => 'anonymous',
                'id'    => 0,
            );
        }
        
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
    public function log_plugin_action($action, $plugin_slug, $status = RISEUP_STATUS_SUCCESS, $details = array(), $error_msg = null, $extra_enhanced = array()) {
        $this->file_logger->info('Logging plugin action', array(
            'action' => $action,
            'plugin' => $plugin_slug,
            'status' => $status,
        ));
        
        $user = $this->get_user_info();
        $source_machine = $this->get_source_machine();
        
        // Include source machine in enhanced fields
        $enhanced = array();
        if ($source_machine) {
            $enhanced['source_machine'] = $source_machine;
        }
        
        // Always include plugin_version — use RISEUP_VERSION as fallback
        // This ensures every action (enable, disable, delete, etc.) records which
        // version of the uploader plugin performed the operation.
        if (empty($enhanced['plugin_version']) && defined('RISEUP_VERSION')) {
            $enhanced['plugin_version'] = RISEUP_VERSION;
        }
        
        // Merge any extra enhanced fields (plugin_version, upload_source, etc.)
        // Extra enhanced fields override defaults (e.g., upload passes the target plugin's version)
        if (!empty($extra_enhanced)) {
            $enhanced = array_merge($enhanced, $extra_enhanced);
        }
        
        return $this->get_db()->log_transaction(
            $action,
            $plugin_slug,
            null, // post_id
            $user['login'],
            $user['id'],
            $this->get_client_ip(),
            $details,
            $status,
            $error_msg,
            $enhanced
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
        $this->file_logger->info('Logging post action', array(
            'action'  => $action,
            'post_id' => $post_id,
            'status'  => $status,
        ));
        
        $user = $this->get_user_info();
        $source_machine = $this->get_source_machine();
        
        // Include source machine in enhanced fields
        $enhanced = array();
        if ($source_machine) {
            $enhanced['source_machine'] = $source_machine;
        }
        
        // Always include plugin_version for audit trail
        if (defined('RISEUP_VERSION')) {
            $enhanced['plugin_version'] = RISEUP_VERSION;
        }
        
        return $this->get_db()->log_transaction(
            $action,
            null, // plugin_slug
            $post_id,
            $user['login'],
            $user['id'],
            $this->get_client_ip(),
            $details,
            $status,
            $error_msg,
            $enhanced
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
        $this->file_logger->warn('Auth failure', array('reason' => $reason));
        
        // For auth failures, we may not have a valid user
        $provided_user = isset($details['username']) ? $details['username'] : 'unknown';
        $source_machine = $this->get_source_machine();
        
        // Include source machine in enhanced fields
        $enhanced = array();
        if ($source_machine) {
            $enhanced['source_machine'] = $source_machine;
        }
        
        return $this->get_db()->log_transaction(
            RISEUP_ACTION_AUTH_FAILED,
            null,
            null,
            $provided_user,
            null,
            $this->get_client_ip(),
            $details,
            RISEUP_STATUS_FAILED,
            $reason,
            $enhanced
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
    public function log_upload($plugin_slug, $details = array(), $extra_enhanced = array()) {
        return $this->log_plugin_action(RISEUP_ACTION_UPLOAD, $plugin_slug, RISEUP_STATUS_SUCCESS, $details, null, $extra_enhanced);
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
        $this->file_logger->error('Upload failed', array('plugin' => $plugin_slug, 'error' => $error));
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
