<?php
/**
 * Admin Settings Page Template
 *
 * @package RiseupAsiaUploader
 * @since   1.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\AdminPageType;
use RiseupAsia\Enums\AjaxActionType;
use RiseupAsia\Enums\EndpointType;
use RiseupAsia\Enums\NonceType;
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
<?php
$pluginName = PluginConfigType::Name->value;
$pluginSlug = PluginConfigType::Slug->value;
?>
<style>
.riseup-admin .button .dashicons {
    vertical-align: middle;
    margin-top: -2px;
    margin-right: 2px;
}
</style>
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

        <!-- Database Snapshot Settings -->
        <div class="riseup-card">
            <h2>
                <span class="dashicons dashicons-database"></span>
                <?php esc_html_e('Database Snapshot Settings', $pluginSlug); ?>
            </h2>
            <p class="description">
                <?php esc_html_e('Configure automated database snapshots, retention policies, and provider preferences. Manage snapshots from the', $pluginSlug); ?>
                <a href="<?php echo esc_url(AdminPageType::Snapshots->adminUrl()); ?>"><?php esc_html_e('Snapshots Dashboard', $pluginSlug); ?></a>.
            </p>

            <!-- Provider Selection -->
            <h3><?php esc_html_e('Snapshot Provider', $pluginSlug); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="snap_preferred_provider"><?php esc_html_e('Preferred Provider', $pluginSlug); ?></label>
                    </th>
                    <td>
                        <select id="snap_preferred_provider">
                            <option value="<?php echo esc_attr(SnapshotProviderType::Auto->value); ?>" <?php selected($snapshotSettings['preferred_provider'], SnapshotProviderType::Auto->value); ?>>
                                <?php esc_html_e('Auto-detect (recommended)', $pluginSlug); ?>
                            </option>
                            <?php foreach ($snapshotProviders as $provider): ?>
                                <option value="<?php echo esc_attr($provider['id']); ?>" 
                                        <?php selected($snapshotSettings['preferred_provider'], $provider['id']); ?>
                                        <?php $isProviderUnavailable = ($provider['available'] === false); disabled($isProviderUnavailable); ?>>
                                    <?php echo esc_html($provider['name']); ?>
                                    <?php if ($isProviderUnavailable): ?>(<?php esc_html_e('not installed', $pluginSlug); ?>)<?php endif; ?>
                                    <?php $hasProviderVersion = BooleanHelpers::hasValue($provider['version'] ?? null); if ($hasProviderVersion): ?>(v<?php echo esc_html($provider['version']); ?>)<?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e('Priority: WP Reset > UpdraftPlus > Native SQLite.', $pluginSlug); ?></p>
                    </td>
                </tr>
            </table>

            <!-- Scheduling -->
            <h3><?php esc_html_e('Scheduling', $pluginSlug); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="snap_schedule_enabled"><?php esc_html_e('Enable Scheduled Snapshots', $pluginSlug); ?></label>
                    </th>
                    <td>
                        <label class="toggle-switch">
                            <input type="checkbox" id="snap_schedule_enabled" value="1" <?php checked($snapshotSettings['schedule_enabled']); ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="snap_schedule_frequency"><?php esc_html_e('Frequency', $pluginSlug); ?></label>
                    </th>
                    <td>
                        <select id="snap_schedule_frequency">
                            <option value="<?php echo esc_attr(SnapshotFrequencyType::Manual->value); ?>" <?php selected($snapshotSettings['schedule_frequency'], SnapshotFrequencyType::Manual->value); ?>><?php esc_html_e('Manual Only', $pluginSlug); ?></option>
                            <option value="<?php echo esc_attr(SnapshotFrequencyType::Hourly->value); ?>" <?php selected($snapshotSettings['schedule_frequency'], SnapshotFrequencyType::Hourly->value); ?>><?php esc_html_e('Hourly', $pluginSlug); ?></option>
                            <option value="<?php echo esc_attr(SnapshotFrequencyType::Daily->value); ?>" <?php selected($snapshotSettings['schedule_frequency'], SnapshotFrequencyType::Daily->value); ?>><?php esc_html_e('Daily', $pluginSlug); ?></option>
                            <option value="<?php echo esc_attr(SnapshotFrequencyType::Weekly->value); ?>" <?php selected($snapshotSettings['schedule_frequency'], SnapshotFrequencyType::Weekly->value); ?>><?php esc_html_e('Weekly', $pluginSlug); ?></option>
                            <option value="<?php echo esc_attr(SnapshotFrequencyType::Monthly->value); ?>" <?php selected($snapshotSettings['schedule_frequency'], SnapshotFrequencyType::Monthly->value); ?>><?php esc_html_e('Monthly', $pluginSlug); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="snap_schedule_time"><?php esc_html_e('Time', $pluginSlug); ?></label>
                    </th>
                    <td>
                        <input type="time" id="snap_schedule_time" value="<?php echo esc_attr($snapshotSettings['schedule_time']); ?>">
                    </td>
                </tr>
                <tr id="snap_day_row" style="<?php $isHiddenFreq = SnapshotFrequencyType::tryFrom($snapshotSettings['schedule_frequency'] ?? ''); echo ($isHiddenFreq !== null && $isHiddenFreq->isAnyOf(SnapshotFrequencyType::Hourly, SnapshotFrequencyType::Daily, SnapshotFrequencyType::Manual)) ? 'display:none;' : ''; ?>">
                    <th scope="row">
                        <label for="snap_schedule_day"><?php esc_html_e('Day', $pluginSlug); ?></label>
                    </th>
                    <td>
                        <input type="number" id="snap_schedule_day" min="1" max="28" value="<?php echo esc_attr($snapshotSettings['schedule_day']); ?>" class="small-text">
                        <p class="description"><?php esc_html_e('Day of week (1=Mon, 7=Sun) for weekly, or day of month (1-28) for monthly.', $pluginSlug); ?></p>
                    </td>
                </tr>
            </table>

            <!-- Default Scope -->
            <h3><?php esc_html_e('Default Scope', $pluginSlug); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="snap_default_scope"><?php esc_html_e('Tables to Snapshot', $pluginSlug); ?></label>
                    </th>
                    <td>
                        <select id="snap_default_scope">
                            <option value="<?php echo esc_attr(SnapshotScopeType::All->value); ?>" <?php selected($snapshotSettings['default_scope'], SnapshotScopeType::All->value); ?>><?php esc_html_e('All Tables', $pluginSlug); ?></option>
                            <option value="<?php echo esc_attr(SnapshotScopeType::WordPress->value); ?>" <?php selected($snapshotSettings['default_scope'], SnapshotScopeType::WordPress->value); ?>><?php esc_html_e('WordPress Core Only', $pluginSlug); ?></option>
                            <option value="<?php echo esc_attr(SnapshotScopeType::Content->value); ?>" <?php selected($snapshotSettings['default_scope'], SnapshotScopeType::Content->value); ?>><?php esc_html_e('Content Only (posts, terms, comments)', $pluginSlug); ?></option>
                            <option value="<?php echo esc_attr(SnapshotScopeType::Custom->value); ?>" <?php selected($snapshotSettings['default_scope'], SnapshotScopeType::Custom->value); ?>><?php esc_html_e('Custom Selection', $pluginSlug); ?></option>
                        </select>
                    </td>
                </tr>
            </table>

            <!-- Retention Policy -->
            <h3><?php esc_html_e('Retention Policy', $pluginSlug); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="snap_retention_type"><?php esc_html_e('Cleanup Strategy', $pluginSlug); ?></label>
                    </th>
                    <td>
                        <select id="snap_retention_type">
                            <option value="<?php echo esc_attr(RetentionType::None->value); ?>" <?php selected($snapshotSettings['retention_type'], RetentionType::None->value); ?>><?php esc_html_e('None (manual only)', $pluginSlug); ?></option>
                            <option value="<?php echo esc_attr(RetentionType::Days->value); ?>" <?php selected($snapshotSettings['retention_type'], RetentionType::Days->value); ?>><?php esc_html_e('Keep for N days', $pluginSlug); ?></option>
                            <option value="<?php echo esc_attr(RetentionType::Count->value); ?>" <?php selected($snapshotSettings['retention_type'], RetentionType::Count->value); ?>><?php esc_html_e('Keep last N snapshots', $pluginSlug); ?></option>
                        </select>
                    </td>
                </tr>
                <tr id="snap_retention_days_row" style="<?php echo $snapshotSettings['retention_type'] !== RetentionType::Days->value ? 'display:none;' : ''; ?>">
                    <th scope="row">
                        <label for="snap_retention_days"><?php esc_html_e('Retention Days', $pluginSlug); ?></label>
                    </th>
                    <td>
                        <input type="number" id="snap_retention_days" min="1" max="365" value="<?php echo esc_attr($snapshotSettings['retention_days']); ?>" class="small-text">
                        <p class="description"><?php esc_html_e('Snapshots older than this will be deleted during cleanup.', $pluginSlug); ?></p>
                    </td>
                </tr>
                <tr id="snap_retention_count_row" style="<?php echo $snapshotSettings['retention_type'] !== RetentionType::Count->value ? 'display:none;' : ''; ?>">
                    <th scope="row">
                        <label for="snap_retention_count"><?php esc_html_e('Maximum Count', $pluginSlug); ?></label>
                    </th>
                    <td>
                        <input type="number" id="snap_retention_count" min="1" max="100" value="<?php echo esc_attr($snapshotSettings['retention_count']); ?>" class="small-text">
                        <p class="description"><?php esc_html_e('Oldest snapshots beyond this limit will be deleted.', $pluginSlug); ?></p>
                    </td>
                </tr>
            </table>

            <!-- Worker Pool & Storage Mode (Phase 5) -->
            <h3>
                <span class="dashicons dashicons-performance" style="font-size: 16px; margin-right: 4px;"></span>
                <?php esc_html_e('Worker Pool & Storage', $pluginSlug); ?>
            </h3>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="snap_storage_mode"><?php esc_html_e('Storage Mode', $pluginSlug); ?></label>
                    </th>
                    <td>
                        <div class="riseup-storage-mode-cards" style="display: flex; gap: 12px; max-width: 520px;">
                            <label class="riseup-mode-card" id="mode_card_single" style="flex: 1; cursor: pointer; padding: 12px; border: 2px solid <?php echo $snapshotSettings['storage_mode'] === StorageModeType::Single->value ? '#2271b1' : '#dcdcde'; ?>; border-radius: 8px; background: <?php echo $snapshotSettings['storage_mode'] === StorageModeType::Single->value ? '#f0f6fc' : '#fff'; ?>; transition: all 0.2s;">
                                <input type="radio" name="snap_storage_mode" value="<?php echo esc_attr(StorageModeType::Single->value); ?>" <?php checked($snapshotSettings['storage_mode'], StorageModeType::Single->value); ?> style="display: none;">
                                <span class="dashicons dashicons-database" style="color: #2271b1; font-size: 20px;"></span>
                                <strong style="display: block; margin: 4px 0 2px;"><?php esc_html_e('Single File', $pluginSlug); ?></strong>
                                <span style="font-size: 12px; color: #646970;"><?php esc_html_e('All tables in one SQLite database. Simpler management.', $pluginSlug); ?></span>
                            </label>
                            <label class="riseup-mode-card" id="mode_card_pertable" style="flex: 1; cursor: pointer; padding: 12px; border: 2px solid <?php echo $snapshotSettings['storage_mode'] === StorageModeType::PerTable->value ? '#2271b1' : '#dcdcde'; ?>; border-radius: 8px; background: <?php echo $snapshotSettings['storage_mode'] === StorageModeType::PerTable->value ? '#f0f6fc' : '#fff'; ?>; transition: all 0.2s;">
                                <input type="radio" name="snap_storage_mode" value="<?php echo esc_attr(StorageModeType::PerTable->value); ?>" <?php checked($snapshotSettings['storage_mode'], StorageModeType::PerTable->value); ?> style="display: none;">
                                <span class="dashicons dashicons-grid-view" style="color: #2271b1; font-size: 20px;"></span>
                                <strong style="display: block; margin: 4px 0 2px;"><?php esc_html_e('Per-Table Files', $pluginSlug); ?></strong>
                                <span style="font-size: 12px; color: #646970;"><?php esc_html_e('Separate SQLite file per table. Parallel backup via worker pool.', $pluginSlug); ?></span>
                            </label>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="snap_worker_pool_size"><?php esc_html_e('Worker Pool Size', $pluginSlug); ?></label>
                    </th>
                    <td>
                        <div style="display: flex; align-items: center; gap: 12px; max-width: 340px;">
                            <input type="range" id="snap_worker_pool_size" 
                                   min="<?php echo esc_attr(SnapshotConfigType::WorkerPoolMin->value); ?>" 
                                   max="<?php echo esc_attr(SnapshotConfigType::WorkerPoolMax->value); ?>" 
                                   value="<?php echo esc_attr($snapshotSettings['worker_pool_size']); ?>" 
                                   style="flex: 1; accent-color: #2271b1;">
                            <span id="snap_worker_pool_value" style="font-family: monospace; font-size: 14px; min-width: 24px; text-align: center; font-weight: 600; color: #2271b1;"><?php echo esc_html($snapshotSettings['worker_pool_size']); ?></span>
                        </div>
                        <p class="description"><?php printf(esc_html__('Number of concurrent backup workers (%d–%d). Higher values export faster but use more resources.', $pluginSlug), SnapshotConfigType::WorkerPoolMin->value, SnapshotConfigType::WorkerPoolMax->value); ?></p>
                    </td>
                </tr>
            </table>

            <!-- Safety & Limits -->
            <h3><?php esc_html_e('Safety & Limits', $pluginSlug); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="snap_pre_restore_backup"><?php esc_html_e('Pre-Restore Backup', $pluginSlug); ?></label>
                    </th>
                    <td>
                        <label class="toggle-switch">
                            <input type="checkbox" id="snap_pre_restore_backup" value="1" <?php checked($snapshotSettings['pre_restore_backup']); ?>>
                            <span class="toggle-slider"></span>
                        </label>
                        <p class="description"><?php esc_html_e('Automatically create a backup before restoring a snapshot.', $pluginSlug); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="snap_max_size"><?php esc_html_e('Max Snapshot Size (MB)', $pluginSlug); ?></label>
                    </th>
                    <td>
                        <input type="number" id="snap_max_size" min="50" max="2000" value="<?php echo esc_attr($snapshotSettings['max_snapshot_size_mb']); ?>" class="small-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="snap_batch_size"><?php esc_html_e('Batch Size', $pluginSlug); ?></label>
                    </th>
                    <td>
                        <input type="number" id="snap_batch_size" min="100" max="10000" step="100" value="<?php echo esc_attr($snapshotSettings['batch_size']); ?>" class="small-text">
                        <p class="description"><?php esc_html_e('Rows per batch during export/import. Lower values use less memory.', $pluginSlug); ?></p>
                    </td>
                </tr>
            </table>

            <!-- Storage Stats -->
            <h3><?php esc_html_e('Storage', $pluginSlug); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Storage Info', $pluginSlug); ?></th>
                    <td>
                        <span id="snap_storage_info"><em><?php esc_html_e('Loading...', $pluginSlug); ?></em></span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Actions', $pluginSlug); ?></th>
                    <td>
                        <button type="button" id="btn_save_snapshot_settings" class="button button-primary">
                            <span class="dashicons dashicons-yes"></span>
                            <?php esc_html_e('Save Snapshot Settings', $pluginSlug); ?>
                        </button>
                        <button type="button" id="btn_run_cleanup" class="button button-secondary">
                            <span class="dashicons dashicons-update"></span>
                            <?php esc_html_e('Run Cleanup Now', $pluginSlug); ?>
                        </button>
                        <span id="snap_action_status" style="margin-left: 10px;"></span>
                    </td>
                </tr>
            </table>
        </div>

        <!-- PHP Log Retrieval Settings -->
        <div class="riseup-card">
            <h2>
                <span class="dashicons dashicons-media-text"></span>
                <?php esc_html_e('PHP Log Retrieval (Remote API)', $pluginSlug); ?>
            </h2>
            <p class="description">
                <?php esc_html_e('Controls which log files are included when the Go backend requests PHP logs via the /error-logs endpoint. This endpoint returns the raw log file contents as JSON for remote diagnostics.', $pluginSlug); ?>
            </p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="log_include_error"><?php esc_html_e('Include Error Log', $pluginSlug); ?></label>
                    </th>
                    <td>
                        <label class="toggle-switch">
                            <input type="checkbox" 
                                   id="log_include_error"
                                   name="<?php echo esc_attr(OptionNameType::PluginSettings->value); ?>[log_retrieval][include_error_log]" 
                                   value="1" 
                                   <?php $isIncludeErrorLog = BooleanHelpers::hasValue($settings['log_retrieval']['include_error_log'] ?? null); checked($isIncludeErrorLog); ?>>
                            <span class="toggle-slider"></span>
                        </label>
                        <p class="description">
                            <?php esc_html_e('Include error.txt (errors and warnings only). Enabled by default — this is the most important log for diagnostics.', $pluginSlug); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="log_include_full"><?php esc_html_e('Include Full Log', $pluginSlug); ?></label>
                    </th>
                    <td>
                        <label class="toggle-switch">
                            <input type="checkbox" 
                                   id="log_include_full"
                                   name="<?php echo esc_attr(OptionNameType::PluginSettings->value); ?>[log_retrieval][include_full_log]" 
                                   value="1" 
                                   <?php $isIncludeFullLog = BooleanHelpers::hasValue($settings['log_retrieval']['include_full_log'] ?? null); checked($isIncludeFullLog); ?>>
                            <span class="toggle-slider"></span>
                        </label>
                        <p class="description">
                            <?php esc_html_e('Include log.txt (all log levels including INFO and DEBUG). Disabled by default — can be very large.', $pluginSlug); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="log_include_stacktrace"><?php esc_html_e('Include Stack Trace Log', $pluginSlug); ?></label>
                    </th>
                    <td>
                        <label class="toggle-switch">
                            <input type="checkbox" 
                                   id="log_include_stacktrace"
                                   name="<?php echo esc_attr(OptionNameType::PluginSettings->value); ?>[log_retrieval][include_stacktrace]" 
                                   value="1" 
                                   <?php $isIncludeStacktrace = BooleanHelpers::hasValue($settings['log_retrieval']['include_stacktrace'] ?? null); checked($isIncludeStacktrace); ?>>
                            <span class="toggle-slider"></span>
                        </label>
                        <p class="description">
                            <?php esc_html_e('Include stacktrace.txt (10-frame PHP backtraces for every error). Enabled by default — essential for deep diagnostics.', $pluginSlug); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="log_max_lines"><?php esc_html_e('Max Lines', $pluginSlug); ?></label>
                    </th>
                    <td>
                        <input type="number" 
                               id="log_max_lines"
                               name="<?php echo esc_attr(OptionNameType::PluginSettings->value); ?>[log_retrieval][max_lines]" 
                               value="<?php echo esc_attr($settings['log_retrieval']['max_lines']); ?>" 
                               min="50" max="5000" step="50"
                               class="small-text">
                        <p class="description">
                            <?php esc_html_e('Maximum number of lines to return per log file (most recent lines, tail). Range: 50–5000.', $pluginSlug); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Endpoint', $pluginSlug); ?></th>
                    <td>
                        <code><?php echo esc_html(rest_url(PluginConfigType::apiFullNamespace() . '/' . EndpointType::ErrorLogs->value)); ?></code>
                        <p class="description"><?php esc_html_e('GET request with Basic Auth. Returns JSON with error_log, full_log, and/or stacktrace_log fields.', $pluginSlug); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button(__('Save Settings', $pluginSlug)); ?>
    </form>
</div>

<script type="text/javascript">
// --- JS Constants from PHP Enums (eliminates magic strings) ---
var UPDATE_AJAX = {
    TEST_CONNECTION: '<?php echo esc_js(AjaxActionType::TestUpdateConnection->value); ?>',
    CLEAR_CACHE:     '<?php echo esc_js(AjaxActionType::ClearUpdateCache->value); ?>',
    CHECK_UPDATES:   '<?php echo esc_js(AjaxActionType::CheckForUpdates->value); ?>'
};

jQuery(document).ready(function($) {
    var ajaxNonce = '<?php echo wp_create_nonce(NonceType::Admin->value); ?>';
    var $status = $('#update_action_status');

    function showStatus(message, isError) {
        $status.html(message).css('color', isError ? '#dc3232' : '#46b450');
        setTimeout(function() { $status.fadeOut(); }, 5000);
        $status.show();
    }

    $('#btn_test_connection').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).find('.dashicons').removeClass('dashicons-yes-alt').addClass('dashicons-update spin');
        
        $.post(ajaxurl, {
            action: UPDATE_AJAX.TEST_CONNECTION,
            nonce: ajaxNonce
        }, function(response) {
            if (response.success) {
                showStatus('✓ ' + response.data.message + (response.data.resolved_url ? ' → ' + response.data.resolved_url : ''), false);
                if (response.data.resolved_url) {
                    $('#resolved_url_display').text(response.data.resolved_url);
                }
            } else {
                showStatus('✗ ' + (response.data.message || 'Connection failed'), true);
            }
        }).fail(function() {
            showStatus('✗ Request failed', true);
        }).always(function() {
            $btn.prop('disabled', false).find('.dashicons').removeClass('dashicons-update spin').addClass('dashicons-yes-alt');
        });
    });

    $('#btn_clear_cache').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true);
        
        $.post(ajaxurl, {
            action: UPDATE_AJAX.CLEAR_CACHE,
            nonce: ajaxNonce
        }, function(response) {
            if (response.success) {
                showStatus('✓ ' + response.data.message, false);
                $('#resolved_url_display').text('');
            } else {
                showStatus('✗ ' + (response.data.message || 'Failed to clear cache'), true);
            }
        }).fail(function() {
            showStatus('✗ Request failed', true);
        }).always(function() {
            $btn.prop('disabled', false);
        });
    });

    $('#btn_check_updates').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).find('.dashicons').addClass('spin');
        
        $.post(ajaxurl, {
            action: UPDATE_AJAX.CHECK_UPDATES,
            nonce: ajaxNonce
        }, function(response) {
            if (response.success) {
                var msg = response.data.message;
                if (response.data.update_info && response.data.update_info.version) {
                    msg += ' (v' + response.data.update_info.version + ')';
                }
                showStatus('✓ ' + msg, false);
            } else {
                showStatus('✗ ' + (response.data.message || 'Update check failed'), true);
            }
        }).fail(function() {
            showStatus('✗ Request failed', true);
        }).always(function() {
            $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
        });
    });
});
</script>

<style>
.dashicons.spin {
    animation: spin 1s linear infinite;
}
@keyframes spin {
    100% { transform: rotate(360deg); }
}
.riseup-error-text {
    color: #dc3232;
}
</style>

<script type="text/javascript">
// --- JS Constants from PHP Enums (eliminates magic strings) ---
var SNAP_FREQUENCY = {
    MANUAL:  '<?php echo esc_js(SnapshotFrequencyType::Manual->value); ?>',
    HOURLY:  '<?php echo esc_js(SnapshotFrequencyType::Hourly->value); ?>',
    DAILY:   '<?php echo esc_js(SnapshotFrequencyType::Daily->value); ?>',
    WEEKLY:  '<?php echo esc_js(SnapshotFrequencyType::Weekly->value); ?>',
    MONTHLY: '<?php echo esc_js(SnapshotFrequencyType::Monthly->value); ?>'
};
var SNAP_RETENTION = {
    NONE:  '<?php echo esc_js(RetentionType::None->value); ?>',
    DAYS:  '<?php echo esc_js(RetentionType::Days->value); ?>',
    COUNT: '<?php echo esc_js(RetentionType::Count->value); ?>'
};
var SNAP_STORAGE = {
    SINGLE:   '<?php echo esc_js(StorageModeType::Single->value); ?>',
    PERTABLE: '<?php echo esc_js(StorageModeType::PerTable->value); ?>'
};
var SNAP_AJAX = {
    STORAGE_STATS:     '<?php echo esc_js(AjaxActionType::GetSnapshotStorageStats->value); ?>',
    SAVE_SETTINGS:     '<?php echo esc_js(AjaxActionType::SaveSnapshotSettings->value); ?>',
    RUN_CLEANUP:       '<?php echo esc_js(AjaxActionType::RunSnapshotCleanup->value); ?>'
};

jQuery(document).ready(function($) {
    var ajaxNonce = '<?php echo wp_create_nonce(NonceType::Admin->value); ?>';
    var $snapStatus = $('#snap_action_status');

    function showSnapStatus(message, isError) {
        $snapStatus.html(message).css('color', isError ? '#dc3232' : '#46b450').show();
        setTimeout(function() { $snapStatus.fadeOut(); }, 5000);
    }

    // Toggle day row visibility based on frequency
    $('#snap_schedule_frequency').on('change', function() {
        var freq = $(this).val();
        var isHourly = freq === SNAP_FREQUENCY.HOURLY;
        $('#snap_day_row').toggle(freq === SNAP_FREQUENCY.WEEKLY || freq === SNAP_FREQUENCY.MONTHLY);
        $('#snap_schedule_time').closest('tr').toggle(!isHourly);
    });

    // Toggle retention rows based on type
    $('#snap_retention_type').on('change', function() {
        var type = $(this).val();
        $('#snap_retention_days_row').toggle(type === SNAP_RETENTION.DAYS);
        $('#snap_retention_count_row').toggle(type === SNAP_RETENTION.COUNT);
    });

    // Storage mode card selection
    $('input[name="snap_storage_mode"]').on('change', function() {
        var val = $(this).val();
        $('#mode_card_single').css({
            'border-color': val === SNAP_STORAGE.SINGLE ? '#2271b1' : '#dcdcde',
            'background': val === SNAP_STORAGE.SINGLE ? '#f0f6fc' : '#fff'
        });
        $('#mode_card_pertable').css({
            'border-color': val === SNAP_STORAGE.PERTABLE ? '#2271b1' : '#dcdcde',
            'background': val === SNAP_STORAGE.PERTABLE ? '#f0f6fc' : '#fff'
        });
    });

    // Worker pool slider live value update
    $('#snap_worker_pool_size').on('input', function() {
        $('#snap_worker_pool_value').text($(this).val());
    });

    // Load storage stats on page load
    function loadStorageStats() {
        $.post(ajaxurl, {
            action: SNAP_AJAX.STORAGE_STATS,
            nonce: ajaxNonce
        }, function(response) {
            if (response.success) {
                var d = response.data;
                var info = d.total_snapshots + ' snapshots, ' + d.total_size_formatted + ' used';
                if (d.disk_free_formatted) {
                    info += ' (' + d.disk_free_formatted + ' free)';
                }
                $('#snap_storage_info').html(info);
            } else {
                $('#snap_storage_info').html('<em>Unable to load stats</em>');
            }
        }).fail(function() {
            $('#snap_storage_info').html('<em>Unable to load stats</em>');
        });
    }
    loadStorageStats();

    // Save snapshot settings
    $('#btn_save_snapshot_settings').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true);

        $.post(ajaxurl, {
            action: SNAP_AJAX.SAVE_SETTINGS,
            nonce: ajaxNonce,
            preferred_provider: $('#snap_preferred_provider').val(),
            schedule_enabled: $('#snap_schedule_enabled').is(':checked') ? '1' : '0',
            schedule_frequency: $('#snap_schedule_frequency').val(),
            schedule_time: $('#snap_schedule_time').val(),
            schedule_day: $('#snap_schedule_day').val(),
            default_scope: $('#snap_default_scope').val(),
            retention_type: $('#snap_retention_type').val(),
            retention_days: $('#snap_retention_days').val(),
            retention_count: $('#snap_retention_count').val(),
            pre_restore_backup: $('#snap_pre_restore_backup').is(':checked') ? '1' : '0',
            max_snapshot_size_mb: $('#snap_max_size').val(),
            batch_size: $('#snap_batch_size').val(),
            storage_mode: $('input[name="snap_storage_mode"]:checked').val(),
            worker_pool_size: $('#snap_worker_pool_size').val()
        }, function(response) {
            showSnapStatus('✓ ' + (response.data ? response.data.message : 'Saved'), false);
        }).fail(function() {
            showSnapStatus('✗ Failed to save settings', true);
        }).always(function() {
            $btn.prop('disabled', false);
        });
    });

    // Run cleanup
    $('#btn_run_cleanup').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).find('.dashicons').addClass('spin');

        $.post(ajaxurl, {
            action: SNAP_AJAX.RUN_CLEANUP,
            nonce: ajaxNonce
        }, function(response) {
            if (response.success) {
                showSnapStatus('✓ ' + response.data.message, false);
                loadStorageStats();
            } else {
                showSnapStatus('✗ ' + (response.data ? response.data.message : 'Cleanup failed'), true);
            }
        }).fail(function() {
            showSnapStatus('✗ Request failed', true);
        }).always(function() {
            $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
        });
    });
});
</script>
