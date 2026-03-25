<?php
/**
 * History Page Template - Category Generator
 * 
 * @package Category_Generator_Area
 * @author MD Alim Ul Karim
 */

if (!defined('ABSPATH')) {
    exit;
}

$inner_templates = $this->db->get_inner_templates();
?>

<div class="wrap <?php echo CG_CSS::ADMIN_WRAP; ?>">
    <h1 class="<?php echo CG_CSS::TITLE; ?>">
        <span class="dashicons dashicons-backup"></span>
        <?php _e('Category History', 'category-generator'); ?>
    </h1>
    
    <div class="<?php echo CG_CSS::CARD; ?>">
        <!-- Header with Search and Actions -->
        <?php include CG_PLUGIN_PATH . 'templates/partials/history-header.php'; ?>
        
        <!-- Bulk Actions Bar -->
        <?php include CG_PLUGIN_PATH . 'templates/partials/history-bulk-actions.php'; ?>
        
        <!-- History Table with Pagination -->
        <?php include CG_PLUGIN_PATH . 'templates/partials/history-table.php'; ?>
    </div>
</div>

<!-- Modals -->
<?php include CG_PLUGIN_PATH . 'templates/partials/history-modals.php'; ?>

<!-- Styles -->
<?php include CG_PLUGIN_PATH . 'templates/partials/history-styles.php'; ?>

<!-- Scripts -->
<?php include CG_PLUGIN_PATH . 'templates/partials/history-scripts.php'; ?>
