<?php
/**
 * Templates Page - Tab Navigation
 * 
 * @package Category_Generator_Area
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="<?php echo esc_attr(CG_CSS::LAYOUT_TABS); ?>">
    <button class="<?php echo esc_attr(CG_CSS::LAYOUT_TAB); ?> active" data-tab="html"><?php _e('HTML Templates', 'category-generator'); ?></button>
    <button class="<?php echo esc_attr(CG_CSS::LAYOUT_TAB); ?>" data-tab="meta"><?php _e('Meta Templates', 'category-generator'); ?></button>
    <button class="<?php echo esc_attr(CG_CSS::LAYOUT_TAB); ?>" data-tab="schema"><?php _e('Schema Templates', 'category-generator'); ?></button>
    <button class="<?php echo esc_attr(CG_CSS::LAYOUT_TAB); ?>" data-tab="categories"><?php _e('Categories', 'category-generator'); ?></button>
</div>
