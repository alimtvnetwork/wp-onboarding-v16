<?php
/**
 * Snapshot Table Row Partial
 * 
 * Renders a single snapshot row for manual or auto snapshots table.
 * 
 * @package Category_Generator_Area
 * @var array $snapshot The snapshot data
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<tr data-id="<?php echo esc_attr($snapshot['id']); ?>">
    <td class="<?php echo CG_CSS::COLUMN_TITLE; ?>">
        <strong><?php echo esc_html($snapshot['title']); ?></strong>
        <div class="row-actions">
            <span class="filename"><?php echo esc_html($snapshot['filename']); ?></span>
        </div>
    </td>
    <td class="<?php echo CG_CSS::COLUMN_NOTES; ?>">
        <?php echo esc_html($snapshot['notes'] ?: '—'); ?>
    </td>
    <td class="<?php echo CG_CSS::COLUMN_COUNTS; ?>">
        <span title="<?php esc_attr_e('Terms', 'category-generator'); ?>"><?php echo intval($snapshot['terms_count']); ?></span>
    </td>
    <td class="<?php echo CG_CSS::COLUMN_SIZE; ?>">
        <?php echo CG_Constants::format_filesize($snapshot['filesize']); ?>
    </td>
    <td class="<?php echo CG_CSS::COLUMN_DATE; ?>">
        <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($snapshot['created_at'])); ?>
    </td>
    <td class="<?php echo CG_CSS::COLUMN_ACTIONS; ?>">
        <button type="button" class="button <?php echo CG_CSS::RESTORE_SNAPSHOT; ?>" data-id="<?php echo esc_attr($snapshot['id']); ?>" title="<?php esc_attr_e('Restore', 'category-generator'); ?>">
            <span class="dashicons dashicons-undo"></span>
        </button>
        <button type="button" class="button <?php echo CG_CSS::DOWNLOAD_SNAPSHOT; ?>" data-id="<?php echo esc_attr($snapshot['id']); ?>" title="<?php esc_attr_e('Download', 'category-generator'); ?>">
            <span class="dashicons dashicons-download"></span>
        </button>
        <button type="button" class="button <?php echo CG_CSS::DELETE_SNAPSHOT; ?>" data-id="<?php echo esc_attr($snapshot['id']); ?>" title="<?php esc_attr_e('Delete', 'category-generator'); ?>">
            <span class="dashicons dashicons-trash"></span>
        </button>
    </td>
</tr>
