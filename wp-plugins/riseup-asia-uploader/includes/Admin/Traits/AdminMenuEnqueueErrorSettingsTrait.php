<?php
/**
 * AdminMenuEnqueueErrorSettingsTrait — Error Log and Settings page asset enqueuing.
 *
 * @package RiseupAsia\Admin\Traits
 * @since   2.37.0
 */

namespace RiseupAsia\Admin\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\AdminTabType;
use RiseupAsia\Enums\AjaxActionType;
use RiseupAsia\Enums\NonceType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\RetentionType;
use RiseupAsia\Enums\SnapshotFrequencyType;
use RiseupAsia\Enums\StorageModeType;

trait AdminMenuEnqueueErrorSettingsTrait {

    /** Enqueue Error Log page assets. */
    private function enqueueErrorsAssets(string $pluginFile, string $version, string $pluginSlug): void {
        wp_enqueue_style('riseup-admin-errors', plugins_url('assets/css/admin-errors.css', $pluginFile), [], $version);
        wp_enqueue_script('riseup-admin-errors', plugins_url('assets/js/admin-errors.js', $pluginFile), ['jquery'], $version, true);

        $activeTab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : AdminTabType::Sessions->value;

        wp_localize_script('riseup-admin-errors', 'RiseupErrors', [
            'nonce'        => wp_create_nonce(NonceType::Admin->value),
            'activeTab'    => $activeTab,
            'actions'      => [
                'dismissFlash'  => AjaxActionType::DismissErrorFlash->value,
                'clearSessions' => AjaxActionType::ClearErrorSessions->value,
                'readLogFile'   => AjaxActionType::ReadLogFile->value,
                'clearLogFile'  => AjaxActionType::ClearLogFile->value,
                'clearAllLogs'  => AjaxActionType::ClearAllLogs->value,
            ],
            'tabs'         => [
                'sessions'   => AdminTabType::Sessions->value,
                'log'        => AdminTabType::Log->value,
                'error'      => AdminTabType::Error->value,
                'stacktrace' => AdminTabType::Stacktrace->value,
            ],
            'responseKeys' => [
                'content'  => ResponseKeyType::Content->value,
                'exists'   => ResponseKeyType::Exists->value,
                'size'     => ResponseKeyType::Size->value,
                'filename' => ResponseKeyType::Filename->value,
                'message'  => ResponseKeyType::Message->value,
            ],
            'i18n'         => [
                'dismissing'      => __('Dismissing...', $pluginSlug),
                'markAsSeen'      => __('Mark as Seen', $pluginSlug),
                'confirmClearAll' => __('Are you sure you want to clear all error sessions? This cannot be undone.', $pluginSlug),
                'clearFailed'     => __('Failed to clear errors.', $pluginSlug),
                'clearLogFailed'  => __('Failed to clear log file.', $pluginSlug),
                'copied'          => __('Copied!', $pluginSlug),
                'confirmClearLog' => __('Are you sure you want to clear this log file?', $pluginSlug),
                'confirmClearAllLogs' => __('Are you sure you want to clear ALL log files for both Riseup Asia and QUpload? This includes file logs and error sessions. This cannot be undone.', $pluginSlug),
                'clearAllLogsFailed'  => __('Failed to clear all logs.', $pluginSlug),
                'noStackTrace'    => __('No stack trace available.', $pluginSlug),
                'noContextData'   => __('No context data', $pluginSlug),
            ],
        ]);
    }

    /** Enqueue Settings page assets. */
    private function enqueueSettingsAssets(string $pluginFile, string $version, string $pluginSlug): void {
        wp_enqueue_style('riseup-admin-settings', plugins_url('assets/css/admin-settings.css', $pluginFile), [], $version);
        wp_enqueue_script('riseup-admin-settings', plugins_url('assets/js/admin-settings.js', $pluginFile), ['jquery'], $version, true);

        wp_localize_script('riseup-admin-settings', 'RiseupSettings', [
            'nonce'         => wp_create_nonce(NonceType::Admin->value),
            'updateActions' => [
                'testConnection' => AjaxActionType::TestUpdateConnection->value,
                'clearCache'     => AjaxActionType::ClearUpdateCache->value,
                'checkUpdates'   => AjaxActionType::CheckForUpdates->value,
            ],
            'snapFrequency' => [
                'manual'  => SnapshotFrequencyType::Manual->value,
                'hourly'  => SnapshotFrequencyType::Hourly->value,
                'daily'   => SnapshotFrequencyType::Daily->value,
                'weekly'  => SnapshotFrequencyType::Weekly->value,
                'monthly' => SnapshotFrequencyType::Monthly->value,
            ],
            'snapRetention' => [
                'none'  => RetentionType::None->value,
                'days'  => RetentionType::Days->value,
                'count' => RetentionType::Count->value,
            ],
            'snapStorage'   => [
                'single'   => StorageModeType::Single->value,
                'perTable' => StorageModeType::PerTable->value,
            ],
            'snapActions'   => [
                'storageStats' => AjaxActionType::GetSnapshotStorageStats->value,
                'saveSettings' => AjaxActionType::SaveSnapshotSettings->value,
                'runCleanup'   => AjaxActionType::RunSnapshotCleanup->value,
            ],
            'i18n'          => [
                'connectionFailed'    => __('Connection failed', $pluginSlug),
                'requestFailed'       => __('Request failed', $pluginSlug),
                'clearCacheFailed'    => __('Failed to clear cache', $pluginSlug),
                'updateCheckFailed'   => __('Update check failed', $pluginSlug),
                'snapshotsInfo'       => __('%1$d snapshots, %2$s used', $pluginSlug),
                'snapshotsInfoFree'   => __('(%s free)', $pluginSlug),
                'unableToLoadStats'   => __('Unable to load stats', $pluginSlug),
                'saved'               => __('Saved', $pluginSlug),
                'failedToSave'        => __('Failed to save settings', $pluginSlug),
                'cleanupFailed'       => __('Cleanup failed', $pluginSlug),
            ],
        ]);
    }
}
