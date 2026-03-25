<?php
/**
 * Settings Page - Remote Templates Tab
 * 
 * @package Category_Generator_Area
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="<?php echo esc_attr(CG_CSS::LAYOUT_TAB_CONTENT); ?>" id="tab-remote">
    <div class="<?php echo esc_attr(CG_CSS::LAYOUT_CARD); ?>">
        <h2><?php _e('Remote Template APIs', 'category-generator'); ?></h2>
        <p class="<?php echo esc_attr(CG_CSS::TEXT_DESCRIPTION); ?>"><?php _e('Configure external APIs to import templates from remote servers.', 'category-generator'); ?></p>
        
        <div id="cg-remote-apis-list">
            <?php foreach ($remote_apis as $index => $api): ?>
            <div class="cg-remote-api-item" data-id="<?php echo esc_attr($api['id']); ?>">
                <div class="cg-api-header">
                    <strong><?php echo esc_html($api['name']); ?></strong>
                    <span class="cg-api-status <?php echo $api['enabled'] ? 'enabled' : 'disabled'; ?>">
                        <?php echo $api['enabled'] ? '● Active' : '○ Inactive'; ?>
                    </span>
                </div>
                <div class="cg-api-url"><?php echo esc_html($api['url']); ?></div>
                <div class="cg-api-actions">
                    <button type="button" class="<?php echo esc_attr(CG_CSS::BTN_DEFAULT); ?> cg-import-from-api" data-id="<?php echo esc_attr($api['id']); ?>">
                        <?php _e('Import Templates', 'category-generator'); ?>
                    </button>
                    <button type="button" class="<?php echo esc_attr(CG_CSS::BTN_DEFAULT); ?> cg-edit-api" data-id="<?php echo esc_attr($api['id']); ?>">
                        <?php _e('Edit', 'category-generator'); ?>
                    </button>
                    <button type="button" class="<?php echo esc_attr(CG_CSS::BTN_DEFAULT); ?> cg-delete-api" data-id="<?php echo esc_attr($api['id']); ?>">
                        <?php _e('Delete', 'category-generator'); ?>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if (empty($remote_apis)): ?>
            <p class="<?php echo esc_attr(CG_CSS::TEXT_EMPTY); ?>"><?php _e('No remote APIs configured yet.', 'category-generator'); ?></p>
            <?php endif; ?>
        </div>
        
        <div class="cg-add-api-section">
            <h3><?php _e('Add New API', 'category-generator'); ?></h3>
            
            <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
                <label for="new_api_name"><?php _e('API Name', 'category-generator'); ?></label>
                <input type="text" id="new_api_name" placeholder="My Template Server">
            </div>
            
            <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
                <label for="new_api_url"><?php _e('API URL', 'category-generator'); ?></label>
                <input type="url" id="new_api_url" placeholder="https://templates.example.com/api/v1/templates">
            </div>
            
            <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
                <label for="new_api_key"><?php _e('API Key (optional)', 'category-generator'); ?></label>
                <input type="password" id="new_api_key" placeholder="Optional authentication key">
            </div>
            
            <button type="button" class="<?php echo esc_attr(CG_CSS::BTN_PRIMARY); ?>" id="cg-add-api-btn">
                <span class="dashicons dashicons-plus-alt2"></span>
                <?php _e('Add API', 'category-generator'); ?>
            </button>
        </div>
    </div>
</div>
