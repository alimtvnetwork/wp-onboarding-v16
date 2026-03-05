<?php
/**
 * Admin Error Log — Scripts partial.
 *
 * Included from admin-errors.php to keep template clean.
 * Uses centralized JS constants from PHP enums (no magic strings).
 *
 * @package RiseupAsiaUploader
 * @since   2.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\AjaxActionType;
use RiseupAsia\Enums\AdminTabType;
use RiseupAsia\Enums\NonceType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\ResponseKeyType;
?>
<script type="text/javascript">
// =========================================================================
// ENUM CONSTANTS (from PHP — prevents magic strings in JS)
// =========================================================================
var ERROR_AJAX = {
    DISMISS_FLASH:   '<?php echo esc_js(AjaxActionType::DismissErrorFlash->value); ?>',
    CLEAR_SESSIONS:  '<?php echo esc_js(AjaxActionType::ClearErrorSessions->value); ?>',
    READ_LOG_FILE:   '<?php echo esc_js(AjaxActionType::ReadLogFile->value); ?>',
    CLEAR_LOG_FILE:  '<?php echo esc_js(AjaxActionType::ClearLogFile->value); ?>'
};

var ERROR_TABS = {
    SESSIONS:   '<?php echo esc_js(AdminTabType::Sessions->value); ?>',
    LOG:        '<?php echo esc_js(AdminTabType::Log->value); ?>',
    ERROR:      '<?php echo esc_js(AdminTabType::Error->value); ?>',
    STACKTRACE: '<?php echo esc_js(AdminTabType::Stacktrace->value); ?>'
};

var RESPONSE_KEYS = {
    content:  '<?php echo esc_js(ResponseKeyType::Content->value); ?>',
    exists:   '<?php echo esc_js(ResponseKeyType::Exists->value); ?>',
    size:     '<?php echo esc_js(ResponseKeyType::Size->value); ?>',
    filename: '<?php echo esc_js(ResponseKeyType::Filename->value); ?>',
    message:  '<?php echo esc_js(ResponseKeyType::Message->value); ?>'
};

jQuery(document).ready(function($) {
    var ajaxNonce = '<?php echo wp_create_nonce(NonceType::Admin->value); ?>';
    var activeTab = '<?php echo esc_js($activeTab); ?>';
    var pluginSlug = '<?php echo esc_js(PluginConfigType::Slug->value); ?>';
    var autoRefreshTimer = null;

    // =====================================================================
    // FLASH BANNER — Dismiss (mark all as seen)
    // =====================================================================
    $('#riseup-dismiss-flash').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('<?php echo esc_js(__('Dismissing...', $pluginSlug)); ?>');

        $.post(ajaxurl, {
            action: ERROR_AJAX.DISMISS_FLASH,
            nonce: ajaxNonce
        }, function(response) {
            if (response.success) {
                $('#riseup-flash-banner').slideUp(300);
                $('.tab-badge, .error-count-badge').fadeOut(200);
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('<?php echo esc_js(__('Mark as Seen', $pluginSlug)); ?>');
        });
    });

    // =====================================================================
    // CLEAR ALL ERRORS
    // =====================================================================
    $('#riseup-clear-errors').on('click', function() {
        if (!confirm('<?php echo esc_js(__('Are you sure you want to clear all error sessions? This cannot be undone.', $pluginSlug)); ?>')) {
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true);

        $.post(ajaxurl, {
            action: ERROR_AJAX.CLEAR_SESSIONS,
            nonce: ajaxNonce
        }, function(response) {
            if (response.success) {
                location.reload();
            }
        }).fail(function() {
            $btn.prop('disabled', false);
            alert('<?php echo esc_js(__('Failed to clear errors.', $pluginSlug)); ?>');
        });
    });

    // =====================================================================
    // FILE VIEWER — Load, Refresh, Copy, Download, Clear
    // =====================================================================
    if (activeTab !== ERROR_TABS.SESSIONS) {
        loadLogFile();
    }

    function loadLogFile() {
        var $loading = $('#file-loading');
        var $content = $('#file-content');
        var $empty = $('#file-empty');
        var $sizeLabel = $('#file-size-label');

        $loading.show();
        $content.hide();
        $empty.hide();

        $.post(ajaxurl, {
            action: ERROR_AJAX.READ_LOG_FILE,
            nonce: ajaxNonce,
            file_type: activeTab
        }, function(response) {
            $loading.hide();

            if (!response.success) {
                $empty.show();
                return;
            }

            var data = response.data;
            var hasContent = data[RESPONSE_KEYS.exists] && data[RESPONSE_KEYS.content].length > 0;

            if (hasContent) {
                $content.text(data[RESPONSE_KEYS.content]).show();
                $content[0].scrollTop = $content[0].scrollHeight;
                $sizeLabel.text(formatBytes(data[RESPONSE_KEYS.size]));
            } else {
                $empty.show();
                $sizeLabel.text('');
            }
        }).fail(function() {
            $loading.hide();
            $empty.show();
        });
    }

    // Refresh button
    $('#btn-refresh-log').on('click', function() {
        loadLogFile();
    });

    // Copy to clipboard
    $('#btn-copy-log').on('click', function() {
        var content = $('#file-content').text();
        if (!content) return;

        if (navigator.clipboard) {
            navigator.clipboard.writeText(content).then(function() {
                showBtnFeedback('#btn-copy-log', '<?php echo esc_js(__('Copied!', $pluginSlug)); ?>');
            });
        }
    });

    // Download file
    $('#btn-download-log').on('click', function() {
        var content = $('#file-content').text();
        if (!content) return;

        var blob = new Blob([content], { type: 'text/plain' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = activeTab + '.txt';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    });

    // Clear log file
    $('#btn-clear-log').on('click', function() {
        if (!confirm('<?php echo esc_js(__('Are you sure you want to clear this log file?', $pluginSlug)); ?>')) {
            return;
        }

        $.post(ajaxurl, {
            action: ERROR_AJAX.CLEAR_LOG_FILE,
            nonce: ajaxNonce,
            file_type: activeTab
        }, function(response) {
            if (response.success) {
                loadLogFile();
            }
        });
    });

    // Auto-refresh toggle
    $('#chk-auto-refresh').on('change', function() {
        var $dot = $('#live-dot');

        if ($(this).is(':checked')) {
            $dot.addClass('active');
            autoRefreshTimer = setInterval(loadLogFile, 3000);
        } else {
            $dot.removeClass('active');
            if (autoRefreshTimer) {
                clearInterval(autoRefreshTimer);
                autoRefreshTimer = null;
            }
        }
    });

    // =====================================================================
    // ERROR DETAILS MODAL
    // =====================================================================
    $('.toggle-error-details').on('click', function() {
        var $btn = $(this);
        var contextJson = $btn.data('context');
        var stackTrace = $btn.data('stack');
        var level = $btn.data('level');
        var message = $btn.data('message');
        var source = $btn.data('source');
        var timestamp = $btn.data('timestamp');

        // Populate summary
        $('#summary-message').text(message);
        $('#summary-source').text(source);
        $('#summary-timestamp').text(timestamp);

        // Level badge
        var $levelBadge = $('#modal-error-level');
        $levelBadge.text(level);

        // Context tree
        var context = (typeof contextJson === 'string') ? JSON.parse(contextJson) : contextJson;
        $('#modal-context-tree').html(renderContextTree(context));

        // Stack trace
        var $stackContent = $('#modal-stack-content');
        $stackContent.text(stackTrace || '<?php echo esc_js(__('No stack trace available.', $pluginSlug)); ?>');

        // Raw JSON
        var rawData = { context: context, stackTrace: stackTrace };
        $('#modal-raw-content').text(JSON.stringify(rawData, null, 2));

        // Reset to first tab
        activateModalTab('context');

        // Show modal
        $('#riseup-error-modal').show();
    });

    // Modal tab switching
    $(document).on('click', '.modal-tab', function() {
        activateModalTab($(this).data('modal-tab'));
    });

    function activateModalTab(tabName) {
        $('.modal-tab').removeClass('active');
        $('.modal-tab[data-modal-tab="' + tabName + '"]').addClass('active');
        $('.modal-tab-pane').hide();
        $('#modal-' + tabName + '-tab').show();
    }

    // Copy all from modal
    $('#modal-copy-all').on('click', function() {
        var rawContent = $('#modal-raw-content').text();
        if (navigator.clipboard) {
            navigator.clipboard.writeText(rawContent).then(function() {
                showBtnFeedback('#modal-copy-all', '<?php echo esc_js(__('Copied!', $pluginSlug)); ?>');
            });
        }
    });

    // Close modal
    $('.riseup-modal-close').on('click', function() {
        $(this).closest('.riseup-modal').hide();
    });
    $('#riseup-error-modal').on('click', function(e) {
        if (e.target === this) {
            $(this).hide();
        }
    });
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('#riseup-error-modal').hide();
        }
    });

    // =====================================================================
    // HELPERS
    // =====================================================================
    function formatBytes(bytes) {
        if (bytes === 0) return '0 B';
        var k = 1024;
        var sizes = ['B', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }

    function showBtnFeedback(selector, text) {
        var $btn = $(selector);
        var original = $btn.html();
        $btn.html('<span class="dashicons dashicons-yes"></span> ' + text);
        setTimeout(function() { $btn.html(original); }, 1500);
    }

    function renderContextTree(obj) {
        if (!obj || typeof obj !== 'object') {
            return '<span class="ctx-null"><?php echo esc_js(__('No context data', $pluginSlug)); ?></span>';
        }

        var html = '<div style="padding-left: 0;">';
        for (var key in obj) {
            if (!obj.hasOwnProperty(key)) continue;
            var val = obj[key];
            html += '<div style="margin-bottom: 4px;">';
            html += '<span class="ctx-key">' + escapeHtml(key) + ':</span> ';

            if (val === null || val === undefined) {
                html += '<span class="ctx-null">null</span>';
            } else if (typeof val === 'object') {
                html += '<pre style="margin: 4px 0 4px 16px; font-size: 11px;">' + escapeHtml(JSON.stringify(val, null, 2)) + '</pre>';
            } else {
                html += '<span class="ctx-value">' + escapeHtml(String(val)) + '</span>';
            }

            html += '</div>';
        }
        html += '</div>';
        return html;
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }
});
</script>
