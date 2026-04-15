/**
 * Snapshot Dashboard — List & Table Rendering
 *
 * Loads snapshot data, builds hierarchical table rows, handles pagination.
 * Depends on admin-snapshots-utils.js and admin-snapshots-progress.js.
 *
 * @package RiseupAsiaUploader
 * @since   2.15.0
 */
(function($) {
    'use strict';

    var C = window.RiseupSnapshots;
    var App = window.RiseupSnapshotsApp = window.RiseupSnapshotsApp || {};
    var SNAP_STATUS = C.status;
    var SNAP_MODE = C.mode;
    var SNAP_ENDPOINTS = C.endpoints;
    var SNAP_RESPONSE_KEYS = C.responseKeys;
    var SNAP_LABELS = C.i18n;
    var restBase = C.restBase;
    var restNonce = C.restNonce;

    App.currentPage = 1;
    App.allSnapshots = [];
    App.isInitialLoad = true;

    App.loadSnapshots = function(page) {
        page = page || 1;
        App.currentPage = page;
        var limit = C.paginationLimit;
        var offset = (page - 1) * limit;
        var $status = $('#snapshot_action_status');

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
                App.isInitialLoad = false;
                var snapshots = response[SNAP_RESPONSE_KEYS.snapshots] || [];
                App.allSnapshots = snapshots;

                if (snapshots.length === 0 && page === 1) {
                    $('#snapshots_empty').show();
                    return;
                }

                // Build hierarchy: group incrementals under their parent
                var fullSnapshots = [];
                var incrementalsByParent = {};

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
                    if (s.status === SNAP_STATUS.running || s.status === 'in_progress') {
                        if (s.job_id && !App.activeJobId) {
                            App.startProgressPolling(s.job_id);
                        }
                    }
                });

                var html = '';
                fullSnapshots.forEach(function(s) {
                    var incrCount = (incrementalsByParent[s.id] || []).length;
                    html += buildSnapshotRow(s, false, incrCount);

                    if (incrementalsByParent[s.id]) {
                        incrementalsByParent[s.id].forEach(function(child) {
                            html += buildSnapshotRow(child, true, 0);
                        });
                    }
                });

                // Render orphan incrementals
                snapshots.forEach(function(s) {
                    var isIncr = (s.snapshot_type === SNAP_MODE.incremental || s.scope === SNAP_MODE.incremental);
                    var isParentMissing = s.parent_id && fullSnapshots.every(function(f) { return f.id !== s.parent_id; });
                    if (isIncr && isParentMissing) {
                        html += buildSnapshotRow(s, true, 0);
                    }
                });

                $('#snapshots_tbody').html(html);
                $('#snapshots_table').show();
                renderPagination(response, page, limit);
            },
            error: function(xhr) {
                $('#snapshots_loading').hide();
                if (App.isInitialLoad) {
                    App.isInitialLoad = false;
                    $('#snapshots_empty').show();
                    return;
                }
                App.showErrorStatus($status, xhr, SNAP_LABELS.failedLoadSnapshots);
            }
        });
    };

    function renderPagination(response, page, limit) {
        var total = response[SNAP_RESPONSE_KEYS.total] || (response[SNAP_RESPONSE_KEYS.snapshots] || []).length;
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
    }

    function buildSnapshotRow(s, isNested, incrCount) {
        var isIncr = (s.snapshot_type === SNAP_MODE.incremental || s.scope === SNAP_MODE.incremental);
        var rowClass = isNested ? 'riseup-nested-row' : '';
        var isRunning = (s.status === SNAP_STATUS.running || s.status === 'in_progress');

        var html = '<tr class="' + rowClass + '" data-id="' + s.id + '" data-type="' + (s.snapshot_type || SNAP_MODE.full) + '" data-incr-count="' + incrCount + '">';
        html += '<td>' + (s.sequence || s.id) + '</td>';
        html += '<td>' + App.typeBadge(s.snapshot_type || SNAP_MODE.full) + '</td>';
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
        html += '<td>' + App.scopeBadge(s.scope) + '</td>';
        html += '<td><span class="riseup-badge" style="background:#f5f5f5;color:#757575;">' + (s.provider || '-') + '</span></td>';
        html += '<td>' + (s.table_count || '-') + '</td>';
        html += '<td>' + (s.total_rows ? s.total_rows.toLocaleString() : '-') + '</td>';
        html += '<td>' + App.formatBytes(s.file_size) + '</td>';
        html += '<td>' + App.statusBadge(s.status || SNAP_STATUS.complete) + '</td>';
        html += '<td title="' + (s.created_at || '') + '">' + App.relativeTime(s.created_at) + '</td>';
        html += '<td class="riseup-snapshot-actions">';
        if (s.status === SNAP_STATUS.complete || !s.status) {
            html += '<button class="button button-small btn-restore" data-id="' + s.id + '" data-name="' + (s.filename || '#' + s.id) + '" data-type="' + (s.snapshot_type || SNAP_MODE.full) + '" title="Restore">';
            html += '<span class="dashicons dashicons-database-import"></span></button> ';
            if (!isIncr) {
                html += '<button class="button button-small btn-download-zip" data-id="' + s.id + '" title="Download ZIP">';
                html += '<span class="dashicons dashicons-download"></span></button> ';
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

    // Refresh & Pagination
    $(document).ready(function() {
        $('#btn_refresh_list').on('click', function() {
            App.loadSnapshots(App.currentPage);
        });

        $(document).on('click', '.page-link', function() {
            App.loadSnapshots(parseInt($(this).data('page')));
        });
    });

})(jQuery);
