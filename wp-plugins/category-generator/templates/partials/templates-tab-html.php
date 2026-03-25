<?php
/**
 * Templates Page - HTML Templates Tab
 * 
 * @package Category_Generator_Area
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="<?php echo esc_attr(CG_CSS::LAYOUT_TAB_CONTENT); ?> active" id="tab-html">
    <div class="<?php echo esc_attr(CG_CSS::LAYOUT_CARD); ?>">
        <div class="cg-card-header">
            <h2><?php _e('HTML Description Templates', 'category-generator'); ?></h2>
            <div class="cg-card-header-actions">
                <select id="cg-filter-html-category" class="cg-category-filter">
                    <option value=""><?php _e('All Categories', 'category-generator'); ?></option>
                    <?php foreach ($category_tree as $cat): ?>
                        <option value="<?php echo esc_attr($cat['name']); ?>"><?php echo esc_html($cat['display_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="<?php echo esc_attr(CG_CSS::BTN_PRIMARY); ?>" id="cg-add-html-template">
                    <span class="dashicons dashicons-plus-alt2"></span>
                    <?php _e('Add New Template', 'category-generator'); ?>
                </button>
            </div>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 50px;"><?php _e('ID', 'category-generator'); ?></th>
                    <th><?php _e('Name', 'category-generator'); ?></th>
                    <th style="width: 180px;"><?php _e('Category', 'category-generator'); ?></th>
                    <th><?php _e('Description', 'category-generator'); ?></th>
                    <th style="width: 80px;"><?php _e('Default', 'category-generator'); ?></th>
                    <th style="width: 150px;"><?php _e('Actions', 'category-generator'); ?></th>
                </tr>
            </thead>
            <tbody id="html-templates-body">
                <?php foreach ($html_templates as $template): ?>
                <tr data-id="<?php echo esc_attr($template['id']); ?>" data-category="<?php echo esc_attr($template['category'] ?? ''); ?>">
                    <td><?php echo esc_html($template['id']); ?></td>
                    <td><strong><?php echo esc_html($template['name']); ?></strong></td>
                    <td>
                        <?php if (!empty($template['category'])): ?>
                            <span class="cg-category-badge"><?php echo esc_html($template['category']); ?></span>
                        <?php else: ?>
                            <span class="cg-category-badge cg-uncategorized"><?php _e('Uncategorized', 'category-generator'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html(substr($template['description'] ?? '', 0, 80)); ?></td>
                    <td>
                        <?php if ($template['is_default']): ?>
                            <span class="<?php echo esc_attr(CG_CSS::BADGE_YES); ?>"><?php _e('Yes', 'category-generator'); ?></span>
                        <?php else: ?>
                            <span class="<?php echo esc_attr(CG_CSS::BADGE_NO); ?>"><?php _e('No', 'category-generator'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button class="<?php echo esc_attr(CG_CSS::BTN_DEFAULT); ?> cg-edit-template" data-type="html" data-id="<?php echo esc_attr($template['id']); ?>"><?php _e('Edit', 'category-generator'); ?></button>
                        <?php if (!$template['is_default']): ?>
                            <button class="<?php echo esc_attr(CG_CSS::BTN_DEFAULT); ?> cg-delete-template" data-type="html" data-id="<?php echo esc_attr($template['id']); ?>"><?php _e('Delete', 'category-generator'); ?></button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div class="<?php echo esc_attr(CG_CSS::LAYOUT_CARD); ?>" style="margin-top: <?php echo CG_Constants::SPACING_LARGE; ?>px;">
        <h3><?php _e('Available Placeholders for HTML Templates', 'category-generator'); ?></h3>
        <div class="cg-placeholder-list">
            <code><?php echo CG_Constants::PLACEHOLDER_TITLE; ?></code> - Service/Product name<br>
            <code><?php echo CG_Constants::PLACEHOLDER_AREA; ?></code> - Location name<br>
            <code><?php echo CG_Constants::PLACEHOLDER_CATEGORY; ?></code> - Full category name<br>
            <code><?php echo CG_Constants::PLACEHOLDER_SLUG; ?></code> - URL slug<br>
            <code><?php echo CG_Constants::PLACEHOLDER_URL; ?></code> - Category URL<br>
            <code><?php echo CG_Constants::PLACEHOLDER_META_TITLE; ?></code> - Generated meta title<br>
            <code><?php echo CG_Constants::PLACEHOLDER_META_DESC; ?></code> - Generated meta description<br>
            <code><?php echo CG_Constants::PLACEHOLDER_BUSINESS_NAME; ?></code> - Business name from profile<br>
            <code>{phone}</code>, <code>{email}</code>, <code>{website}</code> - Contact info<br>
            <code>{street_address}</code>, <code>{city}</code>, <code>{state}</code>, <code>{postal_code}</code>, <code>{country}</code> - Address<br>
            <code>{rating_value}</code>, <code>{rating_count}</code> - Ratings<br>
            <code>{contact_url}</code> - Contact page URL<br>
            <code><?php echo CG_Constants::PLACEHOLDER_INNER; ?>name}</code> - Insert inner template by name
        </div>
    </div>
</div>
