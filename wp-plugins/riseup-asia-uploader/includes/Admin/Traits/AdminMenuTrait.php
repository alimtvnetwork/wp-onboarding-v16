<?php
/**
 * AdminMenuTrait — Menu registration, submenus, and asset enqueuing.
 *
 * @package RiseupAsia\Admin\Traits
 * @since   2.0.0
 */

namespace RiseupAsia\Admin\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\ActionType;
use RiseupAsia\Enums\AdminPageType;
use RiseupAsia\Enums\AdminTabType;
use RiseupAsia\Enums\AgentStatusType;
use RiseupAsia\Enums\AjaxActionType;
use RiseupAsia\Enums\CapabilityType;
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
use RiseupAsia\Enums\StatusType;
use RiseupAsia\Enums\StorageModeType;

trait AdminMenuTrait {

    /** Add admin menu items. */
    public function addAdminMenu() {
        $this->registerMainMenu();
        $this->registerSubmenus();
        $this->registerErrorSubmenu();
    }

    /** Register the main admin menu page. */
    private function registerMainMenu() {
        $slug = PluginConfigType::Slug->value;
        $pluginSlug = $slug;

        add_menu_page(
            __('Riseup Asia Uploader', $pluginSlug),
            __('Riseup Uploader', $pluginSlug),
            CapabilityType::ManageOptions->value,
            $slug,
            array($this, 'renderLogsPage'),
            'dashicons-upload',
            80,
        );
    }

    /** Register standard submenus. */
    private function registerSubmenus() {
        $slug = PluginConfigType::Slug->value;
        $pluginSlug = $slug;

        $submenus = array(
            array(
                $slug,
                'Activity Logs',
                'renderLogsPage',
            ),
            array(
                AdminPageType::Settings->value,
                'Settings',
                'renderSettingsPage',
            ),
            array(
                AdminPageType::Agents->value,
                'Agent Sites',
                'renderAgentsPage',
            ),
            array(
                AdminPageType::Snapshots->value,
                'Snapshots',
                'renderSnapshotsPage',
            ),
            array(
                AdminPageType::License->value,
                'License',
                'renderLicensePage',
            ),
        );

        foreach ($submenus as $item) {
            add_submenu_page(
                $slug,
                __($item[1], $pluginSlug),
                __($item[1], $pluginSlug),
                CapabilityType::ManageOptions->value,
                $item[0],
                array($this, $item[2]),
            );
        }
    }

    /** Register the error log submenu with notification bubble. */
    private function registerErrorSubmenu() {
        $slug = PluginConfigType::Slug->value;
        $pluginSlug = $slug;
        $errorBubble = $this->buildErrorBubble();

        add_submenu_page(
            $slug,
            __('Error Log', $pluginSlug),
            __('Error Log', $pluginSlug) . $errorBubble,
            CapabilityType::ManageOptions->value,
            AdminPageType::Errors->value,
            array($this, 'renderErrorsPage'),
        );
    }

    /** Build the error count bubble HTML. */
    private function buildErrorBubble(): string {
        $unseen = $this->getUnseenErrorCount();
        if ($unseen <= 0) {
            return '';
        }

        return sprintf(' <span class="riseup-error-bubble">%d</span>', $unseen);
    }

    /** Enqueue admin assets. */
    public function enqueueAdminAssets($hook) {
        $isNonPluginPage = (strpos($hook, 'riseup-asia') === false);

        if ($isNonPluginPage) {
            return;
        }

        $version = PluginConfigType::Version->value;
        $pluginSlug = PluginConfigType::Slug->value;
        $pluginFile = dirname(__FILE__, 4) . '/' . $pluginSlug . '.php';

        // Global admin CSS (always loaded on plugin pages)
        wp_enqueue_style(
            'riseup-admin-styles',
            plugins_url('assets/admin.css', $pluginFile),
            array(),
            $version,
        );

        // Determine current page from query string
        $currentPage = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';

        // Per-page CSS + JS + localize
        $this->enqueuePerPageAssets($currentPage, $pluginFile, $version, $pluginSlug);
    }

    /** Enqueue page-specific CSS, JS, and localized data. */
    private function enqueuePerPageAssets(string $currentPage, string $pluginFile, string $version, string $pluginSlug): void {
        $slug = PluginConfigType::Slug->value;

        switch ($currentPage) {
            case AdminPageType::Errors->value:
                $this->enqueueErrorsAssets($pluginFile, $version, $pluginSlug);
                break;

            case AdminPageType::Settings->value:
                $this->enqueueSettingsAssets($pluginFile, $version, $pluginSlug);
                break;

            case AdminPageType::Agents->value:
                $this->enqueueAgentsAssets($pluginFile, $version, $pluginSlug);
                break;

            case AdminPageType::Snapshots->value:
                $this->enqueueSnapshotsAssets($pluginFile, $version, $pluginSlug);
                break;

            case AdminPageType::License->value:
                $this->enqueueLicenseAssets($pluginFile, $version, $pluginSlug);
                break;

            case $slug: // Main page = Activity Logs
                $this->enqueueLogsAssets($pluginFile, $version, $pluginSlug);
                break;
        }
    }

