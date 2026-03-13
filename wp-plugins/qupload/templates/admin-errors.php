<?php
/**
 * Admin Error Logs Template — Tabbed file viewer with Copy, Download, Clear.
 *
 * @package QUpload
 * @since   2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use QUpload\Enums\AdminPageType;
use QUpload\Enums\AdminTabType;
use QUpload\Enums\PluginConfigType;

$pluginName = PluginConfigType::Name->value;
$pluginSlug = PluginConfigType::Slug->value;

$activeTab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : AdminTabType::Log->value;
?>
<div class="wrap qupload-admin qupload-error-log">
    <h1>
        <span class="dashicons dashicons-warning" style="font-size: 28px; margin-right: 8px;"></span>
        <?php echo esc_html($pluginName); ?> — <?php esc_html_e('Error Logs', $pluginSlug); ?>
        <span class="qupload-version-badge">v<?php echo esc_html(PluginConfigType::Version->value); ?></span>
    </h1>

    <!-- Tab Navigation -->
    <nav class="nav-tab-wrapper qupload-tabs">
        <a href="<?php echo esc_url(add_query_arg('tab', AdminTabType::Log->value)); ?>"
           class="nav-tab <?php echo $activeTab === AdminTabType::Log->value ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-text-page"></span>
            <?php esc_html_e('Log', $pluginSlug); ?>
        </a>
        <a href="<?php echo esc_url(add_query_arg('tab', AdminTabType::Error->value)); ?>"
           class="nav-tab <?php echo $activeTab === AdminTabType::Error->value ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-warning"></span>
            <?php esc_html_e('Error Log', $pluginSlug); ?>
        </a>
        <a href="<?php echo esc_url(add_query_arg('tab', AdminTabType::Stacktrace->value)); ?>"
           class="nav-tab <?php echo $activeTab === AdminTabType::Stacktrace->value ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-editor-code"></span>
            <?php esc_html_e('Stack Trace', $pluginSlug); ?>
        </a>
    </nav>

    <!-- File Viewer -->
    <div class="qupload-tab-content">
        <?php
        $fileLabels = [
            AdminTabType::Log->value        => __('General Log', $pluginSlug) . ' (log.txt)',
            AdminTabType::Error->value      => __('Error Log', $pluginSlug) . ' (error.txt)',
            AdminTabType::Stacktrace->value => __('Stack Trace', $pluginSlug) . ' (stacktrace.txt)',
        ];
        $fileLabel = isset($fileLabels[$activeTab]) ? $fileLabels[$activeTab] : $activeTab;
        ?>

        <div class="qupload-file-viewer-card">
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
    </div>
</div>
