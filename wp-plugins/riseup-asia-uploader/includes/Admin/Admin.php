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

/**
 * Class RiseupAdmin
 *
 * Handles admin menu pages and settings.
 */
class RiseupAdmin {

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

    /**
     * Add admin menu items.
     */
    public function add_admin_menu() {
        $this->registerMainMenu();
        $this->registerSubmenus();
        $this->registerErrorSubmenu();
    }

    /**
     * Register the main admin menu page.
     */
    private function registerMainMenu() {
        add_menu_page(
            __('Riseup Asia Uploader', 'riseup-asia-uploader'),
            __('Riseup Uploader', 'riseup-asia-uploader'),
            CapabilityType::ManageOptions->value,
            'riseup-asia-uploader',
            array($this, 'render_logs_page'),
            'dashicons-upload',
            80
        );
    }

    /**
     * Register standard submenus.
     */
    private function registerSubmenus() {
        $submenus = array(
            array('riseup-asia-uploader', 'Activity Logs', 'render_logs_page'),
            array('riseup-asia-settings', 'Settings', 'render_settings_page'),
            array('riseup-asia-agents', 'Agent Sites', 'render_agents_page'),
            array('riseup-asia-snapshots', 'Snapshots', 'render_snapshots_page'),
        );

        foreach ($submenus as $item) {
            add_submenu_page(
                'riseup-asia-uploader',
                __($item[1], 'riseup-asia-uploader'),
                __($item[1], 'riseup-asia-uploader'),
                CapabilityType::ManageOptions->value,
                $item[0],
                array($this, $item[2])
            );
        }
    }

    /**
     * Register the error log submenu with notification bubble.
     */
    private function registerErrorSubmenu() {
        $error_bubble = $this->buildErrorBubble();

        add_submenu_page(
            'riseup-asia-uploader',
            __('Error Log', 'riseup-asia-uploader'),
            __('Error Log', 'riseup-asia-uploader') . $error_bubble,
            CapabilityType::ManageOptions->value,
            'riseup-asia-errors',
            array($this, 'render_errors_page')
        );
    }

    /**
     * Build the error count bubble HTML.
     *
     * @return string HTML string or empty.
     */
    private function buildErrorBubble(): string {
        $unseen = $this->get_unseen_error_count();
        if ($unseen <= 0) {
            return '';
        }

        return sprintf(' <span class="riseup-error-bubble">%d</span>', $unseen);
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
            PLUGIN_VERSION
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
            RiseupUpdateResolver::OPTION_NAME,
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
            $sanitized['log_retrieval']['include_error_log']  = !empty($input['log_retrieval']['include_error_log']);
            $sanitized['log_retrieval']['include_full_log']   = !empty($input['log_retrieval']['include_full_log']);
            $sanitized['log_retrieval']['include_stacktrace'] = !empty($input['log_retrieval']['include_stacktrace']);
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
        $current = get_option(RiseupUpdateResolver::OPTION_NAME, array());

        $sanitized = $this->buildSanitizedUpdateFields($input, $current);

        // If master URL changed, clear cache
        if (isset($current['master_url']) && $current['master_url'] !== $sanitized['master_url']) {
            $sanitized['resolved_url'] = '';
            $sanitized['resolved_at'] = '';
        }

        return $sanitized;
    }

    /**
     * Build sanitized update settings fields.
     *
     * @param array $input   Raw input.
     * @param array $current Current settings.
     * @return array Sanitized fields.
     */
    private function buildSanitizedUpdateFields(array $input, array $current): array {
        return array(
            'enabled'      => !empty($input['enabled']),
            'master_url'   => isset($input['master_url']) ? esc_url_raw($input['master_url']) : '',
            'cache_days'   => isset($input['cache_days']) ? max(1, min(30, (int) $input['cache_days'])) : 7,
            'resolved_url' => isset($current['resolved_url']) ? $current['resolved_url'] : '',
            'resolved_at'  => isset($current['resolved_at']) ? $current['resolved_at'] : '',
            'last_check'   => isset($current['last_check']) ? $current['last_check'] : '',
            'last_error'   => isset($current['last_error']) ? $current['last_error'] : '',
            'package_url'  => isset($current['package_url']) ? $current['package_url'] : '',
            'new_version'  => isset($current['new_version']) ? $current['new_version'] : '',
            'update_info'  => isset($current['update_info']) ? $current['update_info'] : array(),
        );
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
        $filters = $this->buildLogFilters();
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 50;
        $offset = ($page - 1) * $per_page;

        $db = RiseupDatabase::get_instance();
        $result = $db->query_transactions($filters, $per_page, $offset);
        $logs = $result['logs'];
        $total = $result['total'];
        $total_pages = ceil($total / $per_page);

        $action_labels = $this->getActionLabels();

        include dirname(__FILE__) . '/../templates/admin-logs.php';
    }

