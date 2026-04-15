<?php
/**
 * AdminMenuEnqueueSnapshotsTrait — Snapshots page asset enqueuing.
 *
 * @package RiseupAsia\Admin\Traits
 * @since   2.37.0
 */

namespace RiseupAsia\Admin\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\AdminPageType;
use RiseupAsia\Enums\AjaxActionType;
use RiseupAsia\Enums\EndpointType;
use RiseupAsia\Enums\NonceType;
use RiseupAsia\Enums\PaginationConfigType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\RetentionType;
use RiseupAsia\Enums\SnapshotFrequencyType;
use RiseupAsia\Enums\SnapshotModeType;
use RiseupAsia\Enums\SnapshotScopeType;
use RiseupAsia\Enums\SnapshotStatusType;

trait AdminMenuEnqueueSnapshotsTrait {

    /** Enqueue Snapshots page assets. */
    private function enqueueSnapshotsAssets(string $pluginFile, string $version, string $pluginSlug): void {
        wp_enqueue_style('riseup-admin-shared', plugins_url('assets/css/admin-shared.css', $pluginFile), [], $version);
        wp_enqueue_style('riseup-admin-snapshots', plugins_url('assets/css/admin-snapshots.css', $pluginFile), ['riseup-admin-shared'], $version);

        // Module chain: utils → progress → list/actions/settings → analytics → orchestrator
        wp_enqueue_script('riseup-admin-snapshots-utils', plugins_url('assets/js/admin-snapshots-utils.js', $pluginFile), ['jquery'], $version, true);
        wp_enqueue_script('riseup-admin-snapshots-progress', plugins_url('assets/js/admin-snapshots-progress.js', $pluginFile), ['riseup-admin-snapshots-utils'], $version, true);
        wp_enqueue_script('riseup-admin-snapshots-list', plugins_url('assets/js/admin-snapshots-list.js', $pluginFile), ['riseup-admin-snapshots-progress'], $version, true);
        wp_enqueue_script('riseup-admin-snapshots-actions', plugins_url('assets/js/admin-snapshots-actions.js', $pluginFile), ['riseup-admin-snapshots-progress'], $version, true);
        wp_enqueue_script('riseup-admin-snapshots-modals', plugins_url('assets/js/admin-snapshots-modals.js', $pluginFile), ['riseup-admin-snapshots-utils'], $version, true);
        wp_enqueue_script('riseup-admin-snapshots-settings', plugins_url('assets/js/admin-snapshots-settings.js', $pluginFile), ['riseup-admin-snapshots-utils'], $version, true);
        wp_enqueue_script('riseup-admin-snapshots-analytics', plugins_url('assets/js/admin-snapshots-analytics.js', $pluginFile), ['riseup-admin-snapshots-settings'], $version, true);
        wp_enqueue_script('riseup-admin-snapshots', plugins_url('assets/js/admin-snapshots.js', $pluginFile), [
            'riseup-admin-snapshots-list',
            'riseup-admin-snapshots-actions',
            'riseup-admin-snapshots-modals',
            'riseup-admin-snapshots-analytics',
        ], $version, true);

        wp_localize_script('riseup-admin-snapshots-utils', 'RiseupSnapshots', [
            'nonce'           => wp_create_nonce(NonceType::Admin->value),
            'restNonce'       => wp_create_nonce(NonceType::WpRest->value),
            'restBase'        => esc_url(rest_url(PluginConfigType::apiFullNamespace())),
            'logsPageUrl'     => AdminPageType::Logs->adminUrl(),
            'paginationLimit' => PaginationConfigType::DefaultLimit->value,
            'status'          => [
                'complete'  => SnapshotStatusType::Complete->value,
                'running'   => SnapshotStatusType::Running->value,
                'failed'    => SnapshotStatusType::Failed->value,
                'pending'   => SnapshotStatusType::Pending->value,
                'scheduled' => SnapshotStatusType::Scheduled->value,
            ],
            'mode'            => [
                'full'        => SnapshotModeType::Full->value,
                'incremental' => SnapshotModeType::Incremental->value,
            ],
            'scope'           => [
                'all'       => SnapshotScopeType::All->value,
                'wordpress' => SnapshotScopeType::WordPress->value,
                'content'   => SnapshotScopeType::Content->value,
                'custom'    => SnapshotScopeType::Custom->value,
            ],
            'frequency'       => [
                'manual'  => SnapshotFrequencyType::Manual->value,
                'hourly'  => SnapshotFrequencyType::Hourly->value,
                'daily'   => SnapshotFrequencyType::Daily->value,
                'weekly'  => SnapshotFrequencyType::Weekly->value,
                'monthly' => SnapshotFrequencyType::Monthly->value,
            ],
            'endpoints'       => [
                'list'        => EndpointType::SnapshotList->value,
                'schedule'    => EndpointType::SnapshotSchedule->value,
                'info'        => EndpointType::SnapshotInfo->value,
                'delete_'     => EndpointType::SnapshotDelete->value,
                'restore'     => EndpointType::SnapshotRestore->value,
                'export_'     => EndpointType::SnapshotExport->value,
                'import_'     => EndpointType::SnapshotImport->value,
                'settings'    => EndpointType::SnapshotSettings->value,
                'providers'   => EndpointType::SnapshotProviders->value,
                'tables'      => EndpointType::SnapshotTables->value,
                'fullBackup'  => EndpointType::SnapshotFullBackup->value,
                'incremental' => EndpointType::SnapshotIncremental->value,
                'cleanup'     => EndpointType::SnapshotCleanup->value,
                'download'    => EndpointType::SnapshotDownload->value,
                'progress'    => EndpointType::SnapshotProgress->value,
            ],
            'responseKeys'    => [
                'snapshots' => ResponseKeyType::Snapshots->value,
                'total'     => ResponseKeyType::Total->value,
                'jobId'     => ResponseKeyType::JobId->value,
                'message'   => ResponseKeyType::Message->value,
                'success'   => ResponseKeyType::Success->value,
            ],
            'retention'       => [
                'none'  => RetentionType::None->value,
                'days'  => RetentionType::Days->value,
                'count' => RetentionType::Count->value,
            ],
            'actions'         => [
                'saveSettings' => AjaxActionType::SaveSnapshotSettings->value,
            ],
            'monthNames'      => [
                __('January', $pluginSlug),
                __('February', $pluginSlug),
                __('March', $pluginSlug),
                __('April', $pluginSlug),
                __('May', $pluginSlug),
                __('June', $pluginSlug),
                __('July', $pluginSlug),
                __('August', $pluginSlug),
                __('September', $pluginSlug),
                __('October', $pluginSlug),
                __('November', $pluginSlug),
                __('December', $pluginSlug),
            ],
            'i18n'            => [
                'copied'               => __('Copied!', $pluginSlug),
                'copy'                 => __('Copy', $pluginSlug),
                'copyReport'           => __('Copy Report', $pluginSlug),
                'provider'             => __('Provider', $pluginSlug),
                'available'            => __('Available', $pluginSlug),
                'priority'             => __('Priority', $pluginSlug),
                'importing'            => __('Importing...', $pluginSlug),
                'uploadImport'         => __('Upload & Import', $pluginSlug),
                'restoring'            => __('Restoring...', $pluginSlug),
                'restoreNow'           => __('Restore Now', $pluginSlug),
                'cached'               => __('Cached', $pluginSlug),
                'built'                => __('Built', $pluginSlug),
                'confirmDeleteSnap'    => __('Are you sure you want to delete snapshot "%s"? This cannot be undone.', $pluginSlug),
                'fullBackup'           => __('Full backup', $pluginSlug),
                'incrementalBackup'    => __('Incremental', $pluginSlug),
                'scheduledBackup'      => __('Scheduled backup', $pluginSlug),
                'snapshotCompleted'    => __('Snapshot completed successfully', $pluginSlug),
                'snapshotJobFailed'    => __('Snapshot job failed', $pluginSlug),
                'failedLoadSnapshots'  => __('Failed to load snapshots', $pluginSlug),
                'snapshotQueued'       => __('Snapshot job queued — running in background', $pluginSlug),
                'snapshotCreateFailed' => __('Snapshot creation failed', $pluginSlug),
                'noFullSnapshot'       => __('No full snapshot found — create a full snapshot first', $pluginSlug),
                'incrementalQueued'    => __('Incremental backup queued', $pluginSlug),
                'incrementalFailed'    => __('Incremental backup failed', $pluginSlug),
                'importSuccess'        => __('Snapshot imported successfully', $pluginSlug),
                'importFailed'         => __('Import failed', $pluginSlug),
                'restoreQueued'        => __('Restore queued — running in background', $pluginSlug),
                'restoreFailed'        => __('Restore failed', $pluginSlug),
                'noDownloadUrl'        => __('No download URL returned', $pluginSlug),
                'snapshotDeleted'      => __('Snapshot deleted', $pluginSlug),
                'deleteFailed'         => __('Delete failed', $pluginSlug),
                'cascadeWarning'       => __('This full snapshot has %d incremental backup(s). Deleting it will also permanently remove all %d incremental snapshot(s).', $pluginSlug),
                'settingsSaved'        => __('Settings saved', $pluginSlug),
                'saveFailed'           => __('Save failed', $pluginSlug),
                'networkError'         => __('Network error', $pluginSlug),
                'failedLoadSettings'   => __('Failed to load settings.', $pluginSlug),
                'noProvidersDetected'  => __('No providers detected yet.', $pluginSlug),
                'failedDetectProviders' => __('Failed to detect providers.', $pluginSlug),
                'checkLogs'            => __('Check Logs', $pluginSlug),
                'incrementalSuffix'    => __('incremental', $pluginSlug),
                'incrementalsSuffix'   => __('incrementals', $pluginSlug),
            ],
        ]);
    }
}
