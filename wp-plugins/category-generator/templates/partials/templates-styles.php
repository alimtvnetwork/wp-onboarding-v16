<?php
/**
 * Templates Page - Styles
 * 
 * @package Category_Generator_Area
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<style>
.<?php echo CG_CSS::LAYOUT_TABS; ?> { display: flex; gap: 0; margin-bottom: 0; border-bottom: 1px solid #c3c4c7; }
.<?php echo CG_CSS::LAYOUT_TAB; ?> { padding: 12px 24px; background: #f0f0f1; border: 1px solid #c3c4c7; border-bottom: none; cursor: pointer; font-size: 14px; margin-right: -1px; border-radius: 4px 4px 0 0; }
.<?php echo CG_CSS::LAYOUT_TAB; ?>.active { background: white; border-bottom: 1px solid white; margin-bottom: -1px; font-weight: 600; }
.<?php echo CG_CSS::LAYOUT_TAB_CONTENT; ?> { display: none; }
.<?php echo CG_CSS::LAYOUT_TAB_CONTENT; ?>.active { display: block; }
.cg-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: <?php echo CG_Constants::SPACING_LARGE; ?>px; flex-wrap: wrap; gap: 10px; }
.cg-card-header h2 { margin: 0; }
.cg-card-header-actions { display: flex; gap: 10px; align-items: center; }
.cg-category-filter { min-width: 180px; padding: 6px 10px; }
.cg-placeholder-list { background: #f8f9fa; padding: <?php echo CG_Constants::SPACING_MEDIUM; ?>px <?php echo CG_Constants::SPACING_LARGE; ?>px; border-radius: 6px; font-size: 13px; line-height: 2; }
.cg-placeholder-list code { background: #e9ecef; padding: 2px 6px; border-radius: 3px; }
.cg-category-badge { display: inline-block; padding: 3px <?php echo CG_Constants::SPACING_SMALL; ?>px; background: #e7f3ff; color: #0073aa; border-radius: 4px; font-size: 11px; font-weight: 500; }
.cg-category-badge.cg-uncategorized { background: #f0f0f1; color: #646970; }
.cg-form-row-2col { display: grid; grid-template-columns: 1fr 1fr; gap: <?php echo CG_Constants::SPACING_LARGE; ?>px; }

/* Category Tree Styles */
.cg-category-tree { margin-top: <?php echo CG_Constants::SPACING_LARGE; ?>px; }
.cg-tree-level { list-style: none; margin: 0; padding: 0; }
.cg-tree-level.cg-tree-category { padding-left: <?php echo CG_Constants::SPACING_XLARGE; ?>px; }
.cg-tree-level.cg-tree-subcategory { padding-left: <?php echo CG_Constants::SPACING_XLARGE; ?>px; }
.cg-tree-item { margin: <?php echo CG_Constants::SPACING_SMALL; ?>px 0; }
.cg-tree-item-content { display: flex; align-items: center; gap: 10px; padding: 10px <?php echo CG_Constants::SPACING_MEDIUM; ?>px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 6px; }
.cg-tree-root > .cg-tree-item > .cg-tree-item-content { background: #e7f3ff; border-color: #0073aa; }
.cg-tree-category > .cg-tree-item > .cg-tree-item-content { background: #fff3cd; border-color: #ffc107; }
.cg-tree-subcategory > .cg-tree-item > .cg-tree-item-content { background: #d4edda; border-color: #28a745; }
.cg-tree-icon { font-size: <?php echo CG_Constants::ICON_SIZE_MEDIUM; ?>px; }
.cg-tree-name { flex: 1; font-weight: 600; }
.cg-tree-level-badge { font-size: 10px; padding: 2px 6px; background: rgba(0,0,0,0.1); border-radius: 3px; text-transform: uppercase; }
.cg-tree-actions { display: flex; gap: 6px; }
.cg-parent-display { background: #f0f0f1; padding: <?php echo CG_Constants::SPACING_SMALL; ?>px 12px; border-radius: 4px; margin: 0; }

/* Modal Styles */
.<?php echo CG_CSS::MODAL; ?> { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: <?php echo CG_Constants::Z_INDEX_MODAL; ?>; display: flex; align-items: center; justify-content: center; }
.<?php echo CG_CSS::MODAL_CONTENT; ?> { background: white; width: 90%; max-width: 800px; max-height: 90vh; border-radius: <?php echo CG_Constants::SPACING_SMALL; ?>px; display: flex; flex-direction: column; }
.<?php echo CG_CSS::MODAL_HEADER; ?> { display: flex; justify-content: space-between; align-items: center; padding: 16px 24px; border-bottom: 1px solid #ddd; }
.<?php echo CG_CSS::MODAL_HEADER; ?> h2 { margin: 0; }
.<?php echo CG_CSS::MODAL_CLOSE; ?> { background: none; border: none; font-size: 24px; cursor: pointer; color: #666; }
.<?php echo CG_CSS::MODAL_BODY; ?> { padding: 24px; overflow-y: auto; flex: 1; }
.<?php echo CG_CSS::MODAL_FOOTER; ?> { padding: 16px 24px; border-top: 1px solid #ddd; display: flex; justify-content: flex-end; gap: 10px; }
.<?php echo CG_CSS::FORM_GROUP; ?> { margin-bottom: 16px; }
.<?php echo CG_CSS::FORM_GROUP; ?> label { display: block; font-weight: 600; margin-bottom: 6px; }
.<?php echo CG_CSS::FORM_GROUP; ?> input, 
.<?php echo CG_CSS::FORM_GROUP; ?> textarea, 
.<?php echo CG_CSS::FORM_GROUP; ?> select { width: 100%; padding: 10px 12px; border: 1px solid #c3c4c7; border-radius: 4px; }
.<?php echo CG_CSS::FORM_GROUP; ?> textarea { font-family: monospace; font-size: 13px; }
.<?php echo CG_CSS::BADGE; ?> { display: inline-block; padding: 3px <?php echo CG_Constants::SPACING_SMALL; ?>px; border-radius: 3px; font-size: 11px; font-weight: 600; }
.<?php echo CG_CSS::BADGE_YES; ?> { background: #d4edda; color: #155724; }
.<?php echo CG_CSS::BADGE_NO; ?> { background: #f8f9fa; color: #6c757d; }
.<?php echo CG_CSS::TEXT_EMPTY; ?> { color: #646970; font-style: italic; padding: <?php echo CG_Constants::SPACING_LARGE; ?>px; text-align: center; }
</style>
