<?php
/**
 * Errors Partial — File viewer tab (log, error, stacktrace).
 *
 * Variables expected from parent: $pluginSlug, $activeTab.
 *
 * @package RiseupAsiaUploader
 * @since   2.33.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\AdminTabType;

$fileType = $activeTab;
$fileLabels = [
    AdminTabType::Log->value        => __('General Log', $pluginSlug) . ' (log.txt)',
    AdminTabType::Error->value      => __('Error Log', $pluginSlug) . ' (error.txt)',
    AdminTabType::Stacktrace->value => __('Stack Trace', $pluginSlug) . ' (stacktrace.txt)',
];
$fileLabel = isset($fileLabels[$fileType]) ? $fileLabels[$fileType] : $fileType;
?>
<div class="riseup-file-viewer-card">
    <div class="file-viewer-header">
        <h2><?php echo esc_html($fileLabel); ?></h2>
        <div class="file-viewer-actions">
            <span class="file-size-label" id="file-size-label"></span>

            <!-- Auto-refresh toggle -->
            <label class="auto-refresh-toggle" title="<?php esc_attr_e('Auto-refresh every 3 seconds', $pluginSlug); ?>">
                <input type="checkbox" id="chk-auto-refresh">
                <span class="auto-refresh-label">
                    <span class="live-dot" id="live-dot"></span>
                    <?php esc_html_e('Live', $pluginSlug); ?>
                </span>
            </label>

            <button type="button" class="button button-small" id="btn-refresh-log" title="<?php esc_attr_e('Refresh', $pluginSlug); ?>">
                <span class="dashicons dashicons-update"></span>
                <?php esc_html_e('Refresh', $pluginSlug); ?>
            </button>
            <button type="button" class="button button-small" id="btn-copy-log" title="<?php esc_attr_e('Copy to Clipboard', $pluginSlug); ?>">
                <span class="dashicons dashicons-clipboard"></span>
                <?php esc_html_e('Copy', $pluginSlug); ?>
            </button>
            <button type="button" class="button button-small" id="btn-download-log" title="<?php esc_attr_e('Download File', $pluginSlug); ?>">
                <span class="dashicons dashicons-download"></span>
                <?php esc_html_e('Download', $pluginSlug); ?>
            </button>
            <button type="button" class="button button-small button-link-delete" id="btn-clear-log" title="<?php esc_attr_e('Clear File', $pluginSlug); ?>">
                <span class="dashicons dashicons-trash"></span>
                <?php esc_html_e('Clear', $pluginSlug); ?>
            </button>
        </div>
    </div>
    <div class="file-viewer-body">
        <div id="file-loading" class="file-loading">
            <span class="spinner is-active"></span>
            <?php esc_html_e('Loading file contents...', $pluginSlug); ?>
        </div>
        <pre id="file-content" class="file-content-pre" style="display: none;"></pre>
        <div id="file-empty" class="file-empty" style="display: none;">
            <span class="dashicons dashicons-yes-alt"></span>
            <?php esc_html_e('File is empty or does not exist yet.', $pluginSlug); ?>
        </div>
    </div>
</div>
