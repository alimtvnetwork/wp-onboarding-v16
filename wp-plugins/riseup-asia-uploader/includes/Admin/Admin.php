<?php
/**
 * Riseup Asia Uploader - Admin Pages
 *
 * WordPress admin menu pages for logs viewer and settings.
 *
 * @package RiseupAsia\Admin
 * @since   1.5.0
 */

namespace RiseupAsia\Admin;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Admin\Traits\AdminMenuSettingsTrait;
use RiseupAsia\Admin\Traits\AdminNoticesTrait;
use RiseupAsia\Admin\Traits\AdminPagesTrait;
use RiseupAsia\Admin\Traits\AdminAjaxTrait;
use RiseupAsia\Admin\Traits\AdminErrorPageTrait;
use RiseupAsia\Admin\Traits\AdminErrorAjaxTrait;
use RiseupAsia\Admin\Traits\AdminFeedbackAjaxTrait;
use RiseupAsia\Admin\Traits\AdminLicensePageTrait;
use RiseupAsia\Admin\Traits\AdminLicenseAjaxTrait;
use RiseupAsia\Enums\AjaxActionType;
use RiseupAsia\Enums\CapabilityType;
use RiseupAsia\Enums\HookType;
use RiseupAsia\Enums\PaginationConfigType;
use RiseupAsia\Helpers\InitHelpers;
use RiseupAsia\Enums\PhpNativeType;

/**
 * Class Admin
 *
 * Handles admin menu pages and settings.
 */
class Admin {
    use AdminMenuSettingsTrait;
    use AdminNoticesTrait;
    use AdminPagesTrait;
    use AdminAjaxTrait;
    use AdminErrorPageTrait;
    use AdminErrorAjaxTrait;
    use AdminFeedbackAjaxTrait;
    use AdminLicensePageTrait;
    use AdminLicenseAjaxTrait;


    /**
     * Default settings.
     */
    private static $defaults = [
        'endpoints' => [
            'status'         => ['enabled' => true, 'auth_required' => true],
            'upload'         => ['enabled' => true, 'auth_required' => true],
            'plugins'        => ['enabled' => true, 'auth_required' => true],
            'plugin_files'   => ['enabled' => true, 'auth_required' => true],
            'plugin_file'    => ['enabled' => true, 'auth_required' => true],
            'export_self'    => ['enabled' => true, 'auth_required' => true],
            'posts'          => ['enabled' => true, 'auth_required' => true],
            'categories'     => ['enabled' => true, 'auth_required' => true],
            'logs'           => ['enabled' => true, 'auth_required' => true],
            'logs_stats'     => ['enabled' => true, 'auth_required' => true],
            'logs_status'    => ['enabled' => true, 'auth_required' => true],
            'logs_clear'     => ['enabled' => true, 'auth_required' => true],
            'logs_confirm'   => ['enabled' => true, 'auth_required' => true],
            'logs_email'     => ['enabled' => true, 'auth_required' => true],
            'error_logs'     => ['enabled' => true, 'auth_required' => true],
            'error_sessions' => ['enabled' => true, 'auth_required' => true],
            'openapi'        => ['enabled' => true, 'auth_required' => true],
            'snapshots'      => ['enabled' => true, 'auth_required' => true],
        ],
        'log_retrieval' => [
            'include_error_log'  => true,
            'include_full_log'   => false,
            'include_stacktrace' => true,
            'max_lines'          => 500,
        ],
    ];

    /** @var Admin|null */
    private static $instance = null;

    /** Get singleton instance. */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Get plugin settings merged with defaults.
     *
     * @return array<string, mixed>
     */
    public static function get_settings(): array {
        $saved = get_option('riseup_asia_uploader_settings', []);

        return array_replace_recursive(self::$defaults, gettype($saved) === PhpNativeType::PhpArray->value ? $saved : []);
    }

    /** Constructor. */
    private function __construct() {
        InitHelpers::errorLogWithPrefix('Admin::__construct() — registering admin hooks');
        $this->registerBootNotices();
        add_action(HookType::AdminMenu->value, [$this, 'addAdminMenu']);
        add_action(HookType::AdminInit->value, [$this, 'registerSettings']);
        add_action(HookType::AdminEnqueue->value, [$this, 'enqueueAdminAssets']);
        add_action(HookType::AdminNotices->value, [$this, 'renderGlobalErrorNotice']);
        add_action(HookType::ajax(AjaxActionType::TestUpdateConnection->value), [$this, 'ajaxTestUpdateConnection']);
        add_action(HookType::ajax(AjaxActionType::ClearUpdateCache->value), [$this, 'ajaxClearUpdateCache']);
        add_action(HookType::ajax(AjaxActionType::CheckForUpdates->value), [$this, 'ajaxCheckForUpdates']);
        add_action(HookType::ajax(AjaxActionType::SaveSnapshotSettings->value), [$this, 'ajaxSaveSnapshotSettings']);
        add_action(HookType::ajax(AjaxActionType::RunSnapshotCleanup->value), [$this, 'ajaxRunSnapshotCleanup']);
        add_action(HookType::ajax(AjaxActionType::GetSnapshotStorageStats->value), [$this, 'ajaxGetSnapshotStorageStats']);
        add_action(HookType::ajax(AjaxActionType::DismissErrorFlash->value), [$this, 'ajaxDismissErrorFlash']);
        add_action(HookType::ajax(AjaxActionType::ClearErrorSessions->value), [$this, 'ajaxClearErrorSessions']);
        add_action(HookType::ajax(AjaxActionType::ReadLogFile->value), [$this, 'ajaxReadLogFile']);
        add_action(HookType::ajax(AjaxActionType::ClearLogFile->value), [$this, 'ajaxClearLogFile']);
        add_action(HookType::ajax(AjaxActionType::ClearAllLogs->value), [$this, 'ajaxClearAllLogs']);
        add_action(HookType::ajax(AjaxActionType::LicenseSave->value), [$this, 'ajaxLicenseSave']);
        add_action(HookType::ajax(AjaxActionType::LicenseActivate->value), [$this, 'ajaxLicenseActivate']);
        add_action(HookType::ajax(AjaxActionType::LicenseDeactivate->value), [$this, 'ajaxLicenseDeactivate']);
        add_action(HookType::ajax(AjaxActionType::LicenseRemove->value), [$this, 'ajaxLicenseRemove']);
        add_action(HookType::ajax(AjaxActionType::LicenseRefresh->value), [$this, 'ajaxLicenseRefresh']);
        add_action(HookType::ajax(AjaxActionType::SendFeedback->value), [$this, 'ajaxSendFeedback']);
        add_action(HookType::ajax(AjaxActionType::CheckFeedbackReady->value), [$this, 'ajaxCheckFeedbackReady']);
    }
}
