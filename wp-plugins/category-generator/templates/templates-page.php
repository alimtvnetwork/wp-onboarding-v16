<?php
/**
 * Templates Management Page - Category Generator
 * 
 * @package Category_Generator_Area
 * @author MD Alim Ul Karim
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get template categories
$template_categories = $this->db->get_template_categories();

// Build hierarchical category list
function cg_build_category_tree($categories, $parent_id = 0, $prefix = '') {
    $tree = array();
    foreach ($categories as $cat) {
        if ($cat['parent_id'] == $parent_id) {
            $cat['display_name'] = $prefix . $cat['name'];
            $tree[] = $cat;
            $tree = array_merge($tree, cg_build_category_tree($categories, $cat['id'], $prefix . '— '));
        }
    }
    return $tree;
}
$category_tree = cg_build_category_tree($template_categories);
?>

<div class="wrap cg-admin-wrap">
    <h1 class="<?php echo esc_attr(CG_CSS::TEXT_TITLE); ?>">
        <span class="dashicons dashicons-media-code"></span>
        <?php _e('Template Manager', 'category-generator'); ?>
    </h1>
    
    <?php include CG_PLUGIN_PATH . 'templates/partials/templates-tabs.php'; ?>
    
    <?php include CG_PLUGIN_PATH . 'templates/partials/templates-tab-html.php'; ?>
    <?php include CG_PLUGIN_PATH . 'templates/partials/templates-tab-meta.php'; ?>
    <?php include CG_PLUGIN_PATH . 'templates/partials/templates-tab-schema.php'; ?>
    <?php include CG_PLUGIN_PATH . 'templates/partials/templates-tab-categories.php'; ?>
</div>

<?php include CG_PLUGIN_PATH . 'templates/partials/templates-modal-edit.php'; ?>
<?php include CG_PLUGIN_PATH . 'templates/partials/templates-modal-category.php'; ?>
<?php include CG_PLUGIN_PATH . 'templates/partials/templates-styles.php'; ?>
<?php include CG_PLUGIN_PATH . 'templates/partials/templates-scripts.php'; ?>
