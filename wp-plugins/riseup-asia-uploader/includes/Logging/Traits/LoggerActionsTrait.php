<?php
/**
 * Logger Actions Trait — plugin and post action logging methods.
 *
 * @package RiseupAsia\Logging\Traits
 * @since   1.4.0
 */

namespace RiseupAsia\Logging\Traits;

use RiseupAsia\Enums\ActionType;
use RiseupAsia\Enums\StatusType;

trait LoggerActionsTrait {

    /** Log a plugin operation. */
    public function logPluginAction(
        string $action,
        string $pluginSlug,
        string $status = '',
        array $details = array(),
        ?string $errorMsg = null,
        array $extraEnhanced = array(),
    ) {
        $status = $status ?: StatusType::Success->value;
        $this->fileLogger->info('Logging plugin action', array(
            'action' => $action,
            'plugin' => $pluginSlug,
            'status' => $status,
        ));

        $user = $this->getUserInfo();
        $enhanced = $this->buildEnhancedFields($extraEnhanced);

        return $this->getDb()->logTransaction(
            $action,
            $pluginSlug,
            null,
            $user['login'],
            $user['id'],
            $this->getClientIp(),
            $details,
            $status,
            $errorMsg,
            $enhanced,
        );
    }

    /** Log a post operation. */
    public function logPostAction(
        string $action,
        int $postId,
        string $status = '',
        array $details = array(),
        ?string $errorMsg = null,
    ) {
        $status = $status ?: StatusType::Success->value;
        $this->fileLogger->info('Logging post action', array(
            'action' => $action,
            'post_id' => $postId,
            'status' => $status,
        ));

        $user = $this->getUserInfo();
        $enhanced = $this->buildEnhancedFields();

        return $this->getDb()->logTransaction(
            $action,
            null,
            $postId,
            $user['login'],
            $user['id'],
            $this->getClientIp(),
            $details,
            $status,
            $errorMsg,
            $enhanced,
        );
    }

    /** Log an authentication failure. */
    public function logAuthFailure(string $reason, array $details = array()) {
        $this->fileLogger->warn('Auth failure', array('reason' => $reason));
        $providedUser = isset($details['username']) ? $details['username'] : 'unknown';
        $enhanced = $this->buildEnhancedFields();

        return $this->getDb()->logTransaction(
            ActionType::AuthFailed->value,
            null,
            null,
            $providedUser,
            null,
            $this->getClientIp(),
            $details,
            StatusType::Failed->value,
            $reason,
            $enhanced,
        );
    }

    /** Log upload initiated. */
    public function logUploadInitiated(
        string $pluginSlug,
        array $details = array(),
        array $extraEnhanced = array(),
    ) {
        return $this->logPluginAction(
            ActionType::UploadInitiated->value,
            $pluginSlug,
            StatusType::Success->value,
            $details,
            null,
            $extraEnhanced,
        );
    }

    /** Log upload success. */
    public function logUpload(
        string $pluginSlug,
        array $details = array(),
        array $extraEnhanced = array(),
    ) {
        return $this->logPluginAction(
            ActionType::Upload->value,
            $pluginSlug,
            StatusType::Success->value,
            $details,
            null,
            $extraEnhanced,
        );
    }

    /** Log upload failure. */
    public function logUploadFailed(
        string $pluginSlug,
        string $error,
        array $details = array(),
    ) {
        $this->fileLogger->error('Upload failed', array('plugin' => $pluginSlug, 'error' => $error));

        return $this->logPluginAction(
            ActionType::Upload->value,
            $pluginSlug,
            StatusType::Failed->value,
            $details,
            $error,
        );
    }

    /** Log plugin enable. */
    public function logEnable(string $pluginSlug, array $details = array()) {
        return $this->logPluginAction(
            ActionType::Enable->value,
            $pluginSlug,
            StatusType::Success->value,
            $details,
        );
    }

    /** Log plugin disable. */
    public function logDisable(string $pluginSlug, array $details = array()) {
        return $this->logPluginAction(
            ActionType::Disable->value,
            $pluginSlug,
            StatusType::Success->value,
            $details,
        );
    }

    /** Log plugin delete. */
    public function logDelete(string $pluginSlug, array $details = array()) {
        return $this->logPluginAction(
            ActionType::Delete->value,
            $pluginSlug,
            StatusType::Success->value,
            $details,
        );
    }

    /** Log file replace. */
    public function logFileReplace(
        string $pluginSlug,
        string $filePath,
        array $details = array(),
    ) {
        $details['file_path'] = $filePath;

        return $this->logPluginAction(
            ActionType::FileReplace->value,
            $pluginSlug,
            StatusType::Success->value,
            $details,
        );
    }

    /** Log file delete. */
    public function logFileDelete(
        string $pluginSlug,
        string $filePath,
        array $details = array(),
    ) {
        $details['file_path'] = $filePath;

        return $this->logPluginAction(
            ActionType::FileDelete->value,
            $pluginSlug,
            StatusType::Success->value,
            $details,
        );
    }

    /** Log post creation. */
    public function logPostCreate(int $postId, array $details = array()) {
        return $this->logPostAction(
            ActionType::PostCreate->value,
            $postId,
            StatusType::Success->value,
            $details,
        );
    }

    /** Log post update. */
    public function logPostUpdate(int $postId, array $details = array()) {
        return $this->logPostAction(
            ActionType::PostUpdate->value,
            $postId,
            StatusType::Success->value,
            $details,
        );
    }

    /** Log category creation. */
    public function logCategoryCreate(int $termId, array $details = array()) {
        return $this->logPostAction(
            ActionType::CategoryCreate->value,
            $termId,
            StatusType::Success->value,
            $details,
        );
    }
}
