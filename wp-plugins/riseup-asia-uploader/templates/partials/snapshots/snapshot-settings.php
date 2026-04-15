<?php
/**
 * Snapshots Partial — Inline settings panel (schedule, retention, provider, worker pool).
 *
 * Variables expected: $pluginSlug.
 *
 * @package RiseupAsiaUploader
 * @since   2.33.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\RetentionType;
use RiseupAsia\Enums\SnapshotFrequencyType;
use RiseupAsia\Enums\SnapshotProviderType;
use RiseupAsia\Enums\SnapshotScopeType;
use RiseupAsia\Enums\StorageModeType;
?>
<!-- Snapshot Settings -->
<div class="riseup-card">
    <h2>
        <span class="dashicons dashicons-admin-generic"></span>
        <?php esc_html_e('Snapshot Settings', $pluginSlug); ?>
    </h2>

    <div id="settings_loading">
        <span class="spinner is-active" style="float: none;"></span>
        <?php esc_html_e('Loading settings...', $pluginSlug); ?>
    </div>

    <div id="settings_form" style="display: none;">
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="setting_schedule"><?php esc_html_e('Schedule', $pluginSlug); ?></label>
                </th>
                <td>
                    <select id="setting_schedule">
                        <option value="<?php echo esc_attr(SnapshotFrequencyType::Manual->value); ?>"><?php esc_html_e('Manual Only', $pluginSlug); ?></option>
                        <option value="<?php echo esc_attr(SnapshotFrequencyType::Hourly->value); ?>"><?php esc_html_e('Hourly', $pluginSlug); ?></option>
                        <option value="<?php echo esc_attr(SnapshotFrequencyType::Daily->value); ?>"><?php esc_html_e('Daily', $pluginSlug); ?></option>
                        <option value="<?php echo esc_attr(SnapshotFrequencyType::Weekly->value); ?>"><?php esc_html_e('Weekly', $pluginSlug); ?></option>
                        <option value="<?php echo esc_attr(SnapshotFrequencyType::Monthly->value); ?>"><?php esc_html_e('Monthly', $pluginSlug); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="setting_retention_type"><?php esc_html_e('Retention Policy', $pluginSlug); ?></label>
                </th>
                <td>
                    <select id="setting_retention_type">
                        <option value="<?php echo esc_attr(RetentionType::None->value); ?>"><?php esc_html_e('None (Manual Cleanup)', $pluginSlug); ?></option>
                        <option value="<?php echo esc_attr(RetentionType::Days->value); ?>"><?php esc_html_e('Keep for N Days', $pluginSlug); ?></option>
                        <option value="<?php echo esc_attr(RetentionType::Count->value); ?>"><?php esc_html_e('Keep Last N Snapshots', $pluginSlug); ?></option>
                    </select>
                </td>
            </tr>
            <tr id="retention_value_row" style="display: none;">
                <th scope="row">
                    <label for="setting_retention_value"><?php esc_html_e('Retention Value', $pluginSlug); ?></label>
                </th>
                <td>
                    <input type="number" id="setting_retention_value" min="1" max="365" value="30" class="small-text">
                    <span id="retention_value_label"></span>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="setting_scope"><?php esc_html_e('Default Scope', $pluginSlug); ?></label>
                </th>
                <td>
                    <select id="setting_scope">
                        <option value="<?php echo esc_attr(SnapshotScopeType::All->value); ?>"><?php esc_html_e('All Tables', $pluginSlug); ?></option>
                        <option value="<?php echo esc_attr(SnapshotScopeType::WordPress->value); ?>"><?php esc_html_e('WordPress Core', $pluginSlug); ?></option>
                        <option value="<?php echo esc_attr(SnapshotScopeType::Content->value); ?>"><?php esc_html_e('Content Only', $pluginSlug); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="setting_provider"><?php esc_html_e('Default Provider', $pluginSlug); ?></label>
                </th>
                <td>
                    <select id="setting_provider">
                        <option value="<?php echo esc_attr(SnapshotProviderType::Auto->value); ?>"><?php esc_html_e('Auto-Detect', $pluginSlug); ?></option>
                        <option value="<?php echo esc_attr(SnapshotProviderType::Native->value); ?>"><?php esc_html_e('Native SQLite', $pluginSlug); ?></option>
                    </select>
                </td>
            </tr>
        </table>

        <!-- Worker Pool & Storage Mode -->
        <h3 style="margin-top: 25px; padding-top: 15px; border-top: 1px solid #eee;">
            <span class="dashicons dashicons-performance"></span>
            <?php esc_html_e('Worker Pool & Storage', $pluginSlug); ?>
        </h3>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="setting_storage_mode"><?php esc_html_e('Storage Mode', $pluginSlug); ?></label>
                </th>
                <td>
                    <div class="riseup-storage-cards">
                        <label class="riseup-storage-card" data-mode="<?php echo esc_attr(StorageModeType::Single->value); ?>">
                            <input type="radio" name="setting_storage_mode" value="<?php echo esc_attr(StorageModeType::Single->value); ?>">
                            <div class="riseup-storage-card-inner">
                                <span class="dashicons dashicons-media-archive" style="font-size: 24px; width: 24px; height: 24px;"></span>
                                <strong><?php esc_html_e('Single File', $pluginSlug); ?></strong>
                                <span class="description"><?php esc_html_e('One SQLite file per snapshot', $pluginSlug); ?></span>
                            </div>
                        </label>
                        <label class="riseup-storage-card active" data-mode="<?php echo esc_attr(StorageModeType::PerTable->value); ?>">
                            <input type="radio" name="setting_storage_mode" value="<?php echo esc_attr(StorageModeType::PerTable->value); ?>" checked>
                            <div class="riseup-storage-card-inner">
                                <span class="dashicons dashicons-grid-view" style="font-size: 24px; width: 24px; height: 24px;"></span>
                                <strong><?php esc_html_e('Per-Table', $pluginSlug); ?></strong>
                                <span class="description"><?php esc_html_e('Separate file per table (faster)', $pluginSlug); ?></span>
                            </div>
                        </label>
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="setting_worker_pool"><?php esc_html_e('Worker Pool Size', $pluginSlug); ?></label>
                </th>
                <td>
                    <div class="riseup-slider-row">
                        <input type="range" id="setting_worker_pool" min="1" max="10" value="5" class="riseup-range-slider">
                        <span id="worker_pool_display" class="riseup-slider-value">5</span>
                    </div>
                    <p class="description"><?php esc_html_e('Number of tables to export in parallel per batch.', $pluginSlug); ?></p>
                </td>
            </tr>
        </table>

        <p>
            <button type="button" id="btn_save_settings" class="button button-primary">
                <?php esc_html_e('Save Settings', $pluginSlug); ?>
            </button>
            <span id="settings_status" class="riseup-inline-status"></span>
        </p>
    </div>
</div>
