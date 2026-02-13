<?php
/**
 * AdminSettingsTrait — Settings registration, sanitization, and retrieval.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait AdminSettingsTrait {

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
