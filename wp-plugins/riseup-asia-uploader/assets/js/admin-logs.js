/**
 * Admin Logs Page — Scripts
 *
 * Uses RiseupLogs localized object for all PHP-dependent values.
 *
 * @package RiseupAsiaUploader
 * @since   2.11.0
 */
jQuery(document).ready(function($) {
    // Toggle details modal via button
    $('.toggle-details').on('click', function(e) {
        e.stopPropagation();
        var details = $(this).data('details');
        var formatted = JSON.stringify(details, null, 2);
        $('#riseup-details-content').text(formatted);
        $('#riseup-details-modal').show();
    });

    // Clickable rows - open details modal when row has data
    $('.riseup-log-row.has-details').on('click', function(e) {
        if ($(e.target).is('button, a, .toggle-details') || $(e.target).closest('button, a').length) {
            return;
        }
        var details = $(this).data('details');
        if (details) {
            var formatted = JSON.stringify(details, null, 2);
            $('#riseup-details-content').text(formatted);
            $('#riseup-details-modal').show();
        }
    });

    // Close modal
    $('.riseup-modal-close, .riseup-modal').on('click', function(e) {
        if (e.target === this) {
            $('#riseup-details-modal').hide();
        }
    });

    // ESC to close
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('#riseup-details-modal').hide();
        }
    });
});
