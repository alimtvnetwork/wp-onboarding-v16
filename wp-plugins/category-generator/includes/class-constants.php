<?php
/**
 * Constants Class for Category Generator
 * 
 * Centralizes all hard-coded values, limits, formats, and configuration.
 * 
 * @package Category_Generator_Area
 * @author MD Alim Ul Karim <contact@riseup-asia.com>
 * @copyright 2024 Riseup Asia LLC
 */

if (!defined('ABSPATH')) {
    exit;
}

class CG_Constants {
    
    // ==================== NUMERIC LIMITS ====================
    
    /** Default pagination items per page */
    const PAGINATION_DEFAULT = 50;
    
    /** Pagination option: medium */
    const PAGINATION_MEDIUM = 100;
    
    /** Maximum recent snapshots to show in quick restore */
    const RECENT_SNAPSHOTS_LIMIT = 10;
    
    /** Default snapshot storage limit */
    const SNAPSHOT_LIMIT_DEFAULT = 20;
    
    /** Snapshot limit constraints */
    const SNAPSHOT_LIMIT_MIN = 5;
    const SNAPSHOT_LIMIT_MAX = 100;
    const SNAPSHOT_LIMIT_STEP = 5;
    
    /** Meta description minimum character count */
    const META_DESC_MIN_CHARS = 135;
    
    /** Number of meta title variations */
    const META_TITLE_VARIATIONS = 6;
    
    /** Number of meta description variations */
    const META_DESC_VARIATIONS = 12;
    
    /** Filesize threshold for KB display (1024 bytes) */
    const FILESIZE_KB_THRESHOLD = 1024;
    
    /** Filesize threshold for MB display (1024 * 1024 bytes) */
    const FILESIZE_MB_THRESHOLD = 1048576;
    
    /** Animation fade duration in milliseconds */
    const ANIMATION_FADE_DURATION = 300;
    
    /** Notice display duration in milliseconds */
    const NOTICE_DURATION = 3000;
    
    /** Maximum characters to show in truncated text */
    const TRUNCATE_SHORT = 30;
    const TRUNCATE_MEDIUM = 40;
    const TRUNCATE_LONG = 100;
    
    /** Input field widths */
    const INPUT_WIDTH_SMALL = '200px';
    const INPUT_WIDTH_MEDIUM = '300px';
    const INPUT_WIDTH_LARGE = '400px';
    
    /** Modal max widths */
    const MODAL_WIDTH_SMALL = '400px';
    const MODAL_WIDTH_DEFAULT = '500px';
    const MODAL_WIDTH_MEDIUM = '600px';
    const MODAL_WIDTH_LARGE = '900px';
    
    /** Icon sizes */
    const ICON_SIZE_SMALL = 16;
    const ICON_SIZE_MEDIUM = 18;
    const ICON_SIZE_LARGE = 24;
    const ICON_SIZE_XLARGE = 32;
    const ICON_SIZE_EMPTY_STATE = 48;
    
    /** Spacing values */
    const SPACING_XS = 4;
    const SPACING_SMALL = 8;
    const SPACING_SM = 8;
    const SPACING_MEDIUM = 15;
    const SPACING_MD = 12;
    const SPACING_LARGE = 20;
    const SPACING_LG = 16;
    const SPACING_XLARGE = 30;
    const SPACING_XL = 20;
    const SPACING_XXL = 24;
    const SPACING_SECTION = 40;
    
    /** Border radius values */
    const BORDER_RADIUS_XS = 2;
    const BORDER_RADIUS_SM = 3;
    const BORDER_RADIUS_MD = 6;
    const BORDER_RADIUS_LG = 8;
    const BORDER_RADIUS_ROUND = 12;
    
    /** Yoast SEO score thresholds */
    const YOAST_SCORE_GOOD = 70;
    const YOAST_SCORE_OK = 40;
    
    /** Z-index values */
    const Z_INDEX_MODAL = 100000;
    const Z_INDEX_NOTICE = 9999;
    
    /** Breakpoints */
    const BREAKPOINT_TABLET = 1200;
    const BREAKPOINT_MOBILE = 768;
    
    /** Rating values */
    const RATING_MIN = 1;
    const RATING_MAX = 5;
    const RATING_STEP = 0.1;
    
    // ==================== DATE FORMATS ====================
    
    /** Sortable date format for filenames */
    const DATE_FORMAT_SORTABLE = 'Y-m-d_His';
    
    /** Snapshot display date format */
    const DATE_FORMAT_SNAPSHOT = 'M j, H:i';
    
    /** Timestamp date format */
    const DATE_FORMAT_TIMESTAMP = 'Y-m-d H:i:s';
    
    // ==================== TABLE COLUMNS ====================
    
    /** History table column count (without Yoast) */
    const HISTORY_COLUMNS_DEFAULT = 12;
    
    /** History table column count (with Yoast) */
    const HISTORY_COLUMNS_WITH_YOAST = 13;
    
