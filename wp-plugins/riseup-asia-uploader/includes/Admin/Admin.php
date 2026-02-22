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
use RiseupAsia\Enums\AjaxActionType;
use RiseupAsia\Enums\CapabilityType;
use RiseupAsia\Enums\HookType;
use RiseupAsia\Enums\PaginationConfigType;
use RiseupAsia\Helpers\InitHelpers;

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

    /**
     * Default settings.
     */
    private static $defaults = array(
        'endpoints' => array(
            'status'       => array('enabled' => true, 'auth_required' => true),
            'upload'       => array('enabled' => true, 'auth_required' => true),
            'plugins'      => array('enabled' => true, 'auth_required' => true),
            'plugin_files' => array('enabled' => true, 'auth_required' => true),
            'plugin_file'  => array('enabled' => true, 'auth_required' => true),
            'export_self'  => array('enabled' => true, 'auth_required' => true),
            'posts'        => array('enabled' => true, 'auth_required' => true),
            'categories'   => array('enabled' => true, 'auth_required' => true),
            'logs'         => array('enabled' => true, 'auth_required' => true),
            'logs_stats'   => array('enabled' => true, 'auth_required' => true),
            'openapi'      => array('enabled' => true, 'auth_required' => true),
            'error_logs'   => array('enabled' => true, 'auth_required' => true),
            'snapshots'    => array('enabled' => true, 'auth_required' => true),
        ),
        'log_retrieval' => array(
            'include_error_log'  => true,
            'include_full_log'   => false,
            'include_stacktrace' => true,
            'max_lines'          => PaginationConfigType::LogRetrievalMaxLines->value,
        ),
    );

    /** @var Admin|null */
    private static $instance = null;

    /** Get singleton instance. */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /** Constructor. */
    private function __construct() {
        InitHelpers::errorLogWithPrefix('Admin::__construct() — registering admin hooks');
        $this->registerBootNotices();
        add_action(HookType::AdminMenu->value, array($this, 'addAdminMenu'));
        add_action(HookType::AdminInit->value, array($this, 'registerSettings'));
        add_action(HookType::AdminEnqueue->value, array($this, 'enqueueAdminAssets'));
        add_action(HookType::AdminNotices->value, array($this, 'renderGlobalErrorNotice'));
        add_action(HookType::ajax(AjaxActionType::TestUpdateConnection->value), array($this, 'ajaxTestUpdateConnection'));
        add_action(HookType::ajax(AjaxActionType::ClearUpdateCache->value), array($this, 'ajaxClearUpdateCache'));
        add_action(HookType::ajax(AjaxActionType::CheckForUpdates->value), array($this, 'ajaxCheckForUpdates'));
        add_action(HookType::ajax(AjaxActionType::SaveSnapshotSettings->value), array($this, 'ajaxSaveSnapshotSettings'));
        add_action(HookType::ajax(AjaxActionType::RunSnapshotCleanup->value), array($this, 'ajaxRunSnapshotCleanup'));
        add_action(HookType::ajax(AjaxActionType::GetSnapshotStorageStats->value), array($this, 'ajaxGetSnapshotStorageStats'));
        add_action(HookType::ajax(AjaxActionType::DismissErrorFlash->value), array($this, 'ajaxDismissErrorFlash'));
        add_action(HookType::ajax(AjaxActionType::ClearErrorSessions->value), array($this, 'ajaxClearErrorSessions'));
        add_action(HookType::ajax(AjaxActionType::ReadLogFile->value), array($this, 'ajaxReadLogFile'));
        add_action(HookType::ajax(AjaxActionType::ClearLogFile->value), array($this, 'ajaxClearLogFile'));
    }
}
