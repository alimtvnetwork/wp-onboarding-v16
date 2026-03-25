<?php
/**
 * Settings Page - Danger Zone Tab
 * 
 * @package Category_Generator_Area
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="<?php echo esc_attr(CG_CSS::LAYOUT_TAB_CONTENT); ?>" id="tab-danger">
    <div class="<?php echo esc_attr(CG_CSS::LAYOUT_CARD); ?> cg-danger-card">
        <h2><?php _e('Danger Zone', 'category-generator'); ?></h2>
        <p class="<?php echo esc_attr(CG_CSS::TEXT_DESCRIPTION); ?>"><?php _e('These actions are irreversible. Please be careful.', 'category-generator'); ?></p>
        
        <!-- Database Backup Section -->
        <div class="cg-backup-section">
            <h3><?php _e('Database Backup', 'category-generator'); ?></h3>
            <p><?php _e('Download a complete backup of the SQLite database file or restore from a previous backup.', 'category-generator'); ?></p>
            
            <div class="cg-backup-actions">
                <button type="button" class="<?php echo esc_attr(CG_CSS::BTN_PRIMARY); ?>" id="cg-download-db-btn">
                    <span class="dashicons dashicons-database-export"></span>
                    <?php _e('Download Database', 'category-generator'); ?>
                </button>
                <button type="button" class="<?php echo esc_attr(CG_CSS::BTN_DEFAULT); ?>" id="cg-restore-db-btn">
                    <span class="dashicons dashicons-database-import"></span>
                    <?php _e('Restore Database', 'category-generator'); ?>
                </button>
            </div>
        </div>
        
        <hr style="margin: <?php echo CG_Constants::SPACING_XLARGE; ?>px 0; border-color: #f5c6cb;">
        
        <div class="cg-danger-section">
            <h3><?php _e('Reset Database', 'category-generator'); ?></h3>
            <p><?php _e('This will permanently delete:', 'category-generator'); ?></p>
            <ul class="cg-danger-list">
                <li><?php _e('All HTML, Meta, and Schema templates', 'category-generator'); ?></li>
                <li><?php _e('All Inner Templates and Variables', 'category-generator'); ?></li>
                <li><?php _e('All saved Titles and Areas', 'category-generator'); ?></li>
                <li><?php _e('Complete category generation history', 'category-generator'); ?></li>
                <li><?php _e('All plugin settings and preferences', 'category-generator'); ?></li>
                <li><?php _e('Business profile data', 'category-generator'); ?></li>
            </ul>
            <p><strong><?php _e('Default templates will be recreated after reset.', 'category-generator'); ?></strong></p>
            
            <div class="cg-danger-actions">
                <button type="button" class="<?php echo esc_attr(CG_CSS::BTN_DEFAULT); ?>" id="cg-export-before-reset">
                    <span class="dashicons dashicons-download"></span>
                    <?php _e('Export All Data First', 'category-generator'); ?>
                </button>
                <button type="button" class="<?php echo esc_attr(CG_CSS::BTN_DANGER); ?>" id="cg-reset-database-btn">
                    <span class="dashicons dashicons-warning"></span>
                    <?php _e('Reset All Data', 'category-generator'); ?>
                </button>
            </div>
        </div>
    </div>
</div>
