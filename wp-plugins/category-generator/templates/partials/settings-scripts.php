<?php
/**
 * Settings Page - Scripts
 * 
 * @package Category_Generator_Area
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<script>
jQuery(document).ready(function($) {
    const RESET_CONFIRM_TEXT = '<?php echo esc_js(CG_Constants::RESET_CONFIRM_TEXT); ?>';
    const NOTICE_DURATION = <?php echo CG_Constants::NOTICE_DURATION; ?>;
    
    // Tab switching
    $('.<?php echo esc_js(CG_CSS::LAYOUT_TAB); ?>').on('click', function() {
        const tab = $(this).data('tab');
        $('.<?php echo esc_js(CG_CSS::LAYOUT_TAB); ?>').removeClass('active');
        $(this).addClass('active');
        $('.<?php echo esc_js(CG_CSS::LAYOUT_TAB_CONTENT); ?>').removeClass('active');
        $('#tab-' + tab).addClass('active');
    });
    
    // AI Provider switching
    $('#ai_provider').on('change', function() {
        const provider = $(this).val();
        $('.cg-ai-provider-config').hide();
        $('#ai-config-' + provider).show();
    });
    
    // Save settings
    $('#cg-settings-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = $(this).serializeArray();
        const data = {
            action: '<?php echo esc_js(CG_Constants::AJAX_SAVE_SETTINGS); ?>',
            nonce: cgAdmin.nonce
        };
        
        formData.forEach(function(item) {
            data[item.name] = item.value;
        });
        
        $.ajax({
            url: cgAdmin.ajaxUrl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    const $notice = $('<div class="cg-save-notice">✓ <?php echo esc_js(__('Settings saved successfully!', 'category-generator')); ?></div>');
                    $('body').append($notice);
                    setTimeout(function() { $notice.fadeOut(300, function() { $(this).remove(); }); }, NOTICE_DURATION);
                } else {
                    alert(response.data.message || '<?php echo esc_js(__('Error saving settings', 'category-generator')); ?>');
                }
            }
        });
    });
    
    // Add new API
    $('#cg-add-api-btn').on('click', function() {
        const name = $('#new_api_name').val().trim();
        const url = $('#new_api_url').val().trim();
        const key = $('#new_api_key').val();
        
        if (!name || !url) {
            alert('<?php echo esc_js(__('Please enter API name and URL', 'category-generator')); ?>');
            return;
        }
        
        $.ajax({
            url: cgAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: '<?php echo esc_js(CG_Constants::AJAX_ADD_REMOTE_API); ?>',
                nonce: cgAdmin.nonce,
                name: name,
                url: url,
                api_key: key
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || '<?php echo esc_js(__('Error adding API', 'category-generator')); ?>');
                }
            }
        });
    });
    
    // Import from API
    $(document).on('click', '.cg-import-from-api', function() {
        const apiId = $(this).data('id');
        const $btn = $(this);
        
        $btn.prop('disabled', true).text('<?php echo esc_js(__('Importing...', 'category-generator')); ?>');
        
        $.ajax({
            url: cgAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: '<?php echo esc_js(CG_Constants::AJAX_IMPORT_REMOTE); ?>',
                nonce: cgAdmin.nonce,
                api_id: apiId
            },
            success: function(response) {
                $btn.prop('disabled', false).text('<?php echo esc_js(__('Import Templates', 'category-generator')); ?>');
                if (response.success) {
                    alert('<?php echo esc_js(__('Imported', 'category-generator')); ?> ' + response.data.count + ' <?php echo esc_js(__('templates successfully!', 'category-generator')); ?>');
                } else {
                    alert(response.data.message || '<?php echo esc_js(__('Import failed', 'category-generator')); ?>');
                }
            }
        });
    });
    
    // Delete API
    $(document).on('click', '.cg-delete-api', function() {
        if (!confirm('<?php echo esc_js(__('Are you sure you want to delete this API?', 'category-generator')); ?>')) return;
        
        const apiId = $(this).data('id');
        
        $.ajax({
            url: cgAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: '<?php echo esc_js(CG_Constants::AJAX_DELETE_REMOTE_API); ?>',
                nonce: cgAdmin.nonce,
                api_id: apiId
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                }
            }
        });
    });
    
    // Export before reset
    $('#cg-export-before-reset').on('click', function() {
        window.location.href = cgAdmin.ajaxUrl + '?action=<?php echo esc_js(CG_Constants::AJAX_EXPORT_DATA); ?>&nonce=' + cgAdmin.nonce + '&type=all';
    });
    
    // Download database
    $('#cg-download-db-btn').on('click', function() {
        window.location.href = cgAdmin.ajaxUrl + '?action=<?php echo esc_js(CG_Constants::AJAX_DOWNLOAD_DATABASE); ?>&nonce=' + cgAdmin.nonce;
    });
    
    // Open restore modal
    $('#cg-restore-db-btn').on('click', function() {
        $('#cg-restore-file').val('');
        $('#cg-restore-file-info').hide();
        $('#cg-confirm-restore-btn').prop('disabled', true);
        $('#<?php echo esc_js(CG_CSS::ID_SETTINGS_RESTORE_MODAL); ?>').fadeIn(200);
    });
    
    // Handle file selection for restore
    $('#cg-restore-file').on('change', function() {
        const file = this.files[0];
        if (file) {
            $('#cg-restore-filename').text(file.name);
            $('#cg-restore-filesize').text((file.size / 1024).toFixed(2) + ' KB');
            $('#cg-restore-file-info').show();
            $('#cg-confirm-restore-btn').prop('disabled', false);
        } else {
            $('#cg-restore-file-info').hide();
            $('#cg-confirm-restore-btn').prop('disabled', true);
        }
    });
    
    // Confirm restore
    $('#cg-confirm-restore-btn').on('click', function() {
        const file = $('#cg-restore-file')[0].files[0];
        if (!file) return;
        
        const formData = new FormData();
        formData.append('action', '<?php echo esc_js(CG_Constants::AJAX_RESTORE_DATABASE); ?>');
        formData.append('nonce', cgAdmin.nonce);
        formData.append('database_file', file);
        
        $(this).prop('disabled', true).text('<?php echo esc_js(__('Restoring...', 'category-generator')); ?>');
        
        $.ajax({
            url: cgAdmin.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert('<?php echo esc_js(__('Database restored successfully! Page will reload.', 'category-generator')); ?>');
                    location.reload();
                } else {
                    alert(response.data.message || '<?php echo esc_js(__('Error restoring database', 'category-generator')); ?>');
                    $('#cg-confirm-restore-btn').prop('disabled', false).text('<?php echo esc_js(__('Restore Database', 'category-generator')); ?>');
                }
            }
        });
    });
    
    // Reset database modal
    $('#cg-reset-database-btn').on('click', function() {
        $('#cg-reset-confirm-text').val('');
        $('#cg-confirm-reset-btn').prop('disabled', true);
        $('#<?php echo esc_js(CG_CSS::ID_SETTINGS_RESET_MODAL); ?>').fadeIn(200);
    });
    
    // Enable reset button when correct text is typed
    $('#cg-reset-confirm-text').on('input', function() {
        const isValid = $(this).val().toUpperCase() === RESET_CONFIRM_TEXT;
        $('#cg-confirm-reset-btn').prop('disabled', !isValid);
    });
    
    // Confirm reset
    $('#cg-confirm-reset-btn').on('click', function() {
        const exportFirst = $('#cg-export-before-confirm').is(':checked');
        
        if (exportFirst) {
            window.location.href = cgAdmin.ajaxUrl + '?action=<?php echo esc_js(CG_Constants::AJAX_EXPORT_DATA); ?>&nonce=' + cgAdmin.nonce + '&type=all';
        }
        
        $(this).prop('disabled', true).text('<?php echo esc_js(__('Resetting...', 'category-generator')); ?>');
        
        $.ajax({
            url: cgAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: '<?php echo esc_js(CG_Constants::AJAX_RESET_DATABASE); ?>',
                nonce: cgAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('<?php echo esc_js(__('Database reset successfully! Page will reload.', 'category-generator')); ?>');
                    location.reload();
                } else {
                    alert(response.data.message || '<?php echo esc_js(__('Error resetting database', 'category-generator')); ?>');
                    $('#cg-confirm-reset-btn').prop('disabled', false).text('<?php echo esc_js(__('Yes, Reset Everything', 'category-generator')); ?>');
                }
            }
        });
    });
    
    // Close modals
    $('.<?php echo esc_js(CG_CSS::MODAL_CLOSE); ?>').on('click', function() {
        $(this).closest('.<?php echo esc_js(CG_CSS::MODAL); ?>').fadeOut(200);
    });
    
    $('.<?php echo esc_js(CG_CSS::MODAL); ?>').on('click', function(e) {
        if (e.target === this) {
            $(this).fadeOut(200);
        }
    });
});
</script>
