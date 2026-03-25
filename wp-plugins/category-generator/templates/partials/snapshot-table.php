<?php
/**
 * Snapshot Table Partial
 * 
 * Renders a table of snapshots (manual or auto).
 * 
 * @package Category_Generator_Area
 * @var array $snapshots Array of snapshot data
 * @var string $type 'manual' or 'auto'
 * @var string $list_id ID for the tbody element
 * @var string $empty_message Message to show when empty
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<?php if (empty($snapshots)): ?>
    <div class="<?php echo CG_CSS::EMPTY_STATE; ?>">
        <span class="dashicons dashicons-<?php echo $type === CG_Constants::SNAPSHOT_TYPE_AUTO ? 'update' : 'backup'; ?>"></span>
        <p><?php echo esc_html($empty_message); ?></p>
    </div>
<?php else: ?>
    <table class="wp-list-table widefat fixed striped <?php echo CG_CSS::SNAPSHOTS_TABLE; ?>">
        <thead>
            <tr>
                <th class="<?php echo CG_CSS::COLUMN_TITLE; ?>"><?php _e('Snapshot Name', 'category-generator'); ?></th>
                <th class="<?php echo CG_CSS::COLUMN_NOTES; ?>"><?php _e('Notes', 'category-generator'); ?></th>
                <th class="<?php echo CG_CSS::COLUMN_COUNTS; ?>"><?php _e('Categories', 'category-generator'); ?></th>
                <th class="<?php echo CG_CSS::COLUMN_SIZE; ?>"><?php _e('Size', 'category-generator'); ?></th>
                <th class="<?php echo CG_CSS::COLUMN_DATE; ?>"><?php _e('Created', 'category-generator'); ?></th>
                <th class="<?php echo CG_CSS::COLUMN_ACTIONS; ?>"><?php _e('Actions', 'category-generator'); ?></th>
            </tr>
        </thead>
        <tbody id="<?php echo esc_attr($list_id); ?>">
            <?php foreach ($snapshots as $snapshot): ?>
                <?php include CG_PLUGIN_PATH . 'templates/partials/snapshot-table-row.php'; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
