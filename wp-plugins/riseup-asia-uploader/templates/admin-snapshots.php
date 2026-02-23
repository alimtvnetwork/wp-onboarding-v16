<?php
/**
 * Admin Snapshots Dashboard Template
 *
 * @package RiseupAsiaUploader
 * @since   1.9.0
 */

use RiseupAsia\Enums\AdminPageType;
use RiseupAsia\Enums\AjaxActionType;
use RiseupAsia\Enums\EndpointType;
use RiseupAsia\Enums\NonceType;
use RiseupAsia\Enums\PaginationConfigType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\RestoreModeType;
use RiseupAsia\Enums\RetentionType;
use RiseupAsia\Enums\SnapshotFrequencyType;
use RiseupAsia\Enums\SnapshotModeType;
use RiseupAsia\Enums\SnapshotProviderType;
use RiseupAsia\Enums\SnapshotScopeType;
use RiseupAsia\Enums\SnapshotStatusType;
use RiseupAsia\Enums\StatusType;
use RiseupAsia\Enums\StorageModeType;

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap riseup-admin">
    <h1>
        <span class="dashicons dashicons-database"></span>
        <?php esc_html_e('Database Snapshots', 'riseup-asia-uploader'); ?>
        <span class="riseup-version-badge">v<?php echo esc_html(PluginConfigType::Version->value); ?></span>
    </h1>

    <p class="description">
        <?php esc_html_e('Create, manage, and restore database snapshots. Snapshots are stored as SQLite files and can be exported/imported as ZIP archives.', 'riseup-asia-uploader'); ?>
    </p>

    <!-- Actions Bar -->
    <div class="riseup-card riseup-snapshots-actions">
        <div class="riseup-actions-row">
            <button type="button" id="btn_snapshot_now" class="button button-primary">
                <span class="dashicons dashicons-camera"></span>
                <?php esc_html_e('Snapshot Now', 'riseup-asia-uploader'); ?>
            </button>

            <button type="button" id="btn_incremental_now" class="button button-secondary">
                <span class="dashicons dashicons-randomize"></span>
                <?php esc_html_e('Incremental Backup', 'riseup-asia-uploader'); ?>
            </button>

            <button type="button" id="btn_import_snapshot" class="button button-secondary">
                <span class="dashicons dashicons-upload"></span>
                <?php esc_html_e('Import Snapshot', 'riseup-asia-uploader'); ?>
            </button>

            <button type="button" id="btn_refresh_list" class="button button-secondary">
                <span class="dashicons dashicons-update"></span>
                <?php esc_html_e('Refresh', 'riseup-asia-uploader'); ?>
            </button>

            <span id="snapshot_action_status" class="riseup-inline-status"></span>
        </div>

        <!-- Snapshot Now Options (hidden by default) -->
        <div id="snapshot_options" class="riseup-snapshot-options" style="display: none;">
            <h3><?php esc_html_e('Snapshot Options', 'riseup-asia-uploader'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="snapshot_scope"><?php esc_html_e('Scope', 'riseup-asia-uploader'); ?></label>
                    </th>
                    <td>
                        <select id="snapshot_scope">
                            <option value="<?php echo esc_attr(SnapshotScopeType::All->value); ?>"><?php esc_html_e('All Tables', 'riseup-asia-uploader'); ?></option>
                            <option value="<?php echo esc_attr(SnapshotScopeType::WordPress->value); ?>"><?php esc_html_e('WordPress Core Only', 'riseup-asia-uploader'); ?></option>
                            <option value="<?php echo esc_attr(SnapshotScopeType::Content->value); ?>"><?php esc_html_e('Content Only (Posts, Terms, Comments)', 'riseup-asia-uploader'); ?></option>
                            <option value="<?php echo esc_attr(SnapshotScopeType::Custom->value); ?>"><?php esc_html_e('Custom Selection', 'riseup-asia-uploader'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr id="custom_tables_row" style="display: none;">
                    <th scope="row">
                        <label for="snapshot_tables"><?php esc_html_e('Tables', 'riseup-asia-uploader'); ?></label>
                    </th>
                    <td>
                        <div id="snapshot_tables_list" class="riseup-tables-list">
                            <em><?php esc_html_e('Loading tables...', 'riseup-asia-uploader'); ?></em>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="snapshot_provider"><?php esc_html_e('Provider', 'riseup-asia-uploader'); ?></label>
                    </th>
                    <td>
                        <select id="snapshot_provider">
                            <option value="<?php echo esc_attr(SnapshotProviderType::Auto->value); ?>"><?php esc_html_e('Auto-Detect (Recommended)', 'riseup-asia-uploader'); ?></option>
                            <option value="<?php echo esc_attr(SnapshotProviderType::Native->value); ?>"><?php esc_html_e('Native SQLite', 'riseup-asia-uploader'); ?></option>
                        </select>
                    </td>
                </tr>
            </table>
            <p>
                <button type="button" id="btn_confirm_snapshot" class="button button-primary">
                    <span class="dashicons dashicons-yes"></span>
                    <?php esc_html_e('Create Snapshot', 'riseup-asia-uploader'); ?>
                </button>
                <button type="button" id="btn_cancel_snapshot" class="button button-secondary">
                    <?php esc_html_e('Cancel', 'riseup-asia-uploader'); ?>
                </button>
            </p>
        </div>

        <!-- Import form (hidden by default) -->
        <div id="import_form" style="display: none;">
            <h3><?php esc_html_e('Import Snapshot', 'riseup-asia-uploader'); ?></h3>
            <p class="description"><?php esc_html_e('Upload a snapshot ZIP archive to import.', 'riseup-asia-uploader'); ?></p>
            <input type="file" id="import_file" accept=".zip">
            <p>
                <button type="button" id="btn_confirm_import" class="button button-primary" disabled>
                    <span class="dashicons dashicons-upload"></span>
                    <?php esc_html_e('Upload & Import', 'riseup-asia-uploader'); ?>
                </button>
                <button type="button" id="btn_cancel_import" class="button button-secondary">
                    <?php esc_html_e('Cancel', 'riseup-asia-uploader'); ?>
                </button>
            </p>
        </div>
    </div>

    <!-- Progress Panel (hidden by default, shown when a job is running) -->
    <div id="progress_panel" class="riseup-card" style="display: none;">
        <h2>
            <span class="dashicons dashicons-performance" style="color: #dba617;"></span>
            <?php esc_html_e('Snapshot In Progress', 'riseup-asia-uploader'); ?>
            <span id="progress_percent_badge" class="riseup-badge" style="background: #2271b1; margin-left: 10px;">0%</span>
        </h2>
        <div class="riseup-progress-bar-wrap">
            <div id="progress_bar" class="riseup-progress-bar" style="width: 0%;"></div>
        </div>
        <div id="progress_meta" class="riseup-progress-meta">
            <span id="progress_status_text"></span>
        </div>
        <div id="progress_tables" class="riseup-progress-tables" style="display: none;">
            <h4><?php esc_html_e('Table Progress', 'riseup-asia-uploader'); ?></h4>
            <div id="progress_tables_list"></div>
        </div>
    </div>

    <!-- Snapshot List -->
    <div class="riseup-card">
        <h2><?php esc_html_e('Snapshots', 'riseup-asia-uploader'); ?></h2>

        <div id="snapshots_loading" style="display: none;">
            <span class="spinner is-active" style="float: none;"></span>
            <?php esc_html_e('Loading snapshots...', 'riseup-asia-uploader'); ?>
        </div>

        <div id="snapshots_empty" style="display: none;">
            <p><em><?php esc_html_e('No snapshots found. Click "Snapshot Now" to create your first backup.', 'riseup-asia-uploader'); ?></em></p>
        </div>

        <table id="snapshots_table" class="wp-list-table widefat fixed striped" style="display: none;">
            <thead>
                <tr>
                    <th class="column-id" style="width: 50px;">#</th>
                    <th class="column-type" style="width: 40px;"></th>
                    <th class="column-filename"><?php esc_html_e('Filename', 'riseup-asia-uploader'); ?></th>
                    <th class="column-scope"><?php esc_html_e('Scope', 'riseup-asia-uploader'); ?></th>
                    <th class="column-provider"><?php esc_html_e('Provider', 'riseup-asia-uploader'); ?></th>
                    <th class="column-tables" style="width: 60px;"><?php esc_html_e('Tables', 'riseup-asia-uploader'); ?></th>
                    <th class="column-rows" style="width: 80px;"><?php esc_html_e('Rows', 'riseup-asia-uploader'); ?></th>
                    <th class="column-size" style="width: 80px;"><?php esc_html_e('Size', 'riseup-asia-uploader'); ?></th>
                    <th class="column-status" style="width: 100px;"><?php esc_html_e('Status', 'riseup-asia-uploader'); ?></th>
                    <th class="column-date"><?php esc_html_e('Created', 'riseup-asia-uploader'); ?></th>
                    <th class="column-actions" style="width: 200px;"><?php esc_html_e('Actions', 'riseup-asia-uploader'); ?></th>
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
                <?php esc_html_e('Storage Analytics', 'riseup-asia-uploader'); ?>
            </h2>
            <div id="analytics_loading">
                <span class="spinner is-active" style="float: none;"></span>
                <?php esc_html_e('Loading analytics...', 'riseup-asia-uploader'); ?>
            </div>
            <div id="analytics_content" style="display: none;">
                <div class="riseup-analytics-summary">
                    <div class="riseup-stat-card">
                        <span class="riseup-stat-value" id="stat_total_size">—</span>
                        <span class="riseup-stat-label"><?php esc_html_e('Total Size', 'riseup-asia-uploader'); ?></span>
                    </div>
                    <div class="riseup-stat-card">
                        <span class="riseup-stat-value" id="stat_total_count">—</span>
                        <span class="riseup-stat-label"><?php esc_html_e('Snapshots', 'riseup-asia-uploader'); ?></span>
                    </div>
                    <div class="riseup-stat-card">
                        <span class="riseup-stat-value" id="stat_avg_size">—</span>
                        <span class="riseup-stat-label"><?php esc_html_e('Avg Size', 'riseup-asia-uploader'); ?></span>
                    </div>
                    <div class="riseup-stat-card">
                        <span class="riseup-stat-value" id="stat_largest">—</span>
                        <span class="riseup-stat-label"><?php esc_html_e('Largest', 'riseup-asia-uploader'); ?></span>
                    </div>
                </div>
                <div class="riseup-chart-container">
                    <div class="riseup-chart-y-axis" id="chart_y_axis"></div>
                    <div class="riseup-chart-bars" id="chart_bars"></div>
                </div>
                <div class="riseup-chart-legend">
                    <span class="riseup-legend-item"><span class="riseup-legend-dot" style="background:#2271b1;"></span> <?php esc_html_e('Full', 'riseup-asia-uploader'); ?></span>
                    <span class="riseup-legend-item"><span class="riseup-legend-dot" style="background:#7b1fa2;"></span> <?php esc_html_e('Incremental', 'riseup-asia-uploader'); ?></span>
                </div>
            </div>
            <div id="analytics_empty" style="display: none;">
                <p><em><?php esc_html_e('No snapshot data available for analytics.', 'riseup-asia-uploader'); ?></em></p>
            </div>
        </div>

        <!-- Monthly Calendar View -->
        <div class="riseup-card riseup-calendar-card">
            <h2>
                <span class="dashicons dashicons-calendar-alt"></span>
                <?php esc_html_e('Backup Calendar', 'riseup-asia-uploader'); ?>
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
                        <th><?php esc_html_e('Sun', 'riseup-asia-uploader'); ?></th>
                        <th><?php esc_html_e('Mon', 'riseup-asia-uploader'); ?></th>
                        <th><?php esc_html_e('Tue', 'riseup-asia-uploader'); ?></th>
                        <th><?php esc_html_e('Wed', 'riseup-asia-uploader'); ?></th>
                        <th><?php esc_html_e('Thu', 'riseup-asia-uploader'); ?></th>
                        <th><?php esc_html_e('Fri', 'riseup-asia-uploader'); ?></th>
                        <th><?php esc_html_e('Sat', 'riseup-asia-uploader'); ?></th>
                    </tr>
                </thead>
                <tbody id="cal_body"></tbody>
            </table>
            <div class="riseup-calendar-legend">
                <span class="riseup-legend-item"><span class="riseup-cal-dot riseup-cal-dot-full"></span> <?php esc_html_e('Full', 'riseup-asia-uploader'); ?></span>
                <span class="riseup-legend-item"><span class="riseup-cal-dot riseup-cal-dot-incr"></span> <?php esc_html_e('Incremental', 'riseup-asia-uploader'); ?></span>
                <span class="riseup-legend-item"><span class="riseup-cal-dot riseup-cal-dot-scheduled"></span> <?php esc_html_e('Scheduled', 'riseup-asia-uploader'); ?></span>
            </div>
        </div>
    </div>

    <!-- Snapshot Settings -->
    <div class="riseup-card">
        <h2>
            <span class="dashicons dashicons-admin-generic"></span>
            <?php esc_html_e('Snapshot Settings', 'riseup-asia-uploader'); ?>
        </h2>

        <div id="settings_loading">
            <span class="spinner is-active" style="float: none;"></span>
            <?php esc_html_e('Loading settings...', 'riseup-asia-uploader'); ?>
        </div>

        <div id="settings_form" style="display: none;">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="setting_schedule"><?php esc_html_e('Schedule', 'riseup-asia-uploader'); ?></label>
                    </th>
                    <td>
                        <select id="setting_schedule">
                            <option value="<?php echo esc_attr(SnapshotFrequencyType::Manual->value); ?>"><?php esc_html_e('Manual Only', 'riseup-asia-uploader'); ?></option>
                            <option value="<?php echo esc_attr(SnapshotFrequencyType::Hourly->value); ?>"><?php esc_html_e('Hourly', 'riseup-asia-uploader'); ?></option>
                            <option value="<?php echo esc_attr(SnapshotFrequencyType::Daily->value); ?>"><?php esc_html_e('Daily', 'riseup-asia-uploader'); ?></option>
                            <option value="<?php echo esc_attr(SnapshotFrequencyType::Weekly->value); ?>"><?php esc_html_e('Weekly', 'riseup-asia-uploader'); ?></option>
                            <option value="<?php echo esc_attr(SnapshotFrequencyType::Monthly->value); ?>"><?php esc_html_e('Monthly', 'riseup-asia-uploader'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="setting_retention_type"><?php esc_html_e('Retention Policy', 'riseup-asia-uploader'); ?></label>
                    </th>
                    <td>
                        <select id="setting_retention_type">
                            <option value="<?php echo esc_attr(RetentionType::None->value); ?>"><?php esc_html_e('None (Manual Cleanup)', 'riseup-asia-uploader'); ?></option>
                            <option value="<?php echo esc_attr(RetentionType::Days->value); ?>"><?php esc_html_e('Keep for N Days', 'riseup-asia-uploader'); ?></option>
                            <option value="<?php echo esc_attr(RetentionType::Count->value); ?>"><?php esc_html_e('Keep Last N Snapshots', 'riseup-asia-uploader'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr id="retention_value_row" style="display: none;">
                    <th scope="row">
                        <label for="setting_retention_value"><?php esc_html_e('Retention Value', 'riseup-asia-uploader'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="setting_retention_value" min="1" max="365" value="30" class="small-text">
                        <span id="retention_value_label"></span>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="setting_scope"><?php esc_html_e('Default Scope', 'riseup-asia-uploader'); ?></label>
                    </th>
                    <td>
                        <select id="setting_scope">
                            <option value="<?php echo esc_attr(SnapshotScopeType::All->value); ?>"><?php esc_html_e('All Tables', 'riseup-asia-uploader'); ?></option>
                            <option value="<?php echo esc_attr(SnapshotScopeType::WordPress->value); ?>"><?php esc_html_e('WordPress Core', 'riseup-asia-uploader'); ?></option>
                            <option value="<?php echo esc_attr(SnapshotScopeType::Content->value); ?>"><?php esc_html_e('Content Only', 'riseup-asia-uploader'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="setting_provider"><?php esc_html_e('Default Provider', 'riseup-asia-uploader'); ?></label>
                    </th>
                    <td>
                        <select id="setting_provider">
                            <option value="<?php echo esc_attr(SnapshotProviderType::Auto->value); ?>"><?php esc_html_e('Auto-Detect', 'riseup-asia-uploader'); ?></option>
                            <option value="<?php echo esc_attr(SnapshotProviderType::Native->value); ?>"><?php esc_html_e('Native SQLite', 'riseup-asia-uploader'); ?></option>
                        </select>
                    </td>
                </tr>
            </table>

            <!-- Worker Pool & Storage Mode -->
            <h3 style="margin-top: 25px; padding-top: 15px; border-top: 1px solid #eee;">
                <span class="dashicons dashicons-performance"></span>
                <?php esc_html_e('Worker Pool & Storage', 'riseup-asia-uploader'); ?>
            </h3>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="setting_storage_mode"><?php esc_html_e('Storage Mode', 'riseup-asia-uploader'); ?></label>
                    </th>
                    <td>
                        <div class="riseup-storage-cards">
                            <label class="riseup-storage-card" data-mode="<?php echo esc_attr(StorageModeType::Single->value); ?>">
                                <input type="radio" name="setting_storage_mode" value="<?php echo esc_attr(StorageModeType::Single->value); ?>">
                                <div class="riseup-storage-card-inner">
                                    <span class="dashicons dashicons-media-archive" style="font-size: 24px; width: 24px; height: 24px;"></span>
                                    <strong><?php esc_html_e('Single File', 'riseup-asia-uploader'); ?></strong>
                                    <span class="description"><?php esc_html_e('One SQLite file per snapshot', 'riseup-asia-uploader'); ?></span>
                                </div>
                            </label>
                            <label class="riseup-storage-card active" data-mode="<?php echo esc_attr(StorageModeType::PerTable->value); ?>">
                                <input type="radio" name="setting_storage_mode" value="<?php echo esc_attr(StorageModeType::PerTable->value); ?>" checked>
                                <div class="riseup-storage-card-inner">
                                    <span class="dashicons dashicons-grid-view" style="font-size: 24px; width: 24px; height: 24px;"></span>
                                    <strong><?php esc_html_e('Per-Table', 'riseup-asia-uploader'); ?></strong>
                                    <span class="description"><?php esc_html_e('Separate file per table (faster)', 'riseup-asia-uploader'); ?></span>
                                </div>
                            </label>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="setting_worker_pool"><?php esc_html_e('Worker Pool Size', 'riseup-asia-uploader'); ?></label>
                    </th>
                    <td>
                        <div class="riseup-slider-row">
                            <input type="range" id="setting_worker_pool" min="1" max="10" value="5" class="riseup-range-slider">
                            <span id="worker_pool_display" class="riseup-slider-value">5</span>
                        </div>
                        <p class="description"><?php esc_html_e('Number of tables to export in parallel per batch.', 'riseup-asia-uploader'); ?></p>
                    </td>
                </tr>
            </table>

            <p>
                <button type="button" id="btn_save_settings" class="button button-primary">
                    <?php esc_html_e('Save Settings', 'riseup-asia-uploader'); ?>
                </button>
                <span id="settings_status" class="riseup-inline-status"></span>
            </p>
        </div>
    </div>

    <!-- Providers Info -->
    <div class="riseup-card">
        <h2>
            <span class="dashicons dashicons-plugins-checked"></span>
            <?php esc_html_e('Available Providers', 'riseup-asia-uploader'); ?>
        </h2>
        <div id="providers_loading">
            <span class="spinner is-active" style="float: none;"></span>
            <?php esc_html_e('Detecting providers...', 'riseup-asia-uploader'); ?>
        </div>
        <div id="providers_list" style="display: none;"></div>
    </div>
</div>

<!-- Restore Confirmation Modal -->
<div id="restore_modal" class="riseup-modal" style="display: none;">
    <div class="riseup-modal-overlay"></div>
    <div class="riseup-modal-content">
        <h2>
            <span class="dashicons dashicons-warning" style="color: #d63638;"></span>
            <?php esc_html_e('Restore Database', 'riseup-asia-uploader'); ?>
        </h2>
        <p class="riseup-warning-text">
            <?php esc_html_e('This will replace your current database tables with the snapshot data. A pre-restore backup will be created automatically.', 'riseup-asia-uploader'); ?>
        </p>
        <div id="restore_incremental_warning" style="display: none;">
            <p class="riseup-warning-text" style="background: #fff8e5; border-left: 4px solid #dba617; padding: 10px 14px; color: #664d03;">
                <span class="dashicons dashicons-randomize" style="vertical-align: middle;"></span>
                <?php esc_html_e('This is an incremental snapshot. It will be merged with its parent full snapshot during restoration.', 'riseup-asia-uploader'); ?>
            </p>
        </div>
        <div id="restore_options">
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Snapshot', 'riseup-asia-uploader'); ?></th>
                    <td><strong id="restore_snapshot_name"></strong></td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="restore_mode"><?php esc_html_e('Mode', 'riseup-asia-uploader'); ?></label>
                    </th>
                    <td>
                        <select id="restore_mode">
                            <option value="<?php echo RestoreModeType::Full->value; ?>"><?php esc_html_e('Full Restore (All Tables)', 'riseup-asia-uploader'); ?></option>
                            <option value="<?php echo RestoreModeType::Selective->value; ?>"><?php esc_html_e('Selective (Choose Tables)', 'riseup-asia-uploader'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label>
                            <input type="checkbox" id="restore_create_backup" checked>
                            <?php esc_html_e('Create Pre-Restore Backup', 'riseup-asia-uploader'); ?>
                        </label>
                    </th>
                    <td>
                        <p class="description"><?php esc_html_e('Strongly recommended. Creates a snapshot before restoring.', 'riseup-asia-uploader'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        <p class="riseup-modal-actions">
            <button type="button" id="btn_confirm_restore" class="button button-primary" style="background: #d63638; border-color: #d63638;">
                <span class="dashicons dashicons-database-import"></span>
                <?php esc_html_e('Restore Now', 'riseup-asia-uploader'); ?>
            </button>
            <button type="button" id="btn_cancel_restore" class="button button-secondary">
                <?php esc_html_e('Cancel', 'riseup-asia-uploader'); ?>
            </button>
        </p>
    </div>
</div>

<!-- Download Error Modal -->
<div id="download_error_modal" class="riseup-modal" style="display: none;">
    <div class="riseup-modal-overlay"></div>
    <div class="riseup-modal-content" style="max-width: 640px;">
        <h2>
            <span class="dashicons dashicons-warning" style="color: #d63638;"></span>
            <?php esc_html_e('Download Failed', 'riseup-asia-uploader'); ?>
        </h2>
        <div id="download_error_summary" style="margin-bottom: 12px;">
            <p id="download_error_message" style="color: #d63638; font-weight: 500;"></p>
            <table class="form-table" style="margin: 0;">
                <tr>
                    <th scope="row" style="padding: 6px 10px 6px 0; width: 120px;"><?php esc_html_e('HTTP Status', 'riseup-asia-uploader'); ?></th>
                    <td style="padding: 6px 0;"><code id="download_error_status"></code></td>
                </tr>
                <tr>
                    <th scope="row" style="padding: 6px 10px 6px 0;"><?php esc_html_e('Plugin Version', 'riseup-asia-uploader'); ?></th>
                    <td style="padding: 6px 0;"><code id="download_error_version"></code></td>
                </tr>
                <tr>
                    <th scope="row" style="padding: 6px 10px 6px 0;"><?php esc_html_e('Timestamp', 'riseup-asia-uploader'); ?></th>
                    <td style="padding: 6px 0;"><code id="download_error_timestamp"></code></td>
                </tr>
            </table>
        </div>
        <div id="download_error_stack_section" style="display: none;">
            <h3 style="margin: 10px 0 6px; font-size: 13px; color: #7b1fa2;">
                <span class="dashicons dashicons-editor-code" style="vertical-align: middle; color: #7b1fa2;"></span>
                <?php esc_html_e('PHP Stack Trace', 'riseup-asia-uploader'); ?>
            </h3>
            <pre id="download_error_stack" class="riseup-stack-trace"></pre>
        </div>
        <div id="download_error_backend_section" style="display: none;">
            <h3 style="margin: 10px 0 6px; font-size: 13px; color: #b45309;">
                <span class="dashicons dashicons-admin-tools" style="vertical-align: middle; color: #b45309;"></span>
                <?php esc_html_e('Backend Details', 'riseup-asia-uploader'); ?>
            </h3>
            <pre id="download_error_backend" class="riseup-stack-trace" style="border-color: #fbbf24; background: #fffbeb;"></pre>
        </div>
        <p class="riseup-modal-actions">
            <button type="button" id="btn_copy_download_error" class="button button-secondary">
                <span class="dashicons dashicons-clipboard" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle;"></span>
                <?php esc_html_e('Copy Report', 'riseup-asia-uploader'); ?>
            </button>
            <button type="button" id="btn_close_download_error" class="button button-primary">
                <?php esc_html_e('Close', 'riseup-asia-uploader'); ?>
            </button>
        </p>
    </div>
</div>

<!-- Delete Confirmation Modal (for cascade warnings) -->
<div id="delete_modal" class="riseup-modal" style="display: none;">
    <div class="riseup-modal-overlay"></div>
    <div class="riseup-modal-content">
        <h2>
            <span class="dashicons dashicons-trash" style="color: #d63638;"></span>
            <?php esc_html_e('Delete Snapshot', 'riseup-asia-uploader'); ?>
        </h2>
        <p id="delete_message"></p>
        <div id="delete_cascade_warning" style="display: none;">
            <p class="riseup-warning-text" style="background: #fef3f2; border-left: 4px solid #d63638; padding: 10px 14px;">
                <span class="dashicons dashicons-warning" style="vertical-align: middle; color: #d63638;"></span>
                <span id="delete_cascade_text"></span>
            </p>
        </div>
        <p class="riseup-modal-actions">
            <button type="button" id="btn_confirm_delete" class="button button-primary" style="background: #d63638; border-color: #d63638;">
                <span class="dashicons dashicons-trash"></span>
                <?php esc_html_e('Delete', 'riseup-asia-uploader'); ?>
            </button>
            <button type="button" id="btn_cancel_delete" class="button button-secondary">
                <?php esc_html_e('Cancel', 'riseup-asia-uploader'); ?>
            </button>
        </p>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    var ajaxNonce = '<?php echo wp_create_nonce(NonceType::Admin->value); ?>';
    var restNonce = '<?php echo wp_create_nonce(NonceType::WpRest->value); ?>';
    var restBase = '<?php echo esc_url(rest_url(PluginConfigType::apiFullNamespace())); ?>';
    var $status = $('#snapshot_action_status');
    var currentPage = 1;
    var currentRestoreId = null;
    var currentRestoreType = null;
    var currentDeleteId = null;
    var isInitialLoad = true;
    var progressTimer = null;
    var activeJobId = null;
    var allSnapshots = []; // cache for hierarchy building

    // =========================================================================
    // ENUM CONSTANTS (from PHP — prevents magic strings in JS)
    // =========================================================================

    var SNAP_STATUS = {
        complete:  '<?php echo esc_js(SnapshotStatusType::Complete->value); ?>',
        running:   '<?php echo esc_js(SnapshotStatusType::Running->value); ?>',
        failed:    '<?php echo esc_js(SnapshotStatusType::Failed->value); ?>',
        pending:   '<?php echo esc_js(SnapshotStatusType::Pending->value); ?>',
        scheduled: '<?php echo esc_js(SnapshotStatusType::Scheduled->value); ?>'
    };
    var SNAP_MODE = {
        full:        '<?php echo esc_js(SnapshotModeType::Full->value); ?>',
        incremental: '<?php echo esc_js(SnapshotModeType::Incremental->value); ?>'
    };
    var SNAP_SCOPE = {
        all:       '<?php echo esc_js(SnapshotScopeType::All->value); ?>',
        wordpress: '<?php echo esc_js(SnapshotScopeType::WordPress->value); ?>',
        content:   '<?php echo esc_js(SnapshotScopeType::Content->value); ?>',
        custom:    '<?php echo esc_js(SnapshotScopeType::Custom->value); ?>'
    };
    var SNAP_FREQ = {
        manual:  '<?php echo esc_js(SnapshotFrequencyType::Manual->value); ?>',
        hourly:  '<?php echo esc_js(SnapshotFrequencyType::Hourly->value); ?>',
        daily:   '<?php echo esc_js(SnapshotFrequencyType::Daily->value); ?>',
        weekly:  '<?php echo esc_js(SnapshotFrequencyType::Weekly->value); ?>',
        monthly: '<?php echo esc_js(SnapshotFrequencyType::Monthly->value); ?>'
    };


    var SNAP_ENDPOINTS = {
        list:        '<?php echo esc_js(EndpointType::SnapshotList->value); ?>',
        schedule:    '<?php echo esc_js(EndpointType::SnapshotSchedule->value); ?>',
        info:        '<?php echo esc_js(EndpointType::SnapshotInfo->value); ?>',
        delete_:     '<?php echo esc_js(EndpointType::SnapshotDelete->value); ?>',
        restore:     '<?php echo esc_js(EndpointType::SnapshotRestore->value); ?>',
        export_:     '<?php echo esc_js(EndpointType::SnapshotExport->value); ?>',
        import_:     '<?php echo esc_js(EndpointType::SnapshotImport->value); ?>',
        settings:    '<?php echo esc_js(EndpointType::SnapshotSettings->value); ?>',
        providers:   '<?php echo esc_js(EndpointType::SnapshotProviders->value); ?>',
        tables:      '<?php echo esc_js(EndpointType::SnapshotTables->value); ?>',
        fullBackup:  '<?php echo esc_js(EndpointType::SnapshotFullBackup->value); ?>',
        incremental: '<?php echo esc_js(EndpointType::SnapshotIncremental->value); ?>',
        cleanup:     '<?php echo esc_js(EndpointType::SnapshotCleanup->value); ?>',
        download:    '<?php echo esc_js(EndpointType::SnapshotDownload->value); ?>',
        progress:    '<?php echo esc_js(EndpointType::SnapshotProgress->value); ?>'
    };

    var SNAP_RESPONSE_KEYS = {
        snapshots: '<?php echo esc_js(ResponseKeyType::Snapshots->value); ?>',
        total:     '<?php echo esc_js(ResponseKeyType::Total->value); ?>',
        jobId:     '<?php echo esc_js(ResponseKeyType::JobId->value); ?>',
        message:   '<?php echo esc_js(ResponseKeyType::Message->value); ?>',
        success:   '<?php echo esc_js(ResponseKeyType::Success->value); ?>'
    };

    var SNAP_LABELS = {
        copied:              '<?php echo esc_js(__("Copied!", "riseup-asia-uploader")); ?>',
        copy:                '<?php echo esc_js(__("Copy", "riseup-asia-uploader")); ?>',
        copyReport:          '<?php echo esc_js(__("Copy Report", "riseup-asia-uploader")); ?>',
        provider:            '<?php echo esc_js(__("Provider", "riseup-asia-uploader")); ?>',
        available:           '<?php echo esc_js(__("Available", "riseup-asia-uploader")); ?>',
        priority:            '<?php echo esc_js(__("Priority", "riseup-asia-uploader")); ?>',
        importing:           '<?php echo esc_js(__("Importing...", "riseup-asia-uploader")); ?>',
        uploadImport:        '<?php echo esc_js(__("Upload & Import", "riseup-asia-uploader")); ?>',
        restoring:           '<?php echo esc_js(__("Restoring...", "riseup-asia-uploader")); ?>',
        restoreNow:          '<?php echo esc_js(__("Restore Now", "riseup-asia-uploader")); ?>',
        cached:              '<?php echo esc_js(__("Cached", "riseup-asia-uploader")); ?>',
        built:               '<?php echo esc_js(__("Built", "riseup-asia-uploader")); ?>',
        confirmDeleteSnap:   '<?php echo esc_js(__("Are you sure you want to delete snapshot \"%s\"? This cannot be undone.", "riseup-asia-uploader")); ?>',
        fullBackup:          '<?php echo esc_js(__("Full backup", "riseup-asia-uploader")); ?>',
        incrementalBackup:   '<?php echo esc_js(__("Incremental", "riseup-asia-uploader")); ?>',
        scheduledBackup:     '<?php echo esc_js(__("Scheduled backup", "riseup-asia-uploader")); ?>'
    };

    var MONTH_NAMES = [
        '<?php echo esc_js(__("January", "riseup-asia-uploader")); ?>',
        '<?php echo esc_js(__("February", "riseup-asia-uploader")); ?>',
        '<?php echo esc_js(__("March", "riseup-asia-uploader")); ?>',
        '<?php echo esc_js(__("April", "riseup-asia-uploader")); ?>',
        '<?php echo esc_js(__("May", "riseup-asia-uploader")); ?>',
        '<?php echo esc_js(__("June", "riseup-asia-uploader")); ?>',
        '<?php echo esc_js(__("July", "riseup-asia-uploader")); ?>',
        '<?php echo esc_js(__("August", "riseup-asia-uploader")); ?>',
        '<?php echo esc_js(__("September", "riseup-asia-uploader")); ?>',
        '<?php echo esc_js(__("October", "riseup-asia-uploader")); ?>',
        '<?php echo esc_js(__("November", "riseup-asia-uploader")); ?>',
        '<?php echo esc_js(__("December", "riseup-asia-uploader")); ?>'
    ];

    // =========================================================================
    // UTILITY HELPERS
    // =========================================================================

    function formatBytes(bytes) {
        if (!bytes || bytes === 0) return '0 B';
        var k = 1024;
        var sizes = ['B', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }

    function relativeTime(dateStr) {
        if (!dateStr) return '';
        try {
            var d = new Date(dateStr);
            var now = new Date();
            var diffMs = now.getTime() - d.getTime();
            var diffMins = Math.floor(diffMs / 60000);
            if (diffMins < 1) return 'just now';
            if (diffMins < 60) return diffMins + 'm ago';
            var diffHours = Math.floor(diffMins / 60);
            if (diffHours < 24) return diffHours + 'h ago';
            var diffDays = Math.floor(diffHours / 24);
            return diffDays + 'd ago';
        } catch (e) {
            return dateStr;
        }
    }

    function statusBadge(status) {
        // Keys match SnapshotStatusType enum values
        var colors = {};
        colors[SNAP_STATUS.complete]  = 'background:#d1e4dd;color:#0a7a4d;';
        colors[SNAP_STATUS.running]   = 'background:#fff3cd;color:#664d03;';
        colors.in_progress            = 'background:#fff3cd;color:#664d03;'; // legacy compat — pre-enum status value
        colors[SNAP_STATUS.failed]    = 'background:#f8d7da;color:#721c24;';
        colors[SNAP_STATUS.pending]   = 'background:#e3f2fd;color:#1565c0;';
        colors[SNAP_STATUS.scheduled] = 'background:#e8eaf6;color:#283593;';
        var style = colors[status] || 'background:#f5f5f5;color:#757575;';
        var icon = '';
        if (status === SNAP_STATUS.running || status === 'in_progress') { // legacy compat — pre-enum status value
            icon = '<span class="dashicons dashicons-update riseup-spin" style="font-size:12px;width:12px;height:12px;vertical-align:middle;margin-right:3px;"></span>';
        } else if (status === SNAP_STATUS.complete) {
            icon = '<span class="dashicons dashicons-yes-alt" style="font-size:12px;width:12px;height:12px;vertical-align:middle;margin-right:3px;"></span>';
        } else if (status === SNAP_STATUS.failed) {
            icon = '<span class="dashicons dashicons-dismiss" style="font-size:12px;width:12px;height:12px;vertical-align:middle;margin-right:3px;"></span>';
        }
        return '<span class="riseup-badge" style="' + style + '">' + icon + status + '</span>';
    }

    function typeBadge(snapshotType) {
        if (snapshotType === SNAP_MODE.incremental) {
            return '<span class="dashicons dashicons-randomize" style="color:#6c3483;font-size:16px;width:16px;height:16px;" title="' + SNAP_MODE.incremental + '"></span>';
        }
        return '<span class="dashicons dashicons-media-archive" style="color:#2271b1;font-size:16px;width:16px;height:16px;" title="' + SNAP_MODE.full + '"></span>';
    }

    function scopeBadge(scope) {
        // Keys match SnapshotScopeType enum values
        var colors = {};
        colors[SNAP_SCOPE.all]       = 'background:#f3e5f5;color:#7b1fa2;';
        colors[SNAP_SCOPE.wordpress] = 'background:#e3f2fd;color:#1565c0;';
        colors[SNAP_SCOPE.content]   = 'background:#e8f5e9;color:#2e7d32;';
        colors[SNAP_SCOPE.custom]    = 'background:#fff3e0;color:#e65100;';
        var style = colors[scope] || 'background:#f5f5f5;color:#757575;';
        return '<span class="riseup-badge" style="' + style + '">' + (scope || SNAP_SCOPE.all) + '</span>';
    }

    // Status helper
    function showStatus($el, message, isError) {
        $el.html(message).css('color', isError ? '#d63638' : '#00a32a').show();
        setTimeout(function() { $el.fadeOut(); }, 8000);
    }

    /**
     * Extract detailed error info from an API error response.
     */
    function extractErrorDetails(xhr) {
        var resp = xhr.responseJSON || {};
        var status = xhr.status || 0;
        var msg = resp.message || resp.Status && resp.Status.Message || 'Unknown error';
        var pluginVersion = resp.plugin_version || resp.Status && resp.Status.PluginVersion || '?';
        var timestamp = resp.timestamp || new Date().toISOString();
        var logHint = resp.log_hint || '';
        var stackTrace = '';
        var backendErrors = '';

        if (resp.Errors) {
            if (resp.Errors.DelegatedServiceErrorStack && resp.Errors.DelegatedServiceErrorStack.length) {
                stackTrace = resp.Errors.DelegatedServiceErrorStack.join('\n');
            }
            if (resp.Errors.BackendMessage) {
                backendErrors = resp.Errors.BackendMessage;
            }
            if (resp.Errors.Backend && resp.Errors.Backend.length) {
                stackTrace = stackTrace || resp.Errors.Backend.join('\n');
            }
        }
        if (resp.data && resp.data.stack_trace) {
            stackTrace = stackTrace || resp.data.stack_trace;
        }

        var diagnostic = '## Error Report\n\n';
        diagnostic += '**Status:** ' + status + '\n';
        diagnostic += '**Message:** ' + msg + '\n';
        diagnostic += '**Plugin Version:** ' + pluginVersion + '\n';
        diagnostic += '**Timestamp:** ' + timestamp + '\n';
        if (backendErrors) diagnostic += '**Backend:** ' + backendErrors + '\n';
        if (logHint) diagnostic += '**Log Hint:** ' + logHint + '\n';
        if (stackTrace) diagnostic += '\n**Stack Trace:**\n```\n' + stackTrace + '\n```\n';

        return {
            message: msg,
            status: status,
            pluginVersion: pluginVersion,
            timestamp: timestamp,
            logHint: logHint,
            stackTrace: stackTrace,
            backendErrors: backendErrors,
            diagnostic: diagnostic
        };
    }

    /**
     * Show a rich error status with Copy button and optional Check Logs link.
     */
    function showErrorStatus($el, xhr, contextLabel) {
        var err = extractErrorDetails(xhr);
        var label = contextLabel ? contextLabel + ': ' : '';
        var html = '<span style="color:#d63638;">✗ ' + label + err.message + '</span>';
        html += ' <button type="button" class="button button-small btn-copy-error" title="Copy diagnostic to clipboard" style="margin-left:6px;">';
        html += '<span class="dashicons dashicons-clipboard" style="font-size:14px;width:14px;height:14px;vertical-align:middle;"></span> Copy</button>';
        if (err.logHint) {
            html += ' <a href="<?php echo esc_url(AdminPageType::Logs->adminUrl()); ?>" class="button button-small" style="margin-left:4px;" title="' + err.logHint + '">';
            html += '<span class="dashicons dashicons-list-view" style="font-size:14px;width:14px;height:14px;vertical-align:middle;"></span> Check Logs</a>';
        }
        $el.html(html).show();
        $el.data('diagnostic', err.diagnostic);
        setTimeout(function() { $el.fadeOut(); }, 15000);
    }

    // Copy error diagnostic to clipboard
    $(document).on('click', '.btn-copy-error', function(e) {
        e.preventDefault();
        var $statusEl = $(this).closest('.riseup-inline-status, #snapshot_action_status, #settings_status');
        var diagnostic = $statusEl.data('diagnostic') || 'No diagnostic data available.';

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(diagnostic).then(function() {
                var $btn = $(e.target).closest('.btn-copy-error');
                $btn.text(SNAP_LABELS.copied);
                setTimeout(function() {
                    $btn.html('<span class="dashicons dashicons-clipboard" style="font-size:14px;width:14px;height:14px;vertical-align:middle;"></span> ' + SNAP_LABELS.copy);
                }, 2000);
            });
        } else {
            var $temp = $('<textarea>').val(diagnostic).appendTo('body').select();
            document.execCommand('copy');
            $temp.remove();
        }
    });

    // =========================================================================
    // PROGRESS POLLING
    // =========================================================================

    function startProgressPolling(jobId) {
        activeJobId = jobId;
        $('#progress_panel').slideDown();
        pollProgress();
    }

    function pollProgress() {
        if (!activeJobId) return;

        $.ajax({
            url: restBase + '/' + SNAP_ENDPOINTS.progress,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ job_id: activeJobId }),
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', restNonce);
            },
            success: function(response) {
                var pct = Math.round(response.percent || 0);
                $('#progress_bar').css('width', pct + '%');
                $('#progress_percent_badge').text(pct + '%');

                var metaText = '';
                if (response.status) metaText += 'Status: ' + response.status;
                if (response.tables_exported !== undefined && response.total_tables) {
                    metaText += ' — Tables: ' + response.tables_exported + '/' + response.total_tables;
                }
                if (response.current_batch && response.total_batches) {
                    metaText += ' — Batch: ' + response.current_batch + '/' + response.total_batches;
                }
                $('#progress_status_text').text(metaText);

                // Per-table progress
                if (response.table_progress && response.table_progress.length > 0) {
                    var html = '';
                    response.table_progress.forEach(function(t) {
                        var tColor = t.status === SNAP_STATUS.complete ? '#0a7a4d' : (t.status === SNAP_STATUS.running ? '#664d03' : '#757575');
                        var tIcon = t.status === SNAP_STATUS.complete ? '✓' : (t.status === SNAP_STATUS.running ? '⟳' : '○');
                        html += '<span class="riseup-table-status" style="color:' + tColor + ';">' + tIcon + ' ' + t.table + '</span> ';
                    });
                    $('#progress_tables_list').html(html);
                    $('#progress_tables').show();
                }

                // Keep polling if still running
                if (response.status === SNAP_STATUS.complete || response.status === SNAP_STATUS.failed) {
                    activeJobId = null;
                    if (response.status === SNAP_STATUS.complete) {
                        showStatus($status, '✓ Snapshot completed successfully', false);
                    } else {
                        showStatus($status, '✗ Snapshot job failed', true);
                    }
                    setTimeout(function() {
                        $('#progress_panel').slideUp();
                        loadSnapshots(1);
                    }, 2000);
                } else {
                    progressTimer = setTimeout(pollProgress, 2000);
                }
            },
            error: function() {
                // Silently retry
                progressTimer = setTimeout(pollProgress, 5000);
            }
        });
    }

    function stopProgressPolling() {
        if (progressTimer) {
            clearTimeout(progressTimer);
            progressTimer = null;
        }
        activeJobId = null;
    }

    // =========================================================================
    // SNAPSHOT LIST WITH HIERARCHY
    // =========================================================================

    function loadSnapshots(page) {
        page = page || 1;
        currentPage = page;
        var limit = <?php echo (int) PaginationConfigType::DefaultLimit->value; ?>;
        var offset = (page - 1) * limit;

        $('#snapshots_loading').show();
        $('#snapshots_table, #snapshots_empty, #snapshots_pagination').hide();

        $.ajax({
            url: restBase + '/' + SNAP_ENDPOINTS.list + '?limit=' + limit + '&offset=' + offset,
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', restNonce);
            },
            success: function(response) {
                $('#snapshots_loading').hide();
                isInitialLoad = false;
                var snapshots = response[SNAP_RESPONSE_KEYS.snapshots] || [];
                allSnapshots = snapshots;

                if (snapshots.length === 0 && page === 1) {
                    $('#snapshots_empty').show();
                    return;
                }

                // Build hierarchy: group incrementals under their parent
                var fullSnapshots = [];
                var incrementalsByParent = {};
                var hasRunningJob = false;

                snapshots.forEach(function(s) {
                    var isIncr = (s.snapshot_type === SNAP_MODE.incremental || s.scope === SNAP_MODE.incremental);
                    if (isIncr && s.parent_id) {
                        if (!incrementalsByParent[s.parent_id]) {
                            incrementalsByParent[s.parent_id] = [];
                        }
                        incrementalsByParent[s.parent_id].push(s);
                    } else {
                        fullSnapshots.push(s);
                    }

                    // Check for running jobs
                    if (s.status === SNAP_STATUS.running || s.status === 'in_progress') { // legacy compat — pre-enum status value
                        hasRunningJob = true;
                        if (s.job_id && !activeJobId) {
                            startProgressPolling(s.job_id);
                        }
                    }
                });

                var html = '';
                fullSnapshots.forEach(function(s) {
                    var incrCount = (incrementalsByParent[s.id] || []).length;
                    html += buildSnapshotRow(s, false, incrCount);

                    // Render nested incrementals
                    if (incrementalsByParent[s.id]) {
                        incrementalsByParent[s.id].forEach(function(child) {
                            html += buildSnapshotRow(child, true, 0);
                        });
                    }
                });

                // Render orphan incrementals (no parent in current page)
                snapshots.forEach(function(s) {
                    var isIncr = (s.snapshot_type === SNAP_MODE.incremental || s.scope === SNAP_MODE.incremental);
                    var isParentMissing = s.parent_id && fullSnapshots.every(function(f) { return f.id !== s.parent_id; });
                    var isOrphanIncremental = isIncr && isParentMissing;
                    if (isOrphanIncremental) {
                        html += buildSnapshotRow(s, true, 0);
                    }
                });

                $('#snapshots_tbody').html(html);
                $('#snapshots_table').show();

                // Pagination
                var total = response[SNAP_RESPONSE_KEYS.total] || snapshots.length;
                var totalPages = Math.ceil(total / limit);
                if (totalPages > 1) {
                    $('#snapshots_count').text(total + ' items');
                    var pagesHtml = '';
                    for (var i = 1; i <= totalPages; i++) {
                        if (i === page) {
                            pagesHtml += '<span class="tablenav-pages-navspan button disabled">' + i + '</span> ';
                        } else {
                            pagesHtml += '<a class="button page-link" data-page="' + i + '">' + i + '</a> ';
                        }
                    }
                    $('#snapshots_pages').html(pagesHtml);
                    $('#snapshots_pagination').show();
                }
            },
            error: function(xhr) {
                $('#snapshots_loading').hide();
                if (isInitialLoad) {
                    isInitialLoad = false;
                    $('#snapshots_empty').show();
                    return;
                }
                showErrorStatus($status, xhr, 'Failed to load snapshots');
            }
        });
    }

    function buildSnapshotRow(s, isNested, incrCount) {
        var isIncr = (s.snapshot_type === SNAP_MODE.incremental || s.scope === SNAP_MODE.incremental);
        var rowClass = isNested ? 'riseup-nested-row' : '';
        var isRunning = (s.status === SNAP_STATUS.running || s.status === 'in_progress'); // legacy compat — pre-enum status value

        var html = '<tr class="' + rowClass + '" data-id="' + s.id + '" data-type="' + (s.snapshot_type || SNAP_MODE.full) + '" data-incr-count="' + incrCount + '">';
        html += '<td>' + (s.sequence || s.id) + '</td>';
        html += '<td>' + typeBadge(s.snapshot_type || SNAP_MODE.full) + '</td>';
        html += '<td>';
        if (isNested) {
            html += '<span class="riseup-indent">↳</span> ';
        }
        html += '<code>' + (s.filename || '-') + '</code>';
        if (isIncr) {
            html += ' <span class="riseup-badge" style="background:#f3e5f5;color:#7b1fa2;font-size:10px;padding:1px 5px;">' + SNAP_MODE.incremental + '</span>';
        }
        if (incrCount > 0) {
            html += ' <span class="riseup-badge" style="background:#e3f2fd;color:#1565c0;font-size:10px;padding:1px 5px;">' + incrCount + ' incremental' + (incrCount > 1 ? 's' : '') + '</span>';
        }
        html += '</td>';
        html += '<td>' + scopeBadge(s.scope) + '</td>';
        html += '<td><span class="riseup-badge" style="background:#f5f5f5;color:#757575;">' + (s.provider || '-') + '</span></td>';
        html += '<td>' + (s.table_count || '-') + '</td>';
        html += '<td>' + (s.total_rows ? s.total_rows.toLocaleString() : '-') + '</td>';
        html += '<td>' + formatBytes(s.file_size) + '</td>';
        html += '<td>' + statusBadge(s.status || SNAP_STATUS.complete) + '</td>';
        html += '<td title="' + (s.created_at || '') + '">' + relativeTime(s.created_at) + '</td>';
        html += '<td class="riseup-snapshot-actions">';
        if (s.status === SNAP_STATUS.complete || !s.status) {
            html += '<button class="button button-small btn-restore" data-id="' + s.id + '" data-name="' + (s.filename || '#' + s.id) + '" data-type="' + (s.snapshot_type || SNAP_MODE.full) + '" title="Restore">';
            html += '<span class="dashicons dashicons-database-import"></span></button> ';

            // Download ZIP button — only for full snapshots
            if (!isIncr) {
                html += '<button class="button button-small btn-download-zip" data-id="' + s.id + '" title="Download ZIP">';
                html += '<span class="dashicons dashicons-download"></span>';
                html += '</button> ';
            }
        }
        if (!isRunning) {
            html += '<button class="button button-small btn-delete-snapshot" data-id="' + s.id + '" data-type="' + (s.snapshot_type || SNAP_MODE.full) + '" data-name="' + (s.filename || '#' + s.id) + '" data-incr-count="' + incrCount + '" title="Delete">';
            html += '<span class="dashicons dashicons-trash" style="color:#d63638;"></span></button>';
        }
        html += '</td>';
        html += '</tr>';
        return html;
    }

    // =========================================================================
    // LOAD SETTINGS
    // =========================================================================

    function loadSettings() {
        $.ajax({
            url: restBase + '/' + SNAP_ENDPOINTS.settings,
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', restNonce);
            },
            success: function(settings) {
                $('#settings_loading').hide();
                $('#settings_form').show();
                if (settings.schedule) {
                    $('#setting_schedule').val(settings.schedule);
                    cachedSchedule = settings.schedule;
                }
                if (settings.retention_type) $('#setting_retention_type').val(settings.retention_type).trigger('change');
                if (settings.retention_value) $('#setting_retention_value').val(settings.retention_value);
                if (settings.default_scope) $('#setting_scope').val(settings.default_scope);
                if (settings.default_provider) $('#setting_provider').val(settings.default_provider);

                // Worker pool & storage mode
                if (settings.worker_pool_size) {
                    $('#setting_worker_pool').val(settings.worker_pool_size);
                    $('#worker_pool_display').text(settings.worker_pool_size);
                }
                if (settings.storage_mode) {
                    $('input[name="setting_storage_mode"][value="' + settings.storage_mode + '"]').prop('checked', true);
                    $('.riseup-storage-card').removeClass('active');
                    $('.riseup-storage-card[data-mode="' + settings.storage_mode + '"]').addClass('active');
                }
                // Rebuild calendar now that schedule is known
                buildCalendar(allSnapshots);
            },
            error: function(xhr) {
                if (isInitialLoad || xhr.status === 404) {
                    $('#settings_loading').hide();
                    $('#settings_form').show();
                    return;
                }
                $('#settings_loading').html('<em>Failed to load settings.</em>');
            }
        });
    }

    // Load providers
    function loadProviders() {
        $.ajax({
            url: restBase + '/' + SNAP_ENDPOINTS.providers,
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', restNonce);
            },
            success: function(providers) {
                $('#providers_loading').hide();
                var html = '<table class="wp-list-table widefat striped"><thead><tr>';
                html += '<th>' + SNAP_LABELS.provider + '</th><th>' + SNAP_LABELS.available + '</th><th>' + SNAP_LABELS.priority + '</th>';
                html += '</tr></thead><tbody>';
                (providers || []).forEach(function(p) {
                    var icon = p.available ? '✓' : '✗';
                    var color = p.available ? '#00a32a' : '#999';
                    html += '<tr>';
                    html += '<td><strong>' + p.name + '</strong></td>';
                    html += '<td style="color:' + color + ';">' + icon + '</td>';
                    html += '<td>' + (p.priority || '-') + '</td>';
                    html += '</tr>';
                });
                html += '</tbody></table>';
                $('#providers_list').html(html).show();
            },
            error: function(xhr) {
                if (isInitialLoad || xhr.status === 404) {
                    $('#providers_loading').html('<em>No providers detected yet.</em>');
                    return;
                }
                $('#providers_loading').html('<em>Failed to detect providers.</em>');
            }
        });
    }

    // =========================================================================
    // ACTIONS: SNAPSHOT NOW
    // =========================================================================

    $('#btn_snapshot_now').on('click', function() {
        $('#snapshot_options').slideToggle();
        $('#import_form').slideUp();
    });

    $('#btn_cancel_snapshot').on('click', function() {
        $('#snapshot_options').slideUp();
    });

    // Scope change - show/hide custom tables
    $('#snapshot_scope').on('change', function() {
        if ($(this).val() === '<?php echo SnapshotScopeType::Custom->value; ?>') {
            $('#custom_tables_row').show();
            loadTables();
        } else {
            $('#custom_tables_row').hide();
        }
    });

    function loadTables() {
        $.ajax({
            url: restBase + '/' + SNAP_ENDPOINTS.tables,
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', restNonce);
            },
            success: function(tables) {
                var html = '';
                (tables || []).forEach(function(t) {
                    html += '<label class="riseup-table-checkbox"><input type="checkbox" value="' + t + '" checked> ' + t + '</label> ';
                });
                $('#snapshot_tables_list').html(html);
            }
        });
    }

    // Confirm create snapshot
    $('#btn_confirm_snapshot').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true);

        var data = {
            scope: $('#snapshot_scope').val(),
            provider: $('#snapshot_provider').val()
        };
        if (data.scope === '<?php echo SnapshotScopeType::Custom->value; ?>') {
            data.tables = [];
            $('#snapshot_tables_list input:checked').each(function() {
                data.tables.push($(this).val());
            });
        }

        $.ajax({
            url: restBase + '/' + SNAP_ENDPOINTS.schedule,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(data),
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', restNonce);
            },
            success: function(response) {
                showStatus($status, '✓ Snapshot job queued — running in background', false);
                $('#snapshot_options').slideUp();
                // Start progress polling if job_id returned
                if (response.job_id) {
                    startProgressPolling(response.job_id);
                } else {
                    loadSnapshots(1);
                }
            },
            error: function(xhr) {
                showErrorStatus($status, xhr, 'Snapshot creation failed');
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });

    // =========================================================================
    // INCREMENTAL BACKUP
    // =========================================================================

    $('#btn_incremental_now').on('click', function() {
        var $btn = $(this);
        // Find the latest full snapshot
        var latestFull = allSnapshots.find(function(s) {
            return (s.snapshot_type === SNAP_MODE.full || (!s.snapshot_type && !s.parent_id)) && s.status === SNAP_STATUS.complete;
        });
        if (!latestFull) {
            showStatus($status, '✗ No full snapshot found — create a full snapshot first', true);
            return;
        }

        $btn.prop('disabled', true);
        $.ajax({
            url: restBase + '/' + SNAP_ENDPOINTS.incremental,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ parent_id: latestFull.id }),
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', restNonce);
            },
            success: function(response) {
                showStatus($status, '✓ Incremental backup queued', false);
                if (response.job_id) {
                    startProgressPolling(response.job_id);
                } else {
                    loadSnapshots(1);
                }
            },
            error: function(xhr) {
                showErrorStatus($status, xhr, 'Incremental backup failed');
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });

    // =========================================================================
    // IMPORT
    // =========================================================================

    $('#btn_import_snapshot').on('click', function() {
        $('#import_form').slideToggle();
        $('#snapshot_options').slideUp();
    });
    $('#btn_cancel_import').on('click', function() {
        $('#import_form').slideUp();
    });

    $('#import_file').on('change', function() {
        $('#btn_confirm_import').prop('disabled', !this.files.length);
    });

    $('#btn_confirm_import').on('click', function() {
        var file = $('#import_file')[0].files[0];
        if (!file) return;

        var $btn = $(this);
        $btn.prop('disabled', true).text(SNAP_LABELS.importing);

        var formData = new FormData();
        formData.append('file', file);

        $.ajax({
            url: restBase + '/' + SNAP_ENDPOINTS.import_,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', restNonce);
            },
            success: function(response) {
                showStatus($status, '✓ Snapshot imported successfully', false);
                $('#import_form').slideUp();
                $('#import_file').val('');
                loadSnapshots(1);
            },
            error: function(xhr) {
                showErrorStatus($status, xhr, 'Import failed');
            },
            complete: function() {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-upload"></span> ' + SNAP_LABELS.uploadImport);
            }
        });
    });

    // =========================================================================
    // REFRESH & PAGINATION
    // =========================================================================

    $('#btn_refresh_list').on('click', function() {
        loadSnapshots(currentPage);
    });

    $(document).on('click', '.page-link', function() {
        loadSnapshots(parseInt($(this).data('page')));
    });

    // =========================================================================
    // RESTORE
    // =========================================================================

    $(document).on('click', '.btn-restore', function() {
        currentRestoreId = $(this).data('id');
        currentRestoreType = $(this).data('type') || SNAP_MODE.full;
        $('#restore_snapshot_name').text($(this).data('name'));

        if (currentRestoreType === SNAP_MODE.incremental) {
            $('#restore_incremental_warning').show();
        } else {
            $('#restore_incremental_warning').hide();
        }

        $('#restore_modal').show();
    });

    $('#btn_cancel_restore, #restore_modal .riseup-modal-overlay').on('click', function() {
        $('#restore_modal').hide();
        currentRestoreId = null;
    });

    $('#btn_confirm_restore').on('click', function() {
        if (!currentRestoreId) return;
        var $btn = $(this);
        $btn.prop('disabled', true).text(SNAP_LABELS.restoring);

        $.ajax({
            url: restBase + '/' + SNAP_ENDPOINTS.restore,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                id: currentRestoreId,
                confirm: true,
                mode: $('#restore_mode').val(),
                create_backup: $('#restore_create_backup').is(':checked')
            }),
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', restNonce);
            },
            success: function(response) {
                $('#restore_modal').hide();
                showStatus($status, '✓ Restore queued — running in background', false);
                if (response.job_id) {
                    startProgressPolling(response.job_id);
                } else {
                    loadSnapshots(currentPage);
                }
            },
            error: function(xhr) {
                showErrorStatus($status, xhr, 'Restore failed');
            },
            complete: function() {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-database-import"></span> ' + SNAP_LABELS.restoreNow);
                currentRestoreId = null;
            }
        });
    });

    // =========================================================================
    // DOWNLOAD ZIP (via new cached exporter endpoint)
    // =========================================================================

    var currentDownloadDiagnostic = '';

    $(document).on('click', '.btn-download-zip', function() {
        var id = $(this).data('id');
        var $btn = $(this);
        var origHtml = $btn.html();

        // Show building state
        $btn.prop('disabled', true).html(
            '<span class="dashicons dashicons-update riseup-spin" style="font-size:14px;width:14px;height:14px;"></span>'
        );

        $.ajax({
            url: restBase + '/' + SNAP_ENDPOINTS.download,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ id: id }),
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', restNonce);
            },
            success: function(response) {
                var downloadUrl = response.url || (response.data && response.data.url);
                var filename = response.filename || (response.data && response.data.filename) || 'snapshot-' + id + '.zip';
                var cached = response.cached || (response.data && response.data.cached) || false;
                var size = response.size || (response.data && response.data.size) || 0;

                if (!downloadUrl) {
                    showStatus($status, '✗ No download URL returned', true);
                    return;
                }

                // Show cached badge briefly
                var badgeColor = cached ? '#d1e4dd' : '#fff3cd';
                var badgeText = cached ? SNAP_LABELS.cached : SNAP_LABELS.built;
                var badgeTextColor = cached ? '#0a7a4d' : '#664d03';
                showStatus($status,
                    '✓ ZIP ready ' +
                    '<span class="riseup-badge" style="background:' + badgeColor + ';color:' + badgeTextColor + ';font-size:10px;padding:1px 6px;">' + badgeText + '</span>' +
                    (size ? ' (' + formatBytes(size) + ')' : ''),
                    false
                );

                // Open download URL in new tab
                var a = document.createElement('a');
                a.href = downloadUrl;
                a.download = filename;
                a.target = '_blank';
                document.body.appendChild(a);
                a.click();
                a.remove();
            },
            error: function(xhr) {
                var err = extractErrorDetails(xhr);
                currentDownloadDiagnostic = err.diagnostic;

                // Populate error modal
                $('#download_error_message').text(err.message);
                $('#download_error_status').text(err.status);
                $('#download_error_version').text(err.pluginVersion);
                $('#download_error_timestamp').text(err.timestamp);

                if (err.stackTrace) {
                    $('#download_error_stack').text(err.stackTrace);
                    $('#download_error_stack_section').show();
                } else {
                    $('#download_error_stack_section').hide();
                }

                if (err.backendErrors) {
                    $('#download_error_backend').text(err.backendErrors);
                    $('#download_error_backend_section').show();
                } else {
                    $('#download_error_backend_section').hide();
                }

                $('#download_error_modal').show();
            },
            complete: function() {
                $btn.prop('disabled', false).html(origHtml);
            }
        });
    });

    // Download error modal actions
    $('#btn_close_download_error, #download_error_modal .riseup-modal-overlay').on('click', function() {
        $('#download_error_modal').hide();
    });

    $('#btn_copy_download_error').on('click', function() {
        var $btn = $(this);
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(currentDownloadDiagnostic).then(function() {
                $btn.text(SNAP_LABELS.copied);
                setTimeout(function() {
                    $btn.html('<span class="dashicons dashicons-clipboard" style="font-size:14px;width:14px;height:14px;vertical-align:middle;"></span> ' + SNAP_LABELS.copyReport);
                }, 2000);
            });
        } else {
            var $temp = $('<textarea>').val(currentDownloadDiagnostic).appendTo('body').select();
            document.execCommand('copy');
            $temp.remove();
            $btn.text(SNAP_LABELS.copied);
            setTimeout(function() {
                $btn.html('<span class="dashicons dashicons-clipboard" style="font-size:14px;width:14px;height:14px;vertical-align:middle;"></span> ' + SNAP_LABELS.copyReport);
            }, 2000);
        }
    });

    // =========================================================================
    // DELETE (with cascade warning)
    // =========================================================================

    $(document).on('click', '.btn-delete-snapshot', function() {
        currentDeleteId = $(this).data('id');
        var name = $(this).data('name');
        var type = $(this).data('type') || SNAP_MODE.full;
        var incrCount = parseInt($(this).data('incr-count')) || 0;

        $('#delete_message').text(SNAP_LABELS.confirmDeleteSnap.replace('%s', name));

        if (type !== SNAP_MODE.incremental && incrCount > 0) {
            $('#delete_cascade_text').text(
                'This full snapshot has ' + incrCount + ' incremental backup' + (incrCount > 1 ? 's' : '') +
                '. Deleting it will also permanently remove all ' + incrCount + ' incremental snapshot' + (incrCount > 1 ? 's' : '') + '.'
            );
            $('#delete_cascade_warning').show();
        } else {
            $('#delete_cascade_warning').hide();
        }

        $('#delete_modal').show();
    });

    $('#btn_cancel_delete, #delete_modal .riseup-modal-overlay').on('click', function() {
        $('#delete_modal').hide();
        currentDeleteId = null;
    });

    $('#btn_confirm_delete').on('click', function() {
        if (!currentDeleteId) return;
        var $btn = $(this);
        $btn.prop('disabled', true);

        $.ajax({
            url: restBase + '/' + SNAP_ENDPOINTS.delete_,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ id: currentDeleteId }),
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', restNonce);
            },
            success: function() {
                $('#delete_modal').hide();
                showStatus($status, '✓ Snapshot deleted', false);
                loadSnapshots(currentPage);
            },
            error: function(xhr) {
                showErrorStatus($status, xhr, 'Delete failed');
            },
            complete: function() {
                $btn.prop('disabled', false);
                currentDeleteId = null;
            }
        });
    });

    // =========================================================================
    // SETTINGS: WORKER POOL & STORAGE MODE
    // =========================================================================

    // Worker pool slider
    $('#setting_worker_pool').on('input', function() {
        $('#worker_pool_display').text($(this).val());
    });

    // Storage mode cards
    $('.riseup-storage-card').on('click', function() {
        $('.riseup-storage-card').removeClass('active');
        $(this).addClass('active');
        $(this).find('input[type="radio"]').prop('checked', true);
    });

    // Retention type change
    $('#setting_retention_type').on('change', function() {
        var val = $(this).val();
        if (val === '<?php echo RetentionType::Days->value; ?>' || val === '<?php echo RetentionType::Count->value; ?>') {
            $('#retention_value_row').show();
            $('#retention_value_label').text(val === '<?php echo RetentionType::Days->value; ?>' ? 'days' : 'snapshots');
        } else {
            $('#retention_value_row').hide();
        }
    });

    // Save settings (including worker pool & storage mode)
    $('#btn_save_settings').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true);

        var data = {
            action: '<?php echo AjaxActionType::SaveSnapshotSettings->value; ?>',
            nonce: ajaxNonce,
            schedule_frequency: $('#setting_schedule').val(),
            retention_type: $('#setting_retention_type').val(),
            default_scope: $('#setting_scope').val(),
            preferred_provider: $('#setting_provider').val(),
            worker_pool_size: $('#setting_worker_pool').val(),
            storage_mode: $('input[name="setting_storage_mode"]:checked').val()
        };

        var retType = data.retention_type;
        if (retType === '<?php echo RetentionType::Days->value; ?>') {
            data.retention_days = parseInt($('#setting_retention_value').val()) || 30;
        } else if (retType === '<?php echo RetentionType::Count->value; ?>') {
            data.retention_count = parseInt($('#setting_retention_value').val()) || 10;
        }

        $.post(ajaxurl, data, function(response) {
            if (response.success) {
                showStatus($('#settings_status'), '✓ Settings saved', false);
            } else {
                showStatus($('#settings_status'), '✗ ' + (response.data && response.data.message || 'Save failed'), true);
            }
            $btn.prop('disabled', false);
        }).fail(function() {
            showStatus($('#settings_status'), '✗ Network error', true);
            $btn.prop('disabled', false);
        });
    });

    // =========================================================================
    // INITIAL LOAD
    // =========================================================================

    // =========================================================================
    // STORAGE ANALYTICS
    // =========================================================================

    function buildAnalytics(snapshots) {
        if (!snapshots || snapshots.length === 0) {
            $('#analytics_loading').hide();
            $('#analytics_empty').show();
            return;
        }

        var totalSize = 0;
        var largest = 0;
        snapshots.forEach(function(s) {
            var sz = parseInt(s.file_size) || 0;
            totalSize += sz;
            if (sz > largest) largest = sz;
        });
        var avgSize = Math.round(totalSize / snapshots.length);

        $('#stat_total_size').text(formatBytes(totalSize));
        $('#stat_total_count').text(snapshots.length);
        $('#stat_avg_size').text(formatBytes(avgSize));
        $('#stat_largest').text(formatBytes(largest));

        // Group by day for chart (last 30 entries max)
        var byDay = {};
        snapshots.forEach(function(s) {
            if (!s.created_at) return;
            var day = s.created_at.substring(0, 10);
            if (!byDay[day]) byDay[day] = { full: 0, incr: 0 };
            var sz = parseInt(s.file_size) || 0;
            var isIncr = (s.snapshot_type === SNAP_MODE.incremental || s.scope === SNAP_MODE.incremental);
            if (isIncr) { byDay[day].incr += sz; } else { byDay[day].full += sz; }
        });

        var days = Object.keys(byDay).sort().slice(-30);
        if (days.length === 0) {
            $('#analytics_loading').hide();
            $('#analytics_empty').show();
            return;
        }

        var maxVal = 0;
        days.forEach(function(d) { var t = byDay[d].full + byDay[d].incr; if (t > maxVal) maxVal = t; });
        if (maxVal === 0) maxVal = 1;

        // Y-axis labels
        var yHtml = '';
        for (var i = 4; i >= 0; i--) {
            yHtml += '<span>' + formatBytes(Math.round(maxVal * i / 4)) + '</span>';
        }
        $('#chart_y_axis').html(yHtml);

        // Bars
        var barsHtml = '';
        days.forEach(function(d) {
            var fullPct = Math.round((byDay[d].full / maxVal) * 100);
            var incrPct = Math.round((byDay[d].incr / maxVal) * 100);
            var label = d.substring(5); // MM-DD
            barsHtml += '<div class="riseup-bar-group" title="' + d + ': ' + formatBytes(byDay[d].full + byDay[d].incr) + '">';
            barsHtml += '<div class="riseup-bar-stack">';
            if (incrPct > 0) barsHtml += '<div class="riseup-bar riseup-bar-incr" style="height:' + incrPct + '%;"></div>';
            if (fullPct > 0) barsHtml += '<div class="riseup-bar riseup-bar-full" style="height:' + fullPct + '%;"></div>';
            barsHtml += '</div>';
            barsHtml += '<span class="riseup-bar-label">' + label + '</span>';
            barsHtml += '</div>';
        });
        $('#chart_bars').html(barsHtml);

        $('#analytics_loading').hide();
        $('#analytics_content').show();
    }

    // =========================================================================
    // MONTHLY CALENDAR
    // =========================================================================

    var calYear, calMonth, cachedSchedule = SNAP_FREQ.manual;
    (function() {
        var now = new Date();
        calYear = now.getFullYear();
        calMonth = now.getMonth();
    })();

    var monthNames = MONTH_NAMES;

    function getScheduledDates(year, month, frequency) {
        var dates = {};
        if (!frequency || frequency === SNAP_FREQ.manual) return dates;
        var daysInMonth = new Date(year, month + 1, 0).getDate();
        var today = new Date();
        today.setHours(0,0,0,0);

        if (frequency === SNAP_FREQ.daily) {
            for (var d = 1; d <= daysInMonth; d++) {
                var dt = new Date(year, month, d);
                if (dt >= today) {
                    var key = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
                    dates[key] = true;
                }
            }
        } else if (frequency === SNAP_FREQ.weekly) {
            // Every Sunday (day 0) in the month, from today onward
            for (var d = 1; d <= daysInMonth; d++) {
                var dt = new Date(year, month, d);
                if (dt >= today && dt.getDay() === 0) {
                    var key = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
                    dates[key] = true;
                }
            }
        } else if (frequency === SNAP_FREQ.monthly) {
            // 1st of month, from today onward
            var dt = new Date(year, month, 1);
            if (dt >= today) {
                var key = year + '-' + String(month + 1).padStart(2, '0') + '-01';
                dates[key] = true;
            }
        }
        return dates;
    }

    function buildCalendar(snapshots) {
        $('#cal_month_label').text(monthNames[calMonth] + ' ' + calYear);

        // Index snapshots by date
        var byDate = {};
        (snapshots || []).forEach(function(s) {
            if (!s.created_at) return;
            var day = s.created_at.substring(0, 10);
            if (!byDate[day]) byDate[day] = [];
            byDate[day].push(s);
        });

        // Compute scheduled future dates
        var scheduledDates = getScheduledDates(calYear, calMonth, cachedSchedule);

        var firstDay = new Date(calYear, calMonth, 1).getDay();
        var daysInMonth = new Date(calYear, calMonth + 1, 0).getDate();
        var today = new Date();
        var todayStr = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');

        var html = '';
        var day = 1;
        for (var row = 0; row < 6; row++) {
            if (day > daysInMonth) break;
            html += '<tr>';
            for (var col = 0; col < 7; col++) {
                if (row === 0 && col < firstDay) {
                    html += '<td class="riseup-cal-empty"></td>';
                } else if (day > daysInMonth) {
                    html += '<td class="riseup-cal-empty"></td>';
                } else {
                    var dateStr = calYear + '-' + String(calMonth + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
                    var isToday = (dateStr === todayStr);
                    var cellClass = isToday ? 'riseup-cal-today' : '';
                    var entries = byDate[dateStr] || [];
                    var isScheduled = !!scheduledDates[dateStr];

                    html += '<td class="riseup-cal-day ' + cellClass + '">';
                    html += '<span class="riseup-cal-num">' + day + '</span>';
                    if (entries.length > 0 || isScheduled) {
                        var hasFull = false, hasIncr = false;
                        entries.forEach(function(e) {
                            if (e.snapshot_type === SNAP_MODE.incremental || e.scope === SNAP_MODE.incremental) hasIncr = true;
                            else hasFull = true;
                        });
                        html += '<div class="riseup-cal-dots">';
                        if (hasFull) html += '<span class="riseup-cal-dot riseup-cal-dot-full" title="' + SNAP_LABELS.fullBackup + '"></span>';
                        if (hasIncr) html += '<span class="riseup-cal-dot riseup-cal-dot-incr" title="' + SNAP_LABELS.incrementalBackup + '"></span>';
                        if (isScheduled) html += '<span class="riseup-cal-dot riseup-cal-dot-scheduled" title="' + SNAP_LABELS.scheduledBackup + '"></span>';
                        html += '</div>';
                        if (entries.length > 0) html += '<span class="riseup-cal-count">' + entries.length + '</span>';
                    }
                    html += '</td>';
                    day++;
                }
            }
            html += '</tr>';
        }
        $('#cal_body').html(html);
    }

    $('#cal_prev').on('click', function() {
        calMonth--;
        if (calMonth < 0) { calMonth = 11; calYear--; }
        buildCalendar(allSnapshots);
    });
    $('#cal_next').on('click', function() {
        calMonth++;
        if (calMonth > 11) { calMonth = 0; calYear++; }
        buildCalendar(allSnapshots);
    });

    // Hook into snapshot load to build analytics + calendar
    var _origLoadSuccess = null;
    // We patch after initial load by observing allSnapshots changes
    function refreshAnalyticsAndCalendar() {
        buildAnalytics(allSnapshots);
        buildCalendar(allSnapshots);
    }

    // Override loadSnapshots success to also refresh analytics
    var _origLoad = loadSnapshots;
    loadSnapshots = function(page) {
        _origLoad(page);
    };

    // Use MutationObserver on tbody to detect when snapshots are rendered
    var snapshotObserver = new MutationObserver(function() {
        refreshAnalyticsAndCalendar();
    });
    snapshotObserver.observe(document.getElementById('snapshots_tbody'), { childList: true });

    // =========================================================================
    // INITIAL LOAD
    // =========================================================================

    loadSnapshots(1);
    loadSettings();
    loadProviders();
});
</script>

