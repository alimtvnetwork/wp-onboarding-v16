<?php
/**
 * Settings Partial — Database Snapshot Settings card.
 *
 * Variables expected: $pluginSlug, $snapshotSettings, $snapshotProviders.
 *
 * @package RiseupAsiaUploader
 * @since   1.64.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\AdminPageType;
use RiseupAsia\Enums\RetentionType;
use RiseupAsia\Enums\SnapshotConfigType;
use RiseupAsia\Enums\SnapshotFrequencyType;
use RiseupAsia\Enums\SnapshotProviderType;
use RiseupAsia\Enums\SnapshotScopeType;
use RiseupAsia\Enums\StorageModeType;
use RiseupAsia\Helpers\BooleanHelpers;
?>
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
                           max="<?php echo esc_attr(SnapshotConfigType::workerPoolMax()); ?>" 
                           value="<?php echo esc_attr($snapshotSettings['worker_pool_size']); ?>" 
                           style="flex: 1; accent-color: #2271b1;">
                    <span id="snap_worker_pool_value" style="font-family: monospace; font-size: 14px; min-width: 24px; text-align: center; font-weight: 600; color: #2271b1;"><?php echo esc_html($snapshotSettings['worker_pool_size']); ?></span>
                </div>
                <p class="description"><?php printf(esc_html__('Number of concurrent backup workers (%d–%d). Higher values export faster but use more resources.', $pluginSlug), SnapshotConfigType::WorkerPoolMin->value, SnapshotConfigType::workerPoolMax()); ?></p>
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
