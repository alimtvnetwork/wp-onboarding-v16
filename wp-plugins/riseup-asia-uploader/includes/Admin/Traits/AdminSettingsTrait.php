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


use RiseupAsia\Enums\OptionNameType;
use RiseupAsia\Enums\PaginationConfigType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\PhpNativeType;

trait AdminSettingsTrait {

    /** Register settings. */
    public function registerSettings(): void {
        register_setting(
            PluginConfigType::SettingsGroup->value,
            OptionNameType::PluginSettings->value,
            [$this, 'sanitizeSettings'],
        );

        register_setting(
            PluginConfigType::SettingsGroup->value,
            OptionNameType::UpdateSettings->value,
            [$this, 'sanitizeUpdateSettings'],
        );

        register_setting(
            PluginConfigType::SettingsGroup->value,
            OptionNameType::SupportSettings->value,
            [$this, 'sanitizeSupportSettings'],
        );
    }

    /** Sanitize settings on save. */
    public function sanitizeSettings(array $input): array {
        $sanitized = self::$defaults;

        $hasEndpoints = !empty($input['endpoints'] ?? null) && gettype($input['endpoints']) === PhpNativeType::PhpArray->value;

        if ($hasEndpoints) {
            foreach ($input['endpoints'] as $endpoint => $config) {
                if (isset($sanitized['endpoints'][$endpoint])) {
                    $sanitized['endpoints'][$endpoint]['enabled'] = !empty($config['enabled'] ?? null);
                    $sanitized['endpoints'][$endpoint]['auth_required'] = !empty($config['auth_required'] ?? null);
                }
            }
        }

        if (isset($input['log_retrieval']) && gettype($input['log_retrieval']) === PhpNativeType::PhpArray->value) {
            $sanitized['log_retrieval']['include_error_log']  = !empty($input['log_retrieval']['include_error_log'] ?? null);
            $sanitized['log_retrieval']['include_full_log']   = !empty($input['log_retrieval']['include_full_log'] ?? null);
            $sanitized['log_retrieval']['include_stacktrace'] = !empty($input['log_retrieval']['include_stacktrace'] ?? null);
            $sanitized['log_retrieval']['max_lines'] = isset($input['log_retrieval']['max_lines'])
                ? max(50, min(5000, (int) $input['log_retrieval']['max_lines']))
                : PaginationConfigType::logRetrievalMaxLines();
        }

        return $sanitized;
    }

    /** Sanitize auto-update settings on save. */
    public function sanitizeUpdateSettings(array $input): array {
        $current = get_option(OptionNameType::UpdateSettings->value, []);
        $sanitized = $this->buildSanitizedUpdateFields($input, $current);

        if (isset($current['master_url']) && $current['master_url'] !== $sanitized['master_url']) {
            $sanitized['resolved_url'] = '';
            $sanitized['resolved_at'] = '';
        }

        return $sanitized;
    }

    /** Build sanitized update settings fields. */
    private function buildSanitizedUpdateFields(array $input, array $current): array {
        return [
            'enabled'      => !empty($input['enabled'] ?? null),
            'master_url'   => isset($input['master_url']) ? esc_url_raw($input['master_url']) : '',
            'cache_days'   => isset($input['cache_days']) ? max(1, min(30, (int) $input['cache_days'])) : 7,
            'resolved_url' => isset($current['resolved_url']) ? $current['resolved_url'] : '',
            'resolved_at'  => isset($current['resolved_at']) ? $current['resolved_at'] : '',
            'last_check'   => isset($current['last_check']) ? $current['last_check'] : '',
            'last_error'   => isset($current['last_error']) ? $current['last_error'] : '',
            'package_url'  => isset($current['package_url']) ? $current['package_url'] : '',
            'new_version'  => isset($current['new_version']) ? $current['new_version'] : '',
            'update_info'  => isset($current['update_info']) ? $current['update_info'] : array(),
        ];
    }

    /** Sanitize support settings on save. */
    public function sanitizeSupportSettings(array $input): array {
        return [
            'support_email' => isset($input['support_email']) ? sanitize_email($input['support_email']) : '',
            'fallback_url'  => isset($input['fallback_url']) ? esc_url_raw($input['fallback_url']) : '',
        ];
    }

    /** Get plugin settings. */
    public static function getSettings(): array {
        $settings = get_option(OptionNameType::PluginSettings->value, []);
        $isSettingsArray = gettype($settings) === PhpNativeType::PhpArray->value;

        if ($isSettingsArray === false) {
            return self::$defaults;
        }

        return array_replace_recursive(self::$defaults, $settings);
    }

    /** Check if an endpoint is enabled. Missing keys default to true (backward-safe). */
    public static function isEndpointEnabled(string $endpoint): bool {
        $settings = self::getSettings();
        $endpointConfig = $settings['endpoints'][$endpoint] ?? null;
        $hasEnabledFlag = (gettype($endpointConfig) === PhpNativeType::PhpArray->value && array_key_exists('enabled', $endpointConfig));

        if ($hasEnabledFlag === false) {
            return true;
        }

        return !empty($endpointConfig['enabled']);
    }

    /** Check if an endpoint requires authentication. Missing keys default to true (secure by default). */
    public static function isAuthRequired(string $endpoint): bool {
        $settings = self::getSettings();
        $endpointConfig = $settings['endpoints'][$endpoint] ?? null;
        $hasAuthFlag = (gettype($endpointConfig) === PhpNativeType::PhpArray->value && array_key_exists('auth_required', $endpointConfig));

        if ($hasAuthFlag === false) {
            return true;
        }

        return !empty($endpointConfig['auth_required']);
    }
}
