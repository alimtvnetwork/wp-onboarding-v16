/**
 * Admin License Page — Scripts
 *
 * Uses RiseupLicense localized object for all PHP-dependent values.
 *
 * @package RiseupAsiaUploader
 * @since   2.11.0
 */
jQuery(document).ready(function($) {
    var C = window.RiseupLicense;
    var nonce = C.nonce;
    var $status = $('#license-action-status');

    function showStatus(message, isError) {
        $status.text(message)
            .removeClass('success error')
            .addClass(isError ? 'error' : 'success')
            .show();
        setTimeout(function() { $status.fadeOut(); }, 5000);
    }

    function licenseRequest(action, extraData) {
        var data = $.extend({ action: action, _nonce: nonce }, extraData || {});
        return $.post(ajaxurl, data);
    }

    // Save & Validate
    $('#btn-license-save').on('click', function() {
        var key = $('#license_key_input').val().trim();
        if (!key) {
            showStatus(C.i18n.enterKey, true);
            return;
        }

        var $btn = $(this).prop('disabled', true);

        licenseRequest(C.actions.save, { license_key: key })
            .done(function(r) {
                showStatus(r.success ? r.data.message : (r.data ? r.data.message : C.i18n.validationFailed), !r.success);
                if (r.success) setTimeout(function() { location.reload(); }, 1500);
            })
            .fail(function() { showStatus(C.i18n.requestFailed, true); })
            .always(function() { $btn.prop('disabled', false); });
    });

    // Activate
    $('#btn-license-activate').on('click', function() {
        var $btn = $(this).prop('disabled', true);

        licenseRequest(C.actions.activate)
            .done(function(r) {
                showStatus(r.success ? r.data.message : (r.data ? r.data.message : C.i18n.activationFailed), !r.success);
                if (r.success) setTimeout(function() { location.reload(); }, 1500);
            })
            .fail(function() { showStatus(C.i18n.requestFailed, true); })
            .always(function() { $btn.prop('disabled', false); });
    });

    // Deactivate
    $('#btn-license-deactivate').on('click', function() {
        if (!confirm(C.i18n.confirmDeactivate)) return;

        var $btn = $(this).prop('disabled', true);

        licenseRequest(C.actions.deactivate)
            .done(function(r) {
                showStatus(r.success ? r.data.message : (r.data ? r.data.message : C.i18n.deactivationFailed), !r.success);
                if (r.success) setTimeout(function() { location.reload(); }, 1500);
            })
            .fail(function() { showStatus(C.i18n.requestFailed, true); })
            .always(function() { $btn.prop('disabled', false); });
    });

    // Remove
    $('#btn-license-remove').on('click', function() {
        if (!confirm(C.i18n.confirmRemove)) return;

        var $btn = $(this).prop('disabled', true);

        licenseRequest(C.actions.remove)
            .done(function(r) {
                showStatus(r.success ? r.data.message : C.i18n.removalFailed, !r.success);
                if (r.success) setTimeout(function() { location.reload(); }, 1500);
            })
            .fail(function() { showStatus(C.i18n.requestFailed, true); })
            .always(function() { $btn.prop('disabled', false); });
    });

    // Refresh
    $('#btn-license-refresh').on('click', function() {
        var $btn = $(this).prop('disabled', true);
        $btn.find('.dashicons').addClass('spin');

        licenseRequest(C.actions.refresh)
            .done(function(r) {
                showStatus(r.success ? r.data.message : (r.data ? r.data.message : C.i18n.refreshFailed), !r.success);
                if (r.success) setTimeout(function() { location.reload(); }, 1500);
            })
            .fail(function() { showStatus(C.i18n.requestFailed, true); })
            .always(function() { $btn.prop('disabled', false).find('.dashicons').removeClass('spin'); });
    });
});
