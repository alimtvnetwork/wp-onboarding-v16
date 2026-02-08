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

/**
 * Class Riseup_Admin
 *
 * Handles admin menu pages and settings.
 */
class Riseup_Admin {

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
        ),
        'log_retrieval' => array(
            'include_error_log' => true,
            'include_full_log'  => false,
            'max_lines'         => 500,
        ),
    );

    /**
     * Singleton instance.
     *
     * @var Riseup_Admin|null
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return Riseup_Admin
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
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_notices', array($this, 'render_global_error_notice'));
        add_action('wp_ajax_riseup_test_update_connection', array($this, 'ajax_test_update_connection'));
        add_action('wp_ajax_riseup_clear_update_cache', array($this, 'ajax_clear_update_cache'));
        add_action('wp_ajax_riseup_check_for_updates', array($this, 'ajax_check_for_updates'));
        add_action('wp_ajax_riseup_save_snapshot_settings', array($this, 'ajax_save_snapshot_settings'));
        add_action('wp_ajax_riseup_run_snapshot_cleanup', array($this, 'ajax_run_snapshot_cleanup'));
        add_action('wp_ajax_riseup_get_snapshot_storage_stats', array($this, 'ajax_get_snapshot_storage_stats'));
        add_action('wp_ajax_riseup_dismiss_error_flash', array($this, 'ajax_dismiss_error_flash'));
        add_action('wp_ajax_riseup_clear_error_sessions', array($this, 'ajax_clear_error_sessions'));
    }

    /**
     * Add admin menu items.
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('Riseup Asia Uploader', 'riseup-asia-uploader'),
            __('Riseup Uploader', 'riseup-asia-uploader'),
            'manage_options',
            'riseup-asia-uploader',
            array($this, 'render_logs_page'),
            'dashicons-upload',
            80
        );

        // Logs submenu (same as main page)
        add_submenu_page(
            'riseup-asia-uploader',
            __('Activity Logs', 'riseup-asia-uploader'),
            __('Activity Logs', 'riseup-asia-uploader'),
            'manage_options',
            'riseup-asia-uploader',
            array($this, 'render_logs_page')
        );

        // Settings submenu
        add_submenu_page(
            'riseup-asia-uploader',
            __('Settings', 'riseup-asia-uploader'),
            __('Settings', 'riseup-asia-uploader'),
            'manage_options',
            'riseup-asia-settings',
            array($this, 'render_settings_page')
        );

        // Agent Sites submenu
        add_submenu_page(
            'riseup-asia-uploader',
            __('Agent Sites', 'riseup-asia-uploader'),
            __('Agent Sites', 'riseup-asia-uploader'),
            'manage_options',
            'riseup-asia-agents',
            array($this, 'render_agents_page')
        );

        // Snapshots submenu
        add_submenu_page(
            'riseup-asia-uploader',
            __('Snapshots', 'riseup-asia-uploader'),
            __('Snapshots', 'riseup-asia-uploader'),
            'manage_options',
            'riseup-asia-snapshots',
            array($this, 'render_snapshots_page')
        );

        // Error Log submenu with notification bubble
        $error_bubble = '';
        $unseen = $this->get_unseen_error_count();
        if ($unseen > 0) {
            $error_bubble = sprintf(' <span class="riseup-error-bubble">%d</span>', $unseen);
        }
        add_submenu_page(
            'riseup-asia-uploader',
            __('Error Log', 'riseup-asia-uploader'),
            __('Error Log', 'riseup-asia-uploader') . $error_bubble,
            'manage_options',
            'riseup-asia-errors',
            array($this, 'render_errors_page')
        );
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook Current admin page.
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our pages
        if (strpos($hook, 'riseup-asia') === false) {
            return;
        }

        wp_enqueue_style(
            'riseup-admin-styles',
            plugins_url('assets/admin.css', dirname(__FILE__)),
            array(),
            RISEUP_VERSION
        );
    }

    /**
     * Register settings.
     */
    public function register_settings() {
        register_setting(
            'riseup_asia_settings_group',
            self::OPTION_NAME,
            array($this, 'sanitize_settings')
        );
        
        // Register auto-update settings
        register_setting(
            'riseup_asia_settings_group',
            Riseup_Update_Resolver::OPTION_NAME,
            array($this, 'sanitize_update_settings')
        );
    }

    /**
     * Sanitize settings on save.
     *
     * @param array $input Raw input.
     * @return array Sanitized settings.
     */
    public function sanitize_settings($input) {
        $sanitized = self::$defaults;

        if (!empty($input['endpoints']) && is_array($input['endpoints'])) {
            foreach ($input['endpoints'] as $endpoint => $config) {
                if (isset($sanitized['endpoints'][$endpoint])) {
                    $sanitized['endpoints'][$endpoint]['enabled'] = !empty($config['enabled']);
                    $sanitized['endpoints'][$endpoint]['auth_required'] = !empty($config['auth_required']);
                }
            }
        }

        // Log retrieval settings
        if (isset($input['log_retrieval']) && is_array($input['log_retrieval'])) {
            $sanitized['log_retrieval']['include_error_log'] = !empty($input['log_retrieval']['include_error_log']);
            $sanitized['log_retrieval']['include_full_log']  = !empty($input['log_retrieval']['include_full_log']);
            $sanitized['log_retrieval']['max_lines'] = isset($input['log_retrieval']['max_lines'])
                ? max(50, min(5000, (int) $input['log_retrieval']['max_lines']))
                : 500;
        }

        return $sanitized;
    }

    /**
     * Sanitize auto-update settings on save.
     *
     * @param array $input Raw input.
     * @return array Sanitized settings.
     */
    public function sanitize_update_settings($input) {
        $current = get_option(Riseup_Update_Resolver::OPTION_NAME, array());
        
        $sanitized = array(
            'enabled'      => !empty($input['enabled']),
            'master_url'   => isset($input['master_url']) ? esc_url_raw($input['master_url']) : '',
            'cache_days'   => isset($input['cache_days']) ? max(1, min(30, (int) $input['cache_days'])) : 7,
            // Preserve these from current settings
            'resolved_url' => isset($current['resolved_url']) ? $current['resolved_url'] : '',
            'resolved_at'  => isset($current['resolved_at']) ? $current['resolved_at'] : '',
            'last_check'   => isset($current['last_check']) ? $current['last_check'] : '',
            'last_error'   => isset($current['last_error']) ? $current['last_error'] : '',
            'package_url'  => isset($current['package_url']) ? $current['package_url'] : '',
            'new_version'  => isset($current['new_version']) ? $current['new_version'] : '',
            'update_info'  => isset($current['update_info']) ? $current['update_info'] : array(),
        );
        
        // If master URL changed, clear cache
        if (isset($current['master_url']) && $current['master_url'] !== $sanitized['master_url']) {
            $sanitized['resolved_url'] = '';
            $sanitized['resolved_at'] = '';
        }
        
        return $sanitized;
    }

    /**
     * Get plugin settings.
     *
     * @return array Settings array.
     */
    public static function get_settings() {
        $settings = get_option(self::OPTION_NAME, array());
        return wp_parse_args($settings, self::$defaults);
    }

    /**
     * Check if an endpoint is enabled.
     *
     * @param string $endpoint Endpoint name.
     * @return bool True if enabled.
     */
    public static function is_endpoint_enabled($endpoint) {
        $settings = self::get_settings();
        return !empty($settings['endpoints'][$endpoint]['enabled']);
    }

    /**
     * Check if an endpoint requires authentication.
     *
     * @param string $endpoint Endpoint name.
     * @return bool True if auth required.
     */
    public static function is_auth_required($endpoint) {
        $settings = self::get_settings();
        return !empty($settings['endpoints'][$endpoint]['auth_required']);
    }

    /**
     * Render the logs page.
     */
    public function render_logs_page() {
        // Get filters from query params
        $filters = array(
            'action'         => isset($_GET['filter_action']) ? sanitize_text_field($_GET['filter_action']) : '',
            'user'           => isset($_GET['filter_user']) ? sanitize_text_field($_GET['filter_user']) : '',
            'status'         => isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : '',
            'plugin'         => isset($_GET['filter_plugin']) ? sanitize_text_field($_GET['filter_plugin']) : '',
            'from'           => isset($_GET['filter_from']) ? sanitize_text_field($_GET['filter_from']) : '',
            'to'             => isset($_GET['filter_to']) ? sanitize_text_field($_GET['filter_to']) : '',
            'triggered_by'   => isset($_GET['filter_triggered_by']) ? sanitize_text_field($_GET['filter_triggered_by']) : '',
            'source_machine' => isset($_GET['filter_source_machine']) ? sanitize_text_field($_GET['filter_source_machine']) : '',
        );

        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 50;
        $offset = ($page - 1) * $per_page;

        // Get logs from database
        $db = Riseup_Database::get_instance();
        $result = $db->query_transactions($filters, $per_page, $offset);
        $logs = $result['logs'];
        $total = $result['total'];
        $total_pages = ceil($total / $per_page);

        // Action labels for display
        $action_labels = array(
            'upload'          => 'Plugin Upload',
            'upload_active'   => 'Upload & Activate',
            'enable'          => 'Plugin Enable',
            'disable'         => 'Plugin Disable',
            'delete'          => 'Plugin Delete',
            'file_replace'    => 'File Replace',
            'file_delete'     => 'File Delete',
            'sync'            => 'Sync Check',
            'post_create'     => 'Post Create',
            'post_update'     => 'Post Update',
            'category_create' => 'Category Create',
            'media_upload'    => 'Media Upload',
            'auth_failed'     => 'Auth Failed',
            'export_self'     => 'Export Self',
        );

        include dirname(__FILE__) . '/../templates/admin-logs.php';
    }

    /**
     * Render the settings page.
     */
    public function render_settings_page() {
        $settings = self::get_settings();
        $update_settings = Riseup_Update_Resolver::get_instance()->get_settings();

        // Snapshot settings
        require_once dirname(__FILE__) . '/class-snapshot-detector.php';
        $detector = new RiseupSnapshotDetector(
            Riseup_File_Logger::get_instance(),
            Riseup_Database::get_instance()
        );
        $snapshot_settings = $detector->getSettings();
        $snapshot_providers = $detector->detectAvailableProviders();

        // Endpoint metadata for display
        $endpoints_meta = array(
            'status'       => array('label' => 'Status Check', 'desc' => 'Returns plugin status and version'),
            'upload'       => array('label' => 'Plugin Upload', 'desc' => 'Upload and install plugins'),
            'plugins'      => array('label' => 'List Plugins', 'desc' => 'List all installed plugins'),
            'plugin_files' => array('label' => 'Plugin Files', 'desc' => 'List files in a plugin'),
            'plugin_file'  => array('label' => 'File Content', 'desc' => 'Get file content from plugin'),
            'export_self'  => array('label' => 'Export Self', 'desc' => 'Export this plugin as ZIP'),
            'posts'        => array('label' => 'Blog Posts', 'desc' => 'Create and manage posts'),
            'categories'   => array('label' => 'Categories', 'desc' => 'Create and manage categories'),
            'logs'         => array('label' => 'Logs API', 'desc' => 'Fetch transaction logs'),
            'logs_stats'   => array('label' => 'Logs Stats', 'desc' => 'Get log statistics'),
            'openapi'      => array('label' => 'OpenAPI Spec', 'desc' => 'API documentation endpoint'),
        );

        include dirname(__FILE__) . '/../templates/admin-settings.php';
    }

    /**
     * AJAX handler: Test update server connection.
     */
    public function ajax_test_update_connection() {
        check_ajax_referer('riseup_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $resolver = Riseup_Update_Resolver::get_instance();
        $result = $resolver->test_connection();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX handler: Clear update URL cache.
     */
    public function ajax_clear_update_cache() {
        check_ajax_referer('riseup_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $resolver = Riseup_Update_Resolver::get_instance();
        $resolver->clear_cache();
        
        wp_send_json_success(array('message' => 'Cache cleared successfully'));
    }

    /**
     * AJAX handler: Check for updates now.
     */
    public function ajax_check_for_updates() {
        check_ajax_referer('riseup_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $resolver = Riseup_Update_Resolver::get_instance();
        $result = $resolver->fetch_update_info(true);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        } else {
            wp_send_json_success(array(
                'message'     => 'Update check complete',
                'update_info' => $result,
            ));
        }
    }

    /**
     * AJAX handler: Save snapshot settings.
     */
    public function ajax_save_snapshot_settings() {
        check_ajax_referer('riseup_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $settings = array();

        // Provider
        if (isset($_POST['preferred_provider'])) {
            $settings['preferred_provider'] = sanitize_text_field($_POST['preferred_provider']);
        }

        // Scheduling
        if (isset($_POST['schedule_enabled'])) {
            $settings['schedule_enabled'] = ($_POST['schedule_enabled'] === '1');
        }
        if (isset($_POST['schedule_frequency'])) {
            $settings['schedule_frequency'] = sanitize_text_field($_POST['schedule_frequency']);
        }
        if (isset($_POST['schedule_time'])) {
            $settings['schedule_time'] = sanitize_text_field($_POST['schedule_time']);
        }
        if (isset($_POST['schedule_day'])) {
            $settings['schedule_day'] = intval($_POST['schedule_day']);
        }

        // Scope
        if (isset($_POST['default_scope'])) {
            $settings['default_scope'] = sanitize_text_field($_POST['default_scope']);
        }

        // Retention
        if (isset($_POST['retention_type'])) {
            $settings['retention_type'] = sanitize_text_field($_POST['retention_type']);
        }
        if (isset($_POST['retention_days'])) {
            $settings['retention_days'] = intval($_POST['retention_days']);
        }
        if (isset($_POST['retention_count'])) {
            $settings['retention_count'] = intval($_POST['retention_count']);
        }

        // Safety
        if (isset($_POST['pre_restore_backup'])) {
            $settings['pre_restore_backup'] = ($_POST['pre_restore_backup'] === '1');
        }

        // Limits
        if (isset($_POST['max_snapshot_size_mb'])) {
            $settings['max_snapshot_size_mb'] = intval($_POST['max_snapshot_size_mb']);
        }
        if (isset($_POST['batch_size'])) {
            $settings['batch_size'] = intval($_POST['batch_size']);
        }

        require_once dirname(__FILE__) . '/class-snapshot-detector.php';
        $detector = new RiseupSnapshotDetector(
            Riseup_File_Logger::get_instance(),
            Riseup_Database::get_instance()
        );
        $result = $detector->updateSettings($settings);

        // Re-sync cron schedule if scheduling changed
        if (isset($settings['schedule_enabled']) || isset($settings['schedule_frequency'])) {
            require_once dirname(__FILE__) . '/class-snapshot-scheduler.php';
            $scheduler = RiseupSnapshotScheduler::getInstance(
                Riseup_File_Logger::get_instance(),
                Riseup_Database::get_instance()
            );
            $scheduler->syncScheduleWithSettings();
        }

        if ($result) {
            wp_send_json_success(array('message' => 'Snapshot settings saved'));
        } else {
            wp_send_json_success(array('message' => 'Settings unchanged'));
        }
    }

    /**
     * AJAX handler: Run manual snapshot cleanup.
     */
    public function ajax_run_snapshot_cleanup() {
        check_ajax_referer('riseup_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        require_once dirname(__FILE__) . '/class-snapshot-scheduler.php';
        $scheduler = RiseupSnapshotScheduler::getInstance(
            Riseup_File_Logger::get_instance(),
            Riseup_Database::get_instance()
        );
        $result = $scheduler->runManualCleanup();

        wp_send_json_success(array(
            'message' => sprintf(
                'Cleanup complete: %d by policy, %d orphans, %d failed removed. Freed %s.',
                $result['deleted_by_policy'],
                $result['deleted_orphans'],
                $result['deleted_failed'],
                RiseupPathUtils::formatBytes($result['space_freed_bytes'])
            ),
            'result' => $result,
        ));
    }

    /**
     * AJAX handler: Get snapshot storage stats.
     */
    public function ajax_get_snapshot_storage_stats() {
        check_ajax_referer('riseup_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        require_once dirname(__FILE__) . '/class-snapshot-scheduler.php';
        $scheduler = RiseupSnapshotScheduler::getInstance(
            Riseup_File_Logger::get_instance(),
            Riseup_Database::get_instance()
        );
        $stats = $scheduler->getStorageStats();

        wp_send_json_success($stats);
    }

    /**
     * Render the agent sites page.
     */
    public function render_agents_page() {
        include dirname(__FILE__) . '/../templates/admin-agents.php';
    }

    /**
     * Render the snapshots page.
     */
    public function render_snapshots_page() {
        include dirname(__FILE__) . '/../templates/admin-snapshots.php';
    }

    /**
     * Render the error log page.
     */
    public function render_errors_page() {
        $db = Riseup_Database::get_instance();
        $pdo = $db->get_pdo();

        // Filters
        $filter_level  = isset($_GET['filter_level']) ? sanitize_text_field($_GET['filter_level']) : '';
        $filter_search = isset($_GET['filter_search']) ? sanitize_text_field($_GET['filter_search']) : '';
        $page          = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page      = 50;
        $offset        = ($page - 1) * $per_page;

        // Build query
        $where = array();
        $params = array();

        if (!empty($filter_level)) {
            $where[] = 'level = ?';
            $params[] = $filter_level;
        }
        if (!empty($filter_search)) {
            $where[] = 'message LIKE ?';
            $params[] = '%' . $filter_search . '%';
        }

        $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count
        $count_sql = "SELECT COUNT(*) FROM error_sessions {$where_sql}";
        $stmt = $pdo->prepare($count_sql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();
        $total_pages = max(1, ceil($total / $per_page));

        // Fetch
        $query_sql = "SELECT * FROM error_sessions {$where_sql} ORDER BY id DESC LIMIT ? OFFSET ?";
        $stmt = $pdo->prepare($query_sql);
        $query_params = array_merge($params, array($per_page, $offset));
        $stmt->execute($query_params);
        $errors = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Flash state
        $last_seen_id = $this->get_flash_value('last_seen_error_id', 0);
        $has_unseen   = ($this->get_flash_value('has_unseen_errors', '0') === '1');
        $unseen_count = $this->get_unseen_error_count();
        $latest_error_time = '';
        if (!empty($errors) && $has_unseen) {
            $latest_error_time = date('Y-m-d H:i:s', strtotime($errors[0]['created_at']));
        }

        include dirname(__FILE__) . '/../templates/admin-errors.php';
    }

    /**
     * Get unseen error count.
     *
     * @return int
     */
    private function get_unseen_error_count() {
        try {
            $db = Riseup_Database::get_instance();
            $pdo = $db->get_pdo();
            if (!$pdo) {
                return 0;
            }
            $last_seen = $this->get_flash_value('last_seen_error_id', 0);
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM error_sessions WHERE id > ?');
            $stmt->execute(array($last_seen));
            return (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * Get a flash state value.
     *
     * @param string $key     Flash key.
     * @param mixed  $default Default value.
     * @return string
     */
    private function get_flash_value($key, $default = '') {
        try {
            $db = Riseup_Database::get_instance();
            $pdo = $db->get_pdo();
            if (!$pdo) {
                return $default;
            }
            $stmt = $pdo->prepare('SELECT value FROM flash_state WHERE key = ?');
            $stmt->execute(array($key));
            $val = $stmt->fetchColumn();
            return ($val !== false) ? $val : $default;
        } catch (Throwable $e) {
            return $default;
        }
    }

    /**
     * Render global admin notice when there are unseen errors.
     * Shows on ALL admin pages, not just the plugin pages.
     */
    public function render_global_error_notice() {
        $unseen = $this->get_unseen_error_count();
        if ($unseen <= 0) {
            return;
        }

        // Don't show on our own error page (we have the flash banner there)
        $current_page = isset($_GET['page']) ? $_GET['page'] : '';
        if ($current_page === 'riseup-asia-errors') {
            return;
        }

        $url = admin_url('admin.php?page=riseup-asia-errors');
        printf(
            '<div class="notice notice-error is-dismissible" style="border-left-color: #dc3545;">
                <p><strong>⚠️ Riseup Asia Uploader:</strong> %s <a href="%s" style="font-weight:600;">%s →</a></p>
            </div>',
            esc_html(sprintf(
                _n('%d new error detected.', '%d new errors detected.', $unseen, 'riseup-asia-uploader'),
                $unseen
            )),
            esc_url($url),
            esc_html__('View Error Log', 'riseup-asia-uploader')
        );
    }

    /**
     * AJAX handler: Dismiss error flash (mark all as seen).
     */
    public function ajax_dismiss_error_flash() {
        check_ajax_referer('riseup_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $db = Riseup_Database::get_instance();
        $pdo = $db->get_pdo();

        // Get max error ID
        $stmt = $pdo->query('SELECT MAX(id) FROM error_sessions');
        $max_id = (int) $stmt->fetchColumn();
        $now = gmdate('Y-m-d\TH:i:s\Z');

        $pdo->exec("INSERT OR REPLACE INTO flash_state (key, value, updated_at) VALUES ('last_seen_error_id', '{$max_id}', '{$now}')");
        $pdo->exec("INSERT OR REPLACE INTO flash_state (key, value, updated_at) VALUES ('has_unseen_errors', '0', '{$now}')");

        wp_send_json_success(array('message' => 'All errors marked as seen', 'last_seen_id' => $max_id));
    }

    /**
     * AJAX handler: Clear all error sessions.
     */
    public function ajax_clear_error_sessions() {
        check_ajax_referer('riseup_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $db = Riseup_Database::get_instance();
        $pdo = $db->get_pdo();

        $pdo->exec('DELETE FROM error_sessions');
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $pdo->exec("INSERT OR REPLACE INTO flash_state (key, value, updated_at) VALUES ('last_seen_error_id', '0', '{$now}')");
        $pdo->exec("INSERT OR REPLACE INTO flash_state (key, value, updated_at) VALUES ('has_unseen_errors', '0', '{$now}')");

        wp_send_json_success(array('message' => 'All error sessions cleared'));
    }
}
