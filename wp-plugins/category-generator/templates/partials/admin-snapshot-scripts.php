<?php
/**
 * Admin Page Scripts Partial
 * 
 * JavaScript for snapshot toolbar on Generate page.
 * 
 * @package Category_Generator_Area
 */

if (!defined('ABSPATH')) {
    exit;
}

$js_constants = CG_Constants::get_js_constants();
$js_css = CG_CSS::get_js_classes();
$js_ids = CG_CSS::get_js_ids();
?>
<script>
jQuery(document).ready(function($) {
    // Constants from PHP
    var CG_CONST = <?php echo json_encode($js_constants); ?>;
    var CG_CSS = <?php echo json_encode($js_css); ?>;
    var CG_IDS = <?php echo json_encode($js_ids); ?>;
    
    // Quick Snapshot
    $('#<?php echo CG_CSS::ID_QUICK_SNAPSHOT_BTN; ?>').on('click', function() {
        var name = $('#<?php echo CG_CSS::ID_QUICK_SNAPSHOT_NAME; ?>').val().trim();
        if (!name) {
            name = 'Quick snapshot - ' + new Date().toLocaleString();
        }
        
        var $btn = $(this);
        $btn.prop('disabled', true).find('.dashicons').removeClass('dashicons-camera').addClass('dashicons-update spin');
        
        $.post(cgAdmin.ajaxUrl, {
            action: '<?php echo CG_Constants::AJAX_CREATE_SNAPSHOT; ?>',
            nonce: cgAdmin.nonce,
            title: name,
            notes: '<?php _e('Created from Generate page', 'category-generator'); ?>',
            type: '<?php echo CG_Constants::SNAPSHOT_TYPE_MANUAL; ?>'
        }, function(response) {
            if (response.success) {
                $('#<?php echo CG_CSS::ID_QUICK_SNAPSHOT_NAME; ?>').val('');
                // Add to dropdown
                var option = '<option value="' + response.data.snapshot_id + '">' + 
                    new Date().toLocaleDateString() + ' - ' + name + '</option>';
                $('#<?php echo CG_CSS::ID_QUICK_RESTORE_SELECT; ?> option:first').after(option);
                alert('<?php _e('Snapshot created!', 'category-generator'); ?>');
            } else {
                alert(response.data.message || '<?php _e('Failed to create snapshot', 'category-generator'); ?>');
            }
        }).fail(function() {
            alert('<?php _e('Request failed', 'category-generator'); ?>');
        }).always(function() {
            $btn.prop('disabled', false).find('.dashicons').removeClass('dashicons-update spin').addClass('dashicons-camera');
        });
    });
    
    // Quick Restore
    $('#<?php echo CG_CSS::ID_QUICK_RESTORE_SELECT; ?>').on('change', function() {
        var snapshotId = $(this).val();
        if (!snapshotId) return;
        
        if (!confirm('<?php _e('Restore this snapshot? This will merge categories from the snapshot.', 'category-generator'); ?>')) {
            $(this).val('');
            return;
        }
        
        $.post(cgAdmin.ajaxUrl, {
            action: '<?php echo CG_Constants::AJAX_RESTORE_SNAPSHOT; ?>',
            nonce: cgAdmin.nonce,
            snapshot_id: snapshotId,
            create_backup: 1
        }, function(response) {
            if (response.success) {
                alert(response.data.message || '<?php _e('Snapshot restored!', 'category-generator'); ?>');
            } else {
                alert(response.data.message || '<?php _e('Failed to restore snapshot', 'category-generator'); ?>');
            }
        }).fail(function() {
            alert('<?php _e('Request failed', 'category-generator'); ?>');
        });
        
        $(this).val('');
    });
    
    // Auto-snapshot toggle
    $('#<?php echo CG_CSS::ID_AUTO_SNAPSHOT_TOGGLE; ?>').on('change', function() {
        var enabled = $(this).is(':checked') ? '1' : '0';
        
        $.post(cgAdmin.ajaxUrl, {
            action: 'cg_save_settings',
            nonce: cgAdmin.nonce,
            <?php echo CG_Constants::SETTING_AUTO_SNAPSHOT; ?>: enabled
        });
    });
});
</script>
