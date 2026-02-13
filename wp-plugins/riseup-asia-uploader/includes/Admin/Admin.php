<?php
/**
 * Riseup Asia Uploader - Admin Pages
 *
 * WordPress admin menu pages for logs viewer and settings.
 *
 * @package RiseupAsiaUploader
 * @since   1.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\CapabilityType;
use RiseupAsia\Enums\HookType;

// Load trait files
require_once __DIR__ . '/Traits/AdminMenuSettingsTrait.php';
require_once __DIR__ . '/Traits/AdminPagesTrait.php';
require_once __DIR__ . '/Traits/AdminAjaxTrait.php';
require_once __DIR__ . '/Traits/AdminErrorPageTrait.php';
require_once __DIR__ . '/Traits/AdminErrorAjaxTrait.php';

/**
 * Class RiseupAdmin
 *
 * Handles admin menu pages and settings.
 */
class RiseupAdmin {

    use AdminMenuSettingsTrait;
    use AdminPagesTrait;
    use AdminAjaxTrait;
    use AdminErrorPageTrait;
    use AdminErrorAjaxTrait;

    /**
     * Option name for plugin settings.
     */
    const OPTION_NAME = 'riseup_asia_settings';

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
            'max_lines'          => 500,
        ),
    );

    /**
     * Singleton instance.
     *
     * @var RiseupAdmin|null
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return RiseupAdmin
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        add_action(HookType::AdminMenu->value, array($this, 'add_admin_menu'));
        add_action(HookType::AdminInit->value, array($this, 'register_settings'));
        add_action(HookType::AdminEnqueue->value, array($this, 'enqueue_admin_assets'));
        add_action(HookType::AdminNotices->value, array($this, 'render_global_error_notice'));
        add_action(HookType::ajax('riseup_test_update_connection'), array($this, 'ajax_test_update_connection'));
        add_action(HookType::ajax('riseup_clear_update_cache'), array($this, 'ajax_clear_update_cache'));
        add_action(HookType::ajax('riseup_check_for_updates'), array($this, 'ajax_check_for_updates'));
        add_action(HookType::ajax('riseup_save_snapshot_settings'), array($this, 'ajax_save_snapshot_settings'));
        add_action(HookType::ajax('riseup_run_snapshot_cleanup'), array($this, 'ajax_run_snapshot_cleanup'));
        add_action(HookType::ajax('riseup_get_snapshot_storage_stats'), array($this, 'ajax_get_snapshot_storage_stats'));
        add_action(HookType::ajax('riseup_dismiss_error_flash'), array($this, 'ajax_dismiss_error_flash'));
        add_action(HookType::ajax('riseup_clear_error_sessions'), array($this, 'ajax_clear_error_sessions'));
        add_action(HookType::ajax('riseup_read_log_file'), array($this, 'ajax_read_log_file'));
        add_action(HookType::ajax('riseup_clear_log_file'), array($this, 'ajax_clear_log_file'));
    }
}
