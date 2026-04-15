/**
 * Snapshot Dashboard — Actions (Create, Incremental, Import, Restore)
 *
 * Handles snapshot creation, incremental backups, file import, and restore.
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

    $(document).ready(function() {
        var $status = $('#snapshot_action_status');

        // === SNAPSHOT NOW ===
        $('#btn_snapshot_now').on('click', function() {
            $('#snapshot_options').slideToggle();
            $('#import_form').slideUp();
        });
        $('#btn_cancel_snapshot').on('click', function() { $('#snapshot_options').slideUp(); });

        $('#snapshot_scope').on('change', function() {
            if ($(this).val() === SNAP_SCOPE.custom) { $('#custom_tables_row').show(); loadTables(); }
            else { $('#custom_tables_row').hide(); }
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
            var restoreType = $(this).data('type') || SNAP_MODE.full;
            $('#restore_snapshot_name').text($(this).data('name'));
            if (restoreType === SNAP_MODE.incremental) { $('#restore_incremental_warning').show(); } else { $('#restore_incremental_warning').hide(); }
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
    });

})(jQuery);