<style>
/* Actions bar */
.riseup-actions-row {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
}
.riseup-actions-row .button .dashicons {
    vertical-align: middle;
    margin-top: -2px;
    margin-right: 2px;
}
.riseup-inline-status {
    font-weight: 500;
    margin-left: 10px;
}

/* Badges */
.riseup-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
}

/* Snapshot actions column */
.riseup-snapshot-actions .button {
    padding: 2px 6px;
    min-height: 28px;
}
.riseup-snapshot-actions .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
    vertical-align: middle;
}

/* Snapshot options & import forms */
.riseup-snapshot-options,
#import_form {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #ddd;
}
.riseup-tables-list {
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid #ddd;
    padding: 8px;
    background: #f9f9f9;
}
.riseup-table-checkbox {
    display: inline-block;
    margin: 2px 10px 2px 0;
}

/* Modal (shared) */
.riseup-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}
.riseup-modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
}
.riseup-modal-content {
    position: relative;
    background: #fff;
    padding: 20px 30px;
    border-radius: 4px;
    max-width: 600px;
    width: 90%;
    box-shadow: 0 5px 20px rgba(0,0,0,0.3);
}
.riseup-modal-actions {
    margin-top: 15px;
    display: flex;
    gap: 10px;
}
.riseup-warning-text {
    color: #d63638;
    font-weight: 500;
}

