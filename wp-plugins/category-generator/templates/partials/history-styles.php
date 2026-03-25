<?php
/**
 * History Page Styles Partial
 * 
 * CSS styles for the history page.
 * 
 * @package Category_Generator_Area
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<style>
.<?php echo CG_CSS::HISTORY_HEADER; ?> {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: <?php echo CG_Constants::SPACING_XL; ?>px;
    flex-wrap: wrap;
    gap: <?php echo CG_Constants::SPACING_LG; ?>px;
}
.<?php echo CG_CSS::SEARCH_BOX; ?> { display: flex; gap: <?php echo CG_Constants::SPACING_SM; ?>px; align-items: stretch; }
.<?php echo CG_CSS::SEARCH_BOX; ?> input { min-width: 280px; padding: <?php echo CG_Constants::SPACING_SM; ?>px <?php echo CG_Constants::SPACING_MD; ?>px; height: 36px; box-sizing: border-box; }
.<?php echo CG_CSS::SEARCH_BTN; ?> { display: inline-flex !important; align-items: center; gap: <?php echo CG_Constants::SPACING_MD; ?>px; height: 36px; padding: 0 14px !important; box-sizing: border-box; }
.<?php echo CG_CSS::SEARCH_BTN; ?> .dashicons { font-size: <?php echo CG_Constants::ICON_SIZE_SMALL; ?>px; width: <?php echo CG_Constants::ICON_SIZE_SMALL; ?>px; height: <?php echo CG_Constants::ICON_SIZE_SMALL; ?>px; line-height: <?php echo CG_Constants::ICON_SIZE_SMALL; ?>px; }
.cg-search-btn-text { line-height: 1; }
.<?php echo CG_CSS::HISTORY_ACTIONS; ?> { display: flex; gap: <?php echo CG_Constants::SPACING_SM; ?>px; margin-left: auto; }
.<?php echo CG_CSS::HISTORY_STATS; ?> { color: #666; font-size: 14px; flex: 1; text-align: center; }

#<?php echo CG_CSS::ID_HISTORY_TABLE; ?> th, #<?php echo CG_CSS::ID_HISTORY_TABLE; ?> td { vertical-align: middle; font-size: 12px; }
.<?php echo CG_CSS::COLUMN_CB; ?> { text-align: center; }
.<?php echo CG_CSS::COLUMN_CB; ?> input { margin: 0; }
.<?php echo CG_CSS::COLUMN_NAME; ?> { font-weight: 600; }
.<?php echo CG_CSS::COLUMN_SLUG; ?>, .<?php echo CG_CSS::COLUMN_META_TITLE; ?>, .<?php echo CG_CSS::COLUMN_META_DESC; ?> { max-width: 120px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.<?php echo CG_CSS::BADGE; ?> { display: inline-block; padding: 2px <?php echo CG_Constants::SPACING_MD; ?>px; border-radius: <?php echo CG_Constants::BORDER_RADIUS_SM; ?>px; font-size: 10px; font-weight: 600; }
.<?php echo CG_CSS::BADGE_YES; ?> { background: #d4edda; color: #155724; }
.<?php echo CG_CSS::BADGE_NO; ?> { background: #f8f9fa; color: #6c757d; }

.<?php echo CG_CSS::YOAST_SCORE; ?> { display: inline-flex; align-items: center; justify-content: center; width: 12px; height: 12px; border-radius: 50%; }
.<?php echo CG_CSS::YOAST_GOOD; ?> { background: #7ad03a; }
.<?php echo CG_CSS::YOAST_OK; ?> { background: #ee7c1b; }
.<?php echo CG_CSS::YOAST_BAD; ?> { background: #dc3232; }
.<?php echo CG_CSS::YOAST_NA; ?> { background: #888; }

.<?php echo CG_CSS::PAGINATION_WRAPPER; ?> { display: flex; justify-content: space-between; align-items: center; margin-top: <?php echo CG_Constants::SPACING_XL; ?>px; padding-top: <?php echo CG_Constants::SPACING_XL; ?>px; border-top: 1px solid #ddd; flex-wrap: wrap; gap: <?php echo CG_Constants::SPACING_LG; ?>px; }
.<?php echo CG_CSS::PER_PAGE_SELECTOR; ?> { display: flex; align-items: center; gap: <?php echo CG_Constants::SPACING_SM; ?>px; font-size: 13px; }
.<?php echo CG_CSS::PER_PAGE_SELECTOR; ?> select { padding: <?php echo CG_Constants::SPACING_XS; ?>px <?php echo CG_Constants::SPACING_SM; ?>px; border-radius: <?php echo CG_Constants::BORDER_RADIUS_XS; ?>px; border: 1px solid #ddd; }
.<?php echo CG_CSS::PAGINATION; ?> { display: flex; justify-content: center; align-items: center; gap: <?php echo CG_Constants::SPACING_SM; ?>px; flex: 1; }
.<?php echo CG_CSS::PAGINATION; ?> button { min-width: 40px; }
.<?php echo CG_CSS::PAGINATION; ?> .<?php echo CG_CSS::CURRENT; ?> { background: #2271b1; color: white; border-color: #2271b1; }

.<?php echo CG_CSS::EMPTY_ROW; ?> td { text-align: center; padding: <?php echo CG_Constants::SPACING_SECTION; ?>px !important; color: #666; }
.<?php echo CG_CSS::ACTION_LINK; ?> { color: #2271b1; text-decoration: none; margin-right: <?php echo CG_Constants::SPACING_SM; ?>px; font-size: 12px; }
.<?php echo CG_CSS::ACTION_LINK; ?>:hover { text-decoration: underline; }
.<?php echo CG_CSS::ACTION_LINK; ?>.<?php echo CG_CSS::INJECT_LINK; ?> { color: #00a32a; }

.<?php echo CG_CSS::BULK_ACTIONS_BAR; ?> { display: flex; align-items: center; justify-content: space-between; background: #f0f6fc; border: 1px solid #2271b1; border-radius: <?php echo CG_Constants::BORDER_RADIUS_MD; ?>px; padding: <?php echo CG_Constants::SPACING_MD; ?>px <?php echo CG_Constants::SPACING_LG; ?>px; margin-bottom: <?php echo CG_Constants::SPACING_LG; ?>px; }
.<?php echo CG_CSS::BULK_SELECTED; ?> { font-weight: 600; color: #2271b1; }
.<?php echo CG_CSS::BULK_BUTTONS; ?> { display: flex; gap: <?php echo CG_Constants::SPACING_SM; ?>px; flex-wrap: wrap; }
.<?php echo CG_CSS::BULK_ACTION; ?> { display: inline-flex !important; align-items: center; gap: <?php echo CG_Constants::SPACING_MD; ?>px; }
.<?php echo CG_CSS::BULK_ACTION; ?> .dashicons { font-size: <?php echo CG_Constants::ICON_SIZE_SMALL; ?>px; width: <?php echo CG_Constants::ICON_SIZE_SMALL; ?>px; height: <?php echo CG_Constants::ICON_SIZE_SMALL; ?>px; }
.<?php echo CG_CSS::BULK_DANGER; ?> { color: #d63638 !important; border-color: #d63638 !important; }
.<?php echo CG_CSS::BULK_DANGER; ?>:hover { background: #d63638 !important; color: #fff !important; }

.<?php echo CG_CSS::MODAL; ?> { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 100000; display: flex; align-items: center; justify-content: center; }
.<?php echo CG_CSS::MODAL_CONTENT; ?> { background: #fff; border-radius: <?php echo CG_Constants::BORDER_RADIUS_LG; ?>px; max-width: <?php echo CG_Constants::MODAL_WIDTH_MEDIUM; ?>; width: 90%; max-height: 80vh; overflow: auto; }
.<?php echo CG_CSS::MODAL_LARGE; ?> { max-width: <?php echo CG_Constants::MODAL_WIDTH_LARGE; ?>; }
.<?php echo CG_CSS::MODAL_HEADER; ?> { display: flex; justify-content: space-between; align-items: center; padding: <?php echo CG_Constants::SPACING_LG; ?>px <?php echo CG_Constants::SPACING_XL; ?>px; border-bottom: 1px solid #ddd; }
.<?php echo CG_CSS::MODAL_HEADER; ?> h2 { margin: 0; font-size: 18px; }
.<?php echo CG_CSS::MODAL_CLOSE; ?> { background: none; border: none; font-size: 24px; cursor: pointer; color: #666; }
.<?php echo CG_CSS::MODAL_BODY; ?> { padding: <?php echo CG_Constants::SPACING_XL; ?>px; }

.<?php echo CG_CSS::VIEW_SECTION; ?> { margin-bottom: <?php echo CG_Constants::SPACING_XL; ?>px; }
.<?php echo CG_CSS::VIEW_SECTION; ?> h4 { margin: 0 0 <?php echo CG_Constants::SPACING_SM; ?>px; color: #1d2327; font-size: 13px; }
.<?php echo CG_CSS::VIEW_SECTION; ?> pre { background: #f6f7f7; padding: <?php echo CG_Constants::SPACING_MD; ?>px; border-radius: <?php echo CG_Constants::BORDER_RADIUS_XS; ?>px; overflow-x: auto; font-size: 12px; margin: 0; }
.<?php echo CG_CSS::VIEW_SECTION; ?> code { background: #f0f0f1; padding: 2px <?php echo CG_Constants::SPACING_MD; ?>px; border-radius: <?php echo CG_Constants::BORDER_RADIUS_SM; ?>px; }

.<?php echo CG_CSS::FORM_ROW; ?> { margin-bottom: <?php echo CG_Constants::SPACING_LG; ?>px; }
.<?php echo CG_CSS::FORM_ROW; ?> label { display: block; font-weight: 600; margin-bottom: <?php echo CG_Constants::SPACING_MD; ?>px; }
.<?php echo CG_CSS::FORM_ROW; ?> select, .<?php echo CG_CSS::FORM_ROW; ?> textarea { width: 100%; }
.<?php echo CG_CSS::FORM_ROW; ?> textarea { font-family: monospace; font-size: 13px; padding: 10px; }

.<?php echo CG_CSS::INJECT_PREVIEW; ?> { background: #f8f9fa; border: 1px solid #ddd; padding: <?php echo CG_Constants::SPACING_MD; ?>px; border-radius: <?php echo CG_Constants::BORDER_RADIUS_XS; ?>px; max-height: 150px; overflow-y: auto; font-family: monospace; font-size: 12px; white-space: pre-wrap; }
.<?php echo CG_CSS::INJECT_ACTIONS; ?> { display: flex; gap: 10px; justify-content: flex-end; padding-top: <?php echo CG_Constants::SPACING_LG; ?>px; border-top: 1px solid #ddd; margin-top: <?php echo CG_Constants::SPACING_XL; ?>px; }
</style>
