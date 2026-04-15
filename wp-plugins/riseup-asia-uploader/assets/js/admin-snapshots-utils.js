/**
 * Snapshot Dashboard — Utility Helpers
 *
 * Badge renderers, status display, error extraction, clipboard copy.
 * Attached to window.RiseupSnapshotsApp namespace.
 *
 * @package RiseupAsiaUploader
 * @since   2.15.0
 */
(function($) {
    'use strict';

    var C = window.RiseupSnapshots;
    var SNAP_STATUS = C.status;
    var SNAP_MODE = C.mode;
    var SNAP_SCOPE = C.scope;
    var SNAP_LABELS = C.i18n;

    var App = window.RiseupSnapshotsApp = window.RiseupSnapshotsApp || {};

    App.formatBytes = function(bytes) {
        if (!bytes || bytes === 0) return '0 B';
        var k = 1024;
        var sizes = ['B', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    };

    App.relativeTime = function(dateStr) {
        if (!dateStr) return '';
        try {
            var d = new Date(dateStr);
            var now = new Date();
            var diffMs = now.getTime() - d.getTime();
            var diffMins = Math.floor(diffMs / 60000);
            if (diffMins < 1) return 'just now';
            if (diffMins < 60) return diffMins + 'm ago';
            var diffHours = Math.floor(diffMins / 60);
            if (diffHours < 24) return diffHours + 'h ago';
            var diffDays = Math.floor(diffHours / 24);
            return diffDays + 'd ago';
        } catch (e) {
            return dateStr;
        }
    };

    App.statusBadge = function(status) {
        var colors = {};
        colors[SNAP_STATUS.complete]  = 'background:#d1e4dd;color:#0a7a4d;';
        colors[SNAP_STATUS.running]   = 'background:#fff3cd;color:#664d03;';
        colors.in_progress            = 'background:#fff3cd;color:#664d03;';
        colors[SNAP_STATUS.failed]    = 'background:#f8d7da;color:#721c24;';
        colors[SNAP_STATUS.pending]   = 'background:#e3f2fd;color:#1565c0;';
        colors[SNAP_STATUS.scheduled] = 'background:#e8eaf6;color:#283593;';
        var style = colors[status] || 'background:#f5f5f5;color:#757575;';
        var icon = '';
        if (status === SNAP_STATUS.running || status === 'in_progress') {
            icon = '<span class="dashicons dashicons-update riseup-spin" style="font-size:12px;width:12px;height:12px;vertical-align:middle;margin-right:3px;"></span>';
        } else if (status === SNAP_STATUS.complete) {
            icon = '<span class="dashicons dashicons-yes-alt" style="font-size:12px;width:12px;height:12px;vertical-align:middle;margin-right:3px;"></span>';
        } else if (status === SNAP_STATUS.failed) {
            icon = '<span class="dashicons dashicons-dismiss" style="font-size:12px;width:12px;height:12px;vertical-align:middle;margin-right:3px;"></span>';
        }
        return '<span class="riseup-badge" style="' + style + '">' + icon + status + '</span>';
    };

    App.typeBadge = function(snapshotType) {
        if (snapshotType === SNAP_MODE.incremental) {
            return '<span class="dashicons dashicons-randomize" style="color:#6c3483;font-size:16px;width:16px;height:16px;" title="' + SNAP_MODE.incremental + '"></span>';
        }
        return '<span class="dashicons dashicons-media-archive" style="color:#2271b1;font-size:16px;width:16px;height:16px;" title="' + SNAP_MODE.full + '"></span>';
    };

    App.scopeBadge = function(scope) {
        var colors = {};
        colors[SNAP_SCOPE.all]       = 'background:#f3e5f5;color:#7b1fa2;';
        colors[SNAP_SCOPE.wordpress] = 'background:#e3f2fd;color:#1565c0;';
        colors[SNAP_SCOPE.content]   = 'background:#e8f5e9;color:#2e7d32;';
        colors[SNAP_SCOPE.custom]    = 'background:#fff3e0;color:#e65100;';
        var style = colors[scope] || 'background:#f5f5f5;color:#757575;';
        return '<span class="riseup-badge" style="' + style + '">' + (scope || SNAP_SCOPE.all) + '</span>';
    };

    App.showStatus = function($el, message, isError) {
        $el.html(message).css('color', isError ? '#d63638' : '#00a32a').show();
        setTimeout(function() { $el.fadeOut(); }, 8000);
    };

    App.extractErrorDetails = function(xhr) {
        var resp = xhr.responseJSON || {};
        var status = xhr.status || 0;
        var msg = resp.message || resp.Status && resp.Status.Message || 'Unknown error';
        var pluginVersion = resp.plugin_version || resp.Status && resp.Status.PluginVersion || '?';
        var timestamp = resp.timestamp || new Date().toISOString();
        var logHint = resp.log_hint || '';
        var stackTrace = '';
        var backendErrors = '';

        if (resp.Errors) {
            if (resp.Errors.DelegatedServiceErrorStack && resp.Errors.DelegatedServiceErrorStack.length) {
                stackTrace = resp.Errors.DelegatedServiceErrorStack.join('\n');
            }
            if (resp.Errors.BackendMessage) {
                backendErrors = resp.Errors.BackendMessage;
            }
            if (resp.Errors.Backend && resp.Errors.Backend.length) {
                stackTrace = stackTrace || resp.Errors.Backend.join('\n');
            }
        }
        if (resp.data && resp.data.stack_trace) {
            stackTrace = stackTrace || resp.data.stack_trace;
        }

        var diagnostic = '## Error Report\n\n';
        diagnostic += '**Status:** ' + status + '\n';
        diagnostic += '**Message:** ' + msg + '\n';
        diagnostic += '**Plugin Version:** ' + pluginVersion + '\n';
        diagnostic += '**Timestamp:** ' + timestamp + '\n';
        if (backendErrors) diagnostic += '**Backend:** ' + backendErrors + '\n';
        if (logHint) diagnostic += '**Log Hint:** ' + logHint + '\n';
        if (stackTrace) diagnostic += '\n**Stack Trace:**\n```\n' + stackTrace + '\n```\n';

        return {
            message: msg,
            status: status,
            pluginVersion: pluginVersion,
            timestamp: timestamp,
            logHint: logHint,
            stackTrace: stackTrace,
            backendErrors: backendErrors,
            diagnostic: diagnostic
        };
    };

    App.showErrorStatus = function($el, xhr, contextLabel) {
        var err = App.extractErrorDetails(xhr);
        var label = contextLabel ? contextLabel + ': ' : '';
        var html = '<span style="color:#d63638;">✗ ' + label + err.message + '</span>';
        html += ' <button type="button" class="button button-small btn-copy-error" title="Copy diagnostic to clipboard" style="margin-left:6px;">';
        html += '<span class="dashicons dashicons-clipboard" style="font-size:14px;width:14px;height:14px;vertical-align:middle;"></span> ' + SNAP_LABELS.copy + '</button>';
        if (err.logHint) {
            html += ' <a href="' + C.logsPageUrl + '" class="button button-small" style="margin-left:4px;" title="' + err.logHint + '">';
            html += '<span class="dashicons dashicons-list-view" style="font-size:14px;width:14px;height:14px;vertical-align:middle;"></span> ' + SNAP_LABELS.checkLogs + '</a>';
        }
        $el.html(html).show();
        $el.data('diagnostic', err.diagnostic);
        setTimeout(function() { $el.fadeOut(); }, 15000);
    };

    // Copy error diagnostic to clipboard
    $(document).on('click', '.btn-copy-error', function(e) {
        e.preventDefault();
        var $statusEl = $(this).closest('.riseup-inline-status, #snapshot_action_status, #settings_status');
        var diagnostic = $statusEl.data('diagnostic') || 'No diagnostic data available.';

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(diagnostic).then(function() {
                var $btn = $(e.target).closest('.btn-copy-error');
                $btn.text(SNAP_LABELS.copied);
                setTimeout(function() {
                    $btn.html('<span class="dashicons dashicons-clipboard" style="font-size:14px;width:14px;height:14px;vertical-align:middle;"></span> ' + SNAP_LABELS.copy);
                }, 2000);
            });
        } else {
            var $temp = $('<textarea>').val(diagnostic).appendTo('body').select();
            document.execCommand('copy');
            $temp.remove();
        }
    });

})(jQuery);
