<?php
/**
 * Settings Page - Yoast Integration Tab
 * 
 * @package Category_Generator_Area
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="<?php echo esc_attr(CG_CSS::LAYOUT_TAB_CONTENT); ?>" id="tab-yoast">
    <div class="<?php echo esc_attr(CG_CSS::LAYOUT_CARD); ?>">
        <h2><?php _e('Yoast SEO Integration', 'category-generator'); ?></h2>
        
        <?php if ($yoast_data['is_active']): ?>
        <div class="<?php echo esc_attr(CG_CSS::NOTICE_SUCCESS); ?>">
            <span class="dashicons dashicons-yes-alt"></span>
            <?php _e('Yoast SEO is active. Settings will be synced automatically.', 'category-generator'); ?>
        </div>
        
        <?php if (!empty($yoast_data['company_name'])): ?>
        <div class="cg-yoast-info">
            <p><strong><?php _e('Company Name:', 'category-generator'); ?></strong> <?php echo esc_html($yoast_data['company_name']); ?></p>
            <?php if (!empty($yoast_data['social_profiles'])): ?>
            <p><strong><?php _e('Social Profiles:', 'category-generator'); ?></strong> <?php echo count($yoast_data['social_profiles']); ?> configured</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <div class="<?php echo esc_attr(CG_CSS::NOTICE_WARNING); ?>">
            <span class="dashicons dashicons-warning"></span>
            <?php _e('Yoast SEO is not active. Meta information will be stored in custom fields.', 'category-generator'); ?>
        </div>
        <?php endif; ?>
        
        <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
            <label>
                <input type="checkbox" name="yoast_use_default_title" value="1" <?php checked($settings->get('yoast_use_default_title', false)); ?>>
                <?php _e('Use WordPress default title format instead of custom pattern', 'category-generator'); ?>
            </label>
        </div>
        
        <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
            <label for="yoast_focus_keyword_pattern"><?php _e('Focus Keyword Pattern', 'category-generator'); ?></label>
            <input type="text" name="yoast_focus_keyword_pattern" id="yoast_focus_keyword_pattern" 
                   value="<?php echo esc_attr($settings->get('yoast_focus_keyword_pattern', CG_Constants::DEFAULT_FOCUS_KEYWORD_PATTERN)); ?>"
                   placeholder="<?php echo esc_attr(CG_Constants::DEFAULT_FOCUS_KEYWORD_PATTERN); ?>">
            <span class="<?php echo esc_attr(CG_CSS::TEXT_HINT); ?>"><?php _e('Pattern for generating Yoast focus keywords', 'category-generator'); ?></span>
        </div>
    </div>
</div>