    // ==================== SNAPSHOT TYPES ====================
    
    const SNAPSHOT_TYPE_MANUAL = 'manual';
    const SNAPSHOT_TYPE_AUTO = 'auto';
    
    // ==================== AJAX ACTIONS ====================
    
    const AJAX_CREATE_SNAPSHOT = 'cg_create_snapshot';
    const AJAX_RESTORE_SNAPSHOT = 'cg_restore_snapshot';
    const AJAX_DELETE_SNAPSHOT = 'cg_delete_snapshot';
    const AJAX_DOWNLOAD_SNAPSHOT = 'cg_download_snapshot';
    const AJAX_GET_RECENT_SNAPSHOTS = 'cg_get_recent_snapshots';
    const AJAX_GET_CATEGORY_HISTORY = 'cg_get_category_history';
    const AJAX_BULK_DELETE_HISTORY = 'cg_bulk_delete_history';
    const AJAX_SAVE_SETTINGS = 'cg_save_settings';
    const AJAX_ADD_REMOTE_API = 'cg_add_remote_api';
    const AJAX_DELETE_REMOTE_API = 'cg_delete_remote_api';
    const AJAX_IMPORT_REMOTE = 'cg_import_from_remote';
    const AJAX_EXPORT_DATA = 'cg_export_data';
    const AJAX_DOWNLOAD_DATABASE = 'cg_download_database';
    const AJAX_RESTORE_DATABASE = 'cg_restore_database';
    const AJAX_RESET_DATABASE = 'cg_reset_database';
    const AJAX_SAVE_BUSINESS_PROFILE = 'cg_save_business_profile';
    const AJAX_SAVE_TEMPLATE = 'cg_save_template';
    const AJAX_GET_TEMPLATE = 'cg_get_template';
    const AJAX_DELETE_TEMPLATE = 'cg_delete_template';
    const AJAX_SAVE_TEMPLATE_CATEGORY = 'cg_save_template_category';
    const AJAX_DELETE_TEMPLATE_CATEGORY = 'cg_delete_template_category';
    const AJAX_RUN_TESTS = 'cg_run_tests';
    const AJAX_GET_SAVED_TITLES = 'cg_get_saved_titles';
    const AJAX_GET_SAVED_AREAS = 'cg_get_saved_areas';
    const AJAX_SAVE_TITLES = 'cg_save_titles';
    const AJAX_SAVE_AREAS = 'cg_save_areas';
    
    // ==================== SETTING KEYS ====================
    
    const SETTING_SNAPSHOT_LIMIT = 'snapshot_limit';
    const SETTING_AUTO_SNAPSHOT = 'auto_snapshot_before_generate';
    
    // ==================== PLACEHOLDERS ====================
    
    const PLACEHOLDER_TITLE = '{title}';
    const PLACEHOLDER_AREA = '{area}';
    const PLACEHOLDER_CATEGORY = '{category}';
    const PLACEHOLDER_SLUG = '{slug}';
    const PLACEHOLDER_URL = '{url}';
    const PLACEHOLDER_BUSINESS_NAME = '{business_name}';
    const PLACEHOLDER_META_TITLE = '{meta_title}';
    const PLACEHOLDER_META_DESC = '{meta_description}';
    const PLACEHOLDER_INNER = '{inner:}';
    
    // ==================== DEFAULT VALUES ====================
    
    const DEFAULT_TAXONOMY = 'category';
    const DEFAULT_FORMAT = '{title} {area}';
    const DEFAULT_COUNTRY = 'Australia';
    const DEFAULT_RATING = 5.0;
    const DEFAULT_RATING_VALUE = '5.0';
    const DEFAULT_RATING_COUNT = 100;
    const DEFAULT_SCHEMA_TYPE = 'LocalBusiness';
    const DEFAULT_AI_PROVIDER = 'openai';
    const DEFAULT_WRAPPER_CLASS = 'riseup-category-generator';
    const DEFAULT_HEADER_CLASS = 'category-header';
    const DEFAULT_PARAGRAPH_CLASS = 'seo-container-para';
    const DEFAULT_SCHEMA_WRAPPER_CLASS = 'category-schema-wrapper';
    const DEFAULT_FOCUS_KEYWORD_PATTERN = '{title} {area}';
    const DEFAULT_BUSINESS_TYPE = 'LocalBusiness';
    const DEFAULT_PRICE_RANGE = '$$';
    
    /** Reset confirmation text */
    const RESET_CONFIRM_TEXT = 'RESET';
    
    // ==================== HTML MARKERS ====================
    
    const HTML_WRAPPER_CLASS = 'riseup-category-generator';
    
