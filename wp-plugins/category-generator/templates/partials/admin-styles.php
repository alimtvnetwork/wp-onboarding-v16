<?php
/**
 * Admin Page Styles Partial
 * 
 * CSS styles specific to the Generate page.
 * 
 * @package Category_Generator_Area
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<style>
/* Snapshot Toolbar */
.<?php echo CG_CSS::SNAPSHOT_TOOLBAR; ?> {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, #f0f7ff 0%, #e8f4f8 100%);
    border: 1px solid #c3dff7;
    border-radius: <?php echo CG_Constants::BORDER_RADIUS_LG; ?>px;
    padding: <?php echo CG_Constants::SPACING_MD; ?>px <?php echo CG_Constants::SPACING_XL; ?>px;
    margin-bottom: <?php echo CG_Constants::SPACING_XL; ?>px;
    flex-wrap: wrap;
    gap: <?php echo CG_Constants::SPACING_MD; ?>px;
}

.<?php echo CG_CSS::SNAPSHOT_TOOLBAR_LEFT; ?>,
.<?php echo CG_CSS::SNAPSHOT_TOOLBAR_RIGHT; ?> {
    display: flex;
    align-items: center;
    gap: <?php echo CG_Constants::SPACING_SM; ?>px;
}

/* Font Awesome icon styling in toolbar */
.cg-toolbar-icon {
    color: #2271b1;
    font-size: 18px;
    width: 24px;
    text-align: center;
}

.<?php echo CG_CSS::SNAPSHOT_TOOLBAR_LEFT; ?> input[type="text"] {
    width: 200px;
    padding: 8px 12px;
    border: 1px solid #c3c4c7;
    border-radius: <?php echo CG_Constants::BORDER_RADIUS_XS; ?>px;
    font-size: 13px;
    height: 36px;
    box-sizing: border-box;
}

/* Snapshot button styling */
.<?php echo CG_CSS::SNAPSHOT_TOOLBAR_LEFT; ?> .button-primary {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 0 14px;
    height: 36px;
    font-size: 13px;
    font-weight: 500;
    border-radius: <?php echo CG_Constants::BORDER_RADIUS_XS; ?>px;
    line-height: 1;
}

.<?php echo CG_CSS::SNAPSHOT_TOOLBAR_LEFT; ?> .button-primary i {
    font-size: 14px;
}

/* Auto snapshot toggle */
.<?php echo CG_CSS::AUTO_SNAPSHOT_TOGGLE; ?> {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: #50575e;
    background: white;
    padding: 8px 12px;
    border-radius: <?php echo CG_Constants::BORDER_RADIUS_XS; ?>px;
    border: 1px solid #c3c4c7;
    cursor: pointer;
    height: 36px;
    box-sizing: border-box;
}

.<?php echo CG_CSS::AUTO_SNAPSHOT_TOGGLE; ?> input { 
    margin: 0; 
    width: 16px;
    height: 16px;
}

.<?php echo CG_CSS::AUTO_SNAPSHOT_TOGGLE; ?> span {
    white-space: nowrap;
}

/* Restore dropdown */
#<?php echo CG_CSS::ID_QUICK_RESTORE_SELECT; ?> {
    min-width: 200px;
    padding: 0 10px;
    height: 36px;
    font-size: 13px;
    border: 1px solid #c3c4c7;
    border-radius: <?php echo CG_Constants::BORDER_RADIUS_XS; ?>px;
}

/* Settings button */
.cg-settings-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    padding: 0 !important;
    border-radius: <?php echo CG_Constants::BORDER_RADIUS_XS; ?>px;
}

.cg-settings-btn i {
    font-size: 16px;
    color: #50575e;
}

.cg-settings-btn:hover i {
    color: #2271b1;
}

/* Inline selector for titles/areas */
.<?php echo CG_CSS::INLINE_SELECTOR; ?> { 
    display: flex; 
    align-items: center;
    gap: 8px; 
    margin-bottom: <?php echo CG_Constants::SPACING_SM; ?>px; 
}

.<?php echo CG_CSS::INLINE_SELECTOR; ?> select { 
    flex: 1; 
    height: 36px;
    padding: 0 10px;
}

.<?php echo CG_CSS::INLINE_SELECTOR; ?> .button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 36px;
    min-width: 36px;
    padding: 0 10px;
}

.<?php echo CG_CSS::INLINE_SELECTOR; ?> .button .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
    line-height: 1;
}

/* Save as new button */
#cg-save-titles-as-new,
#cg-save-areas-as-new {
    width: 36px;
    padding: 0 !important;
}

#cg-save-titles-as-new .dashicons,
#cg-save-areas-as-new .dashicons {
    margin: 0;
}

.<?php echo CG_CSS::HIDDEN; ?> { display: none !important; }

.<?php echo CG_CSS::SAVE_BTN; ?>.cg-modified { 
    display: inline-flex !important; 
    background: #2271b1; 
    color: #fff; 
}

.<?php echo CG_CSS::TEMPLATE_SELECTOR; ?> .dashicons { 
    vertical-align: text-bottom; 
}

/* Meta variations */
.<?php echo CG_CSS::META_VARIATIONS_SCROLL; ?> { 
    max-height: 200px; 
    overflow-y: auto; 
    border: 1px solid #ddd; 
    padding: 10px; 
    border-radius: <?php echo CG_Constants::BORDER_RADIUS_XS; ?>px; 
    background: #f9f9f9; 
}

.<?php echo CG_CSS::META_DESC_SCROLL; ?> { max-height: 350px; }

.<?php echo CG_CSS::META_VARIATION; ?> { 
    display: flex; 
    gap: 10px; 
    align-items: flex-start; 
    margin-bottom: <?php echo CG_Constants::SPACING_SM; ?>px; 
}

.<?php echo CG_CSS::META_DESC_VARIATION; ?> { margin-bottom: <?php echo CG_Constants::SPACING_MD; ?>px; }

.<?php echo CG_CSS::VARIATION_NUM; ?> { 
    background: #2271b1; 
    color: white; 
    width: 22px; 
    height: 22px; 
    border-radius: 50%; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    font-size: 11px; 
    font-weight: 600; 
    flex-shrink: 0; 
    margin-top: 10px; 
}

.<?php echo CG_CSS::META_VARIATION; ?> input, 
.<?php echo CG_CSS::META_VARIATION; ?> textarea { 
    flex: 1; 
}

.<?php echo CG_CSS::CHAR_COUNT; ?> { 
    font-size: 11px; 
    color: #666; 
    white-space: nowrap; 
    margin-top: 10px; 
}

.<?php echo CG_CSS::CHAR_COUNT; ?> span { font-weight: 600; }
</style>
