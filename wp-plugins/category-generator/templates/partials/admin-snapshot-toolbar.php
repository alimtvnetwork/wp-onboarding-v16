<?php
/**
 * Admin Snapshot Toolbar Partial
 * 
 * Quick snapshot and restore toolbar for Generate page.
 * 
 * @package Category_Generator_Area
 * @var array $recent_snapshots Recent snapshots for dropdown
 * @var bool $auto_snapshot_enabled Whether auto-snapshot is enabled
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="<?php echo CG_CSS::SNAPSHOT_TOOLBAR; ?>">
    <div class="<?php echo CG_CSS::SNAPSHOT_TOOLBAR_LEFT; ?>">
        <i class="fas fa-history cg-toolbar-icon"></i>
        <input type="text" id="<?php echo CG_CSS::ID_QUICK_SNAPSHOT_NAME; ?>" placeholder="<?php esc_attr_e('Snapshot name...', 'category-generator'); ?>">
        <button type="button" class="button button-primary" id="<?php echo CG_CSS::ID_QUICK_SNAPSHOT_BTN; ?>" title="<?php esc_attr_e('Take Snapshot Now', 'category-generator'); ?>">
            <i class="fas fa-camera"></i>
            <span><?php _e('Snapshot', 'category-generator'); ?></span>
        </button>
    </div>
    <div class="<?php echo CG_CSS::SNAPSHOT_TOOLBAR_RIGHT; ?>">
        <label class="<?php echo CG_CSS::AUTO_SNAPSHOT_TOGGLE; ?>">
            <input type="checkbox" id="<?php echo CG_CSS::ID_AUTO_SNAPSHOT_TOGGLE; ?>" <?php checked($auto_snapshot_enabled); ?>>
            <span><?php _e('Auto before generate', 'category-generator'); ?></span>
        </label>
        <select id="<?php echo CG_CSS::ID_QUICK_RESTORE_SELECT; ?>">
            <option value=""><?php _e('— Restore snapshot —', 'category-generator'); ?></option>
            <?php foreach ($recent_snapshots as $snapshot): ?>
                <option value="<?php echo esc_attr($snapshot['id']); ?>">
                    <?php echo esc_html(date(CG_Constants::DATE_FORMAT_SNAPSHOT, strtotime($snapshot['created_at'])) . ' - ' . $snapshot['title']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <a href="<?php echo admin_url('admin.php?page=cg-snapshots'); ?>" class="button cg-settings-btn" title="<?php esc_attr_e('Manage Snapshots', 'category-generator'); ?>">
            <i class="fas fa-cog"></i>
        </a>
    </div>
</div>
