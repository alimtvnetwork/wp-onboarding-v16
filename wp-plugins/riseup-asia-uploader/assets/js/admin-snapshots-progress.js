/**
 * Snapshot Dashboard — Progress Polling
 *
 * Manages real-time progress tracking for running snapshot jobs.
 * Depends on admin-snapshots-utils.js (App.formatBytes, App.showStatus).
 *
 * @package RiseupAsiaUploader
 * @since   2.15.0
 */
(function($) {
    'use strict';

    var C = window.RiseupSnapshots;
    var App = window.RiseupSnapshotsApp = window.RiseupSnapshotsApp || {};
    var SNAP_STATUS = C.status;
    var SNAP_ENDPOINTS = C.endpoints;
    var SNAP_LABELS = C.i18n;
    var restBase = C.restBase;
    var restNonce = C.restNonce;

    App.activeJobId = null;
    var progressTimer = null;

    App.startProgressPolling = function(jobId) {
        App.activeJobId = jobId;
        $('#progress_panel').slideDown();
        pollProgress();
    };

    function pollProgress() {
        if (!App.activeJobId) return;

        $.ajax({
            url: restBase + '/' + SNAP_ENDPOINTS.progress,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ job_id: App.activeJobId }),
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
                var $status = $('#snapshot_action_status');
                if (response.status === SNAP_STATUS.complete || response.status === SNAP_STATUS.failed) {
                    App.activeJobId = null;
                    if (response.status === SNAP_STATUS.complete) {
                        App.showStatus($status, '✓ ' + SNAP_LABELS.snapshotCompleted, false);
                    } else {
                        App.showStatus($status, '✗ ' + SNAP_LABELS.snapshotJobFailed, true);
                    }
                    setTimeout(function() {
                        $('#progress_panel').slideUp();
                        if (App.loadSnapshots) App.loadSnapshots(1);
                    }, 2000);
                } else {
                    progressTimer = setTimeout(pollProgress, 2000);
                }
            },
            error: function() {
                progressTimer = setTimeout(pollProgress, 5000);
            }
        });
    }

    App.stopProgressPolling = function() {
        if (progressTimer) {
            clearTimeout(progressTimer);
            progressTimer = null;
        }
        App.activeJobId = null;
    };

})(jQuery);
