/**
 * QUpload Admin — Error Logs Page JavaScript
 *
 * Handles log file viewing, copy, download, clear, and auto-refresh.
 *
 * @package QUpload
 * @since   2.1.0
 */
(function ($) {
    'use strict';

    if (typeof QUploadErrors === 'undefined') {
        return;
    }

    var config = QUploadErrors;
    var autoRefreshInterval = null;
    var currentContent = '';

    // ─── Initialization ────────────────────────────────────────────

    $(document).ready(function () {
        loadLogFile();
        bindEvents();
    });

    function bindEvents() {
        $('#btn-refresh-log').on('click', loadLogFile);
        $('#btn-copy-log').on('click', copyLogToClipboard);
        $('#btn-download-log').on('click', downloadLogFile);
        $('#btn-clear-log').on('click', clearLogFile);
        $('#chk-auto-refresh').on('change', toggleAutoRefresh);
    }

    // ─── Load Log File ─────────────────────────────────────────────

    function loadLogFile() {
        var $loading = $('#file-loading');
        var $content = $('#file-content');
        var $empty = $('#file-empty');
        var $sizeLabel = $('#file-size-label');

        $loading.show();
        $content.hide();
        $empty.hide();

        $.post(ajaxurl, {
            action: config.actions.readLogFile,
            nonce: config.nonce,
            file_type: config.activeTab
        }, function (response) {
            $loading.hide();

            if (!response.success || !response.data) {
                $empty.show();
                return;
            }

            var data = response.data;
            var hasContent = data.exists && data.content && data.content.length > 0;

            if (hasContent) {
                currentContent = data.content;
                $content.text(data.content).show();
                $sizeLabel.text(formatBytes(data.size));

                // Auto-scroll to bottom
                $content[0].scrollTop = $content[0].scrollHeight;
            } else {
                currentContent = '';
                $empty.show();
                $sizeLabel.text('');
            }
        }).fail(function () {
            $loading.hide();
            $empty.show();
        });
    }

    // ─── Copy ──────────────────────────────────────────────────────

    function copyLogToClipboard() {
        if (!currentContent) {
            return;
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(currentContent).then(function () {
                showCopyFeedback(config.i18n.copied);
            });
        } else {
            // Fallback for older browsers
            var $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(currentContent).select();
            document.execCommand('copy');
            $temp.remove();
            showCopyFeedback(config.i18n.copied);
        }
    }

    function showCopyFeedback(message) {
        var $feedback = $('<div class="qupload-copy-feedback">' + message + '</div>');
        $('body').append($feedback);

        setTimeout(function () {
            $feedback.remove();
        }, 2000);
    }

    // ─── Download ──────────────────────────────────────────────────

    function downloadLogFile() {
        if (!currentContent) {
            return;
        }

        var filename = config.activeTab + '.txt';
        var blob = new Blob([currentContent], { type: 'text/plain' });
        var url = URL.createObjectURL(blob);

        var $link = $('<a>').attr({
            href: url,
            download: 'qupload-' + filename
        });

        $('body').append($link);
        $link[0].click();
        $link.remove();
        URL.revokeObjectURL(url);
    }

    // ─── Clear ─────────────────────────────────────────────────────

    function clearLogFile() {
        if (!confirm(config.i18n.confirmClearLog)) {
            return;
        }

        $.post(ajaxurl, {
            action: config.actions.clearLogFile,
            nonce: config.nonce,
            file_type: config.activeTab
        }, function (response) {
            if (response.success) {
                loadLogFile();
            } else {
                alert(config.i18n.clearFailed);
            }
        }).fail(function () {
            alert(config.i18n.clearFailed);
        });
    }

    // ─── Auto-Refresh ──────────────────────────────────────────────

    function toggleAutoRefresh() {
        var isChecked = $(this).is(':checked');

        if (isChecked) {
            loadLogFile();
            autoRefreshInterval = setInterval(loadLogFile, 3000);
        } else {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
                autoRefreshInterval = null;
            }
        }
    }

    // ─── Helpers ───────────────────────────────────────────────────

    function formatBytes(bytes) {
        if (bytes === 0) return '0 B';

        var units = ['B', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(1024));
        var size = (bytes / Math.pow(1024, i)).toFixed(1);

        return size + ' ' + units[i];
    }

})(jQuery);
