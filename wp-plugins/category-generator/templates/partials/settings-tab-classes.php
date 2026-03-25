<?php
/**
 * Settings Page - CSS Classes Tab
 * 
 * @package Category_Generator_Area
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="<?php echo esc_attr(CG_CSS::LAYOUT_TAB_CONTENT); ?>" id="tab-classes">
    <div class="<?php echo esc_attr(CG_CSS::LAYOUT_CARD); ?>">
        <h2><?php _e('CSS Class Names', 'category-generator'); ?></h2>
        <p class="<?php echo esc_attr(CG_CSS::TEXT_DESCRIPTION); ?>"><?php _e('Customize the CSS classes used in generated HTML. These classes are applied to category descriptions.', 'category-generator'); ?></p>
        
        <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
            <label for="wrapper_class"><?php _e('Main Wrapper Class', 'category-generator'); ?></label>
            <input type="text" name="wrapper_class" id="wrapper_class" 
                   value="<?php echo esc_attr($settings->get('wrapper_class', CG_Constants::DEFAULT_WRAPPER_CLASS)); ?>"
                   placeholder="<?php echo esc_attr(CG_Constants::DEFAULT_WRAPPER_CLASS); ?>">
            <span class="<?php echo esc_attr(CG_CSS::TEXT_HINT); ?>"><?php _e('Applied to the outer div wrapping all content', 'category-generator'); ?></span>
        </div>
        
        <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
            <label for="header_class"><?php _e('Header Class Prefix', 'category-generator'); ?></label>
            <input type="text" name="header_class" id="header_class" 
                   value="<?php echo esc_attr($settings->get('header_class', CG_Constants::DEFAULT_HEADER_CLASS)); ?>"
                   placeholder="<?php echo esc_attr(CG_Constants::DEFAULT_HEADER_CLASS); ?>">
            <span class="<?php echo esc_attr(CG_CSS::TEXT_HINT); ?>"><?php _e('H2 will get category-header-2, H3 gets category-header-3, etc.', 'category-generator'); ?></span>
        </div>
        
        <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
            <label for="paragraph_class"><?php _e('Paragraph/Content Class', 'category-generator'); ?></label>
            <input type="text" name="paragraph_class" id="paragraph_class" 
                   value="<?php echo esc_attr($settings->get('paragraph_class', CG_Constants::DEFAULT_PARAGRAPH_CLASS)); ?>"
                   placeholder="<?php echo esc_attr(CG_Constants::DEFAULT_PARAGRAPH_CLASS); ?>">
        </div>
        
        <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
            <label for="schema_wrapper_class"><?php _e('Schema Wrapper Class', 'category-generator'); ?></label>
            <input type="text" name="schema_wrapper_class" id="schema_wrapper_class" 
                   value="<?php echo esc_attr($settings->get('schema_wrapper_class', CG_Constants::DEFAULT_SCHEMA_WRAPPER_CLASS)); ?>"
                   placeholder="<?php echo esc_attr(CG_Constants::DEFAULT_SCHEMA_WRAPPER_CLASS); ?>">
            <span class="<?php echo esc_attr(CG_CSS::TEXT_HINT); ?>"><?php _e('Applied to the div containing JSON-LD schema', 'category-generator'); ?></span>
        </div>
    </div>
</div>
