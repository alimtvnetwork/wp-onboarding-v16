<?php
/**
 * Snapshots Partial — Snapshot list table with pagination.
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