/* Progress bar */
.riseup-progress-bar-wrap {
    background: #e0e0e0;
    border-radius: 4px;
    height: 20px;
    overflow: hidden;
    margin: 12px 0 8px;
}
.riseup-progress-bar {
    background: linear-gradient(90deg, #2271b1, #135e96);
    height: 100%;
    border-radius: 4px;
    transition: width 0.5s ease;
    min-width: 0;
}
.riseup-progress-meta {
    font-size: 12px;
    color: #50575e;
    margin-bottom: 6px;
}
.riseup-progress-tables {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid #eee;
}
.riseup-progress-tables h4 {
    margin: 0 0 8px;
    font-size: 12px;
    font-weight: 600;
}
.riseup-table-status {
    display: inline-block;
    font-size: 11px;
    font-family: monospace;
    margin: 2px 8px 2px 0;
    white-space: nowrap;
}

/* Spin animation */
@keyframes riseup-spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
.riseup-spin {
    animation: riseup-spin 1.5s linear infinite;
    display: inline-block;
}

/* Nested snapshot rows */
.riseup-nested-row td {
    background: #f8f9fa !important;
}
.riseup-nested-row td:first-child {
    border-left: 3px solid #7b1fa2;
}
.riseup-indent {
    color: #7b1fa2;
    font-weight: 700;
    margin-right: 4px;
}

/* Storage mode cards */
.riseup-storage-cards {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}
.riseup-storage-card {
    cursor: pointer;
    border: 2px solid #dcdcde;
    border-radius: 6px;
    padding: 0;
    transition: border-color 0.2s, box-shadow 0.2s;
    flex: 1;
    min-width: 160px;
    max-width: 220px;
}
.riseup-storage-card input[type="radio"] {
    display: none;
}
.riseup-storage-card-inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: 16px 12px;
    text-align: center;
}
.riseup-storage-card-inner strong {
    font-size: 13px;
}
.riseup-storage-card-inner .description {
    font-size: 11px;
    margin: 0;
    font-style: normal;
    color: #646970;
}
.riseup-storage-card:hover {
    border-color: #2271b1;
}
.riseup-storage-card.active {
    border-color: #2271b1;
    box-shadow: 0 0 0 1px #2271b1;
    background: #f0f6fc;
}

