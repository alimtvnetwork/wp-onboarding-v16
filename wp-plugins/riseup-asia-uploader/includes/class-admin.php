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
            'action' => isset($_GET['filter_action']) ? sanitize_text_field($_GET['filter_action']) : '',
            'user'   => isset($_GET['filter_user']) ? sanitize_text_field($_GET['filter_user']) : '',
            'status' => isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : '',
            'plugin' => isset($_GET['filter_plugin']) ? sanitize_text_field($_GET['filter_plugin']) : '',
            'from'   => isset($_GET['filter_from']) ? sanitize_text_field($_GET['filter_from']) : '',
            'to'     => isset($_GET['filter_to']) ? sanitize_text_field($_GET['filter_to']) : '',
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
}
