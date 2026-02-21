<?php
/**
 * AdminSettingsTrait — Settings registration, sanitization, and retrieval.
 *
 * @package RiseupAsia\Admin\Traits
 * @since   2.0.0
 */

namespace RiseupAsia\Admin\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Helpers\BooleanHelpers;
use RiseupAsia\Enums\OptionNameType;
use RiseupAsia\Enums\PaginationConfigType;
use RiseupAsia\Enums\PluginConfigType;

trait AdminSettingsTrait {

    /** Register settings. */
    public function registerSettings(): void {
        register_setting(
            PluginConfigType::SettingsGroup->value,
            OptionNameType::PluginSettings->value,
            array($this, 'sanitizeSettings'),
        );

        register_setting(
            PluginConfigType::SettingsGroup->value,
            OptionNameType::UpdateSettings->value,
            array($this, 'sanitizeUpdateSettings'),
        );
    }

    /** Sanitize settings on save. */
    public function sanitizeSettings(array $input): array {
        $sanitized = self::$defaults;

        $hasEndpoints = BooleanHelpers::hasValue($input['endpoints'] ?? null) && is_array($input['endpoints']);

        if ($hasEndpoints) {
            foreach ($input['endpoints'] as $endpoint => $config) {
                if (isset($sanitized['endpoints'][$endpoint])) {
                    $sanitized['endpoints'][$endpoint]['enabled'] = BooleanHelpers::hasValue($config['enabled'] ?? null);
                    $sanitized['endpoints'][$endpoint]['auth_required'] = BooleanHelpers::hasValue($config['auth_required'] ?? null);
                }
            }
        }

        if (isset($input['log_retrieval']) && is_array($input['log_retrieval'])) {
            $sanitized['log_retrieval']['include_error_log']  = BooleanHelpers::hasValue($input['log_retrieval']['include_error_log'] ?? null);
            $sanitized['log_retrieval']['include_full_log']   = BooleanHelpers::hasValue($input['log_retrieval']['include_full_log'] ?? null);
            $sanitized['log_retrieval']['include_stacktrace'] = BooleanHelpers::hasValue($input['log_retrieval']['include_stacktrace'] ?? null);
            $sanitized['log_retrieval']['max_lines'] = isset($input['log_retrieval']['max_lines'])
                ? max(50, min(5000, (int) $input['log_retrieval']['max_lines']))
                : PaginationConfigType::LogRetrievalMaxLines->value;
        }

        return $sanitized;
    }

    /** Sanitize auto-update settings on save. */
    public function sanitizeUpdateSettings(array $input): array {
        $current = get_option(OptionNameType::UpdateSettings->value, array());
        $sanitized = $this->buildSanitizedUpdateFields($input, $current);

        if (isset($current['master_url']) && $current['master_url'] !== $sanitized['master_url']) {
            $sanitized['resolved_url'] = '';
            $sanitized['resolved_at'] = '';
        }

        return $sanitized;
    }

    /** Build sanitized update settings fields. */
    private function buildSanitizedUpdateFields(array $input, array $current): array {
        return array(
            'enabled'      => BooleanHelpers::hasValue($input['enabled'] ?? null),
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

    /** Get plugin settings. */
    public static function getSettings(): array {
        $settings = get_option(OptionNameType::PluginSettings->value, array());

        return wp_parse_args($settings, self::$defaults);
    }

    /** Check if an endpoint is enabled. */
    public static function isEndpointEnabled(string $endpoint): bool {
        $settings = self::getSettings();

        return BooleanHelpers::hasValue($settings['endpoints'][$endpoint]['enabled'] ?? null);
    }

    /** Check if an endpoint requires authentication. */
    public static function isAuthRequired(string $endpoint): bool {
        $settings = self::getSettings();

        return BooleanHelpers::hasValue($settings['endpoints'][$endpoint]['auth_required'] ?? null);
    }
}
