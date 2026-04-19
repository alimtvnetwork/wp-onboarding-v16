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
        <div class="cg-columns-dropdown-wrapper" style="position: relative; display: inline-block;">
            <button type="button" class="button" id="cg-columns-toggle-btn" aria-haspopup="true" aria-expanded="false">
                <span class="dashicons dashicons-columns"></span>
                <?php _e('Columns', 'category-generator'); ?>
                <span class="dashicons dashicons-arrow-down-alt2" style="font-size: 14px; width: 14px; height: 14px; line-height: 1;"></span>
            </button>
            <div id="cg-columns-dropdown" class="cg-columns-dropdown" style="display:none;" role="menu" aria-label="<?php esc_attr_e('Toggle column visibility', 'category-generator'); ?>">
                <div class="cg-columns-dropdown-header">
                    <strong><?php _e('Show / Hide Columns', 'category-generator'); ?></strong>
                </div>
                <ul class="cg-columns-list">
                    <li><label><input type="checkbox" class="cg-col-toggle" data-col="<?php echo CG_CSS::COLUMN_ID; ?>"> <?php _e('ID', 'category-generator'); ?></label></li>
                    <li><label><input type="checkbox" class="cg-col-toggle" data-col="<?php echo CG_CSS::COLUMN_NAME; ?>"> <?php _e('Category Name', 'category-generator'); ?></label></li>
                    <li><label><input type="checkbox" class="cg-col-toggle" data-col="<?php echo CG_CSS::COLUMN_SLUG; ?>"> <?php _e('Slug', 'category-generator'); ?></label></li>
                    <li><label><input type="checkbox" class="cg-col-toggle" data-col="<?php echo CG_CSS::COLUMN_TITLE; ?>"> <?php _e('Title', 'category-generator'); ?></label></li>
                    <li><label><input type="checkbox" class="cg-col-toggle" data-col="<?php echo CG_CSS::COLUMN_AREA; ?>"> <?php _e('Area', 'category-generator'); ?></label></li>
                    <li><label><input type="checkbox" class="cg-col-toggle" data-col="<?php echo CG_CSS::COLUMN_TAXONOMY; ?>"> <?php _e('Taxonomy', 'category-generator'); ?></label></li>
                    <li><label><input type="checkbox" class="cg-col-toggle" data-col="<?php echo CG_CSS::COLUMN_META_TITLE; ?>"> <?php _e('Meta Title', 'category-generator'); ?></label></li>
                    <li><label><input type="checkbox" class="cg-col-toggle" data-col="<?php echo CG_CSS::COLUMN_META_DESC; ?>"> <?php _e('Meta Desc', 'category-generator'); ?></label></li>
                    <li><label><input type="checkbox" class="cg-col-toggle" data-col="<?php echo CG_CSS::COLUMN_SCHEMA; ?>"> <?php _e('Schema', 'category-generator'); ?></label></li>
                    <?php if (defined('WPSEO_VERSION')): ?>
                    <li><label><input type="checkbox" class="cg-col-toggle" data-col="<?php echo CG_CSS::COLUMN_YOAST; ?>"> <?php _e('SEO (Yoast)', 'category-generator'); ?></label></li>
                    <?php endif; ?>
                    <li><label><input type="checkbox" class="cg-col-toggle" data-col="<?php echo CG_CSS::COLUMN_DATE; ?>"> <?php _e('Created', 'category-generator'); ?></label></li>
                    <li><label><input type="checkbox" class="cg-col-toggle" data-col="<?php echo CG_CSS::COLUMN_ACTIONS; ?>"> <?php _e('Actions', 'category-generator'); ?></label></li>
                </ul>
                <div class="cg-columns-dropdown-footer">
                    <button type="button" class="button button-small" id="cg-columns-show-all"><?php _e('Show all', 'category-generator'); ?></button>
                    <button type="button" class="button button-small" id="cg-columns-reset"><?php _e('Reset', 'category-generator'); ?></button>
                </div>
            </div>
        </div>
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
