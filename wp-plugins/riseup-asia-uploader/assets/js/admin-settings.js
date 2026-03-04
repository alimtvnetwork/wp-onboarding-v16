/**
 * Admin Settings Page — Scripts
 *
 * Uses RiseupSettings localized object for all PHP-dependent values.
 *
 * @package RiseupAsiaUploader
 * @since   2.11.0
 */
jQuery(document).ready(function($) {
    var C = window.RiseupSettings;
    var ajaxNonce = C.nonce;

    // =========================================================================
    // AUTO-UPDATE SECTION
    // =========================================================================
    var $status = $('#update_action_status');

    function showStatus(message, isError) {
        $status.html(message).css('color', isError ? '#dc3232' : '#46b450');
        setTimeout(function() { $status.fadeOut(); }, 5000);
        $status.show();
    }

    $('#btn_test_connection').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).find('.dashicons').removeClass('dashicons-yes-alt').addClass('dashicons-update spin');

        $.post(ajaxurl, {
            action: C.updateActions.testConnection,
            nonce: ajaxNonce
        }, function(response) {
            if (response.success) {
                showStatus('✓ ' + response.data.message + (response.data.resolved_url ? ' → ' + response.data.resolved_url : ''), false);
                if (response.data.resolved_url) {
                    $('#resolved_url_display').text(response.data.resolved_url);
                }
            } else {
                showStatus('✗ ' + (response.data.message || 'Connection failed'), true);
            }
        }).fail(function() {
            showStatus('✗ Request failed', true);
        }).always(function() {
            $btn.prop('disabled', false).find('.dashicons').removeClass('dashicons-update spin').addClass('dashicons-yes-alt');
        });
    });

    $('#btn_clear_cache').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true);

        $.post(ajaxurl, {
            action: C.updateActions.clearCache,
            nonce: ajaxNonce
        }, function(response) {
            if (response.success) {
                showStatus('✓ ' + response.data.message, false);
                $('#resolved_url_display').text('');
            } else {
                showStatus('✗ ' + (response.data.message || 'Failed to clear cache'), true);
            }
        }).fail(function() {
            showStatus('✗ Request failed', true);
        }).always(function() {
            $btn.prop('disabled', false);
        });
    });

    $('#btn_check_updates').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).find('.dashicons').addClass('spin');

        $.post(ajaxurl, {
            action: C.updateActions.checkUpdates,
            nonce: ajaxNonce
        }, function(response) {
            if (response.success) {
                var msg = response.data.message;
                if (response.data.update_info && response.data.update_info.version) {
                    msg += ' (v' + response.data.update_info.version + ')';
                }
                showStatus('✓ ' + msg, false);
            } else {
                showStatus('✗ ' + (response.data.message || 'Update check failed'), true);
            }
        }).fail(function() {
            showStatus('✗ Request failed', true);
        }).always(function() {
            $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
        });
    });

    // =========================================================================
    // SNAPSHOT SETTINGS SECTION
    // =========================================================================
    var $snapStatus = $('#snap_action_status');

    function showSnapStatus(message, isError) {
        $snapStatus.html(message).css('color', isError ? '#dc3232' : '#46b450').show();
        setTimeout(function() { $snapStatus.fadeOut(); }, 5000);
    }

    // Toggle day row visibility based on frequency
    $('#snap_schedule_frequency').on('change', function() {
        var freq = $(this).val();
        var isHourly = freq === C.snapFrequency.hourly;
        $('#snap_day_row').toggle(freq === C.snapFrequency.weekly || freq === C.snapFrequency.monthly);
        $('#snap_schedule_time').closest('tr').toggle(!isHourly);
    });

    // Toggle retention rows based on type
    $('#snap_retention_type').on('change', function() {
        var type = $(this).val();
        $('#snap_retention_days_row').toggle(type === C.snapRetention.days);
        $('#snap_retention_count_row').toggle(type === C.snapRetention.count);
    });

    // Storage mode card selection
    $('input[name="snap_storage_mode"]').on('change', function() {
        var val = $(this).val();
        $('#mode_card_single').css({
            'border-color': val === C.snapStorage.single ? '#2271b1' : '#dcdcde',
            'background': val === C.snapStorage.single ? '#f0f6fc' : '#fff'
        });
        $('#mode_card_pertable').css({
            'border-color': val === C.snapStorage.perTable ? '#2271b1' : '#dcdcde',
            'background': val === C.snapStorage.perTable ? '#f0f6fc' : '#fff'
        });
    });

    // Worker pool slider live value update
    $('#snap_worker_pool_size').on('input', function() {
        $('#snap_worker_pool_value').text($(this).val());
    });

    // Load storage stats on page load
    function loadStorageStats() {
        $.post(ajaxurl, {
            action: C.snapActions.storageStats,
            nonce: ajaxNonce
        }, function(response) {
            if (response.success) {
                var d = response.data;
                var info = d.total_snapshots + ' snapshots, ' + d.total_size_formatted + ' used';
                if (d.disk_free_formatted) {
                    info += ' (' + d.disk_free_formatted + ' free)';
                }
                $('#snap_storage_info').html(info);
            } else {
                $('#snap_storage_info').html('<em>Unable to load stats</em>');
            }
        }).fail(function() {
            $('#snap_storage_info').html('<em>Unable to load stats</em>');
        });
    }
    loadStorageStats();

    // Save snapshot settings
    $('#btn_save_snapshot_settings').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true);

        $.post(ajaxurl, {
            action: C.snapActions.saveSettings,
            nonce: ajaxNonce,
            preferred_provider: $('#snap_preferred_provider').val(),
            schedule_enabled: $('#snap_schedule_enabled').is(':checked') ? '1' : '0',
            schedule_frequency: $('#snap_schedule_frequency').val(),
            schedule_time: $('#snap_schedule_time').val(),
            schedule_day: $('#snap_schedule_day').val(),
            default_scope: $('#snap_default_scope').val(),
            retention_type: $('#snap_retention_type').val(),
            retention_days: $('#snap_retention_days').val(),
            retention_count: $('#snap_retention_count').val(),
            pre_restore_backup: $('#snap_pre_restore_backup').is(':checked') ? '1' : '0',
            max_snapshot_size_mb: $('#snap_max_size').val(),
            batch_size: $('#snap_batch_size').val(),
            storage_mode: $('input[name="snap_storage_mode"]:checked').val(),
            worker_pool_size: $('#snap_worker_pool_size').val()
        }, function(response) {
            showSnapStatus('✓ ' + (response.data ? response.data.message : 'Saved'), false);
        }).fail(function() {
            showSnapStatus('✗ Failed to save settings', true);
        }).always(function() {
            $btn.prop('disabled', false);
        });
    });

    // Run cleanup
    $('#btn_run_cleanup').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).find('.dashicons').addClass('spin');

        $.post(ajaxurl, {
            action: C.snapActions.runCleanup,
            nonce: ajaxNonce
        }, function(response) {
            if (response.success) {
                showSnapStatus('✓ ' + response.data.message, false);
                loadStorageStats();
            } else {
                showSnapStatus('✗ ' + (response.data ? response.data.message : 'Cleanup failed'), true);
            }
        }).fail(function() {
            showSnapStatus('✗ Request failed', true);
        }).always(function() {
            $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
        });
    });
});
