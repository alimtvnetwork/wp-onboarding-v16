<?php
/**
 * Settings Page - General Tab
 * 
 * @package Category_Generator_Area
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="<?php echo esc_attr(CG_CSS::LAYOUT_TAB_CONTENT); ?> active" id="tab-general">
    <div class="<?php echo esc_attr(CG_CSS::LAYOUT_CARD); ?>">
        <h2><?php _e('General Settings', 'category-generator'); ?></h2>
        
        <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
            <label>
                <input type="checkbox" name="auto_save_templates" value="1" <?php checked($settings->get('auto_save_templates', true)); ?>>
                <?php _e('Auto-save templates when modified', 'category-generator'); ?>
            </label>
        </div>
        
        <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
            <label>
                <input type="checkbox" name="confirm_before_generate" value="1" <?php checked($settings->get('confirm_before_generate', true)); ?>>
                <?php _e('Show confirmation dialog before generating categories', 'category-generator'); ?>
            </label>
        </div>
        
        <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
            <label>
                <input type="checkbox" name="use_dynamic_location" value="1" <?php checked($settings->get('use_dynamic_location', true)); ?>>
                <?php _e('Use dynamic location from area for address in schema', 'category-generator'); ?>
            </label>
        </div>
        
        <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
            <label for="default_business_profile_id"><?php _e('Default Business Profile', 'category-generator'); ?></label>
            <select name="default_business_profile_id" id="default_business_profile_id">
                <?php
                $profiles = $db->get_all_business_profiles();
                foreach ($profiles as $profile):
                ?>
                    <option value="<?php echo esc_attr($profile['id']); ?>" <?php selected($settings->get('default_business_profile_id'), $profile['id']); ?>>
                        <?php echo esc_html($profile['business_name'] ?: 'Profile #' . $profile['id']); ?>
                    </option>
                <?php endforeach; ?>
        </select>
        </div>
    </div>
    
    <!-- Database Location Info -->
    <div class="<?php echo esc_attr(CG_CSS::LAYOUT_CARD); ?>">
        <h2>
            <span class="dashicons dashicons-database" style="margin-right: <?php echo CG_Constants::SPACING_SMALL; ?>px;"></span>
            <?php _e('Database Location', 'category-generator'); ?>
        </h2>
        
        <?php 
        $db_path = ($db && method_exists($db, 'get_db_path')) ? $db->get_db_path() : '';
        $db_connected = $db && method_exists($db, 'is_connected') && $db->is_connected();
        ?>
        
        <?php if (!$db_connected): ?>
        <div class="notice notice-error inline" style="margin: 0 0 15px 0;">
            <p><strong><?php _e('Database Error:', 'category-generator'); ?></strong> <?php _e('SQLite database is not connected. Check if SQLite extension is enabled and database path is writable.', 'category-generator'); ?></p>
        </div>
        <?php endif; ?>
        
        <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
            <label><?php _e('SQLite Database Path', 'category-generator'); ?></label>
            <div style="display: flex; gap: 10px; align-items: center;">
                <input type="text" id="db_path_display" value="<?php echo esc_attr($db_path); ?>" readonly 
                       style="flex: 1; background: #f1f1f1; cursor: not-allowed;">
                <button type="button" class="<?php echo esc_attr(CG_CSS::BTN_SECONDARY); ?>" id="copy-db-path" title="<?php _e('Copy path', 'category-generator'); ?>">
                    <span class="dashicons dashicons-clipboard"></span>
                </button>
            </div>
            <span class="<?php echo esc_attr(CG_CSS::TEXT_HINT); ?>"><?php _e('This is where all plugin data (templates, history, business profiles) is stored.', 'category-generator'); ?></span>
        </div>
        
        <?php
        $db_size = ($db_path && file_exists($db_path)) ? filesize($db_path) : 0;
        $db_modified = ($db_path && file_exists($db_path)) ? filemtime($db_path) : 0;
        ?>
        <div style="background: #f8f9fa; padding: <?php echo CG_Constants::SPACING_MEDIUM; ?>px; border-radius: 6px; margin-top: <?php echo CG_Constants::SPACING_SMALL; ?>px;">
            <p style="margin: 0 0 8px 0;">
                <strong><?php _e('Database Size:', 'category-generator'); ?></strong>
                <span style="margin-left: 10px;"><?php echo size_format($db_size, 2); ?></span>
            </p>
            <p style="margin: 0 0 8px 0;">
                <strong><?php _e('Last Modified:', 'category-generator'); ?></strong>
                <span style="margin-left: 10px;"><?php echo $db_modified ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $db_modified) : __('N/A', 'category-generator'); ?></span>
            </p>
            <p style="margin: 0;">
                <strong><?php _e('Status:', 'category-generator'); ?></strong>
                <span style="margin-left: 10px; color: <?php echo $db_connected ? '#00a32a' : '#d63638'; ?>;">
                    <?php echo $db_connected ? __('Connected', 'category-generator') : __('Not Connected', 'category-generator'); ?>
                </span>
            </p>
        </div>
    </div>
    
    <!-- Snapshot Settings -->
    <div class="<?php echo esc_attr(CG_CSS::LAYOUT_CARD); ?>">
        <h2>
            <span class="dashicons dashicons-backup" style="margin-right: <?php echo CG_Constants::SPACING_SMALL; ?>px;"></span>
            <?php _e('Category Snapshot Settings', 'category-generator'); ?>
        </h2>
        <p class="<?php echo esc_attr(CG_CSS::TEXT_DESCRIPTION); ?>"><?php _e('Configure automatic snapshots of WordPress category tables before generation.', 'category-generator'); ?></p>
        
        <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
            <label>
                <input type="checkbox" name="auto_snapshot_before_generate" value="1" <?php checked($settings->get('auto_snapshot_before_generate', false)); ?>>
                <strong><?php _e('Take automatic snapshot before each category generation', 'category-generator'); ?></strong>
            </label>
            <span class="<?php echo esc_attr(CG_CSS::TEXT_HINT); ?>"><?php _e('Creates a backup of all categories before any changes are made', 'category-generator'); ?></span>
        </div>
        
        <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
            <label for="snapshot_limit"><?php _e('Maximum Snapshots to Keep', 'category-generator'); ?></label>
            <input type="number" name="snapshot_limit" id="snapshot_limit" 
                   value="<?php echo esc_attr($settings->get('snapshot_limit', CG_Constants::SNAPSHOT_LIMIT_DEFAULT)); ?>"
                   min="<?php echo CG_Constants::SNAPSHOT_LIMIT_MIN; ?>" 
                   max="<?php echo CG_Constants::SNAPSHOT_LIMIT_MAX; ?>" 
                   step="<?php echo CG_Constants::SNAPSHOT_LIMIT_STEP; ?>" 
                   style="width: 100px;">
            <span class="<?php echo esc_attr(CG_CSS::TEXT_HINT); ?>"><?php _e('Oldest automatic snapshots will be deleted when limit is reached. Manual snapshots are not affected.', 'category-generator'); ?></span>
        </div>
        
        <div class="cg-snapshot-stats" style="background: #f8f9fa; padding: <?php echo CG_Constants::SPACING_MEDIUM; ?>px; border-radius: 6px; margin-top: <?php echo CG_Constants::SPACING_MEDIUM; ?>px;">
            <?php
            $manual_count = ($db && $db_connected) ? $db->get_snapshots_count('manual') : 0;
            $auto_count = ($db && $db_connected) ? $db->get_snapshots_count('auto') : 0;
            ?>
            <p style="margin: 0;">
                <strong><?php _e('Current Snapshots:', 'category-generator'); ?></strong>
                <span style="margin-left: 10px;"><?php printf(__('%d manual', 'category-generator'), $manual_count); ?></span>
                <span style="margin-left: 10px;"><?php printf(__('%d automatic', 'category-generator'), $auto_count); ?></span>
                <a href="<?php echo admin_url('admin.php?page=cg-snapshots'); ?>" style="margin-left: <?php echo CG_Constants::SPACING_MEDIUM; ?>px;"><?php _e('Manage Snapshots →', 'category-generator'); ?></a>
            </p>
        </div>
    </div>
</div>
