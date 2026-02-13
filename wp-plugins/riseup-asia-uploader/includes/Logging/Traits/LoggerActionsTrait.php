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
     * @param string      $action        Action type.
     * @param string      $pluginSlug    Plugin slug.
     * @param string      $status        Status.
     * @param array       $details       Additional details.
     * @param string|null $errorMsg      Error message if failed.
     * @param array       $extraEnhanced Extra enhanced fields.
     * @return int|false Insert ID or false.
     */
    public function logPluginAction($action, $pluginSlug, $status = STATUS_SUCCESS, $details = array(), $errorMsg = null, $extraEnhanced = array()) {
        $this->fileLogger->info('Logging plugin action', array(
            'action' => $action, 'plugin' => $pluginSlug, 'status' => $status,
        ));

        $user = $this->getUserInfo();
        $enhanced = $this->buildEnhancedFields($extraEnhanced);

        return $this->getDb()->log_transaction(
            $action, $pluginSlug, null, $user['login'], $user['id'],
            $this->getClientIp(), $details, $status, $errorMsg, $enhanced
        );
    }

    /**
     * Log a post operation.
     *
     * @param string      $action   Action type.
     * @param int         $postId   Post ID.
     * @param string      $status   Status.
     * @param array       $details  Additional details.
     * @param string|null $errorMsg Error message if failed.
     * @return int|false Insert ID or false.
     */
    public function logPostAction($action, $postId, $status = STATUS_SUCCESS, $details = array(), $errorMsg = null) {
        $this->fileLogger->info('Logging post action', array(
            'action' => $action, 'post_id' => $postId, 'status' => $status,
        ));

        $user = $this->getUserInfo();
        $enhanced = $this->buildEnhancedFields();

        return $this->getDb()->log_transaction(
            $action, null, $postId, $user['login'], $user['id'],
            $this->getClientIp(), $details, $status, $errorMsg, $enhanced
        );
    }

    /** Log an authentication failure. */
    public function logAuthFailure($reason, $details = array()) {
        $this->fileLogger->warn('Auth failure', array('reason' => $reason));
        $providedUser = isset($details['username']) ? $details['username'] : 'unknown';
        $enhanced = $this->buildEnhancedFields();

        return $this->getDb()->log_transaction(
            ACTION_AUTH_FAILED, null, null, $providedUser, null,
            $this->getClientIp(), $details, STATUS_FAILED, $reason, $enhanced
        );
    }

    /** Log upload initiated. */
    public function logUploadInitiated($pluginSlug, $details = array(), $extraEnhanced = array()) {
        return $this->logPluginAction(ACTION_UPLOAD_INITIATED, $pluginSlug, STATUS_SUCCESS, $details, null, $extraEnhanced);
    }

    /** Log upload success. */
    public function logUpload($pluginSlug, $details = array(), $extraEnhanced = array()) {
        return $this->logPluginAction(ACTION_UPLOAD, $pluginSlug, STATUS_SUCCESS, $details, null, $extraEnhanced);
    }

    /** Log upload failure. */
    public function logUploadFailed($pluginSlug, $error, $details = array()) {
        $this->fileLogger->error('Upload failed', array('plugin' => $pluginSlug, 'error' => $error));
        return $this->logPluginAction(ACTION_UPLOAD, $pluginSlug, STATUS_FAILED, $details, $error);
    }

    /** Log plugin enable. */
    public function logEnable($pluginSlug, $details = array()) {
        return $this->logPluginAction(ACTION_ENABLE, $pluginSlug, STATUS_SUCCESS, $details);
    }

    /** Log plugin disable. */
    public function logDisable($pluginSlug, $details = array()) {
        return $this->logPluginAction(ACTION_DISABLE, $pluginSlug, STATUS_SUCCESS, $details);
    }

    /** Log plugin delete. */
    public function logDelete($pluginSlug, $details = array()) {
        return $this->logPluginAction(ACTION_DELETE, $pluginSlug, STATUS_SUCCESS, $details);
    }

    /** Log file replace. */
    public function logFileReplace($pluginSlug, $filePath, $details = array()) {
        $details['file_path'] = $filePath;
        return $this->logPluginAction(ACTION_FILE_REPLACE, $pluginSlug, STATUS_SUCCESS, $details);
    }

    /** Log file delete. */
    public function logFileDelete($pluginSlug, $filePath, $details = array()) {
        $details['file_path'] = $filePath;
        return $this->logPluginAction(ACTION_FILE_DELETE, $pluginSlug, STATUS_SUCCESS, $details);
    }

    /** Log post creation. */
    public function logPostCreate($postId, $details = array()) {
        return $this->logPostAction(ACTION_POST_CREATE, $postId, STATUS_SUCCESS, $details);
    }

    /** Log post update. */
    public function logPostUpdate($postId, $details = array()) {
        return $this->logPostAction(ACTION_POST_UPDATE, $postId, STATUS_SUCCESS, $details);
    }

    /** Log category creation. */
    public function logCategoryCreate($termId, $details = array()) {
        return $this->logPostAction(ACTION_CATEGORY_CREATE, $termId, STATUS_SUCCESS, $details);
    }
}
