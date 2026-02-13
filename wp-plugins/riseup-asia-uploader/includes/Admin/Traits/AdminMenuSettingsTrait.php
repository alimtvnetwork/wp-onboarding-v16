<?php
/**
 * Admin Menu & Settings Trait
 *
 * Menu registration, asset enqueuing, and settings sanitization.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\CapabilityType;
use RiseupAsia\Enums\HookType;

trait AdminMenuSettingsTrait {

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
}