    /** Enqueue Error Log page assets. */
    private function enqueueErrorsAssets(string $pluginFile, string $version, string $pluginSlug): void {
        wp_enqueue_style('riseup-admin-errors', plugins_url('assets/css/admin-errors.css', $pluginFile), array(), $version);
        wp_enqueue_script('riseup-admin-errors', plugins_url('assets/js/admin-errors.js', $pluginFile), array('jquery'), $version, true);

        $activeTab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : AdminTabType::Sessions->value;

        wp_localize_script('riseup-admin-errors', 'RiseupErrors', array(
            'nonce'        => wp_create_nonce(NonceType::Admin->value),
            'activeTab'    => $activeTab,
            'actions'      => array(
                'dismissFlash'  => AjaxActionType::DismissErrorFlash->value,
                'clearSessions' => AjaxActionType::ClearErrorSessions->value,
                'readLogFile'   => AjaxActionType::ReadLogFile->value,
                'clearLogFile'  => AjaxActionType::ClearLogFile->value,
            ),
            'tabs'         => array(
                'sessions'   => AdminTabType::Sessions->value,
                'log'        => AdminTabType::Log->value,
                'error'      => AdminTabType::Error->value,
                'stacktrace' => AdminTabType::Stacktrace->value,
            ),
            'responseKeys' => array(
                'content'  => ResponseKeyType::Content->value,
                'exists'   => ResponseKeyType::Exists->value,
                'size'     => ResponseKeyType::Size->value,
                'filename' => ResponseKeyType::Filename->value,
                'message'  => ResponseKeyType::Message->value,
            ),
            'i18n'         => array(
                'dismissing'      => __('Dismissing...', $pluginSlug),
                'markAsSeen'      => __('Mark as Seen', $pluginSlug),
                'confirmClearAll' => __('Are you sure you want to clear all error sessions? This cannot be undone.', $pluginSlug),
                'clearFailed'     => __('Failed to clear errors.', $pluginSlug),
                'copied'          => __('Copied!', $pluginSlug),
                'confirmClearLog' => __('Are you sure you want to clear this log file?', $pluginSlug),
                'noStackTrace'    => __('No stack trace available.', $pluginSlug),
                'noContextData'   => __('No context data', $pluginSlug),
            ),
        ));
    }

    /** Enqueue Settings page assets. */
    private function enqueueSettingsAssets(string $pluginFile, string $version, string $pluginSlug): void {
        wp_enqueue_style('riseup-admin-settings', plugins_url('assets/css/admin-settings.css', $pluginFile), array(), $version);
        wp_enqueue_script('riseup-admin-settings', plugins_url('assets/js/admin-settings.js', $pluginFile), array('jquery'), $version, true);

        wp_localize_script('riseup-admin-settings', 'RiseupSettings', array(
            'nonce'         => wp_create_nonce(NonceType::Admin->value),
            'updateActions' => array(
                'testConnection' => AjaxActionType::TestUpdateConnection->value,
                'clearCache'     => AjaxActionType::ClearUpdateCache->value,
                'checkUpdates'   => AjaxActionType::CheckForUpdates->value,
            ),
            'snapFrequency' => array(
                'manual'  => SnapshotFrequencyType::Manual->value,
                'hourly'  => SnapshotFrequencyType::Hourly->value,
                'daily'   => SnapshotFrequencyType::Daily->value,
                'weekly'  => SnapshotFrequencyType::Weekly->value,
                'monthly' => SnapshotFrequencyType::Monthly->value,
            ),
            'snapRetention' => array(
                'none'  => RetentionType::None->value,
                'days'  => RetentionType::Days->value,
                'count' => RetentionType::Count->value,
            ),
            'snapStorage'   => array(
                'single'   => StorageModeType::Single->value,
                'perTable' => StorageModeType::PerTable->value,
            ),
            'snapActions'   => array(
                'storageStats' => AjaxActionType::GetSnapshotStorageStats->value,
                'saveSettings' => AjaxActionType::SaveSnapshotSettings->value,
                'runCleanup'   => AjaxActionType::RunSnapshotCleanup->value,
            ),
        ));
    }

