<?php
/**
 * CSS Class Names for Category Generator
 * 
 * Centralizes all CSS class names and IDs for consistency.
 * Also provides JavaScript export for client-side use.
 * 
 * @package Category_Generator_Area
 * @author MD Alim Ul Karim <contact@riseup-asia.com>
 * @copyright 2024 Riseup Asia LLC
 */

if (!defined('ABSPATH')) {
    exit;
}

class CG_CSS {
    
    // ==================== LAYOUT CLASSES ====================
    
    const ADMIN_WRAP = 'cg-admin-wrap';
    const CONTAINER = 'cg-container';
    const MAIN = 'cg-main';
    const SIDEBAR = 'cg-sidebar';
    const CARD = 'cg-card';
    const LAYOUT_CARD = 'cg-card';
    const LAYOUT_TABS = 'cg-tabs';
    const LAYOUT_TAB = 'cg-tab';
    const LAYOUT_TAB_CONTENT = 'cg-tab-content';
    
    // ==================== TYPOGRAPHY CLASSES ====================
    
    const TITLE = 'cg-title';
    const TEXT_TITLE = 'cg-title';
    const VERSION = 'cg-version';
    const DESCRIPTION = 'cg-description';
    const TEXT_DESCRIPTION = 'cg-description';
    const HINT = 'cg-hint';
    const TEXT_HINT = 'cg-hint';
    const HINT_INLINE = 'cg-hint-inline';
    const TEXT_EMPTY = 'cg-empty-message';
    
    // ==================== FORM CLASSES ====================
    
    const FORM_ROW = 'cg-form-row';
    const FORM_GROUP = 'cg-form-group';
    const FORM_GROUP_INLINE = 'cg-form-group-inline';
    const INPUT_GROUP = 'cg-input-group';
    const INPUT_GRID = 'cg-input-grid';
    const OPTIONS_GRID = 'cg-options-grid';
    const CHECKBOX_LABEL = 'cg-checkbox-label';
    const COUNT = 'cg-count';
    const CHAR_COUNT = 'cg-char-count';
    
    // ==================== TEMPLATE CLASSES ====================
    
    const TEMPLATE_SELECTOR = 'cg-template-selector';
    const TEMPLATE_HELPERS = 'cg-template-helpers';
    const SAMPLE_SECTION = 'cg-sample-section';
    const INLINE_SELECTOR = 'cg-inline-selector';
    
    // ==================== BUTTON CLASSES ====================
    
    const SAVE_BTN = 'cg-save-btn';
    const HIDDEN = 'cg-hidden';
    const SEARCH_BTN = 'cg-search-btn';
    const BULK_ACTION = 'cg-bulk-action';
    const BULK_DANGER = 'cg-bulk-danger';
    const BTN_DEFAULT = 'button';
    const BTN_PRIMARY = 'button button-primary';
    const BTN_DANGER = 'button cg-danger-btn';
    
    // ==================== TABLE CLASSES ====================
    
    const LOADING_ROW = 'cg-loading-row';
    const EMPTY_ROW = 'cg-empty-row';
    const EMPTY_STATE = 'cg-empty-state';
    const SNAPSHOTS_TABLE = 'cg-snapshots-table';
    const ROW_CHECKBOX = 'cg-row-checkbox';
    
    // ==================== TABLE COLUMN CLASSES ====================
    
    const COLUMN_CB = 'column-cb';
    const COLUMN_ID = 'column-id';
    const COLUMN_NAME = 'column-name';
    const COLUMN_SLUG = 'column-slug';
    const COLUMN_TITLE = 'column-title';
    const COLUMN_AREA = 'column-area';
    const COLUMN_TAXONOMY = 'column-taxonomy';
    const COLUMN_META_TITLE = 'column-meta-title';
    const COLUMN_META_DESC = 'column-meta-desc';
    const COLUMN_SCHEMA = 'column-schema';
    const COLUMN_YOAST = 'column-yoast';
    const COLUMN_DATE = 'column-date';
    const COLUMN_ACTIONS = 'column-actions';
    const COLUMN_NOTES = 'column-notes';
    const COLUMN_COUNTS = 'column-counts';
    const COLUMN_SIZE = 'column-size';
    
    // ==================== BADGE CLASSES ====================
    
