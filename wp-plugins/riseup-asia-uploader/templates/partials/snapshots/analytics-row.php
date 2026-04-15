<?php
/**
 * Snapshots Partial — Storage analytics chart and backup calendar.
 *
 * Variables expected: $pluginSlug.
 *
 * @package RiseupAsiaUploader
 * @since   2.33.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
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
