<?php
/**
 * History Bulk Actions Bar Partial
 * 
 * Bulk action buttons for selected history items.
 * 
 * @package Category_Generator_Area
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="<?php echo CG_CSS::BULK_ACTIONS_BAR; ?>" id="<?php echo CG_CSS::ID_BULK_ACTIONS_BAR; ?>" style="display: none;">
    <div class="<?php echo CG_CSS::BULK_SELECTED; ?>">
        <span id="<?php echo CG_CSS::ID_SELECTED_COUNT; ?>">0</span> <?php _e('selected', 'category-generator'); ?>
    </div>
    <div class="<?php echo CG_CSS::BULK_BUTTONS; ?>">
        <button type="button" class="button <?php echo CG_CSS::BULK_ACTION; ?>" data-action="snapshot">
            <span class="dashicons dashicons-backup"></span>
            <?php _e('Take Snapshot', 'category-generator'); ?>
        </button>
        <button type="button" class="button <?php echo CG_CSS::BULK_ACTION; ?>" data-action="delete-logs">
            <span class="dashicons dashicons-trash"></span>
            <?php _e('Remove Logs Only', 'category-generator'); ?>
        </button>
        <button type="button" class="button <?php echo CG_CSS::BULK_ACTION; ?> <?php echo CG_CSS::BULK_DANGER; ?>" data-action="delete-all">
            <span class="dashicons dashicons-warning"></span>
            <?php _e('Remove Logs + Categories', 'category-generator'); ?>
        </button>
        <button type="button" class="button" id="<?php echo CG_CSS::ID_BULK_CANCEL; ?>">
            <?php _e('Cancel', 'category-generator'); ?>
        </button>
    </div>
</div>
