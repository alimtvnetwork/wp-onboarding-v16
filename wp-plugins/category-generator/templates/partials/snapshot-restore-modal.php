<?php
/**
 * Snapshot Restore Modal Partial
 * 
 * Modal dialog for confirming snapshot restoration.
 * 
 * @package Category_Generator_Area
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div id="<?php echo CG_CSS::ID_RESTORE_SNAPSHOT_MODAL; ?>" class="<?php echo CG_CSS::MODAL; ?>" style="display: none;">
    <div class="<?php echo CG_CSS::MODAL_CONTENT; ?>" style="max-width: <?php echo CG_Constants::MODAL_WIDTH_DEFAULT; ?>;">
        <div class="<?php echo CG_CSS::MODAL_HEADER; ?>">
            <h2><?php _e('🔄 Restore Snapshot', 'category-generator'); ?></h2>
            <button type="button" class="<?php echo CG_CSS::MODAL_CLOSE; ?>">&times;</button>
        </div>
        <div class="<?php echo CG_CSS::MODAL_BODY; ?>">
            <p><strong><?php _e('Restoring:', 'category-generator'); ?></strong> <span id="<?php echo CG_CSS::ID_RESTORE_SNAPSHOT_TITLE; ?>"></span></p>
            <div class="<?php echo CG_CSS::NOTICE; ?> <?php echo CG_CSS::NOTICE_INFO; ?>">
                <span class="dashicons dashicons-info"></span>
                <?php _e('This will merge the snapshot with existing categories. New categories will be added, existing ones will be updated. No categories will be deleted.', 'category-generator'); ?>
            </div>
            <p><?php _e('It is recommended to take a snapshot of the current state before restoring.', 'category-generator'); ?></p>
            <label>
                <input type="checkbox" id="<?php echo CG_CSS::ID_SNAPSHOT_BEFORE_RESTORE; ?>" checked>
                <?php _e('Create snapshot before restoring', 'category-generator'); ?>
            </label>
        </div>
        <div class="<?php echo CG_CSS::MODAL_FOOTER; ?>">
            <button type="button" class="button <?php echo CG_CSS::MODAL_CLOSE; ?>"><?php _e('Cancel', 'category-generator'); ?></button>
            <button type="button" class="button button-primary" id="<?php echo CG_CSS::ID_CONFIRM_RESTORE_BTN; ?>">
                <?php _e('Restore Snapshot', 'category-generator'); ?>
            </button>
        </div>
    </div>
</div>