    const BADGE = 'cg-badge';
    const BADGE_YES = 'cg-badge-yes';
    const BADGE_NO = 'cg-badge-no';
    const COUNT_BADGE = 'cg-count-badge';
    const STATUS_BADGE = 'cg-status-badge';
    const STATUS_ENABLED = 'cg-status-enabled';
    const STATUS_DISABLED = 'cg-status-disabled';
    
    // ==================== YOAST SCORE CLASSES ====================
    
    const YOAST_SCORE = 'cg-yoast-score';
    const YOAST_GOOD = 'cg-yoast-good';
    const YOAST_OK = 'cg-yoast-ok';
    const YOAST_BAD = 'cg-yoast-bad';
    const YOAST_NA = 'cg-yoast-na';
    
    // ==================== MODAL CLASSES ====================
    
    const MODAL = 'cg-modal';
    const MODAL_CONTENT = 'cg-modal-content';
    const MODAL_LARGE = 'cg-modal-large';
    const MODAL_HEADER = 'cg-modal-header';
    const MODAL_BODY = 'cg-modal-body';
    const MODAL_FOOTER = 'cg-modal-footer';
    const MODAL_CLOSE = 'cg-modal-close';
    
    // ==================== HISTORY CLASSES ====================
    
    const HISTORY_HEADER = 'cg-history-header';
    const HISTORY_ACTIONS = 'cg-history-actions';
    const HISTORY_STATS = 'cg-history-stats';
    const SEARCH_BOX = 'cg-search-box';
    const BULK_ACTIONS_BAR = 'cg-bulk-actions-bar';
    const BULK_SELECTED = 'cg-bulk-selected';
    const BULK_BUTTONS = 'cg-bulk-buttons';
    
    // ==================== PAGINATION CLASSES ====================
    
    const PAGINATION = 'cg-pagination';
    const PAGINATION_WRAPPER = 'cg-pagination-wrapper';
    const PER_PAGE_SELECTOR = 'cg-per-page-selector';
    const CURRENT = 'current';
    
    // ==================== ACTION LINK CLASSES ====================
    
    const ACTION_LINK = 'cg-action-link';
    const VIEW_HISTORY = 'cg-view-history';
    const INJECT_LINK = 'cg-inject-link';
    const INJECT_HISTORY = 'cg-inject-history';
    
    // ==================== SNAPSHOT CLASSES ====================
    
    const SNAPSHOTS_INTRO = 'cg-snapshots-intro';
    const CREATE_SNAPSHOT_FORM = 'cg-create-snapshot-form';
    const SNAPSHOT_TOOLBAR = 'cg-snapshot-toolbar';
    const SNAPSHOT_TOOLBAR_LEFT = 'cg-snapshot-toolbar-left';
    const SNAPSHOT_TOOLBAR_RIGHT = 'cg-snapshot-toolbar-right';
    const AUTO_SNAPSHOT_TOGGLE = 'cg-auto-snapshot-toggle';
    const RESTORE_SNAPSHOT = 'cg-restore-snapshot';
    const DOWNLOAD_SNAPSHOT = 'cg-download-snapshot';
    const DELETE_SNAPSHOT = 'cg-delete-snapshot';
    
    // ==================== VIEW/INJECT CLASSES ====================
    
    const VIEW_SECTION = 'cg-view-section';
    const INJECT_PREVIEW = 'cg-inject-preview';
    const INJECT_ACTIONS = 'cg-inject-actions';
    
    // ==================== NOTICE CLASSES ====================
    
    const NOTICE = 'cg-notice';
    const NOTICE_INFO = 'cg-notice-info';
    const NOTICE_SUCCESS = 'cg-notice cg-notice-success';
    const NOTICE_WARNING = 'cg-notice cg-notice-warning';
    
    // ==================== STATS CLASSES ====================
    
    const STATS_GRID = 'cg-stats-grid';
    const STAT = 'cg-stat';
    const STAT_VALUE = 'cg-stat-value';
    const STAT_LABEL = 'cg-stat-label';
    const STAT_NEW = 'cg-stat-new';
    const STAT_EXISTS = 'cg-stat-exists';
    
    // ==================== PREVIEW CLASSES ====================
    
