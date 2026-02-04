/**
 * Plugins Onboard Admin Scripts
 *
 * @package Plugins_Onboard
 */

(function($) {
    'use strict';

    // Document ready
    $(document).ready(function() {
        initConfirmDialogs();
        initCopyToClipboard();
        initDismissibleNotices();
    });

    /**
     * Initialize confirmation dialogs.
     */
    function initConfirmDialogs() {
        // Delete confirmations
        $('.button-link-delete').on('click', function(e) {
            if (!confirm(onboardAdmin.strings.confirm_delete)) {
                e.preventDefault();
                return false;
            }
        });

        // Restore confirmations
        $('[data-confirm-restore]').on('click', function(e) {
            if (!confirm(onboardAdmin.strings.confirm_restore)) {
                e.preventDefault();
                return false;
            }
        });

        // Clear logs confirmation
        $('[data-confirm-clear]').on('click', function(e) {
            if (!confirm(onboardAdmin.strings.confirm_clear_logs)) {
                e.preventDefault();
                return false;
            }
        });
    }

    /**
     * Initialize copy to clipboard.
     */
    function initCopyToClipboard() {
        $('[data-copy]').on('click', function(e) {
            e.preventDefault();
            var textToCopy = $(this).data('copy');
            copyToClipboard(textToCopy);
            
            // Show feedback
            var $btn = $(this);
            var originalText = $btn.text();
            $btn.text('Copied!');
            setTimeout(function() {
                $btn.text(originalText);
            }, 2000);
        });
    }

    /**
     * Copy text to clipboard.
     * 
     * @param {string} text Text to copy.
     */
    function copyToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text);
        } else {
            // Fallback for older browsers
            var textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
        }
    }

    /**
     * Initialize dismissible notices.
     */
    function initDismissibleNotices() {
        $('.notice.is-dismissible').each(function() {
            var $notice = $(this);
            var $button = $('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>');
            
            $button.on('click', function(e) {
                e.preventDefault();
                $notice.fadeOut(300, function() {
                    $notice.remove();
                });
            });
            
            $notice.append($button);
        });
    }

    /**
     * AJAX helper function.
     * 
     * @param {string} action Action name.
     * @param {object} data Request data.
     * @param {function} callback Success callback.
     */
    function ajaxRequest(action, data, callback) {
        data.action = 'onboard_' + action;
        data._wpnonce = onboardAdmin.nonce;

        $.ajax({
            url: onboardAdmin.ajaxUrl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (callback) {
                    callback(response);
                }
            },
            error: function(xhr, status, error) {
                console.error('Onboard AJAX Error:', error);
            }
        });
    }

    // Expose functions to global scope
    window.onboardAdmin = window.onboardAdmin || {};
    window.onboardAdmin.copyToClipboard = copyToClipboard;
    window.onboardAdmin.ajaxRequest = ajaxRequest;

})(jQuery);
