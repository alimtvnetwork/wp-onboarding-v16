<?php
/**
 * Admin UI class.
 *
 * @package PluginsOnboard
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class OnboardAdminUI
 *
 * Handles WordPress admin panel interface.
 */
class OnboardAdminUI {

    /**
     * Database instance.
     *
     * @var OnboardDatabase
     */
    private $db;

    /**
     * OAuth instance.
     *
     * @var OnboardOAuth
     */
    private $oauth;

    /**
     * IP whitelist instance.
     *
     * @var OnboardIPWhitelist
     */
    private $ip_whitelist;

    /**
     * Snapshot instance.
     *
     * @var OnboardSnapshot
     */
    private $snapshot;

    /**
     * Backup manager instance.
     *
     * @var OnboardBackupManager
     */
    private $backup_manager;

    /**
     * Plugin manager instance.
     *
     * @var OnboardPluginManager
     */
    private $plugin_manager;

    /**
     * Audit logger instance.
     *
     * @var OnboardAuditLogger
     */
    private $audit_logger;

    /**
     * Cleanup instance.
     *
     * @var OnboardCleanup
     */
    private $cleanup;

    /**
     * Constructor.
     */
    public function __construct(
        OnboardDatabase $db,
        OnboardOAuth $oauth,
        OnboardIPWhitelist $ip_whitelist,
        OnboardSnapshot $snapshot,
        OnboardBackupManager $backup_manager,
        OnboardPluginManager $plugin_manager,
        OnboardAuditLogger $audit_logger,
        OnboardCleanup $cleanup
    ) {
        $this->db = $db;
        $this->oauth = $oauth;
        $this->ip_whitelist = $ip_whitelist;
        $this->snapshot = $snapshot;
        $this->backup_manager = $backup_manager;
        $this->plugin_manager = $plugin_manager;
        $this->audit_logger = $audit_logger;
        $this->cleanup = $cleanup;

        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_init', array($this, 'handle_actions'));
    }

