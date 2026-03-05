<?php
/**
 * Snapshot Dashboard — Scripts Partial
 *
 * Contains all JavaScript for the snapshot admin page.
 * Included by admin-snapshots.php. Inherits $pluginSlug from parent scope.
 *
 * @package RiseupAsiaUploader
 * @since   2.6.0
 */

use RiseupAsia\Enums\AdminPageType;
use RiseupAsia\Enums\AjaxActionType;
use RiseupAsia\Enums\EndpointType;
use RiseupAsia\Enums\NonceType;
use RiseupAsia\Enums\PaginationConfigType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\RetentionType;
use RiseupAsia\Enums\SnapshotFrequencyType;
use RiseupAsia\Enums\SnapshotModeType;
use RiseupAsia\Enums\SnapshotScopeType;
use RiseupAsia\Enums\SnapshotStatusType;

if (!defined('ABSPATH')) {
    exit;
}
?>
<script type="text/javascript">
jQuery(document).ready(function($) {
    var ajaxNonce = '<?php echo wp_create_nonce(NonceType::Admin->value); ?>';
    var restNonce = '<?php echo wp_create_nonce(NonceType::WpRest->value); ?>';
    var restBase = '<?php echo esc_url(rest_url(PluginConfigType::apiFullNamespace())); ?>';
    var $status = $('#snapshot_action_status');
    var currentPage = 1;
    var currentRestoreId = null;
    var currentRestoreType = null;
    var currentDeleteId = null;
    var isInitialLoad = true;
    var progressTimer = null;
    var activeJobId = null;
    var allSnapshots = []; // cache for hierarchy building

    // =========================================================================
    // ENUM CONSTANTS (from PHP — prevents magic strings in JS)
    // =========================================================================

    var SNAP_STATUS = {
        complete:  '<?php echo esc_js(SnapshotStatusType::Complete->value); ?>',
        running:   '<?php echo esc_js(SnapshotStatusType::Running->value); ?>',
        failed:    '<?php echo esc_js(SnapshotStatusType::Failed->value); ?>',
        pending:   '<?php echo esc_js(SnapshotStatusType::Pending->value); ?>',
        scheduled: '<?php echo esc_js(SnapshotStatusType::Scheduled->value); ?>'
    };
    var SNAP_MODE = {
        full:        '<?php echo esc_js(SnapshotModeType::Full->value); ?>',
        incremental: '<?php echo esc_js(SnapshotModeType::Incremental->value); ?>'
    };
    var SNAP_SCOPE = {
        all:       '<?php echo esc_js(SnapshotScopeType::All->value); ?>',
        wordpress: '<?php echo esc_js(SnapshotScopeType::WordPress->value); ?>',
        content:   '<?php echo esc_js(SnapshotScopeType::Content->value); ?>',
        custom:    '<?php echo esc_js(SnapshotScopeType::Custom->value); ?>'
    };
    var SNAP_FREQ = {
        manual:  '<?php echo esc_js(SnapshotFrequencyType::Manual->value); ?>',
        hourly:  '<?php echo esc_js(SnapshotFrequencyType::Hourly->value); ?>',
        daily:   '<?php echo esc_js(SnapshotFrequencyType::Daily->value); ?>',
        weekly:  '<?php echo esc_js(SnapshotFrequencyType::Weekly->value); ?>',
        monthly: '<?php echo esc_js(SnapshotFrequencyType::Monthly->value); ?>'
    };


    var SNAP_ENDPOINTS = {
        list:        '<?php echo esc_js(EndpointType::SnapshotList->value); ?>',
        schedule:    '<?php echo esc_js(EndpointType::SnapshotSchedule->value); ?>',
        info:        '<?php echo esc_js(EndpointType::SnapshotInfo->value); ?>',
        delete_:     '<?php echo esc_js(EndpointType::SnapshotDelete->value); ?>',
        restore:     '<?php echo esc_js(EndpointType::SnapshotRestore->value); ?>',
        export_:     '<?php echo esc_js(EndpointType::SnapshotExport->value); ?>',
        import_:     '<?php echo esc_js(EndpointType::SnapshotImport->value); ?>',
        settings:    '<?php echo esc_js(EndpointType::SnapshotSettings->value); ?>',
        providers:   '<?php echo esc_js(EndpointType::SnapshotProviders->value); ?>',
        tables:      '<?php echo esc_js(EndpointType::SnapshotTables->value); ?>',
        fullBackup:  '<?php echo esc_js(EndpointType::SnapshotFullBackup->value); ?>',
        incremental: '<?php echo esc_js(EndpointType::SnapshotIncremental->value); ?>',
        cleanup:     '<?php echo esc_js(EndpointType::SnapshotCleanup->value); ?>',
        download:    '<?php echo esc_js(EndpointType::SnapshotDownload->value); ?>',
        progress:    '<?php echo esc_js(EndpointType::SnapshotProgress->value); ?>'
    };

    var SNAP_RESPONSE_KEYS = {
        snapshots: '<?php echo esc_js(ResponseKeyType::Snapshots->value); ?>',
        total:     '<?php echo esc_js(ResponseKeyType::Total->value); ?>',
        jobId:     '<?php echo esc_js(ResponseKeyType::JobId->value); ?>',
        message:   '<?php echo esc_js(ResponseKeyType::Message->value); ?>',
        success:   '<?php echo esc_js(ResponseKeyType::Success->value); ?>'
    };

    var SNAP_RETENTION = {
        none:  '<?php echo esc_js(RetentionType::None->value); ?>',
        days:  '<?php echo esc_js(RetentionType::Days->value); ?>',
        count: '<?php echo esc_js(RetentionType::Count->value); ?>'
    };

    var SNAP_AJAX = {
        saveSettings: '<?php echo esc_js(AjaxActionType::SaveSnapshotSettings->value); ?>'
    };

    var SNAP_LABELS = {
        copied:              '<?php echo esc_js(__("Copied!", $pluginSlug)); ?>',
        copy:                '<?php echo esc_js(__("Copy", $pluginSlug)); ?>',
        copyReport:          '<?php echo esc_js(__("Copy Report", $pluginSlug)); ?>',
        provider:            '<?php echo esc_js(__("Provider", $pluginSlug)); ?>',
        available:           '<?php echo esc_js(__("Available", $pluginSlug)); ?>',
        priority:            '<?php echo esc_js(__("Priority", $pluginSlug)); ?>',
        importing:           '<?php echo esc_js(__("Importing...", $pluginSlug)); ?>',
        uploadImport:        '<?php echo esc_js(__("Upload & Import", $pluginSlug)); ?>',
        restoring:           '<?php echo esc_js(__("Restoring...", $pluginSlug)); ?>',
        restoreNow:          '<?php echo esc_js(__("Restore Now", $pluginSlug)); ?>',
        cached:              '<?php echo esc_js(__("Cached", $pluginSlug)); ?>',
        built:               '<?php echo esc_js(__("Built", $pluginSlug)); ?>',
        confirmDeleteSnap:   '<?php echo esc_js(__("Are you sure you want to delete snapshot \"%s\"? This cannot be undone.", $pluginSlug)); ?>',
        fullBackup:          '<?php echo esc_js(__("Full backup", $pluginSlug)); ?>',
        incrementalBackup:   '<?php echo esc_js(__("Incremental", $pluginSlug)); ?>',
        scheduledBackup:     '<?php echo esc_js(__("Scheduled backup", $pluginSlug)); ?>',
        snapshotCompleted:   '<?php echo esc_js(__("Snapshot completed successfully", $pluginSlug)); ?>',
        snapshotJobFailed:   '<?php echo esc_js(__("Snapshot job failed", $pluginSlug)); ?>',
        failedLoadSnapshots: '<?php echo esc_js(__("Failed to load snapshots", $pluginSlug)); ?>',
        snapshotQueued:      '<?php echo esc_js(__("Snapshot job queued — running in background", $pluginSlug)); ?>',
        snapshotCreateFailed:'<?php echo esc_js(__("Snapshot creation failed", $pluginSlug)); ?>',
        noFullSnapshot:      '<?php echo esc_js(__("No full snapshot found — create a full snapshot first", $pluginSlug)); ?>',
        incrementalQueued:   '<?php echo esc_js(__("Incremental backup queued", $pluginSlug)); ?>',
        incrementalFailed:   '<?php echo esc_js(__("Incremental backup failed", $pluginSlug)); ?>',
        importSuccess:       '<?php echo esc_js(__("Snapshot imported successfully", $pluginSlug)); ?>',
        importFailed:        '<?php echo esc_js(__("Import failed", $pluginSlug)); ?>',
        restoreQueued:       '<?php echo esc_js(__("Restore queued — running in background", $pluginSlug)); ?>',
        restoreFailed:       '<?php echo esc_js(__("Restore failed", $pluginSlug)); ?>',
        noDownloadUrl:       '<?php echo esc_js(__("No download URL returned", $pluginSlug)); ?>',
        snapshotDeleted:     '<?php echo esc_js(__("Snapshot deleted", $pluginSlug)); ?>',
        deleteFailed:        '<?php echo esc_js(__("Delete failed", $pluginSlug)); ?>',
        cascadeWarning:      '<?php echo esc_js(__("This full snapshot has %d incremental backup(s). Deleting it will also permanently remove all %d incremental snapshot(s).", $pluginSlug)); ?>',
        settingsSaved:       '<?php echo esc_js(__("Settings saved", $pluginSlug)); ?>',
        saveFailed:          '<?php echo esc_js(__("Save failed", $pluginSlug)); ?>',
        networkError:        '<?php echo esc_js(__("Network error", $pluginSlug)); ?>',
        failedLoadSettings:  '<?php echo esc_js(__("Failed to load settings.", $pluginSlug)); ?>',
        noProvidersDetected: '<?php echo esc_js(__("No providers detected yet.", $pluginSlug)); ?>',
        failedDetectProviders:'<?php echo esc_js(__("Failed to detect providers.", $pluginSlug)); ?>',
        checkLogs:           '<?php echo esc_js(__("Check Logs", $pluginSlug)); ?>',
        incrementalSuffix:   '<?php echo esc_js(__("incremental", $pluginSlug)); ?>',
        incrementalsSuffix:  '<?php echo esc_js(__("incrementals", $pluginSlug)); ?>'
    };

    var MONTH_NAMES = [
        '<?php echo esc_js(__("January", $pluginSlug)); ?>',
        '<?php echo esc_js(__("February", $pluginSlug)); ?>',
        '<?php echo esc_js(__("March", $pluginSlug)); ?>',
        '<?php echo esc_js(__("April", $pluginSlug)); ?>',
        '<?php echo esc_js(__("May", $pluginSlug)); ?>',
        '<?php echo esc_js(__("June", $pluginSlug)); ?>',
        '<?php echo esc_js(__("July", $pluginSlug)); ?>',
        '<?php echo esc_js(__("August", $pluginSlug)); ?>',
        '<?php echo esc_js(__("September", $pluginSlug)); ?>',
        '<?php echo esc_js(__("October", $pluginSlug)); ?>',
        '<?php echo esc_js(__("November", $pluginSlug)); ?>',
        '<?php echo esc_js(__("December", $pluginSlug)); ?>'
    ];

    // =========================================================================
    // UTILITY HELPERS
    // =========================================================================

    function formatBytes(bytes) {
        if (!bytes || bytes === 0) return '0 B';
        var k = 1024;
        var sizes = ['B', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }

    function relativeTime(dateStr) {
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
    }

    function statusBadge(status) {
        // Keys match SnapshotStatusType enum values
        var colors = {};
        colors[SNAP_STATUS.complete]  = 'background:#d1e4dd;color:#0a7a4d;';
        colors[SNAP_STATUS.running]   = 'background:#fff3cd;color:#664d03;';
        colors.in_progress            = 'background:#fff3cd;color:#664d03;'; // legacy compat — pre-enum status value
        colors[SNAP_STATUS.failed]    = 'background:#f8d7da;color:#721c24;';
        colors[SNAP_STATUS.pending]   = 'background:#e3f2fd;color:#1565c0;';
        colors[SNAP_STATUS.scheduled] = 'background:#e8eaf6;color:#283593;';
        var style = colors[status] || 'background:#f5f5f5;color:#757575;';
        var icon = '';
        if (status === SNAP_STATUS.running || status === 'in_progress') { // legacy compat — pre-enum status value
            icon = '<span class="dashicons dashicons-update riseup-spin" style="font-size:12px;width:12px;height:12px;vertical-align:middle;margin-right:3px;"></span>';
        } else if (status === SNAP_STATUS.complete) {
            icon = '<span class="dashicons dashicons-yes-alt" style="font-size:12px;width:12px;height:12px;vertical-align:middle;margin-right:3px;"></span>';
        } else if (status === SNAP_STATUS.failed) {
            icon = '<span class="dashicons dashicons-dismiss" style="font-size:12px;width:12px;height:12px;vertical-align:middle;margin-right:3px;"></span>';
        }
        return '<span class="riseup-badge" style="' + style + '">' + icon + status + '</span>';
    }

    function typeBadge(snapshotType) {
        if (snapshotType === SNAP_MODE.incremental) {
            return '<span class="dashicons dashicons-randomize" style="color:#6c3483;font-size:16px;width:16px;height:16px;" title="' + SNAP_MODE.incremental + '"></span>';
        }
        return '<span class="dashicons dashicons-media-archive" style="color:#2271b1;font-size:16px;width:16px;height:16px;" title="' + SNAP_MODE.full + '"></span>';
    }

    function scopeBadge(scope) {
        // Keys match SnapshotScopeType enum values
        var colors = {};
        colors[SNAP_SCOPE.all]       = 'background:#f3e5f5;color:#7b1fa2;';
        colors[SNAP_SCOPE.wordpress] = 'background:#e3f2fd;color:#1565c0;';
        colors[SNAP_SCOPE.content]   = 'background:#e8f5e9;color:#2e7d32;';
        colors[SNAP_SCOPE.custom]    = 'background:#fff3e0;color:#e65100;';
        var style = colors[scope] || 'background:#f5f5f5;color:#757575;';
        return '<span class="riseup-badge" style="' + style + '">' + (scope || SNAP_SCOPE.all) + '</span>';
    }

    // Status helper
    function showStatus($el, message, isError) {
        $el.html(message).css('color', isError ? '#d63638' : '#00a32a').show();
        setTimeout(function() { $el.fadeOut(); }, 8000);
    }

    /**
     * Extract detailed error info from an API error response.
     */
    function extractErrorDetails(xhr) {
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
    }

    /**
     * Show a rich error status with Copy button and optional Check Logs link.
     */
    function showErrorStatus($el, xhr, contextLabel) {
        var err = extractErrorDetails(xhr);
        var label = contextLabel ? contextLabel + ': ' : '';
        var html = '<span style="color:#d63638;">✗ ' + label + err.message + '</span>';
        html += ' <button type="button" class="button button-small btn-copy-error" title="Copy diagnostic to clipboard" style="margin-left:6px;">';
        html += '<span class="dashicons dashicons-clipboard" style="font-size:14px;width:14px;height:14px;vertical-align:middle;"></span> ' + SNAP_LABELS.copy + '</button>';
        if (err.logHint) {
            html += ' <a href="<?php echo esc_url(AdminPageType::Logs->adminUrl()); ?>" class="button button-small" style="margin-left:4px;" title="' + err.logHint + '">';
            html += '<span class="dashicons dashicons-list-view" style="font-size:14px;width:14px;height:14px;vertical-align:middle;"></span> ' + SNAP_LABELS.checkLogs + '</a>';
        }
        $el.html(html).show();
        $el.data('diagnostic', err.diagnostic);
        setTimeout(function() { $el.fadeOut(); }, 15000);
    }

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

    // =========================================================================
    // PROGRESS POLLING
    // =========================================================================

    function startProgressPolling(jobId) {
        activeJobId = jobId;
        $('#progress_panel').slideDown();
        pollProgress();
    }

    function pollProgress() {
        if (!activeJobId) return;

        $.ajax({
            url: restBase + '/' + SNAP_ENDPOINTS.progress,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ job_id: activeJobId }),
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', restNonce);
            },
            success: function(response) {
                var pct = Math.round(response.percent || 0);
                $('#progress_bar').css('width', pct + '%');
                $('#progress_percent_badge').text(pct + '%');

                var metaText = '';
                if (response.status) metaText += 'Status: ' + response.status;
                if (response.tables_exported !== undefined && response.total_tables) {
                    metaText += ' — Tables: ' + response.tables_exported + '/' + response.total_tables;
                }
                if (response.current_batch && response.total_batches) {
                    metaText += ' — Batch: ' + response.current_batch + '/' + response.total_batches;
                }
                $('#progress_status_text').text(metaText);

                // Per-table progress
                if (response.table_progress && response.table_progress.length > 0) {
                    var html = '';
                    response.table_progress.forEach(function(t) {
                        var tColor = t.status === SNAP_STATUS.complete ? '#0a7a4d' : (t.status === SNAP_STATUS.running ? '#664d03' : '#757575');
                        var tIcon = t.status === SNAP_STATUS.complete ? '✓' : (t.status === SNAP_STATUS.running ? '⟳' : '○');
                        html += '<span class="riseup-table-status" style="color:' + tColor + ';">' + tIcon + ' ' + t.table + '</span> ';
                    });
                    $('#progress_tables_list').html(html);
                    $('#progress_tables').show();
                }

                // Keep polling if still running
                if (response.status === SNAP_STATUS.complete || response.status === SNAP_STATUS.failed) {
                    activeJobId = null;
                    if (response.status === SNAP_STATUS.complete) {
                        showStatus($status, '✓ ' + SNAP_LABELS.snapshotCompleted, false);
                    } else {
                        showStatus($status, '✗ ' + SNAP_LABELS.snapshotJobFailed, true);
                    }
                    setTimeout(function() {
                        $('#progress_panel').slideUp();
                        loadSnapshots(1);
                    }, 2000);
                } else {
                    progressTimer = setTimeout(pollProgress, 2000);
                }
            },
            error: function() {
                // Silently retry
                progressTimer = setTimeout(pollProgress, 5000);
            }
        });
    }

    function stopProgressPolling() {
        if (progressTimer) {
            clearTimeout(progressTimer);
            progressTimer = null;
        }
        activeJobId = null;
    }

    // =========================================================================
    // SNAPSHOT LIST WITH HIERARCHY
    // =========================================================================

    function loadSnapshots(page) {
        page = page || 1;
        currentPage = page;
        var limit = <?php echo (int) PaginationConfigType::DefaultLimit->value; ?>;
        var offset = (page - 1) * limit;

        $('#snapshots_loading').show();
        $('#snapshots_table, #snapshots_empty, #snapshots_pagination').hide();

        $.ajax({
            url: restBase + '/' + SNAP_ENDPOINTS.list + '?limit=' + limit + '&offset=' + offset,
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', restNonce);
            },
            success: function(response) {
                $('#snapshots_loading').hide();
                isInitialLoad = false;
                var snapshots = response[SNAP_RESPONSE_KEYS.snapshots] || [];
                allSnapshots = snapshots;

                if (snapshots.length === 0 && page === 1) {
                    $('#snapshots_empty').show();
                    return;
                }

                // Build hierarchy: group incrementals under their parent
                var fullSnapshots = [];
                var incrementalsByParent = {};
                var hasRunningJob = false;

                snapshots.forEach(function(s) {
                    var isIncr = (s.snapshot_type === SNAP_MODE.incremental || s.scope === SNAP_MODE.incremental);
                    if (isIncr && s.parent_id) {
                        if (!incrementalsByParent[s.parent_id]) {
                            incrementalsByParent[s.parent_id] = [];
                        }
                        incrementalsByParent[s.parent_id].push(s);
                    } else {
                        fullSnapshots.push(s);
                    }

                    // Check for running jobs
                    if (s.status === SNAP_STATUS.running || s.status === 'in_progress') { // legacy compat — pre-enum status value
                        hasRunningJob = true;
                        if (s.job_id && !activeJobId) {
                            startProgressPolling(s.job_id);
                        }
                    }
                });

                var html = '';
                fullSnapshots.forEach(function(s) {
                    var incrCount = (incrementalsByParent[s.id] || []).length;
                    html += buildSnapshotRow(s, false, incrCount);

                    // Render nested incrementals
                    if (incrementalsByParent[s.id]) {
                        incrementalsByParent[s.id].forEach(function(child) {
                            html += buildSnapshotRow(child, true, 0);
                        });
                    }
                });

                // Render orphan incrementals (no parent in current page)
                snapshots.forEach(function(s) {
                    var isIncr = (s.snapshot_type === SNAP_MODE.incremental || s.scope === SNAP_MODE.incremental);
                    var isParentMissing = s.parent_id && fullSnapshots.every(function(f) { return f.id !== s.parent_id; });
                    var isOrphanIncremental = isIncr && isParentMissing;
                    if (isOrphanIncremental) {
                        html += buildSnapshotRow(s, true, 0);
                    }
                });

                $('#snapshots_tbody').html(html);
                $('#snapshots_table').show();

                // Pagination
                var total = response[SNAP_RESPONSE_KEYS.total] || snapshots.length;
                var totalPages = Math.ceil(total / limit);
                if (totalPages > 1) {
                    $('#snapshots_count').text(total + ' items');
                    var pagesHtml = '';
                    for (var i = 1; i <= totalPages; i++) {
                        if (i === page) {
                            pagesHtml += '<span class="tablenav-pages-navspan button disabled">' + i + '</span> ';
                        } else {
                            pagesHtml += '<a class="button page-link" data-page="' + i + '">' + i + '</a> ';
                        }
                    }
                    $('#snapshots_pages').html(pagesHtml);
                    $('#snapshots_pagination').show();
                }
            },
            error: function(xhr) {
                $('#snapshots_loading').hide();
                if (isInitialLoad) {
                    isInitialLoad = false;
                    $('#snapshots_empty').show();
                    return;
                }
                showErrorStatus($status, xhr, SNAP_LABELS.failedLoadSnapshots);
            }
        });
    }

    function buildSnapshotRow(s, isNested, incrCount) {
        var isIncr = (s.snapshot_type === SNAP_MODE.incremental || s.scope === SNAP_MODE.incremental);
        var rowClass = isNested ? 'riseup-nested-row' : '';
        var isRunning = (s.status === SNAP_STATUS.running || s.status === 'in_progress'); // legacy compat — pre-enum status value

        var html = '<tr class="' + rowClass + '" data-id="' + s.id + '" data-type="' + (s.snapshot_type || SNAP_MODE.full) + '" data-incr-count="' + incrCount + '">';
        html += '<td>' + (s.sequence || s.id) + '</td>';
        html += '<td>' + typeBadge(s.snapshot_type || SNAP_MODE.full) + '</td>';
        html += '<td>';
        if (isNested) {
            html += '<span class="riseup-indent">↳</span> ';
        }
        html += '<code>' + (s.filename || '-') + '</code>';
        if (isIncr) {
            html += ' <span class="riseup-badge" style="background:#f3e5f5;color:#7b1fa2;font-size:10px;padding:1px 5px;">' + SNAP_MODE.incremental + '</span>';
        }
        if (incrCount > 0) {
            html += ' <span class="riseup-badge" style="background:#e3f2fd;color:#1565c0;font-size:10px;padding:1px 5px;">' + incrCount + ' ' + (incrCount > 1 ? SNAP_LABELS.incrementalsSuffix : SNAP_LABELS.incrementalSuffix) + '</span>';
        }
        html += '</td>';
        html += '<td>' + scopeBadge(s.scope) + '</td>';
        html += '<td><span class="riseup-badge" style="background:#f5f5f5;color:#757575;">' + (s.provider || '-') + '</span></td>';
        html += '<td>' + (s.table_count || '-') + '</td>';
        html += '<td>' + (s.total_rows ? s.total_rows.toLocaleString() : '-') + '</td>';
        html += '<td>' + formatBytes(s.file_size) + '</td>';
        html += '<td>' + statusBadge(s.status || SNAP_STATUS.complete) + '</td>';
        html += '<td title="' + (s.created_at || '') + '">' + relativeTime(s.created_at) + '</td>';
        html += '<td class="riseup-snapshot-actions">';
        if (s.status === SNAP_STATUS.complete || !s.status) {
            html += '<button class="button button-small btn-restore" data-id="' + s.id + '" data-name="' + (s.filename || '#' + s.id) + '" data-type="' + (s.snapshot_type || SNAP_MODE.full) + '" title="Restore">';
            html += '<span class="dashicons dashicons-database-import"></span></button> ';

            // Download ZIP button — only for full snapshots
            if (!isIncr) {
                html += '<button class="button button-small btn-download-zip" data-id="' + s.id + '" title="Download ZIP">';
                html += '<span class="dashicons dashicons-download"></span>';
                html += '</button> ';
            }
        }
        if (!isRunning) {
            html += '<button class="button button-small btn-delete-snapshot" data-id="' + s.id + '" data-type="' + (s.snapshot_type || SNAP_MODE.full) + '" data-name="' + (s.filename || '#' + s.id) + '" data-incr-count="' + incrCount + '" title="Delete">';
            html += '<span class="dashicons dashicons-trash" style="color:#d63638;"></span></button>';
        }
        html += '</td>';
        html += '</tr>';
        return html;
    }

    // =========================================================================
    // LOAD SETTINGS
    // =========================================================================

    function loadSettings() {
        $.ajax({
            url: restBase + '/' + SNAP_ENDPOINTS.settings,
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', restNonce);
            },
            success: function(settings) {
                $('#settings_loading').hide();
                $('#settings_form').show();
                if (settings.schedule) {
                    $('#setting_schedule').val(settings.schedule);
                    cachedSchedule = settings.schedule;
                }
                if (settings.retention_type) $('#setting_retention_type').val(settings.retention_type).trigger('change');
                if (settings.retention_value) $('#setting_retention_value').val(settings.retention_value);
                if (settings.default_scope) $('#setting_scope').val(settings.default_scope);
                if (settings.default_provider) $('#setting_provider').val(settings.default_provider);

                // Worker pool & storage mode
                if (settings.worker_pool_size) {
                    $('#setting_worker_pool').val(settings.worker_pool_size);
                    $('#worker_pool_display').text(settings.worker_pool_size);
                }
                if (settings.storage_mode) {
                    $('input[name="setting_storage_mode"][value="' + settings.storage_mode + '"]').prop('checked', true);
                    $('.riseup-storage-card').removeClass('active');
                    $('.riseup-storage-card[data-mode="' + settings.storage_mode + '"]').addClass('active');
                }
                // Rebuild calendar now that schedule is known
                buildCalendar(allSnapshots);
            },
            error: function(xhr) {
                if (isInitialLoad || xhr.status === 404) {
                    $('#settings_loading').hide();
                    $('#settings_form').show();
                    return;
                }
                $('#settings_loading').html('<em>' + SNAP_LABELS.failedLoadSettings + '</em>');
            }
        });
    }

    // Load providers
    function loadProviders() {
        $.ajax({
            url: restBase + '/' + SNAP_ENDPOINTS.providers,
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', restNonce);
            },
            success: function(providers) {
                $('#providers_loading').hide();
                var html = '<table class="wp-list-table widefat striped"><thead><tr>';
                html += '<th>' + SNAP_LABELS.provider + '</th><th>' + SNAP_LABELS.available + '</th><th>' + SNAP_LABELS.priority + '</th>';
                html += '</tr></thead><tbody>';
                (providers || []).forEach(function(p) {
                    var icon = p.available ? '✓' : '✗';
                    var color = p.available ? '#00a32a' : '#999';
                    html += '<tr>';
                    html += '<td><strong>' + p.name + '</strong></td>';
                    html += '<td style="color:' + color + ';">' + icon + '</td>';
                    html += '<td>' + (p.priority || '-') + '</td>';
                    html += '</tr>';
                });
                html += '</tbody></table>';
                $('#providers_list').html(html).show();
            },
            error: function(xhr) {
                if (isInitialLoad || xhr.status === 404) {
                    $('#providers_loading').html('<em>' + SNAP_LABELS.noProvidersDetected + '</em>');
                    return;
                }
                $('#providers_loading').html('<em>' + SNAP_LABELS.failedDetectProviders + '</em>');
            }
        });
    }

    // =========================================================================
    // ACTIONS: SNAPSHOT NOW
    // =========================================================================

    $('#btn_snapshot_now').on('click', function() {
        $('#snapshot_options').slideToggle();
        $('#import_form').slideUp();
    });

    $('#btn_cancel_snapshot').on('click', function() {
        $('#snapshot_options').slideUp();
    });

    // Scope change - show/hide custom tables
    $('#snapshot_scope').on('change', function() {
        if ($(this).val() === SNAP_SCOPE.custom) {
            $('#custom_tables_row').show();
            loadTables();
        } else {
            $('#custom_tables_row').hide();
        }
    });

    function loadTables() {
        $.ajax({
            url: restBase + '/' + SNAP_ENDPOINTS.tables,
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', restNonce);
            },
            success: function(tables) {
                var html = '';
                (tables || []).forEach(function(t) {
                    html += '<label class="riseup-table-checkbox"><input type="checkbox" value="' + t + '" checked> ' + t + '</label> ';
                });
                $('#snapshot_tables_list').html(html);
            }
        });
    }

    // Confirm create snapshot
    $('#btn_confirm_snapshot').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true);

        var data = {
            scope: $('#snapshot_scope').val(),
            provider: $('#snapshot_provider').val()
        };
        if (data.scope === SNAP_SCOPE.custom) {
            data.tables = [];
            $('#snapshot_tables_list input:checked').each(function() {
                data.tables.push($(this).val());
            });
        }

        $.ajax({
            url: restBase + '/' + SNAP_ENDPOINTS.schedule,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(data),
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', restNonce);
            },
            success: function(response) {
                showStatus($status, '✓ ' + SNAP_LABELS.snapshotQueued, false);
                $('#snapshot_options').slideUp();
                // Start progress polling if job_id returned
                if (response.job_id) {
                    startProgressPolling(response.job_id);
                } else {
                    loadSnapshots(1);
                }
            },
            error: function(xhr) {
                showErrorStatus($status, xhr, SNAP_LABELS.snapshotCreateFailed);
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });

    // =========================================================================
    // INCREMENTAL BACKUP
    // =========================================================================

    $('#btn_incremental_now').on('click', function() {
        var $btn = $(this);
        // Find the latest full snapshot
        var latestFull = allSnapshots.find(function(s) {
            return (s.snapshot_type === SNAP_MODE.full || (!s.snapshot_type && !s.parent_id)) && s.status === SNAP_STATUS.complete;
        });
        if (!latestFull) {
            showStatus($status, '✗ ' + SNAP_LABELS.noFullSnapshot, true);
            return;
        }

        $btn.prop('disabled', true);
        $.ajax({
            url: restBase + '/' + SNAP_ENDPOINTS.incremental,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ parent_id: latestFull.id }),
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', restNonce);
            },
            success: function(response) {
                showStatus($status, '✓ ' + SNAP_LABELS.incrementalQueued, false);
                if (response.job_id) {
                    startProgressPolling(response.job_id);
                } else {
                    loadSnapshots(1);
                }
            },
            error: function(xhr) {
                showErrorStatus($status, xhr, SNAP_LABELS.incrementalFailed);
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });

    // =========================================================================
    // IMPORT
    // =========================================================================

    $('#btn_import_snapshot').on('click', function() {
        $('#import_form').slideToggle();
        $('#snapshot_options').slideUp();
    });
    $('#btn_cancel_import').on('click', function() {
        $('#import_form').slideUp();
    });

    $('#import_file').on('change', function() {
        $('#btn_confirm_import').prop('disabled', !this.files.length);
    });

    $('#btn_confirm_import').on('click', function() {
        var file = $('#import_file')[0].files[0];
        if (!file) return;

        var $btn = $(this);
        $btn.prop('disabled', true).text(SNAP_LABELS.importing);

        var formData = new FormData();
        formData.append('file', file);

        $.ajax({
            url: restBase + '/' + SNAP_ENDPOINTS.import_,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', restNonce);
            },
            success: function(response) {
                showStatus($status, '✓ ' + SNAP_LABELS.importSuccess, false);
                $('#import_form').slideUp();
                $('#import_file').val('');
                loadSnapshots(1);
            },
            error: function(xhr) {
                showErrorStatus($status, xhr, SNAP_LABELS.importFailed);
            },
            complete: function() {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-upload"></span> ' + SNAP_LABELS.uploadImport);
            }
        });
    });

    // =========================================================================
    // REFRESH & PAGINATION
    // =========================================================================

    $('#btn_refresh_list').on('click', function() {
        loadSnapshots(currentPage);
    });

    $(document).on('click', '.page-link', function() {
        loadSnapshots(parseInt($(this).data('page')));
    });

    // =========================================================================
    // RESTORE
    // =========================================================================

    $(document).on('click', '.btn-restore', function() {
        currentRestoreId = $(this).data('id');
        currentRestoreType = $(this).data('type') || SNAP_MODE.full;
        $('#restore_snapshot_name').text($(this).data('name'));

        if (currentRestoreType === SNAP_MODE.incremental) {
            $('#restore_incremental_warning').show();
        } else {
            $('#restore_incremental_warning').hide();
        }

        $('#restore_modal').show();
    });

    $('#btn_cancel_restore, #restore_modal .riseup-modal-overlay').on('click', function() {
        $('#restore_modal').hide();
        currentRestoreId = null;
    });

    $('#btn_confirm_restore').on('click', function() {
        if (!currentRestoreId) return;
        var $btn = $(this);
        $btn.prop('disabled', true).text(SNAP_LABELS.restoring);

        $.ajax({
            url: restBase + '/' + SNAP_ENDPOINTS.restore,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                id: currentRestoreId,
                confirm: true,
                mode: $('#restore_mode').val(),
                create_backup: $('#restore_create_backup').is(':checked')
            }),
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', restNonce);
            },
            success: function(response) {
                $('#restore_modal').hide();
                showStatus($status, '✓ ' + SNAP_LABELS.restoreQueued, false);
                if (response.job_id) {
                    startProgressPolling(response.job_id);
                } else {
                    loadSnapshots(currentPage);
                }
            },
            error: function(xhr) {
                showErrorStatus($status, xhr, SNAP_LABELS.restoreFailed);
            },
            complete: function() {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-database-import"></span> ' + SNAP_LABELS.restoreNow);
                currentRestoreId = null;
            }
        });
    });

    // =========================================================================
    // DOWNLOAD ZIP (via new cached exporter endpoint)
    // =========================================================================

    var currentDownloadDiagnostic = '';

    $(document).on('click', '.btn-download-zip', function() {
        var id = $(this).data('id');
        var $btn = $(this);
        var origHtml = $btn.html();

        // Show building state
        $btn.prop('disabled', true).html(
            '<span class="dashicons dashicons-update riseup-spin" style="font-size:14px;width:14px;height:14px;"></span>'
        );

        $.ajax({
            url: restBase + '/' + SNAP_ENDPOINTS.download,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ id: id }),
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', restNonce);
            },
            success: function(response) {
                var downloadUrl = response.url || (response.data && response.data.url);
                var filename = response.filename || (response.data && response.data.filename) || 'snapshot-' + id + '.zip';
                var cached = response.cached || (response.data && response.data.cached) || false;
                var size = response.size || (response.data && response.data.size) || 0;

                if (!downloadUrl) {
                    showStatus($status, '✗ ' + SNAP_LABELS.noDownloadUrl, true);
                    return;
                }

                // Show cached badge briefly
                var badgeColor = cached ? '#d1e4dd' : '#fff3cd';
                var badgeText = cached ? SNAP_LABELS.cached : SNAP_LABELS.built;
                var badgeTextColor = cached ? '#0a7a4d' : '#664d03';
                showStatus($status,
                    '✓ ZIP ready ' +
                    '<span class="riseup-badge" style="background:' + badgeColor + ';color:' + badgeTextColor + ';font-size:10px;padding:1px 6px;">' + badgeText + '</span>' +
                    (size ? ' (' + formatBytes(size) + ')' : ''),
                    false
                );

                // Open download URL in new tab
                var a = document.createElement('a');
                a.href = downloadUrl;
                a.download = filename;
                a.target = '_blank';
                document.body.appendChild(a);
                a.click();
                a.remove();
            },
            error: function(xhr) {
                var err = extractErrorDetails(xhr);
                currentDownloadDiagnostic = err.diagnostic;

                // Populate error modal
                $('#download_error_message').text(err.message);
                $('#download_error_status').text(err.status);
                $('#download_error_version').text(err.pluginVersion);
                $('#download_error_timestamp').text(err.timestamp);

                if (err.stackTrace) {
                    $('#download_error_stack').text(err.stackTrace);
                    $('#download_error_stack_section').show();
                } else {
                    $('#download_error_stack_section').hide();
                }

                if (err.backendErrors) {
                    $('#download_error_backend').text(err.backendErrors);
                    $('#download_error_backend_section').show();
                } else {
                    $('#download_error_backend_section').hide();
                }

                $('#download_error_modal').show();
            },
            complete: function() {
                $btn.prop('disabled', false).html(origHtml);
            }
        });
    });

    // Download error modal actions
    $('#btn_close_download_error, #download_error_modal .riseup-modal-overlay').on('click', function() {
        $('#download_error_modal').hide();
    });

    $('#btn_copy_download_error').on('click', function() {
        var $btn = $(this);
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(currentDownloadDiagnostic).then(function() {
                $btn.text(SNAP_LABELS.copied);
                setTimeout(function() {
                    $btn.html('<span class="dashicons dashicons-clipboard" style="font-size:14px;width:14px;height:14px;vertical-align:middle;"></span> ' + SNAP_LABELS.copyReport);
                }, 2000);
            });
        } else {
            var $temp = $('<textarea>').val(currentDownloadDiagnostic).appendTo('body').select();
            document.execCommand('copy');
            $temp.remove();
            $btn.text(SNAP_LABELS.copied);
            setTimeout(function() {
                $btn.html('<span class="dashicons dashicons-clipboard" style="font-size:14px;width:14px;height:14px;vertical-align:middle;"></span> ' + SNAP_LABELS.copyReport);
            }, 2000);
        }
    });

    // =========================================================================
    // DELETE (with cascade warning)
    // =========================================================================

    $(document).on('click', '.btn-delete-snapshot', function() {
        currentDeleteId = $(this).data('id');
        var name = $(this).data('name');
        var type = $(this).data('type') || SNAP_MODE.full;
        var incrCount = parseInt($(this).data('incr-count')) || 0;

        $('#delete_message').text(SNAP_LABELS.confirmDeleteSnap.replace('%s', name));

        if (type !== SNAP_MODE.incremental && incrCount > 0) {
            $('#delete_cascade_text').text(
                SNAP_LABELS.cascadeWarning.replace('%d', incrCount).replace('%d', incrCount)
            );
            $('#delete_cascade_warning').show();
        } else {
            $('#delete_cascade_warning').hide();
        }

        $('#delete_modal').show();
    });

    $('#btn_cancel_delete, #delete_modal .riseup-modal-overlay').on('click', function() {
        $('#delete_modal').hide();
        currentDeleteId = null;
    });

    $('#btn_confirm_delete').on('click', function() {
        if (!currentDeleteId) return;
        var $btn = $(this);
        $btn.prop('disabled', true);

        $.ajax({
            url: restBase + '/' + SNAP_ENDPOINTS.delete_,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ id: currentDeleteId }),
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', restNonce);
            },
            success: function() {
                $('#delete_modal').hide();
                showStatus($status, '✓ ' + SNAP_LABELS.snapshotDeleted, false);
                loadSnapshots(currentPage);
            },
            error: function(xhr) {
                showErrorStatus($status, xhr, SNAP_LABELS.deleteFailed);
            },
            complete: function() {
                $btn.prop('disabled', false);
                currentDeleteId = null;
            }
        });
    });

    // =========================================================================
    // SETTINGS: WORKER POOL & STORAGE MODE
    // =========================================================================

    // Worker pool slider
    $('#setting_worker_pool').on('input', function() {
        $('#worker_pool_display').text($(this).val());
    });

    // Storage mode cards
    $('.riseup-storage-card').on('click', function() {
        $('.riseup-storage-card').removeClass('active');
        $(this).addClass('active');
        $(this).find('input[type="radio"]').prop('checked', true);
    });

    // Retention type change
    $('#setting_retention_type').on('change', function() {
        var val = $(this).val();
        if (val === SNAP_RETENTION.days || val === SNAP_RETENTION.count) {
            $('#retention_value_row').show();
            $('#retention_value_label').text(val === SNAP_RETENTION.days ? 'days' : 'snapshots');
        } else {
            $('#retention_value_row').hide();
        }
    });

    // Save settings (including worker pool & storage mode)
    $('#btn_save_settings').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true);

        var data = {
            action: SNAP_AJAX.saveSettings,
            nonce: ajaxNonce,
            schedule_frequency: $('#setting_schedule').val(),
            retention_type: $('#setting_retention_type').val(),
            default_scope: $('#setting_scope').val(),
            preferred_provider: $('#setting_provider').val(),
            worker_pool_size: $('#setting_worker_pool').val(),
            storage_mode: $('input[name="setting_storage_mode"]:checked').val()
        };

        var retType = data.retention_type;
        if (retType === SNAP_RETENTION.days) {
            data.retention_days = parseInt($('#setting_retention_value').val()) || 30;
        } else if (retType === SNAP_RETENTION.count) {
            data.retention_count = parseInt($('#setting_retention_value').val()) || 10;
        }

        $.post(ajaxurl, data, function(response) {
            if (response.success) {
                showStatus($('#settings_status'), '✓ ' + SNAP_LABELS.settingsSaved, false);
            } else {
                showStatus($('#settings_status'), '✗ ' + (response.data && response.data.message || SNAP_LABELS.saveFailed), true);
            }
            $btn.prop('disabled', false);
        }).fail(function() {
            showStatus($('#settings_status'), '✗ ' + SNAP_LABELS.networkError, true);
            $btn.prop('disabled', false);
        });
    });

    // =========================================================================
    // INITIAL LOAD
    // =========================================================================

    // =========================================================================
    // STORAGE ANALYTICS
    // =========================================================================

    function buildAnalytics(snapshots) {
        if (!snapshots || snapshots.length === 0) {
            $('#analytics_loading').hide();
            $('#analytics_empty').show();
            return;
        }

        var totalSize = 0;
        var largest = 0;
        snapshots.forEach(function(s) {
            var sz = parseInt(s.file_size) || 0;
            totalSize += sz;
            if (sz > largest) largest = sz;
        });
        var avgSize = Math.round(totalSize / snapshots.length);

        $('#stat_total_size').text(formatBytes(totalSize));
        $('#stat_total_count').text(snapshots.length);
        $('#stat_avg_size').text(formatBytes(avgSize));
        $('#stat_largest').text(formatBytes(largest));

        // Group by day for chart (last 30 entries max)
        var byDay = {};
        snapshots.forEach(function(s) {
            if (!s.created_at) return;
            var day = s.created_at.substring(0, 10);
            if (!byDay[day]) byDay[day] = { full: 0, incr: 0 };
            var sz = parseInt(s.file_size) || 0;
            var isIncr = (s.snapshot_type === SNAP_MODE.incremental || s.scope === SNAP_MODE.incremental);
            if (isIncr) { byDay[day].incr += sz; } else { byDay[day].full += sz; }
        });

        var days = Object.keys(byDay).sort().slice(-30);
        if (days.length === 0) {
            $('#analytics_loading').hide();
            $('#analytics_empty').show();
            return;
        }

        var maxVal = 0;
        days.forEach(function(d) { var t = byDay[d].full + byDay[d].incr; if (t > maxVal) maxVal = t; });
        if (maxVal === 0) maxVal = 1;

        // Y-axis labels
        var yHtml = '';
        for (var i = 4; i >= 0; i--) {
            yHtml += '<span>' + formatBytes(Math.round(maxVal * i / 4)) + '</span>';
        }
        $('#chart_y_axis').html(yHtml);

        // Bars
        var barsHtml = '';
        days.forEach(function(d) {
            var fullPct = Math.round((byDay[d].full / maxVal) * 100);
            var incrPct = Math.round((byDay[d].incr / maxVal) * 100);
            var label = d.substring(5); // MM-DD
            barsHtml += '<div class="riseup-bar-group" title="' + d + ': ' + formatBytes(byDay[d].full + byDay[d].incr) + '">';
            barsHtml += '<div class="riseup-bar-stack">';
            if (incrPct > 0) barsHtml += '<div class="riseup-bar riseup-bar-incr" style="height:' + incrPct + '%;"></div>';
            if (fullPct > 0) barsHtml += '<div class="riseup-bar riseup-bar-full" style="height:' + fullPct + '%;"></div>';
            barsHtml += '</div>';
            barsHtml += '<span class="riseup-bar-label">' + label + '</span>';
            barsHtml += '</div>';
        });
        $('#chart_bars').html(barsHtml);

        $('#analytics_loading').hide();
        $('#analytics_content').show();
    }

    // =========================================================================
    // MONTHLY CALENDAR
    // =========================================================================

    var calYear, calMonth, cachedSchedule = SNAP_FREQ.manual;
    (function() {
        var now = new Date();
        calYear = now.getFullYear();
        calMonth = now.getMonth();
    })();

    var monthNames = MONTH_NAMES;

    function getScheduledDates(year, month, frequency) {
        var dates = {};
        if (!frequency || frequency === SNAP_FREQ.manual) return dates;
        var daysInMonth = new Date(year, month + 1, 0).getDate();
        var today = new Date();
        today.setHours(0,0,0,0);

        if (frequency === SNAP_FREQ.daily) {
            for (var d = 1; d <= daysInMonth; d++) {
                var dt = new Date(year, month, d);
                if (dt >= today) {
                    var key = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
                    dates[key] = true;
                }
            }
        } else if (frequency === SNAP_FREQ.weekly) {
            // Every Sunday (day 0) in the month, from today onward
            for (var d = 1; d <= daysInMonth; d++) {
                var dt = new Date(year, month, d);
                if (dt >= today && dt.getDay() === 0) {
                    var key = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
                    dates[key] = true;
                }
            }
        } else if (frequency === SNAP_FREQ.monthly) {
            // 1st of month, from today onward
            var dt = new Date(year, month, 1);
            if (dt >= today) {
                var key = year + '-' + String(month + 1).padStart(2, '0') + '-01';
                dates[key] = true;
            }
        }
        return dates;
    }

    function buildCalendar(snapshots) {
        $('#cal_month_label').text(monthNames[calMonth] + ' ' + calYear);

        // Index snapshots by date
        var byDate = {};
        (snapshots || []).forEach(function(s) {
            if (!s.created_at) return;
            var day = s.created_at.substring(0, 10);
            if (!byDate[day]) byDate[day] = [];
            byDate[day].push(s);
        });

        // Compute scheduled future dates
        var scheduledDates = getScheduledDates(calYear, calMonth, cachedSchedule);

        var firstDay = new Date(calYear, calMonth, 1).getDay();
        var daysInMonth = new Date(calYear, calMonth + 1, 0).getDate();
        var today = new Date();
        var todayStr = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');

        var html = '';
        var day = 1;
        for (var row = 0; row < 6; row++) {
            if (day > daysInMonth) break;
            html += '<tr>';
            for (var col = 0; col < 7; col++) {
                if (row === 0 && col < firstDay) {
                    html += '<td class="riseup-cal-empty"></td>';
                } else if (day > daysInMonth) {
                    html += '<td class="riseup-cal-empty"></td>';
                } else {
                    var dateStr = calYear + '-' + String(calMonth + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
                    var isToday = (dateStr === todayStr);
                    var cellClass = isToday ? 'riseup-cal-today' : '';
                    var entries = byDate[dateStr] || [];
                    var isScheduled = !!scheduledDates[dateStr];

                    html += '<td class="riseup-cal-day ' + cellClass + '">';
                    html += '<span class="riseup-cal-num">' + day + '</span>';
                    if (entries.length > 0 || isScheduled) {
                        var hasFull = false, hasIncr = false;
                        entries.forEach(function(e) {
                            if (e.snapshot_type === SNAP_MODE.incremental || e.scope === SNAP_MODE.incremental) hasIncr = true;
                            else hasFull = true;
                        });
                        html += '<div class="riseup-cal-dots">';
                        if (hasFull) html += '<span class="riseup-cal-dot riseup-cal-dot-full" title="' + SNAP_LABELS.fullBackup + '"></span>';
                        if (hasIncr) html += '<span class="riseup-cal-dot riseup-cal-dot-incr" title="' + SNAP_LABELS.incrementalBackup + '"></span>';
                        if (isScheduled) html += '<span class="riseup-cal-dot riseup-cal-dot-scheduled" title="' + SNAP_LABELS.scheduledBackup + '"></span>';
                        html += '</div>';
                        if (entries.length > 0) html += '<span class="riseup-cal-count">' + entries.length + '</span>';
                    }
                    html += '</td>';
                    day++;
                }
            }
            html += '</tr>';
        }
        $('#cal_body').html(html);
    }

    $('#cal_prev').on('click', function() {
        calMonth--;
        if (calMonth < 0) { calMonth = 11; calYear--; }
        buildCalendar(allSnapshots);
    });
    $('#cal_next').on('click', function() {
        calMonth++;
        if (calMonth > 11) { calMonth = 0; calYear++; }
        buildCalendar(allSnapshots);
    });

    // Hook into snapshot load to build analytics + calendar
    var _origLoadSuccess = null;
    // We patch after initial load by observing allSnapshots changes
    function refreshAnalyticsAndCalendar() {
        buildAnalytics(allSnapshots);
        buildCalendar(allSnapshots);
    }

    // Override loadSnapshots success to also refresh analytics
    var _origLoad = loadSnapshots;
    loadSnapshots = function(page) {
        _origLoad(page);
    };

    // Use MutationObserver on tbody to detect when snapshots are rendered
    var snapshotObserver = new MutationObserver(function() {
        refreshAnalyticsAndCalendar();
    });
    snapshotObserver.observe(document.getElementById('snapshots_tbody'), { childList: true });

    // =========================================================================
    // INITIAL LOAD
    // =========================================================================

    loadSnapshots(1);
    loadSettings();
    loadProviders();
});
</script>