    const PREVIEW_LIST = 'cg-preview-list';
    const PREVIEW_EMPTY = 'cg-preview-empty';
    const PREVIEW_ITEM = 'cg-preview-item';
    const PREVIEW_SUMMARY = 'cg-preview-summary';
    const PREVIEW_STAT = 'cg-preview-stat';
    const PREVIEW_STATUS = 'cg-preview-status';
    const PREVIEW_NAME = 'cg-preview-name';
    const PREVIEW_BADGE = 'cg-preview-badge';
    const STATUS_NEW = 'cg-status-new';
    const STATUS_EXISTS = 'cg-status-exists';
    
    // ==================== RESULTS CLASSES ====================
    
    const RESULTS_CARD = 'cg-results-card';
    const RESULT_SUCCESS = 'cg-result-success';
    const RESULT_ERRORS = 'cg-result-errors';
    
    // ==================== META VARIATION CLASSES ====================
    
    const META_FIELDS = 'cg-meta-fields';
    const FIELD_GROUP = 'cg-field-group';
    const META_VARIATIONS_SCROLL = 'cg-meta-variations-scroll';
    const META_DESC_SCROLL = 'cg-meta-desc-scroll';
    const META_VARIATION = 'cg-meta-variation';
    const META_DESC_VARIATION = 'cg-meta-desc-variation';
    const VARIATION_NUM = 'cg-variation-num';
    const META_TITLE_FIELD = 'cg-meta-title-field';
    const META_DESC_FIELD = 'cg-meta-desc-field';
    
    // ==================== SETTINGS CLASSES ====================
    
    const SETTINGS_GRID = 'cg-settings-grid';
    const SETTING_GROUP = 'cg-setting-group';
    const ACTIONS = 'cg-actions';
    
    // ==================== STEP NUMBER CLASS ====================
    
    const STEP_NUMBER = 'cg-step-number';
    
    // ==================== LOADING CLASSES ====================
    
    const LOADING = 'cg-loading';
    const LOADING_INNER = 'cg-loading-inner';
    
    // ==================== ELEMENT IDS ====================
    
    const ID_HISTORY_TABLE = 'cg-history-table';
    const ID_HISTORY_BODY = 'cg-history-body';
    const ID_HISTORY_PAGINATION = 'cg-history-pagination';
    const ID_HISTORY_SEARCH = 'cg-history-search';
    const ID_HISTORY_TOTAL = 'cg-history-total';
    const ID_SELECT_ALL = 'cg-select-all';
    const ID_SELECTED_COUNT = 'cg-selected-count';
    const ID_BULK_ACTIONS_BAR = 'cg-bulk-actions-bar';
    const ID_BULK_CANCEL = 'cg-bulk-cancel';
    const ID_PER_PAGE = 'cg-per-page';
    
    // ==================== SNAPSHOT IDS ====================
    
    const ID_SNAPSHOT_TITLE = 'cg-snapshot-title';
    const ID_SNAPSHOT_NOTES = 'cg-snapshot-notes';
    const ID_CREATE_SNAPSHOT_BTN = 'cg-create-snapshot-btn';
    const ID_MANUAL_SNAPSHOTS_LIST = 'cg-manual-snapshots-list';
    const ID_AUTO_SNAPSHOTS_LIST = 'cg-auto-snapshots-list';
    const ID_RESTORE_SNAPSHOT_MODAL = 'cg-restore-snapshot-modal';
    const ID_RESTORE_SNAPSHOT_TITLE = 'cg-restore-snapshot-title';
    const ID_SNAPSHOT_BEFORE_RESTORE = 'cg-snapshot-before-restore';
    const ID_CONFIRM_RESTORE_BTN = 'cg-confirm-restore-snapshot-btn';
    
    // ==================== QUICK SNAPSHOT IDS ====================
    
    const ID_QUICK_SNAPSHOT_NAME = 'cg-quick-snapshot-name';
    const ID_QUICK_SNAPSHOT_BTN = 'cg-quick-snapshot-btn';
    const ID_AUTO_SNAPSHOT_TOGGLE = 'cg-auto-snapshot-toggle';
    const ID_QUICK_RESTORE_SELECT = 'cg-quick-restore-select';
    
    // ==================== SETTINGS IDS ====================
    
    const ID_SETTINGS_RESET_MODAL = 'cg-reset-modal';
    const ID_SETTINGS_RESTORE_MODAL = 'cg-restore-modal';
    
    // ==================== TEMPLATES IDS ====================
    
