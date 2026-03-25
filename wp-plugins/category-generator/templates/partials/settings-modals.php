<?php
/**
 * Settings Page - Modals
 * 
 * @package Category_Generator_Area
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- Reset Confirmation Modal -->
<div id="<?php echo esc_attr(CG_CSS::ID_SETTINGS_RESET_MODAL); ?>" class="<?php echo esc_attr(CG_CSS::MODAL); ?>" style="display: none;">
    <div class="<?php echo esc_attr(CG_CSS::MODAL_CONTENT); ?>" style="max-width: 500px;">
        <div class="<?php echo esc_attr(CG_CSS::MODAL_HEADER); ?> cg-modal-header-danger">
            <h2><?php _e('⚠️ Confirm Database Reset', 'category-generator'); ?></h2>
            <button type="button" class="<?php echo esc_attr(CG_CSS::MODAL_CLOSE); ?>">&times;</button>
        </div>
        <div class="<?php echo esc_attr(CG_CSS::MODAL_BODY); ?>">
            <p class="cg-reset-warning"><?php _e('This action cannot be undone! All your data will be permanently deleted.', 'category-generator'); ?></p>
            
            <div class="cg-reset-export-option">
                <label>
                    <input type="checkbox" id="cg-export-before-confirm" checked>
                    <?php _e('Export all data before resetting (recommended)', 'category-generator'); ?>
                </label>
            </div>
            
            <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
                <label for="cg-reset-confirm-text"><?php _e('Type "RESET" to confirm:', 'category-generator'); ?></label>
                <input type="text" id="cg-reset-confirm-text" placeholder="<?php echo esc_attr(CG_Constants::RESET_CONFIRM_TEXT); ?>" autocomplete="off">
            </div>
        </div>
        <div class="<?php echo esc_attr(CG_CSS::MODAL_FOOTER); ?>">
            <button type="button" class="<?php echo esc_attr(CG_CSS::BTN_DEFAULT); ?> <?php echo esc_attr(CG_CSS::MODAL_CLOSE); ?>"><?php _e('Cancel', 'category-generator'); ?></button>
            <button type="button" class="<?php echo esc_attr(CG_CSS::BTN_DANGER); ?>" id="cg-confirm-reset-btn" disabled>
                <?php _e('Yes, Reset Everything', 'category-generator'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Restore Database Modal -->
<div id="<?php echo esc_attr(CG_CSS::ID_SETTINGS_RESTORE_MODAL); ?>" class="<?php echo esc_attr(CG_CSS::MODAL); ?>" style="display: none;">
    <div class="<?php echo esc_attr(CG_CSS::MODAL_CONTENT); ?>" style="max-width: 500px;">
        <div class="<?php echo esc_attr(CG_CSS::MODAL_HEADER); ?>">
            <h2><?php _e('🔄 Restore Database', 'category-generator'); ?></h2>
            <button type="button" class="<?php echo esc_attr(CG_CSS::MODAL_CLOSE); ?>">&times;</button>
        </div>
        <div class="<?php echo esc_attr(CG_CSS::MODAL_BODY); ?>">
            <p class="cg-reset-warning"><?php _e('This will replace all current data with the backup file. Make sure to download the current database first!', 'category-generator'); ?></p>
            
            <div class="<?php echo esc_attr(CG_CSS::FORM_GROUP); ?>">
                <label for="cg-restore-file"><?php _e('Select SQLite Database File (.db)', 'category-generator'); ?></label>
                <input type="file" id="cg-restore-file" accept=".db,.sqlite,.sqlite3">
            </div>
            
            <div id="cg-restore-file-info" style="display: none;">
                <p><strong><?php _e('Selected file:', 'category-generator'); ?></strong> <span id="cg-restore-filename"></span></p>
                <p><strong><?php _e('Size:', 'category-generator'); ?></strong> <span id="cg-restore-filesize"></span></p>
            </div>
        </div>
        <div class="<?php echo esc_attr(CG_CSS::MODAL_FOOTER); ?>">
            <button type="button" class="<?php echo esc_attr(CG_CSS::BTN_DEFAULT); ?> <?php echo esc_attr(CG_CSS::MODAL_CLOSE); ?>"><?php _e('Cancel', 'category-generator'); ?></button>
            <button type="button" class="<?php echo esc_attr(CG_CSS::BTN_PRIMARY); ?>" id="cg-confirm-restore-btn" disabled>
                <?php _e('Restore Database', 'category-generator'); ?>
            </button>
        </div>
    </div>
</div>