/* Worker pool slider */
.riseup-slider-row {
    display: flex;
    align-items: center;
    gap: 12px;
}
.riseup-range-slider {
    flex: 1;
    max-width: 300px;
    accent-color: #2271b1;
}
.riseup-slider-value {
    display: inline-block;
    background: #1d2327;
    color: #fff;
    padding: 2px 10px;
    border-radius: 4px;
    font-family: monospace;
    font-size: 14px;
    font-weight: 700;
    min-width: 30px;
    text-align: center;
}
/* Analytics Row Layout */
.riseup-analytics-row {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 16px;
    margin-bottom: 0;
}
@media (max-width: 1100px) {
    .riseup-analytics-row { grid-template-columns: 1fr; }
}

/* Stat Cards */
.riseup-analytics-summary {
    display: flex;
    gap: 12px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}
.riseup-stat-card {
    flex: 1;
    min-width: 90px;
    background: #f6f7f7;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    padding: 12px 14px;
    text-align: center;
}
.riseup-stat-value {
    display: block;
    font-size: 18px;
    font-weight: 700;
    color: #1d2327;
    font-family: monospace;
}
.riseup-stat-label {
    display: block;
    font-size: 11px;
    color: #646970;
    margin-top: 2px;
}

/* Chart */
.riseup-chart-container {
    display: flex;
    gap: 6px;
    height: 160px;
    margin-bottom: 8px;
}
.riseup-chart-y-axis {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    width: 55px;
    text-align: right;
    padding-right: 6px;
}
.riseup-chart-y-axis span {
    font-size: 10px;
    color: #888;
    font-family: monospace;
    line-height: 1;
}
.riseup-chart-bars {
    flex: 1;
    display: flex;
    align-items: flex-end;
    gap: 3px;
    border-left: 1px solid #ddd;
    border-bottom: 1px solid #ddd;
    padding: 0 4px 0 6px;
    overflow-x: auto;
}
.riseup-bar-group {
    flex: 1;
    min-width: 14px;
    max-width: 36px;
    display: flex;
    flex-direction: column;
    align-items: center;
}
.riseup-bar-stack {
    width: 100%;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    height: 130px;
}
.riseup-bar {
    width: 100%;
    border-radius: 2px 2px 0 0;
    min-height: 2px;
    transition: height 0.3s;
}
.riseup-bar-full { background: #2271b1; }
.riseup-bar-incr { background: #7b1fa2; }
.riseup-bar-label {
    font-size: 9px;
    color: #888;
    margin-top: 3px;
    transform: rotate(-45deg);
    white-space: nowrap;
}
.riseup-chart-legend,
.riseup-calendar-legend {
    display: flex;
    gap: 14px;
    font-size: 11px;
    color: #646970;
    margin-top: 6px;
}
.riseup-legend-item {
    display: flex;
    align-items: center;
    gap: 4px;
}
.riseup-legend-dot {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 2px;
}

/* Calendar */
.riseup-calendar-card {
    min-width: 300px;
}
.riseup-calendar-nav {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}
.riseup-calendar-nav .button .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
    vertical-align: middle;
}
.riseup-calendar-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}
.riseup-calendar-table th {
    text-align: center;
    font-size: 11px;
    font-weight: 600;
    color: #646970;
    padding: 4px 0;
    border-bottom: 1px solid #ddd;
}
.riseup-calendar-table td {
    text-align: center;
    vertical-align: top;
    height: 42px;
    padding: 3px 2px;
    border: 1px solid #f0f0f1;
    position: relative;
}
.riseup-cal-empty {
    background: #fafafa;
}
.riseup-cal-num {
    font-size: 12px;
    font-weight: 500;
    color: #1d2327;
}
.riseup-cal-today {
    background: #f0f6fc !important;
    border-color: #2271b1 !important;
}
.riseup-cal-today .riseup-cal-num {
    color: #2271b1;
    font-weight: 700;
}
.riseup-cal-dots {
    display: flex;
    justify-content: center;
    gap: 3px;
    margin-top: 2px;
}
.riseup-cal-dot {
    display: inline-block;
    width: 7px;
    height: 7px;
    border-radius: 50%;
}
.riseup-cal-dot-full { background: #2271b1; }
.riseup-cal-dot-incr { background: #7b1fa2; }
.riseup-cal-dot-scheduled { background: #dba617; }
.riseup-cal-count {
    display: block;
    font-size: 9px;
    color: #888;
    font-family: monospace;
}
.riseup-calendar-legend {
    margin-top: 10px;
    padding-top: 8px;
    border-top: 1px solid #eee;
}
/* Stack trace in download error modal */
.riseup-stack-trace {
    background: #faf5ff;
    border: 1px solid #d8b4fe;
    border-radius: 4px;
    padding: 10px 12px;
    font-family: 'Courier New', Courier, monospace;
    font-size: 11px;
    line-height: 1.5;
    max-height: 240px;
    overflow: auto;
    white-space: pre-wrap;
    word-break: break-all;
    color: #581c87;
}
</style>
