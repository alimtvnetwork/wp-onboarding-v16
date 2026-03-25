<?php
/**
 * Templates Page - Template Edit Modal
 * 
 * @package Category_Generator_Area
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="<?php echo esc_attr(CG_CSS::ID_TEMPLATE_MODAL); ?>" class="<?php echo esc_attr(CG_CSS::MODAL); ?>" style="display: none;">
    <div class="<?php echo esc_attr(CG_CSS::MODAL_CONTENT); ?>">
        <div class="<?php echo esc_attr(CG_CSS::MODAL_HEADER); ?>">
            <h2 id="cg-modal-title"><?php _e('Edit Template', 'category-generator'); ?></h2>
            <button type="button" class="<?php echo esc_attr(CG_CSS::MODAL_CLOSE); ?>">&times;</button>
        </div>
        <div class="<?php echo esc_attr(CG_CSS::MODAL_BODY); ?>">
            <form id="cg-template-form">
                <input type="hidden" id="tpl-id" name="id">
                <input type="hidden" id="tpl-type" name="type">
                
                <div class="cg-form-row-2col">
                    <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
                        <label for="tpl-name"><?php _e('Template Name', 'category-generator'); ?></label>
                        <input type="text" id="tpl-name" name="name" required>
                    </div>
                    
                    <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
                        <label for="tpl-category"><?php _e('Category', 'category-generator'); ?></label>
                        <select id="tpl-category" name="category">
                            <option value=""><?php _e('— Uncategorized —', 'category-generator'); ?></option>
                            <?php foreach ($category_tree as $cat): ?>
                                <option value="<?php echo esc_attr($cat['name']); ?>"><?php echo esc_html($cat['display_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <!-- HTML Template Fields -->
                <div class="cg-form-fields cg-fields-html">
                    <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
                        <label for="tpl-description"><?php _e('Description', 'category-generator'); ?></label>
                        <input type="text" id="tpl-description" name="description">
                    </div>
                    <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
                        <label for="tpl-content"><?php _e('HTML Content', 'category-generator'); ?></label>
                        <textarea id="tpl-content" name="content" rows="15"></textarea>
                    </div>
                </div>
                
                <!-- Meta Template Fields -->
                <div class="cg-form-fields cg-fields-meta" style="display: none;">
                    <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
                        <label for="tpl-meta-title"><?php _e('Meta Title Pattern', 'category-generator'); ?></label>
                        <input type="text" id="tpl-meta-title" name="meta_title_pattern">
                    </div>
                    <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
                        <label for="tpl-meta-desc"><?php _e('Meta Description Pattern', 'category-generator'); ?></label>
                        <textarea id="tpl-meta-desc" name="meta_description_pattern" rows="3"></textarea>
                    </div>
                    <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
                        <label for="tpl-slug"><?php _e('Slug Pattern', 'category-generator'); ?></label>
                        <input type="text" id="tpl-slug" name="slug_pattern">
                    </div>
                </div>
                
                <!-- Schema Template Fields -->
                <div class="cg-form-fields cg-fields-schema" style="display: none;">
                    <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
                        <label for="tpl-schema-type"><?php _e('Schema Type', 'category-generator'); ?></label>
                        <select id="tpl-schema-type" name="schema_type">
                            <option value="LocalBusiness">LocalBusiness</option>
                            <option value="ProfessionalService">ProfessionalService</option>
                            <option value="Service">Service</option>
                            <option value="Organization">Organization</option>
                        </select>
                    </div>
                    <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
                        <label for="tpl-schema-content"><?php _e('Schema JSON-LD', 'category-generator'); ?></label>
                        <textarea id="tpl-schema-content" name="content" rows="15"></textarea>
                    </div>
                </div>
            </form>
        </div>
        <div class="<?php echo esc_attr(CG_CSS::MODAL_FOOTER); ?>">
            <button type="button" class="<?php echo esc_attr(CG_CSS::BTN_DEFAULT); ?>" id="cg-modal-cancel"><?php _e('Cancel', 'category-generator'); ?></button>
            <button type="button" class="<?php echo esc_attr(CG_CSS::BTN_PRIMARY); ?>" id="cg-modal-save"><?php _e('Save Template', 'category-generator'); ?></button>
        </div>
    </div>
</div>
