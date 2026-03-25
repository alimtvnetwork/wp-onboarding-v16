<?php
/**
 * Settings Page - Styles
 * 
 * @package Category_Generator_Area
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<style>
.<?php echo CG_CSS::LAYOUT_TABS; ?> { display: flex; gap: 0; margin-bottom: 0; border-bottom: 1px solid #c3c4c7; flex-wrap: wrap; }
.<?php echo CG_CSS::LAYOUT_TAB; ?> { padding: 12px 24px; background: #f0f0f1; border: 1px solid #c3c4c7; border-bottom: none; cursor: pointer; font-size: 14px; margin-right: -1px; border-radius: 4px 4px 0 0; }
.<?php echo CG_CSS::LAYOUT_TAB; ?>.active { background: white; border-bottom: 1px solid white; margin-bottom: -1px; font-weight: 600; }
.<?php echo CG_CSS::LAYOUT_TAB_CONTENT; ?> { display: none; }
.<?php echo CG_CSS::LAYOUT_TAB_CONTENT; ?>.active { display: block; }
.<?php echo CG_CSS::FORM_GROUP; ?> { margin-bottom: <?php echo CG_Constants::SPACING_LARGE; ?>px; }
.<?php echo CG_CSS::FORM_GROUP; ?> label { display: block; font-weight: 600; margin-bottom: 6px; }
.<?php echo CG_CSS::FORM_GROUP; ?> input[type="text"],
.<?php echo CG_CSS::FORM_GROUP; ?> input[type="url"],
.<?php echo CG_CSS::FORM_GROUP; ?> input[type="password"],
.<?php echo CG_CSS::FORM_GROUP; ?> select { width: 100%; max-width: 500px; padding: 10px 12px; border: 1px solid #c3c4c7; border-radius: 4px; }
.<?php echo CG_CSS::FORM_GROUP; ?> input[type="checkbox"] { width: auto; margin-right: <?php echo CG_Constants::SPACING_SMALL; ?>px; }
.<?php echo CG_CSS::TEXT_HINT; ?> { display: block; margin-top: 4px; font-size: 12px; color: #646970; }
.cg-ai-config-grid { margin-top: <?php echo CG_Constants::SPACING_LARGE; ?>px; }
.cg-ai-provider-config { background: #f8f9fa; padding: <?php echo CG_Constants::SPACING_LARGE; ?>px; border-radius: <?php echo CG_Constants::SPACING_SMALL; ?>px; margin-bottom: <?php echo CG_Constants::SPACING_MEDIUM; ?>px; }
.cg-ai-provider-config h3 { margin: 0 0 <?php echo CG_Constants::SPACING_MEDIUM; ?>px 0; }
.cg-remote-api-item { background: #f8f9fa; padding: <?php echo CG_Constants::SPACING_MEDIUM; ?>px <?php echo CG_Constants::SPACING_LARGE; ?>px; border-radius: <?php echo CG_Constants::SPACING_SMALL; ?>px; margin-bottom: <?php echo CG_Constants::SPACING_MEDIUM; ?>px; }
.cg-api-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: <?php echo CG_Constants::SPACING_SMALL; ?>px; }
.cg-api-status { font-size: 12px; }
.cg-api-status.enabled { color: #00a32a; }
.cg-api-status.disabled { color: #646970; }
.cg-api-url { font-family: monospace; font-size: 13px; color: #646970; margin-bottom: 12px; }
.cg-api-actions { display: flex; gap: <?php echo CG_Constants::SPACING_SMALL; ?>px; }
.cg-add-api-section { margin-top: <?php echo CG_Constants::SPACING_XLARGE; ?>px; padding-top: <?php echo CG_Constants::SPACING_LARGE; ?>px; border-top: 1px solid #ddd; }
.cg-add-api-section h3 { margin-bottom: <?php echo CG_Constants::SPACING_MEDIUM; ?>px; }
.<?php echo CG_CSS::NOTICE_SUCCESS; ?> { padding: 12px 16px; border-radius: 6px; margin-bottom: <?php echo CG_Constants::SPACING_LARGE; ?>px; display: flex; align-items: center; gap: <?php echo CG_Constants::SPACING_SMALL; ?>px; background: #d4edda; border: 1px solid #00a32a; color: #155724; }
.<?php echo CG_CSS::NOTICE_WARNING; ?> { padding: 12px 16px; border-radius: 6px; margin-bottom: <?php echo CG_Constants::SPACING_LARGE; ?>px; display: flex; align-items: center; gap: <?php echo CG_Constants::SPACING_SMALL; ?>px; background: #fff3cd; border: 1px solid #ffc107; color: #856404; }
.cg-yoast-info { background: #f8f9fa; padding: <?php echo CG_Constants::SPACING_MEDIUM; ?>px; border-radius: 6px; margin-bottom: <?php echo CG_Constants::SPACING_LARGE; ?>px; }
.cg-yoast-info p { margin: 0 0 <?php echo CG_Constants::SPACING_SMALL; ?>px 0; }
.cg-yoast-info p:last-child { margin-bottom: 0; }
.<?php echo CG_CSS::TEXT_EMPTY; ?> { color: #646970; font-style: italic; }

/* Backup Section Styles */
.cg-backup-section { background: #e7f3ff; padding: <?php echo CG_Constants::SPACING_LARGE; ?>px; border-radius: <?php echo CG_Constants::SPACING_SMALL; ?>px; border: 1px solid #b3d7ff; margin-bottom: <?php echo CG_Constants::SPACING_LARGE; ?>px; }
.cg-backup-section h3 { margin: 0 0 10px 0; color: #004085; }
.cg-backup-section p { color: #004085; margin-bottom: <?php echo CG_Constants::SPACING_MEDIUM; ?>px; }
.cg-backup-actions { display: flex; gap: <?php echo CG_Constants::SPACING_MEDIUM; ?>px; }

/* Danger Zone Styles */
.cg-danger-card { border: 2px solid #dc3545 !important; }
.cg-danger-card h2 { color: #dc3545; }
.cg-danger-section { background: #fff5f5; padding: <?php echo CG_Constants::SPACING_LARGE; ?>px; border-radius: <?php echo CG_Constants::SPACING_SMALL; ?>px; border: 1px solid #f5c6cb; }
.cg-danger-section h3 { margin: 0 0 10px 0; color: #721c24; }
.cg-danger-list { margin: 10px 0 <?php echo CG_Constants::SPACING_MEDIUM; ?>px <?php echo CG_Constants::SPACING_LARGE; ?>px; color: #721c24; }
.cg-danger-list li { margin-bottom: 5px; }
.cg-danger-actions { display: flex; gap: <?php echo CG_Constants::SPACING_MEDIUM; ?>px; margin-top: <?php echo CG_Constants::SPACING_LARGE; ?>px; }
.<?php echo CG_CSS::BTN_DANGER; ?> { background: #dc3545 !important; color: white !important; border-color: #dc3545 !important; }
.<?php echo CG_CSS::BTN_DANGER; ?>:hover { background: #c82333 !important; border-color: #bd2130 !important; }
.<?php echo CG_CSS::BTN_DANGER; ?>:disabled { background: #e9a5ab !important; border-color: #e9a5ab !important; cursor: not-allowed; }

/* Modal Styles */
.<?php echo CG_CSS::MODAL; ?> { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: <?php echo CG_Constants::Z_INDEX_MODAL; ?>; display: flex; align-items: center; justify-content: center; }
.<?php echo CG_CSS::MODAL_CONTENT; ?> { background: white; border-radius: <?php echo CG_Constants::SPACING_SMALL; ?>px; max-width: 600px; width: 90%; max-height: 80vh; overflow: auto; }
.<?php echo CG_CSS::MODAL_HEADER; ?> { display: flex; justify-content: space-between; align-items: center; padding: 16px 24px; border-bottom: 1px solid #ddd; }
.<?php echo CG_CSS::MODAL_HEADER; ?> h2 { margin: 0; }
.cg-modal-header-danger { background: #fff5f5; border-bottom-color: #f5c6cb; }
.cg-modal-header-danger h2 { color: #721c24; }
.<?php echo CG_CSS::MODAL_CLOSE; ?> { background: none; border: none; font-size: 24px; cursor: pointer; color: #666; }
.<?php echo CG_CSS::MODAL_BODY; ?> { padding: 24px; }
.<?php echo CG_CSS::MODAL_FOOTER; ?> { padding: 16px 24px; border-top: 1px solid #ddd; display: flex; justify-content: flex-end; gap: 10px; }
.cg-reset-warning { background: #fff3cd; padding: <?php echo CG_Constants::SPACING_MEDIUM; ?>px; border-radius: 6px; color: #856404; font-weight: 600; margin-bottom: <?php echo CG_Constants::SPACING_LARGE; ?>px; }
.cg-reset-export-option { background: #e7f3ff; padding: 12px <?php echo CG_Constants::SPACING_MEDIUM; ?>px; border-radius: 6px; margin-bottom: <?php echo CG_Constants::SPACING_LARGE; ?>px; }
.cg-save-notice { position: fixed; bottom: <?php echo CG_Constants::SPACING_XLARGE; ?>px; right: <?php echo CG_Constants::SPACING_XLARGE; ?>px; background: #00a32a; color: white; padding: <?php echo CG_Constants::SPACING_MEDIUM; ?>px 25px; border-radius: 6px; font-size: 14px; z-index: <?php echo CG_Constants::Z_INDEX_MODAL + 1; ?>; box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
</style>
