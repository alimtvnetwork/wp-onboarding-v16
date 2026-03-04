<?php
/**
 * Admin Snapshots Dashboard Template
 *
 * Slim orchestrator — delegates styles, modals, and scripts to partials.
 *
 * @package RiseupAsiaUploader
 * @since   1.9.0
 */

use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\RetentionType;
use RiseupAsia\Enums\SnapshotFrequencyType;
use RiseupAsia\Enums\SnapshotProviderType;
use RiseupAsia\Enums\SnapshotScopeType;
use RiseupAsia\Enums\StorageModeType;

if (!defined('ABSPATH')) {
    exit;
}

$pluginName = PluginConfigType::Name->value;
$pluginSlug = PluginConfigType::Slug->value;
?>
<div class="wrap riseup-admin">
    <h1>
        <span class="dashicons dashicons-database"></span>
        <?php echo esc_html(__('Database Snapshots', $pluginSlug)); ?>
        <span class="riseup-version-badge">v<?php echo esc_html(PluginConfigType::Version->value); ?></span>
    </h1>

    <p class="description">
        <?php esc_html_e('Create, manage, and restore database snapshots. Snapshots are stored as SQLite files and can be exported/imported as ZIP archives.', $pluginSlug); ?>
    </p>

    <!-- Actions Bar -->
    <div class="riseup-card riseup-snapshots-actions">
        <div class="riseup-actions-row">
            <button type="button" id="btn_snapshot_now" class="button button-primary">
                <span class="dashicons dashicons-camera"></span>
                <?php esc_html_e('Snapshot Now', $pluginSlug); ?>
            </button>

            <button type="button" id="btn_incremental_now" class="button button-secondary">
                <span class="dashicons dashicons-randomize"></span>
                <?php esc_html_e('Incremental Backup', $pluginSlug); ?>
            </button>

            <button type="button" id="btn_import_snapshot" class="button button-secondary">
                <span class="dashicons dashicons-upload"></span>
                <?php esc_html_e('Import Snapshot', $pluginSlug); ?>
            </button>

            <button type="button" id="btn_refresh_list" class="button button-secondary">
                <span class="dashicons dashicons-update"></span>
                <?php esc_html_e('Refresh', $pluginSlug); ?>
            </button>

            <span id="snapshot_action_status" class="riseup-inline-status"></span>
        </div>

        <!-- Snapshot Now Options (hidden by default) -->
        <div id="snapshot_options" class="riseup-snapshot-options" style="display: none;">
            <h3><?php esc_html_e('Snapshot Options', $pluginSlug); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="snapshot_scope"><?php esc_html_e('Scope', $pluginSlug); ?></label>
                    </th>
                    <td>
                        <select id="snapshot_scope">
                            <option value="<?php echo esc_attr(SnapshotScopeType::All->value); ?>"><?php esc_html_e('All Tables', $pluginSlug); ?></option>
                            <option value="<?php echo esc_attr(SnapshotScopeType::WordPress->value); ?>"><?php esc_html_e('WordPress Core Only', $pluginSlug); ?></option>
                            <option value="<?php echo esc_attr(SnapshotScopeType::Content->value); ?>"><?php esc_html_e('Content Only (Posts, Terms, Comments)', $pluginSlug); ?></option>
                            <option value="<?php echo esc_attr(SnapshotScopeType::Custom->value); ?>"><?php esc_html_e('Custom Selection', $pluginSlug); ?></option>
                        </select>
                    </td>
                </tr>
                <tr id="custom_tables_row" style="display: none;">
                    <th scope="row">
                        <label for="snapshot_tables"><?php esc_html_e('Tables', $pluginSlug); ?></label>
                    </th>
                    <td>
                        <div id="snapshot_tables_list" class="riseup-tables-list">
                            <em><?php esc_html_e('Loading tables...', $pluginSlug); ?></em>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="snapshot_provider"><?php esc_html_e('Provider', $pluginSlug); ?></label>
                    </th>
                    <td>
                        <select id="snapshot_provider">
                            <option value="<?php echo esc_attr(SnapshotProviderType::Auto->value); ?>"><?php esc_html_e('Auto-Detect (Recommended)', $pluginSlug); ?></option>
                            <option value="<?php echo esc_attr(SnapshotProviderType::Native->value); ?>"><?php esc_html_e('Native SQLite', $pluginSlug); ?></option>
                        </select>
                    </td>
                </tr>
            </table>
            <p>
                <button type="button" id="btn_confirm_snapshot" class="button button-primary">
                    <span class="dashicons dashicons-yes"></span>
                    <?php esc_html_e('Create Snapshot', $pluginSlug); ?>
                </button>
                <button type="button" id="btn_cancel_snapshot" class="button button-secondary">
                    <?php esc_html_e('Cancel', $pluginSlug); ?>
                </button>
            </p>
        </div>

        <!-- Import form (hidden by default) -->
        <div id="import_form" style="display: none;">
            <h3><?php esc_html_e('Import Snapshot', $pluginSlug); ?></h3>
            <p class="description"><?php esc_html_e('Upload a snapshot ZIP archive to import.', $pluginSlug); ?></p>
            <input type="file" id="import_file" accept=".zip">
            <p>
                <button type="button" id="btn_confirm_import" class="button button-primary" disabled>
                    <span class="dashicons dashicons-upload"></span>
                    <?php esc_html_e('Upload & Import', $pluginSlug); ?>
                </button>
                <button type="button" id="btn_cancel_import" class="button button-secondary">
                    <?php esc_html_e('Cancel', $pluginSlug); ?>
                </button>
            </p>
        </div>
    </div>

    <!-- Progress Panel (hidden by default, shown when a job is running) -->
    <div id="progress_panel" class="riseup-card" style="display: none;">
        <h2>
            <span class="dashicons dashicons-performance" style="color: #dba617;"></span>
            <?php esc_html_e('Snapshot In Progress', $pluginSlug); ?>
            <span id="progress_percent_badge" class="riseup-badge" style="background: #2271b1; margin-left: 10px;">0%</span>
        </h2>
        <div class="riseup-progress-bar-wrap">
            <div id="progress_bar" class="riseup-progress-bar" style="width: 0%;"></div>
        </div>
        <div id="progress_meta" class="riseup-progress-meta">
            <span id="progress_status_text"></span>
        </div>
        <div id="progress_tables" class="riseup-progress-tables" style="display: none;">
            <h4><?php esc_html_e('Table Progress', $pluginSlug); ?></h4>
            <div id="progress_tables_list"></div>
        </div>
    </div>

    <!-- Snapshot List -->
    <div class="riseup-card">
        <h2><?php esc_html_e('Snapshots', $pluginSlug); ?></h2>

        <div id="snapshots_loading" style="display: none;">
            <span class="spinner is-active" style="float: none;"></span>
            <?php esc_html_e('Loading snapshots...', $pluginSlug); ?>
        </div>

        <div id="snapshots_empty" style="display: none;">
            <p><em><?php esc_html_e('No snapshots found. Click "Snapshot Now" to create your first backup.', $pluginSlug); ?></em></p>
        </div>

        <table id="snapshots_table" class="wp-list-table widefat fixed striped" style="display: none;">
            <thead>
                <tr>
                    <th class="column-id" style="width: 50px;">#</th>
                    <th class="column-type" style="width: 40px;"></th>
                    <th class="column-filename"><?php esc_html_e('Filename', $pluginSlug); ?></th>
                    <th class="column-scope"><?php esc_html_e('Scope', $pluginSlug); ?></th>
                    <th class="column-provider"><?php esc_html_e('Provider', $pluginSlug); ?></th>
                    <th class="column-tables" style="width: 60px;"><?php esc_html_e('Tables', $pluginSlug); ?></th>
                    <th class="column-rows" style="width: 80px;"><?php esc_html_e('Rows', $pluginSlug); ?></th>
                    <th class="column-size" style="width: 80px;"><?php esc_html_e('Size', $pluginSlug); ?></th>
                    <th class="column-status" style="width: 100px;"><?php esc_html_e('Status', $pluginSlug); ?></th>
                    <th class="column-date"><?php esc_html_e('Created', $pluginSlug); ?></th>
                    <th class="column-actions" style="width: 200px;"><?php esc_html_e('Actions', $pluginSlug); ?></th>
                </tr>
            </thead>
            <tbody id="snapshots_tbody">
            </tbody>
        </table>

        <div id="snapshots_pagination" class="tablenav bottom" style="display: none;">
            <div class="tablenav-pages">
                <span class="displaying-num" id="snapshots_count"></span>
                <span class="pagination-links" id="snapshots_pages"></span>
            </div>
        </div>
    </div>

    <!-- Storage Analytics & Calendar Row -->
    <div class="riseup-analytics-row">
        <!-- Storage Analytics Chart -->
        <div class="riseup-card riseup-analytics-chart-card">
            <h2>
                <span class="dashicons dashicons-chart-bar"></span>
                <?php esc_html_e('Storage Analytics', $pluginSlug); ?>
            </h2>
            <div id="analytics_loading">
                <span class="spinner is-active" style="float: none;"></span>
                <?php esc_html_e('Loading analytics...', $pluginSlug); ?>
            </div>
            <div id="analytics_content" style="display: none;">
                <div class="riseup-analytics-summary">
                    <div class="riseup-stat-card">
                        <span class="riseup-stat-value" id="stat_total_size">—</span>
                        <span class="riseup-stat-label"><?php esc_html_e('Total Size', $pluginSlug); ?></span>
                    </div>
                    <div class="riseup-stat-card">
                        <span class="riseup-stat-value" id="stat_total_count">—</span>
                        <span class="riseup-stat-label"><?php esc_html_e('Snapshots', $pluginSlug); ?></span>
                    </div>
                    <div class="riseup-stat-card">
                        <span class="riseup-stat-value" id="stat_avg_size">—</span>
                        <span class="riseup-stat-label"><?php esc_html_e('Avg Size', $pluginSlug); ?></span>
                    </div>
                    <div class="riseup-stat-card">
                        <span class="riseup-stat-value" id="stat_largest">—</span>
                        <span class="riseup-stat-label"><?php esc_html_e('Largest', $pluginSlug); ?></span>
                    </div>
                </div>
                <div class="riseup-chart-container">
                    <div class="riseup-chart-y-axis" id="chart_y_axis"></div>
                    <div class="riseup-chart-bars" id="chart_bars"></div>
                </div>
                <div class="riseup-chart-legend">
                    <span class="riseup-legend-item"><span class="riseup-legend-dot" style="background:#2271b1;"></span> <?php esc_html_e('Full', $pluginSlug); ?></span>
                    <span class="riseup-legend-item"><span class="riseup-legend-dot" style="background:#7b1fa2;"></span> <?php esc_html_e('Incremental', $pluginSlug); ?></span>
                </div>
            </div>
            <div id="analytics_empty" style="display: none;">
                <p><em><?php esc_html_e('No snapshot data available for analytics.', $pluginSlug); ?></em></p>
            </div>
        </div>

        <!-- Monthly Calendar View -->
        <div class="riseup-card riseup-calendar-card">
            <h2>
                <span class="dashicons dashicons-calendar-alt"></span>
                <?php esc_html_e('Backup Calendar', $pluginSlug); ?>
            </h2>
            <div class="riseup-calendar-nav">
                <button type="button" id="cal_prev" class="button button-small">
                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                </button>
                <strong id="cal_month_label"></strong>
                <button type="button" id="cal_next" class="button button-small">
                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                </button>
            </div>
            <table class="riseup-calendar-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Sun', $pluginSlug); ?></th>
                        <th><?php esc_html_e('Mon', $pluginSlug); ?></th>
                        <th><?php esc_html_e('Tue', $pluginSlug); ?></th>
                        <th><?php esc_html_e('Wed', $pluginSlug); ?></th>
                        <th><?php esc_html_e('Thu', $pluginSlug); ?></th>
                        <th><?php esc_html_e('Fri', $pluginSlug); ?></th>
                        <th><?php esc_html_e('Sat', $pluginSlug); ?></th>
                    </tr>
                </thead>
                <tbody id="cal_body"></tbody>
            </table>
            <div class="riseup-calendar-legend">
                <span class="riseup-legend-item"><span class="riseup-cal-dot riseup-cal-dot-full"></span> <?php esc_html_e('Full', $pluginSlug); ?></span>
                <span class="riseup-legend-item"><span class="riseup-cal-dot riseup-cal-dot-incr"></span> <?php esc_html_e('Incremental', $pluginSlug); ?></span>
                <span class="riseup-legend-item"><span class="riseup-cal-dot riseup-cal-dot-scheduled"></span> <?php esc_html_e('Scheduled', $pluginSlug); ?></span>
            </div>
        </div>
    </div>

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

    <!-- Providers Info -->
    <div class="riseup-card">
        <h2>
            <span class="dashicons dashicons-plugins-checked"></span>
            <?php esc_html_e('Available Providers', $pluginSlug); ?>
        </h2>
        <div id="providers_loading">
            <span class="spinner is-active" style="float: none;"></span>
            <?php esc_html_e('Detecting providers...', $pluginSlug); ?>
        </div>
        <div id="providers_list" style="display: none;"></div>
    </div>
</div>

<?php
// ── Partials ────────────────────────────────────────────────────────────────
include __DIR__ . '/partials/snapshots/modals.php';
include __DIR__ . '/partials/snapshots/scripts.php';
include __DIR__ . '/partials/snapshots/styles.php';
?>
