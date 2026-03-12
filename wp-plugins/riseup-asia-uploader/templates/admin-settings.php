<?php
/**
 * Admin Settings Page Template
 *
 * Slim orchestrator — delegates snapshot and log sections to partials.
 *
 * @package RiseupAsiaUploader
 * @since   1.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\AdminPageType;
use RiseupAsia\Enums\EndpointType;
use RiseupAsia\Enums\OptionNameType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\RetentionType;
use RiseupAsia\Enums\SnapshotConfigType;
use RiseupAsia\Enums\SnapshotFrequencyType;
use RiseupAsia\Enums\SnapshotProviderType;
use RiseupAsia\Enums\SnapshotScopeType;
use RiseupAsia\Enums\StorageModeType;
use RiseupAsia\Admin\Admin;
use RiseupAsia\Helpers\BooleanHelpers;

$pluginName = PluginConfigType::Name->value;
$pluginSlug = PluginConfigType::Slug->value;
?>
<div class="wrap riseup-admin">
    <?php
    $pageIcon = 'dashicons-admin-settings';
    $pageTitle = $pluginName . ' - ' . __('Settings', $pluginSlug);
    $pageDescription = __('Configure API endpoints, authentication requirements, and auto-update settings.', $pluginSlug);
    include __DIR__ . '/partials/shared/page-header.php';
    ?>

    <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated']): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Settings saved successfully.', $pluginSlug); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="options.php">
        <?php settings_fields(PluginConfigType::SettingsGroup->value); ?>

        <!-- Plugin Info -->
        <div class="riseup-card">
            <h2><?php esc_html_e('Plugin Information', $pluginSlug); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e('Version', $pluginSlug); ?></th>
                    <td><code><?php echo esc_html(PluginConfigType::Version->value); ?></code></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('API Namespace', $pluginSlug); ?></th>
                    <td><code><?php echo esc_html(PluginConfigType::apiFullNamespace()); ?></code></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('REST API Base', $pluginSlug); ?></th>
                    <td><code><?php echo esc_url(rest_url(PluginConfigType::apiFullNamespace())); ?></code></td>
                </tr>
            </table>
        </div>

        <!-- Auto-Update Configuration -->
        <div class="riseup-card">
            <h2>
                <span class="dashicons dashicons-update"></span>
                <?php esc_html_e('Auto-Update Settings', $pluginSlug); ?>
            </h2>
            <p class="description">
                <?php esc_html_e('Configure automatic updates with 301 redirect URL resolution. The master URL will be resolved through redirects and cached for faster subsequent checks.', $pluginSlug); ?>
            </p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="update_enabled"><?php esc_html_e('Enable Auto-Update', $pluginSlug); ?></label>
                    </th>
                    <td>
                        <label class="toggle-switch">
                            <input type="checkbox" 
                                   id="update_enabled"
                                   name="<?php echo esc_attr(OptionNameType::UpdateSettings->value); ?>[enabled]" 
                                   value="1" 
                                   <?php $isUpdateEnabled = BooleanHelpers::hasValue($updateSettings['enabled'] ?? null); checked($isUpdateEnabled); ?>>
                            <span class="toggle-slider"></span>
                        </label>
                        <p class="description"><?php esc_html_e('Enable automatic update checking via the configured master URL.', $pluginSlug); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="master_url"><?php esc_html_e('Master Update URL', $pluginSlug); ?></label>
                    </th>
                    <td>
                        <input type="url" 
                               id="master_url"
                               name="<?php echo esc_attr(OptionNameType::UpdateSettings->value); ?>[master_url]" 
                               value="<?php echo esc_attr($updateSettings['master_url']); ?>" 
                               class="regular-text"
                               placeholder="https://updates.example.com/plugin">
                        <p class="description"><?php esc_html_e('The URL that will be resolved through 301 redirects to find the actual update endpoint.', $pluginSlug); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="cache_days"><?php esc_html_e('Cache Duration', $pluginSlug); ?></label>
                    </th>
                    <td>
                        <select id="cache_days" name="<?php echo esc_attr(OptionNameType::UpdateSettings->value); ?>[cache_days]">
                            <option value="1" <?php selected($updateSettings['cache_days'], 1); ?>>1 <?php esc_html_e('day', $pluginSlug); ?></option>
                            <option value="7" <?php selected($updateSettings['cache_days'], 7); ?>>7 <?php esc_html_e('days', $pluginSlug); ?></option>
                            <option value="14" <?php selected($updateSettings['cache_days'], 14); ?>>14 <?php esc_html_e('days', $pluginSlug); ?></option>
                            <option value="30" <?php selected($updateSettings['cache_days'], 30); ?>>30 <?php esc_html_e('days', $pluginSlug); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('How long to cache the resolved URL before re-resolving through redirects.', $pluginSlug); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Resolved URL (Cached)', $pluginSlug); ?></th>
                    <td>
                        <?php $hasResolvedUrl = BooleanHelpers::hasValue($updateSettings['resolved_url'] ?? null); ?>
                        <?php if ($hasResolvedUrl): ?>
                            <code id="resolved_url_display"><?php echo esc_html($updateSettings['resolved_url']); ?></code>
                            <br>
                            <small class="text-muted">
                                <?php
                                $hasResolvedAt = BooleanHelpers::hasValue($updateSettings['resolved_at'] ?? null);
                                if ($hasResolvedAt) {
                                    printf(
                                        esc_html__('Cached on: %s', $pluginSlug),
                                        esc_html($updateSettings['resolved_at'])
                                    );
                                }
                                ?>
                            </small>
                        <?php else: ?>
                            <em><?php esc_html_e('Not resolved yet', $pluginSlug); ?></em>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Last Check', $pluginSlug); ?></th>
                    <td>
                        <?php $hasLastCheck = BooleanHelpers::hasValue($updateSettings['last_check'] ?? null); ?>
                        <?php if ($hasLastCheck): ?>
                            <?php echo esc_html($updateSettings['last_check']); ?>
                        <?php else: ?>
                            <em><?php esc_html_e('Never', $pluginSlug); ?></em>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php $hasLastError = BooleanHelpers::hasValue($updateSettings['last_error'] ?? null); if ($hasLastError): ?>
                <tr>
                    <th scope="row"><?php esc_html_e('Last Error', $pluginSlug); ?></th>
                    <td>
                        <span class="riseup-error-text"><?php echo esc_html($updateSettings['last_error']); ?></span>
                    </td>
                </tr>
                <?php endif; ?>
                <?php $hasNewVersion = BooleanHelpers::hasValue($updateSettings['new_version'] ?? null); if ($hasNewVersion): ?>
                <tr>
                    <th scope="row"><?php esc_html_e('Available Version', $pluginSlug); ?></th>
                    <td>
                        <strong><?php echo esc_html($updateSettings['new_version']); ?></strong>
                        <?php if (version_compare($updateSettings['new_version'], PluginConfigType::Version->value, '>')): ?>
                            <span class="dashicons dashicons-arrow-up-alt" style="color: #46b450;"></span>
                            <span style="color: #46b450;"><?php esc_html_e('Update available!', $pluginSlug); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th scope="row"><?php esc_html_e('Actions', $pluginSlug); ?></th>
                    <td>
                        <button type="button" id="btn_test_connection" class="button button-secondary">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php esc_html_e('Test Connection', $pluginSlug); ?>
                        </button>
                        <button type="button" id="btn_clear_cache" class="button button-secondary">
                            <span class="dashicons dashicons-trash"></span>
                            <?php esc_html_e('Clear Cache', $pluginSlug); ?>
                        </button>
                        <button type="button" id="btn_check_updates" class="button button-secondary">
                            <span class="dashicons dashicons-update"></span>
                            <?php esc_html_e('Check Now', $pluginSlug); ?>
                        </button>
                        <span id="update_action_status" style="margin-left: 10px;"></span>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Endpoint Configuration (Grouped) -->
        <div class="riseup-card">
            <h2><?php esc_html_e('API Endpoints Configuration', $pluginSlug); ?></h2>
            <p class="description">
                <?php esc_html_e('Enable or disable specific API endpoints and configure authentication requirements.', $pluginSlug); ?>
            </p>

            <table class="wp-list-table widefat fixed striped riseup-endpoints-table">
                <thead>
                    <tr>
                        <th class="column-endpoint"><?php esc_html_e('Endpoint', $pluginSlug); ?></th>
                        <th class="column-description"><?php esc_html_e('Description', $pluginSlug); ?></th>
                        <th class="column-enabled"><?php esc_html_e('Enabled', $pluginSlug); ?></th>
                        <th class="column-auth"><?php esc_html_e('Auth Required', $pluginSlug); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($endpointGroups as $groupKey => $group): ?>
                        <tr class="endpoint-group-header">
                            <td colspan="4">
                                <span class="dashicons <?php echo esc_attr($group['icon']); ?>"></span>
                                <?php echo esc_html($group['label']); ?>
                            </td>
                        </tr>
                        <?php foreach ($group['endpoints'] as $endpoint => $meta): ?>
                            <tr>
                                <td class="column-endpoint">
                                    <strong><?php echo esc_html($meta['label']); ?></strong>
                                    <br>
                                    <code class="endpoint-path">/<?php echo esc_html($endpoint); ?></code>
                                </td>
                                <td class="column-description">
                                    <?php echo esc_html($meta['desc']); ?>
                                </td>
                                <td class="column-enabled">
                                    <label class="toggle-switch">
                                        <input type="checkbox" 
                                               name="<?php echo esc_attr(OptionNameType::PluginSettings->value); ?>[endpoints][<?php echo esc_attr($endpoint); ?>][enabled]" 
                                               value="1" 
                                               <?php $isEpEnabled = BooleanHelpers::hasValue($settings['endpoints'][$endpoint]['enabled'] ?? null); checked($isEpEnabled); ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </td>
                                <td class="column-auth">
                                    <label class="toggle-switch">
                                        <input type="checkbox" 
                                               name="<?php echo esc_attr(OptionNameType::PluginSettings->value); ?>[endpoints][<?php echo esc_attr($endpoint); ?>][auth_required]" 
                                               value="1" 
                                               <?php $isAuthReq = BooleanHelpers::hasValue($settings['endpoints'][$endpoint]['auth_required'] ?? null); checked($isAuthReq); ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p class="riseup-warning">
                <span class="dashicons dashicons-warning"></span>
                <?php esc_html_e('Warning: Disabling authentication can expose your site to unauthorized access. Only disable for development/testing purposes.', $pluginSlug); ?>
            </p>
        </div>

        <?php include __DIR__ . '/partials/settings/section-snapshot-settings.php'; ?>
        <?php include __DIR__ . '/partials/settings/section-log-retrieval.php'; ?>

        <?php submit_button(__('Save Settings', $pluginSlug)); ?>
    </form>
</div>
