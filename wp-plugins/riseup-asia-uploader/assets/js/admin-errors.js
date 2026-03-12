/**
 * Admin Error Log — Scripts
 *
 * Uses RiseupErrors localized object for all PHP-dependent values.
 *
 * @package RiseupAsiaUploader
 * @since   2.11.0
 */
jQuery(document).ready(function($) {
    var C = window.RiseupErrors;
    var ajaxNonce = C.nonce;
    var activeTab = C.activeTab;
    var autoRefreshTimer = null;

    // =====================================================================
    // FLASH BANNER — Dismiss (mark all as seen)
    // =====================================================================
    $('#riseup-dismiss-flash').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text(C.i18n.dismissing);

        $.post(ajaxurl, {
            action: C.actions.dismissFlash,
            nonce: ajaxNonce
        }, function(response) {
            if (response.success) {
                $('#riseup-flash-banner').slideUp(300);
                $('.tab-badge, .error-count-badge').fadeOut(200);
            }
        }).fail(function() {
            $btn.prop('disabled', false).text(C.i18n.markAsSeen);
        });
    });

    // =====================================================================
    // CLEAR ALL ERRORS
    // =====================================================================
    $('#riseup-clear-errors').on('click', function() {
        if (!confirm(C.i18n.confirmClearAll)) {
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true);

        $.post(ajaxurl, {
            action: C.actions.clearSessions,
            nonce: ajaxNonce
        }, function(response) {
            if (response.success) {
                location.reload();
                return;
            }

            $btn.prop('disabled', false);
            var msg = (response.data && response.data[C.responseKeys.message]) ? response.data[C.responseKeys.message] : C.i18n.clearFailed;
            alert(msg);
        }).fail(function() {
            $btn.prop('disabled', false);
            alert(C.i18n.clearFailed);
        });
    });

    // =====================================================================
    // FILE VIEWER — Load, Refresh, Copy, Download, Clear
    // =====================================================================
    if (activeTab !== C.tabs.sessions) {
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
            action: C.actions.readLogFile,
            nonce: ajaxNonce,
            file_type: activeTab
        }, function(response) {
            $loading.hide();

            if (!response.success) {
                $empty.show();
                return;
            }

            var data = response.data;
            var hasContent = data[C.responseKeys.exists] && data[C.responseKeys.content].length > 0;

            if (hasContent) {
                $content.text(data[C.responseKeys.content]).show();
                $content[0].scrollTop = $content[0].scrollHeight;
                $sizeLabel.text(formatBytes(data[C.responseKeys.size]));
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
                showBtnFeedback('#btn-copy-log', C.i18n.copied);
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
        if (!confirm(C.i18n.confirmClearLog)) {
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true);

        $.post(ajaxurl, {
            action: C.actions.clearLogFile,
            nonce: ajaxNonce,
            file_type: activeTab
        }, function(response) {
            if (response.success) {
                loadLogFile();
                $btn.prop('disabled', false);
                return;
            }

            $btn.prop('disabled', false);
            var msg = (response.data && response.data[C.responseKeys.message]) ? response.data[C.responseKeys.message] : C.i18n.clearFailed;
            alert(msg);
        }).fail(function() {
            $btn.prop('disabled', false);
            alert(C.i18n.clearFailed);
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
        $stackContent.text(stackTrace || C.i18n.noStackTrace);

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
                showBtnFeedback('#modal-copy-all', C.i18n.copied);
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
            return '<span class="ctx-null">' + C.i18n.noContextData + '</span>';
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