    /** Enqueue Agent Sites page assets. */
    private function enqueueAgentsAssets(string $pluginFile, string $version, string $pluginSlug): void {
        wp_enqueue_style('riseup-admin-shared', plugins_url('assets/css/admin-shared.css', $pluginFile), array(), $version);
        wp_enqueue_style('riseup-admin-agents', plugins_url('assets/css/admin-agents.css', $pluginFile), array('riseup-admin-shared'), $version);
        wp_enqueue_script('riseup-admin-agents', plugins_url('assets/js/admin-agents.js', $pluginFile), array('jquery'), $version, true);

        wp_localize_script('riseup-admin-agents', 'RiseupAgents', array(
            'apiBase'       => esc_url(rest_url(PluginConfigType::apiFullNamespace())),
            'nonce'         => wp_create_nonce(NonceType::WpRest->value),
            'endpoints'     => array(
                'agents'        => EndpointType::Agents->value,
                'agentsAdd'     => EndpointType::AgentsAdd->value,
                'agentsRemove'  => EndpointType::AgentsRemove->value,
                'agentsTest'    => EndpointType::AgentsTest->value,
                'agentsSync'    => EndpointType::AgentsSync->value,
                'agentsPlugins' => EndpointType::AgentsPlugins->value,
                'agentAction'   => EndpointType::AgentAction->value,
                'agentHistory'  => EndpointType::AgentHistory->value,
            ),
            'agentStatus'   => array(
                'pending'   => AgentStatusType::Pending->value,
                'connected' => AgentStatusType::Connected->value,
                'error'     => AgentStatusType::Error->value,
            ),
            'status'        => array(
                'success' => StatusType::Success->value,
            ),
            'responseKeys'  => array(
                'agents'  => ResponseKeyType::Agents->value,
                'actions' => ResponseKeyType::Actions->value,
                'plugins' => ResponseKeyType::Plugins->value,
                'count'   => ResponseKeyType::Count->value,
                'message' => ResponseKeyType::Message->value,
                'success' => ResponseKeyType::Success->value,
                'error'   => ResponseKeyType::Error->value,
            ),
            'pluginStatus'  => array(
                'active' => __('active', $pluginSlug),
            ),
            'pluginActions' => array(
                'enable'  => strtolower(ActionType::Enable->value),
                'disable' => strtolower(ActionType::Disable->value),
                'delete_' => strtolower(ActionType::Delete->value),
            ),
            'i18n'          => array(
                'active'              => __('Active', $pluginSlug),
                'inactive'            => __('Inactive', $pluginSlug),
                'enable'              => __('Enable', $pluginSlug),
                'disable'             => __('Disable', $pluginSlug),
                'deleteBtn'           => __('Delete', $pluginSlug),
                'noPluginsFound'      => __('No plugins found', $pluginSlug),
                'failedLoadPlugins'   => __('Failed to load plugins', $pluginSlug),
                'confirmDeletePlugin' => __('Are you sure you want to delete this plugin from the remote site?', $pluginSlug),
                'noActionHistory'     => __('No action history', $pluginSlug),
                'failedLoadHistory'   => __('Failed to load history', $pluginSlug),
                'confirmRemoveAgent'  => __('Remove agent site "%s"? This cannot be undone.', $pluginSlug),
                'connectionSuccess'   => __('Connection successful!', $pluginSlug),
                'connectionFailed'    => __('Connection failed:', $pluginSlug),
                'testFailed'          => __('Test failed:', $pluginSlug),
                'synced'              => __('Synced %d plugins', $pluginSlug),
                'syncFailed'          => __('Sync failed:', $pluginSlug),
                'actionFailed'        => __('Action failed:', $pluginSlug),
                'failedToRemove'      => __('Failed to remove:', $pluginSlug),
                'failedToLoadAgents'  => __('Failed to load agents:', $pluginSlug),
                'failedToAddAgent'    => __('Failed to add agent', $pluginSlug),
                'unknownError'        => __('Unknown error', $pluginSlug),
                'pluginsSuffix'       => __('Plugins', $pluginSlug),
                'historySuffix'       => __('Action History', $pluginSlug),
                'noAgentsYet'         => __('No agent sites registered yet.', $pluginSlug),
            ),
        ));
    }

