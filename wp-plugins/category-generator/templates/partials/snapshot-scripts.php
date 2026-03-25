<?php
/**
 * Snapshot Page Scripts Partial
 * 
 * JavaScript for the snapshots page.
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
    
    var restoreSnapshotId = null;
    
    // Create snapshot
    $('#<?php echo CG_CSS::ID_CREATE_SNAPSHOT_BTN; ?>').on('click', function() {
        var title = $('#<?php echo CG_CSS::ID_SNAPSHOT_TITLE; ?>').val().trim();
        var notes = $('#<?php echo CG_CSS::ID_SNAPSHOT_NOTES; ?>').val().trim();
        
        if (!title) {
            alert('<?php _e('Please enter a snapshot name', 'category-generator'); ?>');
            $('#<?php echo CG_CSS::ID_SNAPSHOT_TITLE; ?>').focus();
            return;
        }
        
        var $btn = $(this);
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> <?php _e('Creating...', 'category-generator'); ?>');
        
        $.post(cgAdmin.ajaxUrl, {
            action: '<?php echo CG_Constants::AJAX_CREATE_SNAPSHOT; ?>',
            nonce: cgAdmin.nonce,
            title: title,
            notes: notes,
            type: '<?php echo CG_Constants::SNAPSHOT_TYPE_MANUAL; ?>'
        }, function(response) {
            if (response.success) {
                alert('<?php _e('Snapshot created successfully!', 'category-generator'); ?>');
                location.reload();
            } else {
                alert(response.data.message || '<?php _e('Failed to create snapshot', 'category-generator'); ?>');
            }
        }).fail(function() {
            alert('<?php _e('Request failed. Please try again.', 'category-generator'); ?>');
        }).always(function() {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-camera"></span> <?php _e('Take Snapshot', 'category-generator'); ?>');
        });
    });
    
    // Restore snapshot - open modal
    $('.' + CG_CSS.snapshot.restore).on('click', function() {
        restoreSnapshotId = $(this).data('id');
        var title = $(this).closest('tr').find('.' + CG_CSS.layout.columnTitle + ' strong').text() || $(this).closest('tr').find('td:first strong').text();
        $('#<?php echo CG_CSS::ID_RESTORE_SNAPSHOT_TITLE; ?>').text(title);
        $('#<?php echo CG_CSS::ID_RESTORE_SNAPSHOT_MODAL; ?>').show();
    });
    
    // Confirm restore
    $('#<?php echo CG_CSS::ID_CONFIRM_RESTORE_BTN; ?>').on('click', function() {
        var $btn = $(this);
        var createBackup = $('#<?php echo CG_CSS::ID_SNAPSHOT_BEFORE_RESTORE; ?>').is(':checked');
        
        $btn.prop('disabled', true).text('<?php _e('Restoring...', 'category-generator'); ?>');
        
        $.post(cgAdmin.ajaxUrl, {
            action: '<?php echo CG_Constants::AJAX_RESTORE_SNAPSHOT; ?>',
            nonce: cgAdmin.nonce,
            snapshot_id: restoreSnapshotId,
            create_backup: createBackup ? 1 : 0
        }, function(response) {
            if (response.success) {
                alert(response.data.message || '<?php _e('Snapshot restored successfully!', 'category-generator'); ?>');
                $('#<?php echo CG_CSS::ID_RESTORE_SNAPSHOT_MODAL; ?>').hide();
                if (createBackup) {
                    location.reload();
                }
            } else {
                alert(response.data.message || '<?php _e('Failed to restore snapshot', 'category-generator'); ?>');
            }
        }).fail(function() {
            alert('<?php _e('Request failed. Please try again.', 'category-generator'); ?>');
        }).always(function() {
            $btn.prop('disabled', false).text('<?php _e('Restore Snapshot', 'category-generator'); ?>');
        });
    });
    
    // Download snapshot
    $('.' + CG_CSS.snapshot.download).on('click', function() {
        var snapshotId = $(this).data('id');
        window.location.href = cgAdmin.ajaxUrl + '?action=<?php echo CG_Constants::AJAX_DOWNLOAD_SNAPSHOT; ?>&nonce=' + cgAdmin.nonce + '&snapshot_id=' + snapshotId;
    });
    
    // Delete snapshot
    $('.' + CG_CSS.snapshot.delete).on('click', function() {
        if (!confirm('<?php _e('Are you sure you want to delete this snapshot?', 'category-generator'); ?>')) {
            return;
        }
        
        var $btn = $(this);
        var snapshotId = $btn.data('id');
        var $row = $btn.closest('tr');
        
        $btn.prop('disabled', true);
        
        $.post(cgAdmin.ajaxUrl, {
            action: '<?php echo CG_Constants::AJAX_DELETE_SNAPSHOT; ?>',
            nonce: cgAdmin.nonce,
            snapshot_id: snapshotId
        }, function(response) {
            if (response.success) {
                $row.fadeOut(CG_CONST.animation.fadeDuration, function() { $(this).remove(); });
            } else {
                alert(response.data.message || '<?php _e('Failed to delete snapshot', 'category-generator'); ?>');
                $btn.prop('disabled', false);
            }
        }).fail(function() {
            alert('<?php _e('Request failed. Please try again.', 'category-generator'); ?>');
            $btn.prop('disabled', false);
        });
    });
    
    // Modal close
    $('.<?php echo CG_CSS::MODAL_CLOSE; ?>').on('click', function() {
        $(this).closest('.<?php echo CG_CSS::MODAL; ?>').hide();
    });
});
</script>
