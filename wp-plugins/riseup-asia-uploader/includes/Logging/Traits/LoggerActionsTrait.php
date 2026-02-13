<?php
/**
 * Logger Actions Trait — plugin and post action logging methods.
 *
 * @package RiseupAsiaUploader
 * @since   1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait LoggerActionsTrait {

    /**
     * Log a plugin operation.
     *
     * @param string      $action         Action type.
     * @param string      $plugin_slug    Plugin slug.
     * @param string      $status         Status.
     * @param array       $details        Additional details.
     * @param string|null $error_msg      Error message if failed.
     * @param array       $extra_enhanced Extra enhanced fields.
     * @return int|false Insert ID or false.
     */
    public function log_plugin_action($action, $plugin_slug, $status = STATUS_SUCCESS, $details = array(), $error_msg = null, $extra_enhanced = array()) {
        $this->file_logger->info('Logging plugin action', array(
            'action' => $action, 'plugin' => $plugin_slug, 'status' => $status,
        ));

        $user = $this->get_user_info();
        $enhanced = $this->buildEnhancedFields($extra_enhanced);

        return $this->get_db()->log_transaction(
            $action, $plugin_slug, null, $user['login'], $user['id'],
            $this->get_client_ip(), $details, $status, $error_msg, $enhanced
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
     * @return int|false Insert ID or false.
     */
    public function log_post_action($action, $post_id, $status = STATUS_SUCCESS, $details = array(), $error_msg = null) {
        $this->file_logger->info('Logging post action', array(
            'action' => $action, 'post_id' => $post_id, 'status' => $status,
        ));

        $user = $this->get_user_info();
        $enhanced = $this->buildEnhancedFields();

        return $this->get_db()->log_transaction(
            $action, null, $post_id, $user['login'], $user['id'],
            $this->get_client_ip(), $details, $status, $error_msg, $enhanced
        );
    }

    /** Log an authentication failure. */
    public function log_auth_failure($reason, $details = array()) {
        $this->file_logger->warn('Auth failure', array('reason' => $reason));
        $provided_user = isset($details['username']) ? $details['username'] : 'unknown';
        $enhanced = $this->buildEnhancedFields();

        return $this->get_db()->log_transaction(
            ACTION_AUTH_FAILED, null, null, $provided_user, null,
            $this->get_client_ip(), $details, STATUS_FAILED, $reason, $enhanced
        );
    }

    /** Log upload initiated. */
    public function log_upload_initiated($plugin_slug, $details = array(), $extra_enhanced = array()) {
        return $this->log_plugin_action(ACTION_UPLOAD_INITIATED, $plugin_slug, STATUS_SUCCESS, $details, null, $extra_enhanced);
    }

    /** Log upload success. */
    public function log_upload($plugin_slug, $details = array(), $extra_enhanced = array()) {
        return $this->log_plugin_action(ACTION_UPLOAD, $plugin_slug, STATUS_SUCCESS, $details, null, $extra_enhanced);
    }

    /** Log upload failure. */
    public function log_upload_failed($plugin_slug, $error, $details = array()) {
        $this->file_logger->error('Upload failed', array('plugin' => $plugin_slug, 'error' => $error));
        return $this->log_plugin_action(ACTION_UPLOAD, $plugin_slug, STATUS_FAILED, $details, $error);
    }

    /** Log plugin enable. */
    public function log_enable($plugin_slug, $details = array()) {
        return $this->log_plugin_action(ACTION_ENABLE, $plugin_slug, STATUS_SUCCESS, $details);
    }

    /** Log plugin disable. */
    public function log_disable($plugin_slug, $details = array()) {
        return $this->log_plugin_action(ACTION_DISABLE, $plugin_slug, STATUS_SUCCESS, $details);
    }

    /** Log plugin delete. */
    public function log_delete($plugin_slug, $details = array()) {
        return $this->log_plugin_action(ACTION_DELETE, $plugin_slug, STATUS_SUCCESS, $details);
    }

    /** Log file replace. */
    public function log_file_replace($plugin_slug, $file_path, $details = array()) {
        $details['file_path'] = $file_path;
        return $this->log_plugin_action(ACTION_FILE_REPLACE, $plugin_slug, STATUS_SUCCESS, $details);
    }

    /** Log file delete. */
    public function log_file_delete($plugin_slug, $file_path, $details = array()) {
        $details['file_path'] = $file_path;
        return $this->log_plugin_action(ACTION_FILE_DELETE, $plugin_slug, STATUS_SUCCESS, $details);
    }

    /** Log post creation. */
    public function log_post_create($post_id, $details = array()) {
        return $this->log_post_action(ACTION_POST_CREATE, $post_id, STATUS_SUCCESS, $details);
    }

    /** Log post update. */
    public function log_post_update($post_id, $details = array()) {
        return $this->log_post_action(ACTION_POST_UPDATE, $post_id, STATUS_SUCCESS, $details);
    }

    /** Log category creation. */
    public function log_category_create($term_id, $details = array()) {
        return $this->log_post_action(ACTION_CATEGORY_CREATE, $term_id, STATUS_SUCCESS, $details);
    }
}