    /** Enqueue Snapshots page assets. */
    private function enqueueSnapshotsAssets(string $pluginFile, string $version, string $pluginSlug): void {
        wp_enqueue_style('riseup-admin-shared', plugins_url('assets/css/admin-shared.css', $pluginFile), array(), $version);
        wp_enqueue_style('riseup-admin-snapshots', plugins_url('assets/css/admin-snapshots.css', $pluginFile), array('riseup-admin-shared'), $version);
        wp_enqueue_script('riseup-admin-snapshots', plugins_url('assets/js/admin-snapshots.js', $pluginFile), array('jquery'), $version, true);

        wp_localize_script('riseup-admin-snapshots', 'RiseupSnapshots', array(
            'nonce'           => wp_create_nonce(NonceType::Admin->value),
            'restNonce'       => wp_create_nonce(NonceType::WpRest->value),
            'restBase'        => esc_url(rest_url(PluginConfigType::apiFullNamespace())),
            'logsPageUrl'     => AdminPageType::Logs->adminUrl(),
            'paginationLimit' => PaginationConfigType::DefaultLimit->value,
            'status'          => array(
                'complete'  => SnapshotStatusType::Complete->value,
                'running'   => SnapshotStatusType::Running->value,
                'failed'    => SnapshotStatusType::Failed->value,
                'pending'   => SnapshotStatusType::Pending->value,
                'scheduled' => SnapshotStatusType::Scheduled->value,
            ),
            'mode'            => array(
                'full'        => SnapshotModeType::Full->value,
                'incremental' => SnapshotModeType::Incremental->value,
            ),
            'scope'           => array(
                'all'       => SnapshotScopeType::All->value,
                'wordpress' => SnapshotScopeType::WordPress->value,
                'content'   => SnapshotScopeType::Content->value,
                'custom'    => SnapshotScopeType::Custom->value,
            ),
            'frequency'       => array(
                'manual'  => SnapshotFrequencyType::Manual->value,
                'hourly'  => SnapshotFrequencyType::Hourly->value,
                'daily'   => SnapshotFrequencyType::Daily->value,
                'weekly'  => SnapshotFrequencyType::Weekly->value,
                'monthly' => SnapshotFrequencyType::Monthly->value,
            ),
            'endpoints'       => array(
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
            ),
            'responseKeys'    => array(
                'snapshots' => ResponseKeyType::Snapshots->value,
                'total'     => ResponseKeyType::Total->value,
                'jobId'     => ResponseKeyType::JobId->value,
                'message'   => ResponseKeyType::Message->value,
                'success'   => ResponseKeyType::Success->value,
            ),
            'retention'       => array(
                'none'  => RetentionType::None->value,
                'days'  => RetentionType::Days->value,
                'count' => RetentionType::Count->value,
            ),
            'actions'         => array(
                'saveSettings' => AjaxActionType::SaveSnapshotSettings->value,
            ),
            'monthNames'      => array(
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
            ),
            'i18n'            => array(
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
            ),
        ));
    }

    /** Enqueue License page assets. */
    private function enqueueLicenseAssets(string $pluginFile, string $version, string $pluginSlug): void {
        wp_enqueue_style('riseup-admin-license', plugins_url('assets/css/admin-license.css', $pluginFile), array(), $version);
        wp_enqueue_script('riseup-admin-license', plugins_url('assets/js/admin-license.js', $pluginFile), array('jquery'), $version, true);

        wp_localize_script('riseup-admin-license', 'RiseupLicense', array(
            'nonce'   => wp_create_nonce(NonceType::License->value),
            'actions' => array(
                'save'       => AjaxActionType::LicenseSave->value,
                'activate'   => AjaxActionType::LicenseActivate->value,
                'deactivate' => AjaxActionType::LicenseDeactivate->value,
                'remove'     => AjaxActionType::LicenseRemove->value,
                'refresh'    => AjaxActionType::LicenseRefresh->value,
            ),
            'i18n'    => array(
                'enterKey'           => __('Please enter a license key.', $pluginSlug),
                'validationFailed'   => __('Validation failed.', $pluginSlug),
                'requestFailed'      => __('Request failed.', $pluginSlug),
                'activationFailed'   => __('Activation failed.', $pluginSlug),
                'confirmDeactivate'  => __('Are you sure you want to deactivate this license?', $pluginSlug),
                'deactivationFailed' => __('Deactivation failed.', $pluginSlug),
                'confirmRemove'      => __('Remove the license key entirely? This cannot be undone.', $pluginSlug),
                'removalFailed'      => __('Removal failed.', $pluginSlug),
                'refreshFailed'      => __('Refresh failed.', $pluginSlug),
            ),
        ));
    }

    /** Enqueue Activity Logs page assets. */
    private function enqueueLogsAssets(string $pluginFile, string $version, string $pluginSlug): void {
        wp_enqueue_style('riseup-admin-logs', plugins_url('assets/css/admin-logs.css', $pluginFile), array(), $version);
        wp_enqueue_script('riseup-admin-logs', plugins_url('assets/js/admin-logs.js', $pluginFile), array('jquery'), $version, true);
    }
}
