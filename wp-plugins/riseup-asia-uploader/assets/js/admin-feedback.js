/**
 * Riseup Asia — Feedback / Report Page JS
 *
 * @since 2.6.0
 */
(function ($) {
    'use strict';

    var C = window.RiseupFeedback || {};

    $(function () {
        checkFeedbackReady();
        bindFormSubmit();
        bindFileValidation();
    });

    function checkFeedbackReady() {
        $.post(ajaxurl, {
            action: C.actions.checkReady,
            nonce: C.nonce
        }).done(function (res) {
            if (res.success && res.data.ready) {
                $('#riseup-feedback-form-container').show();
                $('#riseup-feedback-not-ready').hide();
            } else {
                $('#riseup-feedback-form-container').hide();
                $('#riseup-feedback-not-ready').show();

                if (res.data && res.data.fallback_url) {
                    $('#riseup-feedback-fallback-link')
                        .attr('href', res.data.fallback_url)
                        .text(res.data.fallback_url);
                    $('#riseup-feedback-fallback').show();
                }
            }
        }).fail(function () {
            showStatus(C.i18n.checkFailed, 'error');
        });
    }

    function bindFormSubmit() {
        $('#riseup-feedback-form').on('submit', function (e) {
            e.preventDefault();

            var $btn = $('#feedback-submit-btn');
            var $spinner = $('#feedback-spinner');

            $btn.prop('disabled', true);
            $spinner.addClass('is-active');

            var formData = new FormData(this);
            formData.append('action', C.actions.send);
            formData.append('nonce', C.nonce);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false
            }).done(function (res) {
                if (res.success) {
                    showStatus(res.data.Message || C.i18n.sent, 'success');
                    $('#riseup-feedback-form')[0].reset();
                    $('#feedback-file-list').empty();
                } else {
                    showStatus(res.data.Message || C.i18n.sendFailed, 'error');
                }
            }).fail(function () {
                showStatus(C.i18n.sendFailed, 'error');
            }).always(function () {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');
            });
        });
    }

    function bindFileValidation() {
        $('#feedback-screenshots').on('change', function () {
            var $list = $('#feedback-file-list');
            $list.empty();

            var files = this.files;
            var maxFiles = 3;
            var maxSize = 2 * 1024 * 1024;
            var allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

            if (files.length > maxFiles) {
                showStatus(C.i18n.tooManyFiles, 'error');
                this.value = '';
                return;
            }

            for (var i = 0; i < files.length; i++) {
                var f = files[i];
                var isValid = allowed.indexOf(f.type) !== -1 && f.size <= maxSize;
                var sizeKb = Math.round(f.size / 1024) + ' KB';
                var cls = isValid ? 'file-valid' : 'file-invalid';
                $list.append('<div class="feedback-file-item ' + cls + '">' +
                    '<span class="dashicons ' + (isValid ? 'dashicons-yes' : 'dashicons-no') + '"></span> ' +
                    f.name + ' (' + sizeKb + ')' +
                    '</div>');

                if (!isValid) {
                    this.value = '';
                    showStatus(C.i18n.invalidFile, 'error');
                    return;
                }
            }
        });
    }

    function showStatus(msg, type) {
        var $el = $('#riseup-feedback-status');
        var cls = type === 'success' ? 'notice-success' : 'notice-error';
        $el.attr('class', 'notice ' + cls + ' is-dismissible')
            .html('<p>' + msg + '</p>')
            .show();

        if (type === 'success') {
            setTimeout(function () { $el.fadeOut(); }, 5000);
        }
    }

})(jQuery);
