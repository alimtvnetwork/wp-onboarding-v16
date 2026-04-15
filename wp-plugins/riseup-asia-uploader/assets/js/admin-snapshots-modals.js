/**
 * Snapshot Dashboard — Modals (Download, Delete, Escape)
 *
 * Handles download ZIP with error modal, delete with cascade warning,
 * and Escape key handler for all modals.
 * Depends on admin-snapshots-utils.js.
 *
 * @package RiseupAsiaUploader
 * @since   2.15.0
 */
(function($) {
    'use strict';

    var C = window.RiseupSnapshots;
    var App = window.RiseupSnapshotsApp = window.RiseupSnapshotsApp || {};
    var SNAP_MODE = C.mode;
    var SNAP_ENDPOINTS = C.endpoints;
    var SNAP_LABELS = C.i18n;
    var restBase = C.restBase;
    var restNonce = C.restNonce;

    var currentDeleteId = null;
    var currentDownloadDiagnostic = '';

    $(document).ready(function() {
        var $status = $('#snapshot_action_status');

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
                success: function(response) { handleDownloadSuccess(response, id, $status); },
                error: function(xhr) { handleDownloadError(xhr); },
                complete: function() { $btn.prop('disabled', false).html(origHtml); }
            });
        });

        function handleDownloadSuccess(response, id, $status) {
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

        // === DELETE (with cascade warning) ===
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
                if ($('#restore_modal:visible').length) { $('#restore_modal').hide(); }
                if ($('#download_error_modal:visible').length) { $('#download_error_modal').hide(); }
                if ($('#delete_modal:visible').length) { $('#delete_modal').hide(); currentDeleteId = null; }
            }
        });
    });

})(jQuery);
