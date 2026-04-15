<?php
/**
 * Snapshots Partial — Progress panel shown during snapshot jobs.
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
