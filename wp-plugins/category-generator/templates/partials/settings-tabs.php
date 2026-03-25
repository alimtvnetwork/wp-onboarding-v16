<?php
/**
 * Settings Page - Tab Navigation
 * 
 * @package Category_Generator_Area
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="<?php echo esc_attr(CG_CSS::LAYOUT_TABS); ?>">
    <button class="<?php echo esc_attr(CG_CSS::LAYOUT_TAB); ?> active" data-tab="general"><?php _e('General', 'category-generator'); ?></button>
    <button class="<?php echo esc_attr(CG_CSS::LAYOUT_TAB); ?>" data-tab="classes"><?php _e('CSS Classes', 'category-generator'); ?></button>
    <button class="<?php echo esc_attr(CG_CSS::LAYOUT_TAB); ?>" data-tab="ai"><?php _e('AI Providers', 'category-generator'); ?></button>
    <button class="<?php echo esc_attr(CG_CSS::LAYOUT_TAB); ?>" data-tab="remote"><?php _e('Remote Templates', 'category-generator'); ?></button>
    <button class="<?php echo esc_attr(CG_CSS::LAYOUT_TAB); ?>" data-tab="yoast"><?php _e('Yoast Integration', 'category-generator'); ?></button>
    <button class="<?php echo esc_attr(CG_CSS::LAYOUT_TAB); ?>" data-tab="danger" style="color: #dc3545;"><?php _e('Danger Zone', 'category-generator'); ?></button>
</div>
