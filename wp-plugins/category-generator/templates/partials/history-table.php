<?php
/**
 * History Table Partial
 * 
 * Main history data table with pagination.
 * 
 * @package Category_Generator_Area
 * @var bool $has_yoast Whether Yoast SEO is installed
 */

if (!defined('ABSPATH')) {
    exit;
}

$has_yoast = defined('WPSEO_VERSION');
$col_span = $has_yoast ? CG_Constants::HISTORY_COLUMNS_WITH_YOAST : CG_Constants::HISTORY_COLUMNS_DEFAULT;
?>
<table class="wp-list-table widefat fixed striped" id="<?php echo CG_CSS::ID_HISTORY_TABLE; ?>">
    <thead>
        <tr>
            <th class="<?php echo CG_CSS::COLUMN_CB; ?>" style="width: 40px;"><input type="checkbox" id="<?php echo CG_CSS::ID_SELECT_ALL; ?>"></th>
            <th class="<?php echo CG_CSS::COLUMN_ID; ?>" style="width: 50px;"><?php _e('ID', 'category-generator'); ?></th>
            <th class="<?php echo CG_CSS::COLUMN_NAME; ?>"><?php _e('Category Name', 'category-generator'); ?></th>
            <th class="<?php echo CG_CSS::COLUMN_SLUG; ?>"><?php _e('Slug', 'category-generator'); ?></th>
            <th class="<?php echo CG_CSS::COLUMN_TITLE; ?>"><?php _e('Title', 'category-generator'); ?></th>
            <th class="<?php echo CG_CSS::COLUMN_AREA; ?>"><?php _e('Area', 'category-generator'); ?></th>
            <th class="<?php echo CG_CSS::COLUMN_TAXONOMY; ?>" style="width: 80px;"><?php _e('Taxonomy', 'category-generator'); ?></th>
            <th class="<?php echo CG_CSS::COLUMN_META_TITLE; ?>"><?php _e('Meta Title', 'category-generator'); ?></th>
            <th class="<?php echo CG_CSS::COLUMN_META_DESC; ?>"><?php _e('Meta Desc', 'category-generator'); ?></th>
            <th class="<?php echo CG_CSS::COLUMN_SCHEMA; ?>" style="width: 60px;"><?php _e('Schema', 'category-generator'); ?></th>
            <?php if ($has_yoast): ?>
            <th class="<?php echo CG_CSS::COLUMN_YOAST; ?>" style="width: 60px;"><?php _e('SEO', 'category-generator'); ?></th>
            <?php endif; ?>
            <th class="<?php echo CG_CSS::COLUMN_DATE; ?>" style="width: 130px;"><?php _e('Created', 'category-generator'); ?></th>
            <th class="<?php echo CG_CSS::COLUMN_ACTIONS; ?>" style="width: 160px;"><?php _e('Actions', 'category-generator'); ?></th>
        </tr>
    </thead>
    <tbody id="<?php echo CG_CSS::ID_HISTORY_BODY; ?>">
        <tr class="<?php echo CG_CSS::LOADING_ROW; ?>">
            <td colspan="<?php echo $col_span; ?>" style="text-align: center; padding: <?php echo CG_Constants::SPACING_SECTION; ?>px;">
                <span class="spinner is-active" style="float: none;"></span>
                <?php _e('Loading history...', 'category-generator'); ?>
            </td>
        </tr>
    </tbody>
</table>

<div class="<?php echo CG_CSS::PAGINATION_WRAPPER; ?>">
    <div class="<?php echo CG_CSS::PER_PAGE_SELECTOR; ?>">
        <label for="<?php echo CG_CSS::ID_PER_PAGE; ?>"><?php _e('Show:', 'category-generator'); ?></label>
        <select id="<?php echo CG_CSS::ID_PER_PAGE; ?>">
            <option value="<?php echo CG_Constants::PAGINATION_DEFAULT; ?>"><?php echo CG_Constants::PAGINATION_DEFAULT; ?></option>
            <option value="<?php echo CG_Constants::PAGINATION_MEDIUM; ?>"><?php echo CG_Constants::PAGINATION_MEDIUM; ?></option>
            <option value="all"><?php _e('All', 'category-generator'); ?></option>
        </select>
    </div>
    <div class="<?php echo CG_CSS::PAGINATION; ?>" id="<?php echo CG_CSS::ID_HISTORY_PAGINATION; ?>"></div>
</div>
