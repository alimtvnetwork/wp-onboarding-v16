<?php
/**
 * Templates Page - Category Modal
 * 
 * @package Category_Generator_Area
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="<?php echo esc_attr(CG_CSS::ID_CATEGORY_MODAL); ?>" class="<?php echo esc_attr(CG_CSS::MODAL); ?>" style="display: none;">
    <div class="<?php echo esc_attr(CG_CSS::MODAL_CONTENT); ?>" style="max-width: 500px;">
        <div class="<?php echo esc_attr(CG_CSS::MODAL_HEADER); ?>">
            <h2 id="cg-category-modal-title"><?php _e('Add Category', 'category-generator'); ?></h2>
            <button type="button" class="<?php echo esc_attr(CG_CSS::MODAL_CLOSE); ?>">&times;</button>
        </div>
        <div class="<?php echo esc_attr(CG_CSS::MODAL_BODY); ?>">
            <input type="hidden" id="cat-parent-id" value="0">
            <input type="hidden" id="cat-level" value="0">
            
            <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
                <label for="cat-name"><?php _e('Category Name', 'category-generator'); ?></label>
                <input type="text" id="cat-name" placeholder="<?php esc_attr_e('e.g., Commercial Cleaning', 'category-generator'); ?>" required>
            </div>
            
            <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
                <label for="cat-template-type"><?php _e('Template Type', 'category-generator'); ?></label>
                <select id="cat-template-type">
                    <option value="all"><?php _e('All Templates', 'category-generator'); ?></option>
                    <option value="html"><?php _e('HTML Only', 'category-generator'); ?></option>
                    <option value="meta"><?php _e('Meta Only', 'category-generator'); ?></option>
                    <option value="schema"><?php _e('Schema Only', 'category-generator'); ?></option>
                </select>
            </div>
            
            <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>" id="cat-parent-display" style="display: none;">
                <label><?php _e('Parent Category', 'category-generator'); ?></label>
                <p id="cat-parent-name" class="cg-parent-display"></p>
            </div>
        </div>
        <div class="<?php echo esc_attr(CG_CSS::MODAL_FOOTER); ?>">
            <button type="button" class="<?php echo esc_attr(CG_CSS::BTN_DEFAULT); ?> <?php echo esc_attr(CG_CSS::MODAL_CLOSE); ?>"><?php _e('Cancel', 'category-generator'); ?></button>
            <button type="button" class="<?php echo esc_attr(CG_CSS::BTN_PRIMARY); ?>" id="cg-save-category-btn"><?php _e('Save Category', 'category-generator'); ?></button>
        </div>
    </div>
</div>