    const ID_TEMPLATE_MODAL = 'cg-template-modal';
    const ID_CATEGORY_MODAL = 'cg-category-modal';
    
    /**
     * Get all CSS classes as JavaScript object
     */
    public static function get_js_classes() {
        return array(
            'layout' => array(
                'adminWrap' => self::ADMIN_WRAP,
                'container' => self::CONTAINER,
                'main' => self::MAIN,
                'sidebar' => self::SIDEBAR,
                'card' => self::CARD,
            ),
            'table' => array(
                'loadingRow' => self::LOADING_ROW,
                'emptyRow' => self::EMPTY_ROW,
                'emptyState' => self::EMPTY_STATE,
                'rowCheckbox' => self::ROW_CHECKBOX,
            ),
            'badge' => array(
                'base' => self::BADGE,
                'yes' => self::BADGE_YES,
                'no' => self::BADGE_NO,
            ),
            'yoast' => array(
                'score' => self::YOAST_SCORE,
                'good' => self::YOAST_GOOD,
                'ok' => self::YOAST_OK,
                'bad' => self::YOAST_BAD,
                'na' => self::YOAST_NA,
            ),
            'modal' => array(
                'base' => self::MODAL,
                'content' => self::MODAL_CONTENT,
                'large' => self::MODAL_LARGE,
                'header' => self::MODAL_HEADER,
                'body' => self::MODAL_BODY,
                'footer' => self::MODAL_FOOTER,
                'close' => self::MODAL_CLOSE,
            ),
            'pagination' => array(
                'base' => self::PAGINATION,
                'wrapper' => self::PAGINATION_WRAPPER,
                'perPage' => self::PER_PAGE_SELECTOR,
                'current' => self::CURRENT,
            ),
            'bulk' => array(
                'bar' => self::BULK_ACTIONS_BAR,
                'selected' => self::BULK_SELECTED,
                'buttons' => self::BULK_BUTTONS,
                'action' => self::BULK_ACTION,
                'danger' => self::BULK_DANGER,
            ),
            'action' => array(
                'link' => self::ACTION_LINK,
                'viewHistory' => self::VIEW_HISTORY,
                'injectLink' => self::INJECT_LINK,
            ),
            'snapshot' => array(
                'restore' => self::RESTORE_SNAPSHOT,
                'download' => self::DOWNLOAD_SNAPSHOT,
                'delete' => self::DELETE_SNAPSHOT,
            ),
        );
    }
    
    /**
     * Get all element IDs as JavaScript object
     */
    public static function get_js_ids() {
        return array(
            'history' => array(
                'table' => self::ID_HISTORY_TABLE,
                'body' => self::ID_HISTORY_BODY,
                'pagination' => self::ID_HISTORY_PAGINATION,
                'search' => self::ID_HISTORY_SEARCH,
                'total' => self::ID_HISTORY_TOTAL,
                'selectAll' => self::ID_SELECT_ALL,
                'selectedCount' => self::ID_SELECTED_COUNT,
                'bulkActionsBar' => self::ID_BULK_ACTIONS_BAR,
                'bulkCancel' => self::ID_BULK_CANCEL,
                'perPage' => self::ID_PER_PAGE,
            ),
            'snapshot' => array(
                'title' => self::ID_SNAPSHOT_TITLE,
                'notes' => self::ID_SNAPSHOT_NOTES,
                'createBtn' => self::ID_CREATE_SNAPSHOT_BTN,
                'manualList' => self::ID_MANUAL_SNAPSHOTS_LIST,
                'autoList' => self::ID_AUTO_SNAPSHOTS_LIST,
                'restoreModal' => self::ID_RESTORE_SNAPSHOT_MODAL,
                'restoreTitle' => self::ID_RESTORE_SNAPSHOT_TITLE,
                'beforeRestore' => self::ID_SNAPSHOT_BEFORE_RESTORE,
                'confirmRestoreBtn' => self::ID_CONFIRM_RESTORE_BTN,
            ),
            'quickSnapshot' => array(
                'name' => self::ID_QUICK_SNAPSHOT_NAME,
                'btn' => self::ID_QUICK_SNAPSHOT_BTN,
                'autoToggle' => self::ID_AUTO_SNAPSHOT_TOGGLE,
                'restoreSelect' => self::ID_QUICK_RESTORE_SELECT,
            ),
        );
    }
}
