/**
 * Snapshot Dashboard — Settings & Providers
 *
 * Load/save snapshot settings, worker pool slider, storage mode cards,
 * retention type, and provider detection.
 * Depends on admin-snapshots-utils.js.
 *
 * @package RiseupAsiaUploader
 * @since   2.15.0
 */
(function($) {
    'use strict';

    var C = window.RiseupSnapshots;
    var App = window.RiseupSnapshotsApp = window.RiseupSnapshotsApp || {};
    var SNAP_ENDPOINTS = C.endpoints;
    var SNAP_RETENTION = C.retention;
    var SNAP_AJAX = C.actions;
    var SNAP_LABELS = C.i18n;
    var restBase = C.restBase;
    var restNonce = C.restNonce;
    var ajaxNonce = C.nonce;

    App.cachedSchedule = C.frequency.manual;

    App.loadSettings = function() {
        $.ajax({
            url: restBase + '/' + SNAP_ENDPOINTS.settings,
            method: 'GET',
            beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', restNonce); },
            success: function(settings) {
                $('#settings_loading').hide();
                $('#settings_form').show();
                if (settings.schedule) { $('#setting_schedule').val(settings.schedule); App.cachedSchedule = settings.schedule; }
                if (settings.retention_type) $('#setting_retention_type').val(settings.retention_type).trigger('change');
                if (settings.retention_value) $('#setting_retention_value').val(settings.retention_value);
                if (settings.default_scope) $('#setting_scope').val(settings.default_scope);
                if (settings.default_provider) $('#setting_provider').val(settings.default_provider);
                if (settings.worker_pool_size) { $('#setting_worker_pool').val(settings.worker_pool_size); $('#worker_pool_display').text(settings.worker_pool_size); }
                if (settings.storage_mode) {
                    $('input[name="setting_storage_mode"][value="' + settings.storage_mode + '"]').prop('checked', true);
                    $('.riseup-storage-card').removeClass('active');
                    $('.riseup-storage-card[data-mode="' + settings.storage_mode + '"]').addClass('active');
                }
                if (App.buildCalendar) App.buildCalendar(App.allSnapshots);
            },
            error: function(xhr) {
                if (App.isInitialLoad || xhr.status === 404) { $('#settings_loading').hide(); $('#settings_form').show(); return; }
                $('#settings_loading').html('<em>' + SNAP_LABELS.failedLoadSettings + '</em>');
            }
        });
    };

    App.loadProviders = function() {
        $.ajax({
            url: restBase + '/' + SNAP_ENDPOINTS.providers,
            method: 'GET',
            beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', restNonce); },
            success: function(providers) {
                $('#providers_loading').hide();
                var html = '<table class="wp-list-table widefat striped"><thead><tr>';
                html += '<th>' + SNAP_LABELS.provider + '</th><th>' + SNAP_LABELS.available + '</th><th>' + SNAP_LABELS.priority + '</th>';
                html += '</tr></thead><tbody>';
                (providers || []).forEach(function(p) {
                    var icon = p.available ? '✓' : '✗';
                    var color = p.available ? '#00a32a' : '#999';
                    html += '<tr><td><strong>' + p.name + '</strong></td><td style="color:' + color + ';">' + icon + '</td><td>' + (p.priority || '-') + '</td></tr>';
                });
                html += '</tbody></table>';
                $('#providers_list').html(html).show();
            },
            error: function(xhr) {
                if (App.isInitialLoad || xhr.status === 404) { $('#providers_loading').html('<em>' + SNAP_LABELS.noProvidersDetected + '</em>'); return; }
                $('#providers_loading').html('<em>' + SNAP_LABELS.failedDetectProviders + '</em>');
            }
        });
    };

    $(document).ready(function() {
        $('#setting_worker_pool').on('input', function() { $('#worker_pool_display').text($(this).val()); });

        $('.riseup-storage-card').on('click', function() {
            $('.riseup-storage-card').removeClass('active');
            $(this).addClass('active');
            $(this).find('input[type="radio"]').prop('checked', true);
        });

        $('#setting_retention_type').on('change', function() {
            var val = $(this).val();
            if (val === SNAP_RETENTION.days || val === SNAP_RETENTION.count) {
                $('#retention_value_row').show();
                $('#retention_value_label').text(val === SNAP_RETENTION.days ? 'days' : 'snapshots');
            } else { $('#retention_value_row').hide(); }
        });

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
            if (retType === SNAP_RETENTION.days) { data.retention_days = parseInt($('#setting_retention_value').val()) || 30; }
            else if (retType === SNAP_RETENTION.count) { data.retention_count = parseInt($('#setting_retention_value').val()) || 10; }

            $.post(ajaxurl, data, function(response) {
                if (response.success) { App.showStatus($('#settings_status'), '✓ ' + SNAP_LABELS.settingsSaved, false); }
                else { App.showStatus($('#settings_status'), '✗ ' + (response.data && response.data.message || SNAP_LABELS.saveFailed), true); }
                $btn.prop('disabled', false);
            }).fail(function() {
                App.showStatus($('#settings_status'), '✗ ' + SNAP_LABELS.networkError, true);
                $btn.prop('disabled', false);
            });
        });
    });

})(jQuery);