    /**
     * Build log filters from query parameters.
     *
     * @return array Filter values.
     */
    private function buildLogFilters(): array {
        $keys = array(
            'action' => 'filter_action', 'user' => 'filter_user', 'status' => 'filter_status',
            'plugin' => 'filter_plugin', 'from' => 'filter_from', 'to' => 'filter_to',
            'triggered_by' => 'filter_triggered_by', 'source_machine' => 'filter_source_machine',
            'upload_source' => 'filter_upload_source',
        );

        $filters = array();
        foreach ($keys as $key => $param) {
            $filters[$key] = isset($_GET[$param]) ? sanitize_text_field($_GET[$param]) : '';
        }

        return $filters;
    }

    /**
     * Get action label map for display.
     *
     * @return array Action labels.
     */
    private function getActionLabels(): array {
        return array(
            'upload_initiated' => 'Upload Initiated',
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
    }

    /**
     * Render the settings page.
     */
    public function render_settings_page() {
        $settings = self::get_settings();
        $update_settings = RiseupUpdateResolver::get_instance()->get_settings();

        require_once dirname(__FILE__) . '/../Snapshot/SnapshotFactory.php';
        $detector = RiseupSnapshotFactory::detector();
        $snapshot_settings = $detector->getSettings();
        $snapshot_providers = $detector->detectAvailableProviders();

        $endpoint_groups = $this->buildEndpointGroups();
        $endpoints_meta = $this->flattenEndpointGroups($endpoint_groups);

        include dirname(__FILE__) . '/../templates/admin-settings.php';
    }

    /**
     * Build endpoint group metadata for display.
     *
     * @return array Endpoint groups.
     */
    private function buildEndpointGroups(): array {
        return array(
            'core' => array(
                'label' => __('Core Operations', 'riseup-asia-uploader'),
                'icon'  => 'dashicons-admin-tools',
                'endpoints' => array(
                    'status'       => array('label' => 'Status Check', 'desc' => 'Returns plugin status and version'),
                    'upload'       => array('label' => 'Plugin Upload', 'desc' => 'Upload and install plugins'),
                    'plugins'      => array('label' => 'List Plugins', 'desc' => 'List all installed plugins'),
                    'plugin_files' => array('label' => 'Plugin Files', 'desc' => 'List files in a plugin'),
                    'plugin_file'  => array('label' => 'File Content', 'desc' => 'Get file content from plugin'),
                    'export_self'  => array('label' => 'Export Self', 'desc' => 'Export this plugin as ZIP'),
                ),
            ),
            'content' => array(
                'label' => __('Content Management', 'riseup-asia-uploader'),
                'icon'  => 'dashicons-edit-page',
                'endpoints' => array(
                    'posts'      => array('label' => 'Blog Posts', 'desc' => 'Create and manage posts'),
                    'categories' => array('label' => 'Categories', 'desc' => 'Create and manage categories'),
                ),
            ),
            'monitoring' => array(
                'label' => __('Monitoring & Logs', 'riseup-asia-uploader'),
                'icon'  => 'dashicons-chart-area',
                'endpoints' => array(
                    'logs'       => array('label' => 'Logs API', 'desc' => 'Fetch transaction logs'),
                    'logs_stats' => array('label' => 'Logs Stats', 'desc' => 'Get log statistics'),
                    'error_logs' => array('label' => 'Error Logs', 'desc' => 'Fetch error log sessions'),
                ),
            ),
            'backup' => array(
                'label' => __('Backups & Snapshots', 'riseup-asia-uploader'),
                'icon'  => 'dashicons-database',
                'endpoints' => array(
                    'snapshots' => array('label' => 'Snapshots', 'desc' => 'Database snapshot operations and scheduling'),
                ),
            ),
            'docs' => array(
                'label' => __('Documentation', 'riseup-asia-uploader'),
                'icon'  => 'dashicons-media-document',
                'endpoints' => array(
                    'openapi' => array('label' => 'OpenAPI Spec', 'desc' => 'API documentation endpoint'),
                ),
            ),
        );
    }

    /**
     * Flatten endpoint groups for backward compatibility.
     *
     * @param array $groups Endpoint groups.
     * @return array Flat endpoint metadata.
     */
    private function flattenEndpointGroups(array $groups): array {
        $endpoints_meta = array();
        foreach ($groups as $group) {
            foreach ($group['endpoints'] as $key => $meta) {
                $endpoints_meta[$key] = $meta;
            }
        }

        return $endpoints_meta;
    }

    /**
     * AJAX handler: Test update server connection.
     */
    public function ajax_test_update_connection() {
        check_ajax_referer('riseup_admin_nonce', 'nonce');
        
        if (!current_user_can(CapabilityType::ManageOptions->value)) {
            wp_send_json_error(array('message' => MSG_UNAUTHORIZED));
        }
        
        $resolver = RiseupUpdateResolver::get_instance();
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
        
        if (!current_user_can(CapabilityType::ManageOptions->value)) {
            wp_send_json_error(array('message' => MSG_UNAUTHORIZED));
        }
        
        $resolver = RiseupUpdateResolver::get_instance();
        $resolver->clear_cache();
        
        wp_send_json_success(array('message' => 'Cache cleared successfully'));
    }

    /**
     * AJAX handler: Check for updates now.
     */
    public function ajax_check_for_updates() {
        check_ajax_referer('riseup_admin_nonce', 'nonce');
        
        if (!current_user_can(CapabilityType::ManageOptions->value)) {
            wp_send_json_error(array('message' => MSG_UNAUTHORIZED));
        }
        
        $resolver = RiseupUpdateResolver::get_instance();
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

        if (!current_user_can(CapabilityType::ManageOptions->value)) {
            wp_send_json_error(array('message' => MSG_UNAUTHORIZED));
        }

        $settings = $this->parseSnapshotSettingsFromPost();
        $this->applySnapshotSettings($settings);
    }

    /**
     * Parse snapshot settings from $_POST data.
     *
     * @return array Parsed settings.
     */
    private function parseSnapshotSettingsFromPost(): array {
        $settings = array();
        $text_fields = array(
            'preferred_provider', 'schedule_frequency', 'schedule_time',
            'default_scope', 'retention_type',
        );

        foreach ($text_fields as $field) {
            if (isset($_POST[$field])) {
                $settings[$field] = sanitize_text_field($_POST[$field]);
            }
        }

        $int_fields = array('schedule_day', 'retention_days', 'retention_count', 'max_snapshot_size_mb', 'batch_size');
        foreach ($int_fields as $field) {
            if (isset($_POST[$field])) {
                $settings[$field] = intval($_POST[$field]);
            }
        }

        $bool_fields = array('schedule_enabled', 'pre_restore_backup');
        foreach ($bool_fields as $field) {
            if (isset($_POST[$field])) {
                $settings[$field] = ($_POST[$field] === '1');
            }
        }

        if (isset($_POST['worker_pool_size'])) {
            $settings['worker_pool_size'] = max(
                SNAPSHOT_WORKER_POOL_MIN,
                min(SNAPSHOT_WORKER_POOL_MAX, intval($_POST['worker_pool_size']))
            );
        }

        if (isset($_POST['storage_mode'])) {
            $mode = sanitize_text_field($_POST['storage_mode']);
            if (in_array($mode, array('single', 'per-table'))) {
                $settings['storage_mode'] = $mode;
            }
        }

        return $settings;
    }

    /**
     * Apply parsed snapshot settings and sync cron.
     *
     * @param array $settings Parsed settings.
     */
    private function applySnapshotSettings(array $settings) {
        require_once dirname(__FILE__) . '/../Snapshot/SnapshotFactory.php';
        $detector = RiseupSnapshotFactory::detector();
        $result = $detector->updateSettings($settings);

        if (isset($settings['schedule_enabled']) || isset($settings['schedule_frequency'])) {
            $scheduler = RiseupSnapshotFactory::scheduler();
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

        if (!current_user_can(CapabilityType::ManageOptions->value)) {
            wp_send_json_error(array('message' => MSG_UNAUTHORIZED));
        }

        require_once dirname(__FILE__) . '/../Snapshot/SnapshotFactory.php';
        $scheduler = RiseupSnapshotFactory::scheduler();
        $result = $scheduler->runManualCleanup();

        wp_send_json_success(array(
            'message' => sprintf(
                'Cleanup complete: %d by policy, %d orphans, %d failed removed. Freed %s.',
                $result['deleted_by_policy'],
                $result['deleted_orphans'],
                $result['deleted_failed'],
                RiseupPathUtils::format_bytes($result['space_freed_bytes'])
            ),
            'result' => $result,
        ));
    }

    /**
     * AJAX handler: Get snapshot storage stats.
     */
    public function ajax_get_snapshot_storage_stats() {
        check_ajax_referer('riseup_admin_nonce', 'nonce');

        if (!current_user_can(CapabilityType::ManageOptions->value)) {
            wp_send_json_error(array('message' => MSG_UNAUTHORIZED));
        }

        require_once dirname(__FILE__) . '/../Snapshot/SnapshotFactory.php';
        $scheduler = RiseupSnapshotFactory::scheduler();
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
        $defaults = $this->getErrorPageDefaults();
        extract($defaults);

        try {
            $result = $this->fetchErrorsForPage($defaults);
            extract($result);
        } catch (Throwable $e) {
            $db_error_message = sprintf(
                __('Database error: %s', 'riseup-asia-uploader'),
                esc_html($e->getMessage())
            );
        }

        include dirname(__FILE__) . '/../templates/admin-errors.php';
    }

    /**
     * Get safe default values for the error page.
     *
     * @return array Default variables.
     */
    private function getErrorPageDefaults(): array {
        return array(
            'errors'           => array(),
            'total'            => 0,
            'total_pages'      => 1,
            'page'             => 1,
            'last_seen_id'     => 0,
            'has_unseen'       => false,
            'unseen_count'     => 0,
            'latest_error_time' => '',
            'filter_level'     => isset($_GET['filter_level']) ? sanitize_text_field($_GET['filter_level']) : '',
            'filter_search'    => isset($_GET['filter_search']) ? sanitize_text_field($_GET['filter_search']) : '',
            'db_error_message' => '',
        );
    }

    /**
     * Fetch errors for the admin page with pagination and filters.
     *
     * @param array $defaults Default page variables.
     * @return array Updated page variables.
     */
    private function fetchErrorsForPage(array $defaults): array {
        $db = RiseupDatabase::get_instance();
        $pdo = $db->get_pdo();

        if (!$pdo) {
            $defaults['db_error_message'] = __('Database connection unavailable. The SQLite database may not be initialized yet.', 'riseup-asia-uploader');

            return $defaults;
        }

        $table_check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='error_sessions'");
        $table_exists = $table_check && $table_check->fetchColumn();

        if (!$table_exists) {
            $defaults['db_error_message'] = __('The error_sessions table does not exist yet. Errors will appear here once the plugin captures its first error.', 'riseup-asia-uploader');

            return $defaults;
        }

        return $this->queryErrorPage($pdo, $defaults);
    }

    /**
     * Query error sessions for page rendering.
     *
     * @param PDO   $pdo      Database connection.
     * @param array $defaults Default variables.
     * @return array Updated variables.
     */
    private function queryErrorPage(PDO $pdo, array $defaults): array {
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 50;
        $offset = ($page - 1) * $per_page;

        $where = array();
        $params = array();

        if (!empty($defaults['filter_level'])) {
            $where[] = 'level = ?';
            $params[] = $defaults['filter_level'];
        }

        if (!empty($defaults['filter_search'])) {
            $where[] = 'message LIKE ?';
            $params[] = '%' . $defaults['filter_search'] . '%';
        }

        $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM error_sessions {$where_sql}");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();
        $total_pages = max(1, ceil($total / $per_page));

        $stmt = $pdo->prepare("SELECT * FROM error_sessions {$where_sql} ORDER BY id DESC LIMIT ? OFFSET ?");
        $stmt->execute(array_merge($params, array($per_page, $offset)));
        $errors = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $last_seen_id = $this->get_flash_value('last_seen_error_id', 0);
        $has_unseen = ($this->get_flash_value('has_unseen_errors', '0') === '1');
        $unseen_count = $this->get_unseen_error_count();
        $latest_error_time = '';
        if (!empty($errors) && $has_unseen) {
            $latest_error_time = date('Y-m-d H:i:s', strtotime($errors[0]['created_at']));
        }

        return array(
            'errors'            => $errors,
            'total'             => $total,
            'total_pages'       => $total_pages,
            'page'              => $page,
            'last_seen_id'      => $last_seen_id,
            'has_unseen'        => $has_unseen,
            'unseen_count'      => $unseen_count,
            'latest_error_time' => $latest_error_time,
            'filter_level'      => $defaults['filter_level'],
            'filter_search'     => $defaults['filter_search'],
            'db_error_message'  => '',
        );
    }

    /**
     * Get unseen error count.
     *
     * @return int
     */
    private function get_unseen_error_count() {
        try {
            $db = RiseupDatabase::get_instance();
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
            $db = RiseupDatabase::get_instance();
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
        if (!current_user_can(CapabilityType::ManageOptions->value)) {
            wp_send_json_error(array('message' => MSG_UNAUTHORIZED));
        }

        $db = RiseupDatabase::get_instance();
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
        if (!current_user_can(CapabilityType::ManageOptions->value)) {
            wp_send_json_error(array('message' => MSG_UNAUTHORIZED));
        }

        $db = RiseupDatabase::get_instance();
        $pdo = $db->get_pdo();

        $pdo->exec('DELETE FROM error_sessions');
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $pdo->exec("INSERT OR REPLACE INTO flash_state (key, value, updated_at) VALUES ('last_seen_error_id', '0', '{$now}')");
        $pdo->exec("INSERT OR REPLACE INTO flash_state (key, value, updated_at) VALUES ('has_unseen_errors', '0', '{$now}')");

        wp_send_json_success(array('message' => 'All error sessions cleared'));
    }

    /**
     * Resolve a log file type to its absolute path.
     *
     * @param string $type One of 'log', 'error', 'stacktrace'.
     * @return string|false File path or false if invalid type.
     */
    private function resolve_log_file_path($type) {
        $logger = RiseupFileLogger::get_instance();
        switch ($type) {
            case 'log':
                return $logger->get_log_file();
            case 'error':
                return $logger->get_error_file();
            case 'stacktrace':
                return $logger->get_stacktrace_file();
            default:
                return false;
        }
    }

    /**
     * AJAX handler: Read a log file's contents.
     */
    public function ajax_read_log_file() {
        check_ajax_referer('riseup_admin_nonce', 'nonce');
        if (!current_user_can(CapabilityType::ManageOptions->value)) {
            wp_send_json_error(array('message' => MSG_UNAUTHORIZED));
        }

        $type = isset($_POST['file_type']) ? sanitize_text_field($_POST['file_type']) : '';
        $path = $this->resolve_log_file_path($type);

        if ($path === false) {
            wp_send_json_error(array('message' => 'Invalid file type'));
        }

        wp_send_json_success($this->readLogFileContent($path));
    }

    /**
     * Read a log file's content with size-based truncation.
     *
     * @param string $path File path.
     * @return array File content data.
     */
    private function readLogFileContent(string $path): array {
        $exists = file_exists($path);
        $content = '';
        $size = 0;

        if ($exists) {
            $size = filesize($path);
            $max_bytes = 512 * 1024;
            if ($size > $max_bytes) {
                $fp = fopen($path, 'r');
                fseek($fp, -$max_bytes, SEEK_END);
                fgets($fp);
                $content = fread($fp, $max_bytes);
                fclose($fp);
                $content = '... (truncated, showing last ' . round($max_bytes / 1024) . 'KB) ...' . PHP_EOL . $content;
            } else {
                $content = file_get_contents($path);
            }
        }

        return array(
            'content'  => $content,
            'exists'   => $exists,
            'size'     => $size,
            'filename' => basename($path),
        );
    }

    /**
     * AJAX handler: Clear (truncate) a log file.
     */
    public function ajax_clear_log_file() {
        check_ajax_referer('riseup_admin_nonce', 'nonce');
        if (!current_user_can(CapabilityType::ManageOptions->value)) {
            wp_send_json_error(array('message' => MSG_UNAUTHORIZED));
        }

        $type = isset($_POST['file_type']) ? sanitize_text_field($_POST['file_type']) : '';
        $path = $this->resolve_log_file_path($type);

        if ($path === false) {
            wp_send_json_error(array('message' => 'Invalid file type'));
        }

        if (file_exists($path)) {
            file_put_contents($path, '');
        }

        wp_send_json_success(array('message' => 'File cleared', 'file_type' => $type));
    }
}
