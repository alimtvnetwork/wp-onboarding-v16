<?php
/**
 * Snapshots Partial — Available providers info panel.
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
