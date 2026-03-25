<?php
/**
 * Templates Page - Categories Tab
 * 
 * @package Category_Generator_Area
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render category tree recursively
 */
function cg_render_category_level($categories, $parent_id = 0, $level = 0) {
    $children = array_filter($categories, function($c) use ($parent_id) {
        return $c['parent_id'] == $parent_id;
    });
    
    if (empty($children)) return;
    
    $class = $level === 0 ? 'cg-tree-root' : ($level === 1 ? 'cg-tree-category' : 'cg-tree-subcategory');
    echo '<ul class="cg-tree-level ' . esc_attr($class) . '">';
    foreach ($children as $cat) {
        $level_label = $cat['level'] == 0 ? 'Root' : ($cat['level'] == 1 ? 'Category' : 'Subcategory');
        echo '<li class="cg-tree-item" data-id="' . esc_attr($cat['id']) . '">';
        echo '<div class="cg-tree-item-content">';
        echo '<span class="cg-tree-icon">📁</span>';
        echo '<span class="cg-tree-name">' . esc_html($cat['name']) . '</span>';
        echo '<span class="cg-tree-level-badge">' . esc_html($level_label) . '</span>';
        echo '<div class="cg-tree-actions">';
        if ($cat['level'] < 2) {
            echo '<button class="button button-small cg-add-child-category" data-parent="' . esc_attr($cat['id']) . '" data-level="' . ($cat['level'] + 1) . '">+ Add Child</button>';
        }
        echo '<button class="button button-small cg-delete-category" data-id="' . esc_attr($cat['id']) . '">Delete</button>';
        echo '</div>';
        echo '</div>';
        cg_render_category_level($categories, $cat['id'], $level + 1);
        echo '</li>';
    }
    echo '</ul>';
}
?>

<div class="<?php echo esc_attr(CG_CSS::LAYOUT_TAB_CONTENT); ?>" id="tab-categories">
    <div class="<?php echo esc_attr(CG_CSS::LAYOUT_CARD); ?>">
        <div class="cg-card-header">
            <h2><?php _e('Template Categories (3-Level Hierarchy)', 'category-generator'); ?></h2>
            <button type="button" class="<?php echo esc_attr(CG_CSS::BTN_PRIMARY); ?>" id="cg-add-category-btn">
                <span class="dashicons dashicons-plus-alt2"></span>
                <?php _e('Add Category', 'category-generator'); ?>
            </button>
        </div>
        
        <p class="<?php echo esc_attr(CG_CSS::TEXT_DESCRIPTION); ?>"><?php _e('Organize your templates with a 3-level hierarchy: Root → Category → Subcategory → Variations', 'category-generator'); ?></p>
        
        <div class="cg-category-tree" id="cg-category-tree">
            <?php
            if (empty($template_categories)) {
                echo '<p class="' . esc_attr(CG_CSS::TEXT_EMPTY) . '">' . __('No categories yet. Click "Add Category" to create a root category.', 'category-generator') . '</p>';
            } else {
                cg_render_category_level($template_categories);
            }
            ?>
        </div>
    </div>
</div>
