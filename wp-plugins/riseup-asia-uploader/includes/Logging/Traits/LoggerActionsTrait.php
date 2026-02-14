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

use RiseupAsia\Enums\ActionType;

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

        return $this->getDb()->logTransaction(
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

        return $this->getDb()->logTransaction(
            $action, null, $postId, $user['login'], $user['id'],
            $this->getClientIp(), $details, $status, $errorMsg, $enhanced
        );
    }

    /** Log an authentication failure. */
    public function logAuthFailure($reason, $details = array()) {
        $this->fileLogger->warn('Auth failure', array('reason' => $reason));
        $providedUser = isset($details['username']) ? $details['username'] : 'unknown';
        $enhanced = $this->buildEnhancedFields();

        return $this->getDb()->logTransaction(
            ActionType::AuthFailed->value, null, null, $providedUser, null,
            $this->getClientIp(), $details, STATUS_FAILED, $reason, $enhanced
        );
    }

    /** Log upload initiated. */
    public function logUploadInitiated($pluginSlug, $details = array(), $extraEnhanced = array()) {
        return $this->logPluginAction(ActionType::UploadInitiated->value, $pluginSlug, STATUS_SUCCESS, $details, null, $extraEnhanced);
    }

    /** Log upload success. */
    public function logUpload($pluginSlug, $details = array(), $extraEnhanced = array()) {
        return $this->logPluginAction(ActionType::Upload->value, $pluginSlug, STATUS_SUCCESS, $details, null, $extraEnhanced);
    }

    /** Log upload failure. */
    public function logUploadFailed($pluginSlug, $error, $details = array()) {
        $this->fileLogger->error('Upload failed', array('plugin' => $pluginSlug, 'error' => $error));
        return $this->logPluginAction(ActionType::Upload->value, $pluginSlug, STATUS_FAILED, $details, $error);
    }

    /** Log plugin enable. */
    public function logEnable($pluginSlug, $details = array()) {
        return $this->logPluginAction(ActionType::Enable->value, $pluginSlug, STATUS_SUCCESS, $details);
    }

    /** Log plugin disable. */
    public function logDisable($pluginSlug, $details = array()) {
        return $this->logPluginAction(ActionType::Disable->value, $pluginSlug, STATUS_SUCCESS, $details);
    }

    /** Log plugin delete. */
    public function logDelete($pluginSlug, $details = array()) {
        return $this->logPluginAction(ActionType::Delete->value, $pluginSlug, STATUS_SUCCESS, $details);
    }

    /** Log file replace. */
    public function logFileReplace($pluginSlug, $filePath, $details = array()) {
        $details['file_path'] = $filePath;
        return $this->logPluginAction(ActionType::FileReplace->value, $pluginSlug, STATUS_SUCCESS, $details);
    }

    /** Log file delete. */
    public function logFileDelete($pluginSlug, $filePath, $details = array()) {
        $details['file_path'] = $filePath;
        return $this->logPluginAction(ActionType::FileDelete->value, $pluginSlug, STATUS_SUCCESS, $details);
    }

    /** Log post creation. */
    public function logPostCreate($postId, $details = array()) {
        return $this->logPostAction(ActionType::PostCreate->value, $postId, STATUS_SUCCESS, $details);
    }

    /** Log post update. */
    public function logPostUpdate($postId, $details = array()) {
        return $this->logPostAction(ActionType::PostUpdate->value, $postId, STATUS_SUCCESS, $details);
    }

    /** Log category creation. */
    public function logCategoryCreate($termId, $details = array()) {
        return $this->logPostAction(ActionType::CategoryCreate->value, $termId, STATUS_SUCCESS, $details);
    }
}