    /**
     * Add admin menu.
     */
    public function add_admin_menu() {
        // Main menu.
        add_menu_page(
            __('Plugins Onboard', 'plugins-onboard'),
            __('Plugins Onboard', 'plugins-onboard'),
            'activate_plugins',
            'plugins-onboard',
            array($this, 'render_dashboard'),
            'dashicons-admin-plugins',
            65
        );

        // Dashboard submenu.
        add_submenu_page(
            'plugins-onboard',
            __('Dashboard', 'plugins-onboard'),
            __('Dashboard', 'plugins-onboard'),
            'activate_plugins',
            'plugins-onboard',
            array($this, 'render_dashboard')
        );

        // Plugins submenu.
        add_submenu_page(
            'plugins-onboard',
            __('Plugins', 'plugins-onboard'),
            __('Plugins', 'plugins-onboard'),
            'activate_plugins',
            'plugins-onboard-plugins',
            array($this, 'render_plugins')
        );

        // Backups submenu.
        add_submenu_page(
            'plugins-onboard',
            __('Backups & Restore', 'plugins-onboard'),
            __('Backups & Restore', 'plugins-onboard'),
            'activate_plugins',
            'plugins-onboard-backups',
            array($this, 'render_backups')
        );

        // Database submenu.
        add_submenu_page(
            'plugins-onboard',
            __('Database', 'plugins-onboard'),
            __('Database', 'plugins-onboard'),
            'manage_options',
            'plugins-onboard-database',
            array($this, 'render_database')
        );

        // Settings submenu.
        add_submenu_page(
            'plugins-onboard',
            __('Settings', 'plugins-onboard'),
            __('Settings', 'plugins-onboard'),
            'manage_options',
            'plugins-onboard-settings',
            array($this, 'render_settings')
        );

        // Applications submenu.
        add_submenu_page(
            'plugins-onboard',
            __('Applications', 'plugins-onboard'),
            __('Applications', 'plugins-onboard'),
            'manage_options',
            'plugins-onboard-applications',
            array($this, 'render_applications')
        );

        // Audit Logs submenu.
        add_submenu_page(
            'plugins-onboard',
            __('Audit Logs', 'plugins-onboard'),
            __('Audit Logs', 'plugins-onboard'),
            'manage_options',
            'plugins-onboard-audit-logs',
            array($this, 'render_audit_logs')
        );

        // Tests submenu.
        add_submenu_page(
            'plugins-onboard',
            __('Test Runner', 'plugins-onboard'),
            __('Test Runner', 'plugins-onboard'),
            'manage_options',
            'plugins-onboard-tests',
            array($this, 'render_tests')
        );

        // Help submenu.
        add_submenu_page(
            'plugins-onboard',
            __('Help & Docs', 'plugins-onboard'),
            __('Help & Docs', 'plugins-onboard'),
            'activate_plugins',
            'plugins-onboard-help',
            array($this, 'render_help')
        );
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_assets($hook) {
        $isOtherPage = strpos($hook, 'plugins-onboard') === false;

        if ($isOtherPage) {
            return;
        }

        wp_enqueue_style(
            'plugins-onboard-admin',
            ONBOARD_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            ONBOARD_PLUGIN_VERSION
        );

        wp_enqueue_script(
            'plugins-onboard-admin',
            ONBOARD_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            ONBOARD_PLUGIN_VERSION,
            true
        );

        wp_localize_script('plugins-onboard-admin', 'onboardAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('onboard_admin'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this?', 'plugins-onboard'),
                'confirm_restore' => __('Are you sure you want to restore this backup?', 'plugins-onboard'),
                'confirm_clear_logs' => __('Are you sure you want to clear all audit logs?', 'plugins-onboard'),
            ),
        ));
    }

    /**
     * Handle admin actions.
     */
    public function handle_actions() {
        $isPageMissing = !isset($_GET['page']);
        $isOtherPage = !$isPageMissing && strpos($_GET['page'], 'plugins-onboard') === false;
        $isNotOnboardPage = $isPageMissing || $isOtherPage;

        if ($isNotOnboardPage) {
            return;
        }

        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
        $nonce = isset($_GET['_wpnonce']) ? $_GET['_wpnonce'] : '';

        switch ($action) {
            case 'approve_ip':
                $this->handle_approve_ip();
                break;
            case 'reject_ip':
                $this->handle_reject_ip();
                break;
            case 'delete_app':
                $this->handle_delete_app();
                break;
            case 'delete_snapshot':
                $this->handle_delete_snapshot();
                break;
            case 'restore_snapshot':
                $this->handle_restore_snapshot();
                break;
            case 'clear_logs':
                $this->handle_clear_logs();
                break;
            case 'clear_temp':
                $this->handle_clear_temp();
                break;
            case 'run_cleanup':
                $this->handle_run_cleanup();
                break;
        }

        // Handle POST actions.
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['onboard_action'])) {
            $post_action = sanitize_text_field($_POST['onboard_action']);
            
            switch ($post_action) {
                case 'create_app':
                    $this->handle_create_app();
                    break;
                case 'save_settings':
                    $this->handle_save_settings();
                    break;
            }
        }
    }

    /**
     * Handle IP approval.
     */
    private function handle_approve_ip() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'plugins-onboard'));
        }

        $code = isset($_GET['code']) ? sanitize_text_field($_GET['code']) : '';
        $approval_id = isset($_GET['approval_id']) ? sanitize_text_field($_GET['approval_id']) : '';

        if ($code) {
            $result = $this->ip_whitelist->approve($code);
        } elseif ($approval_id) {
            $result = $this->ip_whitelist->approve_by_id($approval_id);
        } else {
            wp_redirect(admin_url('admin.php?page=plugins-onboard-applications&error=missing_code'));
            exit;
        }

        if (is_wp_error($result)) {
            wp_redirect(admin_url('admin.php?page=plugins-onboard-applications&error=' . $result->get_error_code()));
        } else {
            wp_redirect(admin_url('admin.php?page=plugins-onboard-applications&success=ip_approved'));
        }
        exit;
    }

    /**
     * Handle IP rejection.
     */
    private function handle_reject_ip() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'plugins-onboard'));
        }

        $approval_id = isset($_GET['approval_id']) ? sanitize_text_field($_GET['approval_id']) : '';

        if (!$approval_id) {
            wp_redirect(admin_url('admin.php?page=plugins-onboard-applications&error=missing_id'));
            exit;
        }

        $result = $this->ip_whitelist->reject_by_id($approval_id);

        if (is_wp_error($result)) {
            wp_redirect(admin_url('admin.php?page=plugins-onboard-applications&error=' . $result->get_error_code()));
        } else {
            wp_redirect(admin_url('admin.php?page=plugins-onboard-applications&success=ip_rejected'));
        }
        exit;
    }

    /**
     * Handle application deletion.
     */
    private function handle_delete_app() {
        $isUnauthorized = !current_user_can('manage_options') || !wp_verify_nonce($_GET['_wpnonce'], 'delete_app');

        if ($isUnauthorized) {
            wp_die(__('Unauthorized', 'plugins-onboard'));
        }

        $app_id = isset($_GET['app_id']) ? sanitize_text_field($_GET['app_id']) : '';
        $this->oauth->delete_application($app_id);

        wp_redirect(admin_url('admin.php?page=plugins-onboard-applications&success=app_deleted'));
        exit;
    }

    /**
     * Handle snapshot deletion.
     */
    private function handle_delete_snapshot() {
        $isUnauthorized = !current_user_can('activate_plugins') || !wp_verify_nonce($_GET['_wpnonce'], 'delete_snapshot');

        if ($isUnauthorized) {
            wp_die(__('Unauthorized', 'plugins-onboard'));
        }

        $snapshot_id = isset($_GET['snapshot_id']) ? sanitize_text_field($_GET['snapshot_id']) : '';
        $this->snapshot->delete($snapshot_id);

        wp_redirect(admin_url('admin.php?page=plugins-onboard-backups&success=snapshot_deleted'));
        exit;
    }

    /**
     * Handle snapshot restoration.
     */
    private function handle_restore_snapshot() {
        $isUnauthorized = !current_user_can('activate_plugins') || !wp_verify_nonce($_GET['_wpnonce'], 'restore_snapshot');

        if ($isUnauthorized) {
            wp_die(__('Unauthorized', 'plugins-onboard'));
        }

        $snapshot_id = isset($_GET['snapshot_id']) ? sanitize_text_field($_GET['snapshot_id']) : '';
        $result = $this->snapshot->restore($snapshot_id);

        if (is_wp_error($result)) {
            wp_redirect(admin_url('admin.php?page=plugins-onboard-backups&error=' . $result->get_error_code()));
        } else {
            wp_redirect(admin_url('admin.php?page=plugins-onboard-backups&success=snapshot_restored'));
        }
        exit;
    }

    /**
     * Handle clearing audit logs.
     */
    private function handle_clear_logs() {
        $isUnauthorized = !current_user_can('manage_options') || !wp_verify_nonce($_GET['_wpnonce'], 'clear_logs');

        if ($isUnauthorized) {
            wp_die(__('Unauthorized', 'plugins-onboard'));
        }

        $this->audit_logger->clear_logs();

        wp_redirect(admin_url('admin.php?page=plugins-onboard-audit-logs&success=logs_cleared'));
        exit;
    }

    /**
     * Handle clearing temp files.
     */
    private function handle_clear_temp() {
        $isUnauthorized = !current_user_can('manage_options') || !wp_verify_nonce($_GET['_wpnonce'], 'clear_temp');

        if ($isUnauthorized) {
            wp_die(__('Unauthorized', 'plugins-onboard'));
        }

        $this->cleanup->clear_all_temp_files();

        wp_redirect(admin_url('admin.php?page=plugins-onboard-database&success=temp_cleared'));
        exit;
    }

    /**
     * Handle running cleanup.
     */
    private function handle_run_cleanup() {
        $isUnauthorized = !current_user_can('manage_options') || !wp_verify_nonce($_GET['_wpnonce'], 'run_cleanup');

        if ($isUnauthorized) {
            wp_die(__('Unauthorized', 'plugins-onboard'));
        }

        $this->cleanup->run_all();

        wp_redirect(admin_url('admin.php?page=plugins-onboard-database&success=cleanup_complete'));
        exit;
    }

    /**
     * Handle application creation.
     */
    private function handle_create_app() {
        $isUnauthorized = !current_user_can('manage_options') || !wp_verify_nonce($_POST['_wpnonce'], 'create_app');

        if ($isUnauthorized) {
            wp_die(__('Unauthorized', 'plugins-onboard'));
        }

        $data = array(
            'app_name' => sanitize_text_field($_POST['app_name']),
            'description' => sanitize_textarea_field($_POST['description']),
            'redirect_uri' => esc_url_raw($_POST['redirect_uri']),
        );

        $result = $this->oauth->create_application($data);

        // Store the client_secret temporarily for display.
        set_transient('onboard_new_app_' . $result['app_id'], $result, 300);

        wp_redirect(admin_url('admin.php?page=plugins-onboard-applications&success=app_created&app_id=' . $result['app_id']));
        exit;
    }

    /**
     * Handle settings save.
     */
    private function handle_save_settings() {
        $isUnauthorized = !current_user_can('manage_options') || !wp_verify_nonce($_POST['_wpnonce'], 'save_settings');

        if ($isUnauthorized) {
            wp_die(__('Unauthorized', 'plugins-onboard'));
        }

        $settings = array(
            'admin_email' => sanitize_email($_POST['admin_email']),
            'auto_backup_enabled' => isset($_POST['auto_backup_enabled']),
            'snapshot_retention_count' => absint($_POST['snapshot_retention_count']),
            'audit_log_retention_days' => absint($_POST['audit_log_retention_days']),
            'require_https' => isset($_POST['require_https']),
            'ip_whitelist_enabled' => isset($_POST['ip_whitelist_enabled']),
            'ip_auto_approve' => isset($_POST['ip_auto_approve']),
            'backup_trigger_upload' => isset($_POST['backup_trigger_upload']),
            'backup_trigger_enable' => isset($_POST['backup_trigger_enable']),
            'backup_trigger_disable' => isset($_POST['backup_trigger_disable']),
            'backup_trigger_delete' => isset($_POST['backup_trigger_delete']),
        );

        foreach ($settings as $key => $value) {
            $this->db->save_setting($key, $value);
        }

        wp_redirect(admin_url('admin.php?page=plugins-onboard-settings&success=settings_saved'));
        exit;
    }

    /**
     * Render dashboard page.
     */
    public function render_dashboard() {
        $plugins = $this->plugin_manager->get_all_plugins();
        $active_count = count(array_filter($plugins, function($p) {
            $isActive = $p['is_active'];

            return $isActive;
        }));
        $snapshot_count = $this->snapshot->get_total_count();
        $snapshot_size = $this->snapshot->get_total_size();
        $audit_stats = $this->audit_logger->get_statistics();
        $recent_logs = $this->audit_logger->get_logs(array(), 10);
        $debug_status = OnboardDebugMaintenance::get_debug_status();
        $maintenance_status = OnboardDebugMaintenance::get_maintenance_status();
        $pending_approvals = $this->ip_whitelist->get_pending_approvals();

        include ONBOARD_PLUGIN_DIR . 'admin/views/Dashboard.php';
    }

    /**
     * Render plugins page.
     */
    public function render_plugins() {
        $plugins = $this->plugin_manager->get_all_plugins();
        $uploaded_plugins = $this->plugin_manager->get_uploaded_plugins();
        
        include ONBOARD_PLUGIN_DIR . 'admin/views/Plugins.php';
    }

    /**
     * Render backups page.
     */
    public function render_backups() {
        $plugins_with_snapshots = $this->snapshot->get_plugins_with_snapshots();
        $selected_plugin = isset($_GET['plugin']) ? sanitize_text_field($_GET['plugin']) : '';
        
        if ($selected_plugin) {
            $snapshots = $this->snapshot->get_snapshots($selected_plugin);
        } else {
            $snapshots = $this->snapshot->get_all_snapshots(50);
        }

        include ONBOARD_PLUGIN_DIR . 'admin/views/Backups.php';
    }

    /**
     * Render database page.
     */
    public function render_database() {
        $db_info = $this->backup_manager->get_database_info();
        $temp_info = $this->backup_manager->get_temp_info();
        $cleanup_status = $this->cleanup->get_status();

        include ONBOARD_PLUGIN_DIR . 'admin/views/Database.php';
    }

    /**
     * Render settings page.
     */
    public function render_settings() {
        $settings = $this->db->get_all_settings();

        include ONBOARD_PLUGIN_DIR . 'admin/views/Settings.php';
    }

    /**
     * Render applications page.
     */
    public function render_applications() {
        $applications = $this->oauth->get_all_applications();
        $pending_approvals = $this->ip_whitelist->get_pending_approvals();
        
        // Check for newly created app.
        $new_app = null;
        if (isset($_GET['app_id'])) {
            $new_app = get_transient('onboard_new_app_' . $_GET['app_id']);
            delete_transient('onboard_new_app_' . $_GET['app_id']);
        }

        include ONBOARD_PLUGIN_DIR . 'admin/views/Applications.php';
    }

    /**
     * Render audit logs page.
     */
    public function render_audit_logs() {
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 50;
        $offset = ($page - 1) * $per_page;

        $filters = array(
            'action' => isset($_GET['filter_action']) ? sanitize_text_field($_GET['filter_action']) : '',
            'status' => isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : '',
        );

        $logs = $this->audit_logger->get_logs(array_filter($filters), $per_page, $offset);
        $total = $this->audit_logger->get_log_count(array_filter($filters));
        $total_pages = ceil($total / $per_page);
        $unique_actions = $this->audit_logger->get_unique_actions();

        include ONBOARD_PLUGIN_DIR . 'admin/views/AuditLogs.php';
    }

    /**
     * Render tests page.
     */
    public function render_tests() {
        $test_runner = new OnboardTestRunner();
        
        include ONBOARD_PLUGIN_DIR . 'admin/views/Tests.php';
    }

    /**
     * Render help page.
     */
    public function render_help() {
        include ONBOARD_PLUGIN_DIR . 'admin/views/Help.php';
    }
}
