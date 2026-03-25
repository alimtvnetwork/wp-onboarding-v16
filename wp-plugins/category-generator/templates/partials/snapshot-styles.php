<?php
/**
 * Snapshot Page Styles Partial
 * 
 * CSS styles for the snapshots page.
 * 
 * @package Category_Generator_Area
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<style>
.<?php echo CG_CSS::SNAPSHOTS_INTRO; ?> {
    background: #f8f9fa;
    padding: <?php echo CG_Constants::SPACING_LG; ?>px <?php echo CG_Constants::SPACING_XL; ?>px;
    border-radius: <?php echo CG_Constants::BORDER_RADIUS_LG; ?>px;
    margin-bottom: <?php echo CG_Constants::SPACING_XL; ?>px;
    border-left: 4px solid #2271b1;
}
.<?php echo CG_CSS::SNAPSHOTS_INTRO; ?> p { margin: 0 0 <?php echo CG_Constants::SPACING_SM; ?>px 0; }
.<?php echo CG_CSS::SNAPSHOTS_INTRO; ?> p:last-child { margin-bottom: 0; }
.<?php echo CG_CSS::SNAPSHOTS_INTRO; ?> code { background: #e8e8e8; padding: 2px <?php echo CG_Constants::SPACING_SM; ?>px; border-radius: <?php echo CG_Constants::BORDER_RADIUS_SM; ?>px; }

.<?php echo CG_CSS::CREATE_SNAPSHOT_FORM; ?> { margin-top: <?php echo CG_Constants::SPACING_LG; ?>px; }
.<?php echo CG_CSS::FORM_ROW; ?> { display: flex; align-items: flex-end; gap: <?php echo CG_Constants::SPACING_XL; ?>px; flex-wrap: wrap; }
.<?php echo CG_CSS::FORM_GROUP_INLINE; ?> { display: flex; flex-direction: column; }
.<?php echo CG_CSS::FORM_GROUP_INLINE; ?> label { margin-bottom: 6px; font-weight: 600; }

.<?php echo CG_CSS::COUNT_BADGE; ?> {
    background: #2271b1;
    color: white;
    padding: 2px 10px;
    border-radius: <?php echo CG_Constants::BORDER_RADIUS_ROUND; ?>px;
    font-size: 12px;
    font-weight: normal;
    margin-left: <?php echo CG_Constants::SPACING_SM; ?>px;
}

.<?php echo CG_CSS::STATUS_BADGE; ?> {
    font-size: 12px;
    font-weight: normal;
    padding: 3px 10px;
    border-radius: <?php echo CG_Constants::BORDER_RADIUS_ROUND; ?>px;
    margin-left: 10px;
}
.<?php echo CG_CSS::STATUS_ENABLED; ?> { background: #d4edda; color: #155724; }
.<?php echo CG_CSS::STATUS_DISABLED; ?> { background: #f8d7da; color: #721c24; }

.<?php echo CG_CSS::SNAPSHOTS_TABLE; ?> .<?php echo CG_CSS::COLUMN_TITLE; ?> { width: 25%; }
.<?php echo CG_CSS::SNAPSHOTS_TABLE; ?> .<?php echo CG_CSS::COLUMN_NOTES; ?> { width: 20%; }
.<?php echo CG_CSS::SNAPSHOTS_TABLE; ?> .<?php echo CG_CSS::COLUMN_COUNTS; ?> { width: 10%; text-align: center; }
.<?php echo CG_CSS::SNAPSHOTS_TABLE; ?> .<?php echo CG_CSS::COLUMN_SIZE; ?> { width: 10%; }
.<?php echo CG_CSS::SNAPSHOTS_TABLE; ?> .<?php echo CG_CSS::COLUMN_DATE; ?> { width: 18%; }
.<?php echo CG_CSS::SNAPSHOTS_TABLE; ?> .<?php echo CG_CSS::COLUMN_ACTIONS; ?> { width: 17%; text-align: right; }

.<?php echo CG_CSS::SNAPSHOTS_TABLE; ?> .row-actions { margin-top: <?php echo CG_Constants::SPACING_XS; ?>px; }
.<?php echo CG_CSS::SNAPSHOTS_TABLE; ?> .filename { font-family: monospace; font-size: 11px; color: #646970; }

.<?php echo CG_CSS::SNAPSHOTS_TABLE; ?> .button { padding: 2px <?php echo CG_Constants::SPACING_SM; ?>px; min-height: 28px; }
.<?php echo CG_CSS::SNAPSHOTS_TABLE; ?> .button .dashicons { font-size: <?php echo CG_Constants::ICON_SIZE_SMALL; ?>px; width: <?php echo CG_Constants::ICON_SIZE_SMALL; ?>px; height: <?php echo CG_Constants::ICON_SIZE_SMALL; ?>px; line-height: <?php echo CG_Constants::ICON_SIZE_SMALL; ?>px; }

.<?php echo CG_CSS::EMPTY_STATE; ?> {
    text-align: center;
    padding: <?php echo CG_Constants::SPACING_SECTION; ?>px <?php echo CG_Constants::SPACING_XL; ?>px;
    color: #646970;
}
.<?php echo CG_CSS::EMPTY_STATE; ?> .dashicons {
    font-size: <?php echo CG_Constants::ICON_SIZE_EMPTY_STATE; ?>px;
    width: <?php echo CG_Constants::ICON_SIZE_EMPTY_STATE; ?>px;
    height: <?php echo CG_Constants::ICON_SIZE_EMPTY_STATE; ?>px;
    opacity: 0.5;
    margin-bottom: 10px;
}
.<?php echo CG_CSS::EMPTY_STATE; ?> p { margin: 0; font-size: 14px; }

.<?php echo CG_CSS::NOTICE_INFO; ?> {
    background: #e7f3ff;
    border: 1px solid #2271b1;
    color: #0a4b78;
    padding: <?php echo CG_Constants::SPACING_MD; ?>px <?php echo CG_Constants::SPACING_LG; ?>px;
    border-radius: <?php echo CG_Constants::BORDER_RADIUS_MD; ?>px;
    margin-bottom: <?php echo CG_Constants::SPACING_LG; ?>px;
    display: flex;
    align-items: flex-start;
    gap: <?php echo CG_Constants::SPACING_SM; ?>px;
}
.<?php echo CG_CSS::NOTICE_INFO; ?> .dashicons { flex-shrink: 0; margin-top: 2px; }
</style>
