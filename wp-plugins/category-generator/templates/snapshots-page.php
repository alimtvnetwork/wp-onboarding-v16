<?php
/**
 * Snapshots Page Template - Category Generator
 * 
 * Displays manual and automatic snapshots of WordPress category tables
 * with options to create, restore, and manage snapshots.
 * 
 * @package Category_Generator_Area
 * @author MD Alim Ul Karim
 */

if (!defined('ABSPATH')) {
    exit;
}

$snapshot_handler = CG_Snapshot::get_instance();
$settings = CG_Settings::get_instance();

$manual_snapshots = $this->db->get_snapshots(CG_Constants::SNAPSHOT_TYPE_MANUAL);
$auto_snapshots = $this->db->get_snapshots(CG_Constants::SNAPSHOT_TYPE_AUTO);
$snapshot_limit = $settings->get(CG_Constants::SETTING_SNAPSHOT_LIMIT, CG_Constants::SNAPSHOT_LIMIT_DEFAULT);
$auto_snapshot_enabled = $settings->get(CG_Constants::SETTING_AUTO_SNAPSHOT, false);
?>

<div class="wrap <?php echo CG_CSS::ADMIN_WRAP; ?>">
    <h1 class="<?php echo CG_CSS::TITLE; ?>">
        <span class="dashicons dashicons-backup"></span>
        <?php _e('Category Snapshots', 'category-generator'); ?>
    </h1>
    
    <div class="<?php echo CG_CSS::SNAPSHOTS_INTRO; ?>">
        <p><?php _e('Snapshots save the current state of your WordPress category tables (terms, taxonomy, and meta). Use them to quickly backup and restore categories before making changes.', 'category-generator'); ?></p>
        <p>
            <strong><?php _e('Storage Location:', 'category-generator'); ?></strong> 
            <code><?php echo esc_html($snapshot_handler->get_snapshot_dir()); ?></code>
        </p>
    </div>
    
    <!-- Create Snapshot Section -->
    <?php include CG_PLUGIN_PATH . 'templates/partials/snapshot-create-form.php'; ?>
    
    <!-- Manual Snapshots Table -->
    <div class="<?php echo CG_CSS::CARD; ?>">
        <h2>
            <span class="dashicons dashicons-admin-users"></span>
            <?php _e('Manual Snapshots', 'category-generator'); ?>
            <span class="<?php echo CG_CSS::COUNT_BADGE; ?>"><?php echo count($manual_snapshots); ?></span>
        </h2>
        
        <?php
        $snapshots = $manual_snapshots;
        $type = CG_Constants::SNAPSHOT_TYPE_MANUAL;
        $list_id = CG_CSS::ID_MANUAL_SNAPSHOTS_LIST;
        $empty_message = __('No manual snapshots yet. Create one above to backup your categories.', 'category-generator');
        include CG_PLUGIN_PATH . 'templates/partials/snapshot-table.php';
        ?>
    </div>
    
    <!-- Automatic Snapshots Table -->
    <div class="<?php echo CG_CSS::CARD; ?>">
        <h2>
            <span class="dashicons dashicons-update"></span>
            <?php _e('Automatic Snapshots', 'category-generator'); ?>
            <span class="<?php echo CG_CSS::COUNT_BADGE; ?>"><?php echo count($auto_snapshots); ?></span>
            <?php if ($auto_snapshot_enabled): ?>
                <span class="<?php echo CG_CSS::STATUS_BADGE; ?> <?php echo CG_CSS::STATUS_ENABLED; ?>"><?php _e('Auto-snapshot enabled', 'category-generator'); ?></span>
            <?php else: ?>
                <span class="<?php echo CG_CSS::STATUS_BADGE; ?> <?php echo CG_CSS::STATUS_DISABLED; ?>"><?php _e('Auto-snapshot disabled', 'category-generator'); ?></span>
            <?php endif; ?>
        </h2>
        
        <p class="<?php echo CG_CSS::DESCRIPTION; ?>">
            <?php printf(
                __('Automatic snapshots are created before category generation when enabled in settings. Limit: %d snapshots (oldest auto-deleted).', 'category-generator'),
                $snapshot_limit
            ); ?>
            <a href="<?php echo admin_url('admin.php?page=cg-settings'); ?>"><?php _e('Configure settings →', 'category-generator'); ?></a>
        </p>
        
        <?php
        $snapshots = $auto_snapshots;
        $type = CG_Constants::SNAPSHOT_TYPE_AUTO;
        $list_id = CG_CSS::ID_AUTO_SNAPSHOTS_LIST;
        $empty_message = __('No automatic snapshots yet. Enable auto-snapshot in settings to create them before each generation.', 'category-generator');
        include CG_PLUGIN_PATH . 'templates/partials/snapshot-table.php';
        ?>
    </div>
</div>

<!-- Restore Confirmation Modal -->
<?php include CG_PLUGIN_PATH . 'templates/partials/snapshot-restore-modal.php'; ?>

<!-- Styles -->
<?php include CG_PLUGIN_PATH . 'templates/partials/snapshot-styles.php'; ?>

<!-- Scripts -->
<?php include CG_PLUGIN_PATH . 'templates/partials/snapshot-scripts.php'; ?>
