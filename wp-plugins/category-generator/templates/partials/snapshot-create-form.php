<?php
/**
 * Snapshot Create Form Partial
 * 
 * Form for creating a new manual snapshot.
 * 
 * @package Category_Generator_Area
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="<?php echo CG_CSS::CARD; ?>">
    <h2><?php _e('Create New Snapshot', 'category-generator'); ?></h2>
    
    <div class="<?php echo CG_CSS::CREATE_SNAPSHOT_FORM; ?>">
        <div class="<?php echo CG_CSS::FORM_ROW; ?>">
            <div class="<?php echo CG_CSS::FORM_GROUP; ?> <?php echo CG_CSS::FORM_GROUP_INLINE; ?>">
                <label for="<?php echo CG_CSS::ID_SNAPSHOT_TITLE; ?>"><?php _e('Snapshot Name', 'category-generator'); ?></label>
                <input type="text" id="<?php echo CG_CSS::ID_SNAPSHOT_TITLE; ?>" placeholder="<?php esc_attr_e('e.g., Before adding Melbourne suburbs', 'category-generator'); ?>" style="width: <?php echo CG_Constants::INPUT_WIDTH_LARGE; ?>;">
            </div>
            
            <div class="<?php echo CG_CSS::FORM_GROUP; ?> <?php echo CG_CSS::FORM_GROUP_INLINE; ?>">
                <label for="<?php echo CG_CSS::ID_SNAPSHOT_NOTES; ?>"><?php _e('Notes (optional)', 'category-generator'); ?></label>
                <input type="text" id="<?php echo CG_CSS::ID_SNAPSHOT_NOTES; ?>" placeholder="<?php esc_attr_e('Any additional notes...', 'category-generator'); ?>" style="width: <?php echo CG_Constants::INPUT_WIDTH_MEDIUM; ?>;">
            </div>
            
            <button type="button" class="button button-primary button-hero" id="<?php echo CG_CSS::ID_CREATE_SNAPSHOT_BTN; ?>">
                <span class="dashicons dashicons-camera"></span>
                <?php _e('Take Snapshot', 'category-generator'); ?>
            </button>
        </div>
    </div>
</div>
