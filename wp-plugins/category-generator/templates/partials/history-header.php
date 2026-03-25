<?php
/**
 * History Header Partial
 * 
 * Search box, stats, and import/export buttons.
 * 
 * @package Category_Generator_Area
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="<?php echo CG_CSS::HISTORY_HEADER; ?>">
    <div class="<?php echo CG_CSS::SEARCH_BOX; ?>">
        <input type="text" id="<?php echo CG_CSS::ID_HISTORY_SEARCH; ?>" placeholder="<?php esc_attr_e('Search categories, titles, or areas...', 'category-generator'); ?>">
        <button type="button" class="button <?php echo CG_CSS::SEARCH_BTN; ?>" id="cg-history-search-btn">
            <span class="dashicons dashicons-search"></span>
            <span class="cg-search-btn-text"><?php _e('Search', 'category-generator'); ?></span>
        </button>
    </div>
    
    <div class="<?php echo CG_CSS::HISTORY_STATS; ?>">
        <span id="<?php echo CG_CSS::ID_HISTORY_TOTAL; ?>">0</span> <?php _e('categories created by this tool', 'category-generator'); ?>
    </div>
    
    <div class="<?php echo CG_CSS::HISTORY_ACTIONS; ?>">
        <button type="button" class="button" id="cg-history-export-btn">
            <span class="dashicons dashicons-download"></span>
            <?php _e('Export', 'category-generator'); ?>
        </button>
        <button type="button" class="button" id="cg-history-import-btn">
            <span class="dashicons dashicons-upload"></span>
            <?php _e('Import', 'category-generator'); ?>
        </button>
    </div>
</div>
