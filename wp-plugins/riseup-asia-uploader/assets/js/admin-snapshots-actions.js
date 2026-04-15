/**
 * Snapshot Dashboard — Actions
 *
 * Create snapshot, incremental backup, import, restore, download ZIP, delete.
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
    var SNAP_SCOPE = C.scope;
    var SNAP_ENDPOINTS = C.endpoints;
    var SNAP_LABELS = C.i18n;
    var restBase = C.restBase;
    var restNonce = C.restNonce;

    var currentRestoreId = null;
    var currentRestoreType = null;
    var currentDeleteId = null;
    var currentDownloadDiagnostic = '';

    $(document).ready(function() {
        var $status = $('#snapshot_action_status');

        // === SNAPSHOT NOW ===
        $('#btn_snapshot_now').on('click', function() {
            $('#snapshot_options').slideToggle();
            $('#import_form').slideUp();
        });
        $('#btn_cancel_snapshot').on('click', function() {
            $('#snapshot_options').slideUp();
        });
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
                beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', restNonce); },
                success: function(tables) {
                    var html = '';
                    (tables || []).forEach(function(t) {
                        html += '<label class="riseup-table-checkbox"><input type="checkbox" value="' + t + '" checked> ' + t + '</label> ';
                    });
                    $('#snapshot_tables_list').html(html);
                }
            });
        }

        $('#btn_confirm_snapshot').on('click', function() {
            var $btn = $(this);
            $btn.prop('disabled', true);
            var data = { scope: $('#snapshot_scope').val(), provider: $('#snapshot_provider').val() };
            if (data.scope === SNAP_SCOPE.custom) {
                data.tables = [];
                $('#snapshot_tables_list input:checked').each(function() { data.tables.push($(this).val()); });
            }
            $.ajax({
                url: restBase + '/' + SNAP_ENDPOINTS.schedule,
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(data),
                beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', restNonce); },
                success: function(response) {
                    App.showStatus($status, '✓ ' + SNAP_LABELS.snapshotQueued, false);
                    $('#snapshot_options').slideUp();
                    if (response.job_id) { App.startProgressPolling(response.job_id); } else { App.loadSnapshots(1); }
                },
                error: function(xhr) { App.showErrorStatus($status, xhr, SNAP_LABELS.snapshotCreateFailed); },
                complete: function() { $btn.prop('disabled', false); }
            });
        });

        // === INCREMENTAL ===
        $('#btn_incremental_now').on('click', function() {
            var $btn = $(this);
            var latestFull = App.allSnapshots.find(function(s) {
                return (s.snapshot_type === SNAP_MODE.full || (!s.snapshot_type && !s.parent_id)) && s.status === SNAP_STATUS.complete;
            });
            if (!latestFull) { App.showStatus($status, '✗ ' + SNAP_LABELS.noFullSnapshot, true); return; }
            $btn.prop('disabled', true);
            $.ajax({
                url: restBase + '/' + SNAP_ENDPOINTS.incremental,
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ parent_id: latestFull.id }),
                beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', restNonce); },
                success: function(response) {
                    App.showStatus($status, '✓ ' + SNAP_LABELS.incrementalQueued, false);
                    if (response.job_id) { App.startProgressPolling(response.job_id); } else { App.loadSnapshots(1); }
                },
                error: function(xhr) { App.showErrorStatus($status, xhr, SNAP_LABELS.incrementalFailed); },
                complete: function() { $btn.prop('disabled', false); }
            });
        });

        // === IMPORT ===
        $('#btn_import_snapshot').on('click', function() { $('#import_form').slideToggle(); $('#snapshot_options').slideUp(); });
        $('#btn_cancel_import').on('click', function() { $('#import_form').slideUp(); });
        $('#import_file').on('change', function() { $('#btn_confirm_import').prop('disabled', !this.files.length); });

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
                beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', restNonce); },
                success: function() {
                    App.showStatus($status, '✓ ' + SNAP_LABELS.importSuccess, false);
                    $('#import_form').slideUp();
                    $('#import_file').val('');
                    App.loadSnapshots(1);
                },
                error: function(xhr) { App.showErrorStatus($status, xhr, SNAP_LABELS.importFailed); },
                complete: function() { $btn.prop('disabled', false).html('<span class="dashicons dashicons-upload"></span> ' + SNAP_LABELS.uploadImport); }
            });
        });

        // === RESTORE ===
        $(document).on('click', '.btn-restore', function() {
            currentRestoreId = $(this).data('id');
            currentRestoreType = $(this).data('type') || SNAP_MODE.full;
            $('#restore_snapshot_name').text($(this).data('name'));
            if (currentRestoreType === SNAP_MODE.incremental) { $('#restore_incremental_warning').show(); } else { $('#restore_incremental_warning').hide(); }
            $('#restore_modal').show();
        });
        $('#btn_cancel_restore, #restore_modal .riseup-modal-overlay').on('click', function() { $('#restore_modal').hide(); currentRestoreId = null; });

        $('#btn_confirm_restore').on('click', function() {
            if (!currentRestoreId) return;
            var $btn = $(this);
            $btn.prop('disabled', true).text(SNAP_LABELS.restoring);
            $.ajax({
                url: restBase + '/' + SNAP_ENDPOINTS.restore,
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ id: currentRestoreId, confirm: true, mode: $('#restore_mode').val(), create_backup: $('#restore_create_backup').is(':checked') }),
                beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', restNonce); },
                success: function(response) {
                    $('#restore_modal').hide();
                    App.showStatus($status, '✓ ' + SNAP_LABELS.restoreQueued, false);
                    if (response.job_id) { App.startProgressPolling(response.job_id); } else { App.loadSnapshots(App.currentPage); }
                },
                error: function(xhr) { App.showErrorStatus($status, xhr, SNAP_LABELS.restoreFailed); },
                complete: function() { $btn.prop('disabled', false).html('<span class="dashicons dashicons-database-import"></span> ' + SNAP_LABELS.restoreNow); currentRestoreId = null; }
            });
        });

        // === DOWNLOAD ZIP ===
        $(document).on('click', '.btn-download-zip', function() {
            var id = $(this).data('id');
            var $btn = $(this);
            var origHtml = $btn.html();
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update riseup-spin" style="font-size:14px;width:14px;height:14px;"></span>');
            $.ajax({
                url: restBase + '/' + SNAP_ENDPOINTS.download,
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ id: id }),
                beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', restNonce); },
                success: function(response) { handleDownloadSuccess(response, id); },
                error: function(xhr) { handleDownloadError(xhr); },
                complete: function() { $btn.prop('disabled', false).html(origHtml); }
            });
        });

        function handleDownloadSuccess(response, id) {
            var downloadUrl = response.url || (response.data && response.data.url);
            var filename = response.filename || (response.data && response.data.filename) || 'snapshot-' + id + '.zip';
            var cached = response.cached || (response.data && response.data.cached) || false;
            var size = response.size || (response.data && response.data.size) || 0;
            if (!downloadUrl) { App.showStatus($status, '✗ ' + SNAP_LABELS.noDownloadUrl, true); return; }
            var badgeColor = cached ? '#d1e4dd' : '#fff3cd';
            var badgeText = cached ? SNAP_LABELS.cached : SNAP_LABELS.built;
            var badgeTextColor = cached ? '#0a7a4d' : '#664d03';
            App.showStatus($status,
                '✓ ZIP ready <span class="riseup-badge" style="background:' + badgeColor + ';color:' + badgeTextColor + ';font-size:10px;padding:1px 6px;">' + badgeText + '</span>' +
                (size ? ' (' + App.formatBytes(size) + ')' : ''), false);
            var a = document.createElement('a');
            a.href = downloadUrl; a.download = filename; a.target = '_blank';
            document.body.appendChild(a); a.click(); a.remove();
        }

        function handleDownloadError(xhr) {
            var err = App.extractErrorDetails(xhr);
            currentDownloadDiagnostic = err.diagnostic;
            $('#download_error_message').text(err.message);
            $('#download_error_status').text(err.status);
            $('#download_error_version').text(err.pluginVersion);
            $('#download_error_timestamp').text(err.timestamp);
            if (err.stackTrace) { $('#download_error_stack').text(err.stackTrace); $('#download_error_stack_section').show(); } else { $('#download_error_stack_section').hide(); }
            if (err.backendErrors) { $('#download_error_backend').text(err.backendErrors); $('#download_error_backend_section').show(); } else { $('#download_error_backend_section').hide(); }
            $('#download_error_modal').show();
        }

        // Download error modal
        $('#btn_close_download_error, #download_error_modal .riseup-modal-overlay').on('click', function() { $('#download_error_modal').hide(); });
        $('#btn_copy_download_error').on('click', function() {
            var $btn = $(this);
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(currentDownloadDiagnostic).then(function() {
                    $btn.text(SNAP_LABELS.copied);
                    setTimeout(function() { $btn.html('<span class="dashicons dashicons-clipboard" style="font-size:14px;width:14px;height:14px;vertical-align:middle;"></span> ' + SNAP_LABELS.copyReport); }, 2000);
                });
            } else {
                var $temp = $('<textarea>').val(currentDownloadDiagnostic).appendTo('body').select();
                document.execCommand('copy');
                $temp.remove();
                $btn.text(SNAP_LABELS.copied);
                setTimeout(function() { $btn.html('<span class="dashicons dashicons-clipboard" style="font-size:14px;width:14px;height:14px;vertical-align:middle;"></span> ' + SNAP_LABELS.copyReport); }, 2000);
            }
        });

        // === DELETE ===
        $(document).on('click', '.btn-delete-snapshot', function() {
            currentDeleteId = $(this).data('id');
            var name = $(this).data('name');
            var type = $(this).data('type') || SNAP_MODE.full;
            var incrCount = parseInt($(this).data('incr-count')) || 0;
            $('#delete_message').text(SNAP_LABELS.confirmDeleteSnap.replace('%s', name));
            if (type !== SNAP_MODE.incremental && incrCount > 0) {
                $('#delete_cascade_text').text(SNAP_LABELS.cascadeWarning.replace('%d', incrCount).replace('%d', incrCount));
                $('#delete_cascade_warning').show();
            } else { $('#delete_cascade_warning').hide(); }
            $('#delete_modal').show();
        });
        $('#btn_cancel_delete, #delete_modal .riseup-modal-overlay').on('click', function() { $('#delete_modal').hide(); currentDeleteId = null; });

        $('#btn_confirm_delete').on('click', function() {
            if (!currentDeleteId) return;
            var $btn = $(this);
            $btn.prop('disabled', true);
            $.ajax({
                url: restBase + '/' + SNAP_ENDPOINTS.delete_,
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ id: currentDeleteId }),
                beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', restNonce); },
                success: function() { $('#delete_modal').hide(); App.showStatus($status, '✓ ' + SNAP_LABELS.snapshotDeleted, false); App.loadSnapshots(App.currentPage); },
                error: function(xhr) { App.showErrorStatus($status, xhr, SNAP_LABELS.deleteFailed); },
                complete: function() { $btn.prop('disabled', false); currentDeleteId = null; }
            });
        });

        // === ESCAPE KEY — CLOSE VISIBLE MODALS ===
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                if ($('#restore_modal:visible').length) { $('#restore_modal').hide(); currentRestoreId = null; }
                if ($('#download_error_modal:visible').length) { $('#download_error_modal').hide(); }
                if ($('#delete_modal:visible').length) { $('#delete_modal').hide(); currentDeleteId = null; }
            }
        });
    });

})(jQuery);
