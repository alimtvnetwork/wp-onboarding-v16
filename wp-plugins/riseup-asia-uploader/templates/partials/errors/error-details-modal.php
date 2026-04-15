<?php
/**
 * Errors Partial — Error details modal (context, stack trace, raw JSON tabs).
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
<!-- Details Modal (for sessions tab) -->
<div id="riseup-error-modal" class="riseup-modal" style="display: none;">
    <div class="riseup-modal-content riseup-modal-fullscreen">
        <div class="riseup-modal-header">
            <div class="modal-header-left">
                <span class="dashicons dashicons-warning modal-header-icon"></span>
                <h3><?php esc_html_e('Error Details', $pluginSlug); ?></h3>
                <span id="modal-error-level" class="level-badge modal-level-badge"></span>
            </div>
            <div class="modal-header-right">
                <button type="button" class="button button-small modal-copy-btn" id="modal-copy-all" title="<?php esc_attr_e('Copy All', $pluginSlug); ?>">
                    <span class="dashicons dashicons-clipboard"></span>
                    <?php esc_html_e('Copy All', $pluginSlug); ?>
                </button>
                <button type="button" class="riseup-modal-close">&times;</button>
            </div>
        </div>
        <div class="riseup-modal-body">
            <!-- Error summary bar -->
            <div id="error-summary-bar" class="error-summary-bar">
                <div class="summary-item">
                    <span class="summary-label"><?php esc_html_e('Message', $pluginSlug); ?></span>
                    <span id="summary-message" class="summary-value"></span>
                </div>
                <div class="summary-item">
                    <span class="summary-label"><?php esc_html_e('Source', $pluginSlug); ?></span>
                    <code id="summary-source" class="summary-value source-file"></code>
                </div>
                <div class="summary-item">
                    <span class="summary-label"><?php esc_html_e('Timestamp', $pluginSlug); ?></span>
                    <span id="summary-timestamp" class="summary-value"></span>
                </div>
            </div>

            <!-- Modal tabs -->
            <div class="modal-tabs">
                <button type="button" class="modal-tab active" data-modal-tab="context">
                    <span class="dashicons dashicons-editor-code"></span>
                    <?php esc_html_e('Context', $pluginSlug); ?>
                </button>
                <button type="button" class="modal-tab" data-modal-tab="stack">
                    <span class="dashicons dashicons-editor-alignleft"></span>
                    <?php esc_html_e('Stack Trace', $pluginSlug); ?>
                </button>
                <button type="button" class="modal-tab" data-modal-tab="raw">
                    <span class="dashicons dashicons-media-text"></span>
                    <?php esc_html_e('Raw JSON', $pluginSlug); ?>
                </button>
            </div>

            <!-- Modal tab content -->
            <div class="modal-tab-content">
                <div id="modal-context-tab" class="modal-tab-pane active">
                    <div id="modal-context-tree" class="context-tree"></div>
                </div>
                <div id="modal-stack-tab" class="modal-tab-pane" style="display: none;">
                    <pre id="modal-stack-content" class="stack-trace-pre"></pre>
                </div>
                <div id="modal-raw-tab" class="modal-tab-pane" style="display: none;">
                    <pre id="modal-raw-content" class="raw-json-pre"></pre>
                </div>
            </div>
        </div>
    </div>
</div>