    /**
     * Get business types for Schema.org
     */
    public static function get_business_types() {
        return [
            'LocalBusiness' => __('Local Business', 'category-generator'),
            'ProfessionalService' => __('Professional Service', 'category-generator'),
            'HomeAndConstructionBusiness' => __('Home & Construction', 'category-generator'),
            'CleaningService' => __('Cleaning Service', 'category-generator'),
            'Plumber' => __('Plumber', 'category-generator'),
            'Electrician' => __('Electrician', 'category-generator'),
            'RealEstateAgent' => __('Real Estate Agent', 'category-generator'),
            'FinancialService' => __('Financial Service', 'category-generator'),
            'HealthAndBeautyBusiness' => __('Health & Beauty', 'category-generator'),
            'LegalService' => __('Legal Service', 'category-generator'),
            'Restaurant' => __('Restaurant', 'category-generator'),
            'Store' => __('Store', 'category-generator'),
        ];
    }
    
    /**
     * Get price range options
     */
    public static function get_price_ranges() {
        return [
            '$' => __('$ - Budget', 'category-generator'),
            '$$' => __('$$ - Moderate', 'category-generator'),
            '$$$' => __('$$$ - Premium', 'category-generator'),
            '$$$$' => __('$$$$ - Luxury', 'category-generator'),
        ];
    }
    
    /**
     * Get pagination options for select dropdown
     */
    public static function get_pagination_options() {
        return [
            self::PAGINATION_DEFAULT,
            self::PAGINATION_MEDIUM,
            'all'
        ];
    }
    
    /**
     * Get all available placeholders
     */
    public static function get_placeholders() {
        return [
            self::PLACEHOLDER_TITLE,
            self::PLACEHOLDER_AREA,
            self::PLACEHOLDER_CATEGORY,
            self::PLACEHOLDER_SLUG,
            self::PLACEHOLDER_URL,
            self::PLACEHOLDER_BUSINESS_NAME,
            self::PLACEHOLDER_META_TITLE,
            self::PLACEHOLDER_META_DESC,
            self::PLACEHOLDER_INNER,
        ];
    }
    
    /**
     * Format filesize for display
     */
    public static function format_filesize($bytes) {
        if ($bytes >= self::FILESIZE_MB_THRESHOLD) {
            return number_format($bytes / self::FILESIZE_MB_THRESHOLD, 2) . ' MB';
        } elseif ($bytes >= self::FILESIZE_KB_THRESHOLD) {
            return number_format($bytes / self::FILESIZE_KB_THRESHOLD, 2) . ' KB';
        }
        return $bytes . ' B';
    }
    
    /**
     * Get Yoast score class based on score value
     */
    public static function get_yoast_score_class($score) {
        if ($score >= self::YOAST_SCORE_GOOD) {
            return 'cg-yoast-good';
        } elseif ($score >= self::YOAST_SCORE_OK) {
            return 'cg-yoast-ok';
        } elseif ($score > 0) {
            return 'cg-yoast-bad';
        }
        return 'cg-yoast-na';
    }
    
    /**
     * Get Yoast score title based on score value
     */
    public static function get_yoast_score_title($score) {
        if ($score >= self::YOAST_SCORE_GOOD) {
            return sprintf(__('Good (%d)', 'category-generator'), $score);
        } elseif ($score >= self::YOAST_SCORE_OK) {
            return sprintf(__('Needs improvement (%d)', 'category-generator'), $score);
        } elseif ($score > 0) {
            return sprintf(__('Poor (%d)', 'category-generator'), $score);
        }
        return __('Not analyzed', 'category-generator');
    }
    
    /**
     * Output constants as JavaScript object for client-side use
     */
    public static function get_js_constants() {
        return [
            'pagination' => [
                'default' => self::PAGINATION_DEFAULT,
                'medium' => self::PAGINATION_MEDIUM,
            ],
            'limits' => [
                'recentSnapshots' => self::RECENT_SNAPSHOTS_LIMIT,
                'snapshotDefault' => self::SNAPSHOT_LIMIT_DEFAULT,
                'metaDescMinChars' => self::META_DESC_MIN_CHARS,
                'metaTitleVariations' => self::META_TITLE_VARIATIONS,
                'metaDescVariations' => self::META_DESC_VARIATIONS,
            ],
            'truncate' => [
                'short' => self::TRUNCATE_SHORT,
                'medium' => self::TRUNCATE_MEDIUM,
                'long' => self::TRUNCATE_LONG,
            ],
            'animation' => [
                'fadeDuration' => self::ANIMATION_FADE_DURATION,
            ],
            'columns' => [
                'historyDefault' => self::HISTORY_COLUMNS_DEFAULT,
                'historyWithYoast' => self::HISTORY_COLUMNS_WITH_YOAST,
            ],
            'yoastScore' => [
                'good' => self::YOAST_SCORE_GOOD,
                'ok' => self::YOAST_SCORE_OK,
            ],
            'dateFormats' => [
                'sortable' => self::DATE_FORMAT_SORTABLE,
                'snapshot' => self::DATE_FORMAT_SNAPSHOT,
            ],
        ];
    }
}
