<?php
/**
 * Backups admin view.
 *
 * @package PluginsOnboard
 */

if (!defined('ABSPATH')) {
    exit;
}

$success = isset($_GET['success']) ? sanitize_text_field($_GET['success']) : '';
$error = isset($_GET['error']) ? sanitize_text_field($_GET['error']) : '';
?>
<div class="wrap onboard-wrap">
    <h1 class="wp-heading-inline">
        <?php esc_html_e('Backups & Restore', 'plugins-onboard'); ?>
    </h1>

    <?php if ($success) : ?>
    <div class="notice notice-success is-dismissible">
        <p>
            <?php
            switch ($success) {
                case 'snapshot_deleted':
                    esc_html_e('Snapshot deleted successfully.', 'plugins-onboard');
                    break;
                case 'snapshot_restored':
                    esc_html_e('Plugin restored successfully from snapshot.', 'plugins-onboard');
                    break;
                default:
                    esc_html_e('Operation completed successfully.', 'plugins-onboard');
            }
            ?>
        </p>
    </div>
    <?php endif; ?>

    <?php if ($error) : ?>
    <div class="notice notice-error is-dismissible">
        <p><?php echo esc_html($error); ?></p>
    </div>
    <?php endif; ?>

    <!-- Download Options -->
    <div class="onboard-section">
        <h2><?php esc_html_e('Download Options', 'plugins-onboard'); ?></h2>
        <div class="onboard-download-options">
            <div class="download-option">
                <h4><?php esc_html_e('All Plugins', 'plugins-onboard'); ?></h4>
                <p><?php esc_html_e('Download ZIP with all installed plugins.', 'plugins-onboard'); ?></p>
                <a href="<?php echo rest_url('onboard-plugin/v1/plugins/backups/download-all'); ?>" class="button" target="_blank">
                    <span class="dashicons dashicons-download"></span>
                    <?php esc_html_e('Download All', 'plugins-onboard'); ?>
                </a>
            </div>
            <div class="download-option">
                <h4><?php esc_html_e('Active Plugins', 'plugins-onboard'); ?></h4>
                <p><?php esc_html_e('Download ZIP with only active plugins.', 'plugins-onboard'); ?></p>
                <a href="<?php echo rest_url('onboard-plugin/v1/plugins/backups/download-active'); ?>" class="button" target="_blank">
                    <span class="dashicons dashicons-download"></span>
                    <?php esc_html_e('Download Active', 'plugins-onboard'); ?>
                </a>
            </div>
            <div class="download-option">
                <h4><?php esc_html_e('All Snapshots', 'plugins-onboard'); ?></h4>
                <p><?php esc_html_e('Download ZIP with all versioned snapshots.', 'plugins-onboard'); ?></p>
                <a href="<?php echo rest_url('onboard-plugin/v1/plugins/backups/download-snapshots'); ?>" class="button" target="_blank">
                    <span class="dashicons dashicons-download"></span>
                    <?php esc_html_e('Download Snapshots', 'plugins-onboard'); ?>
                </a>
            </div>
        </div>
    </div>

    <!-- Filter by Plugin -->
    <div class="onboard-section">
        <h2><?php esc_html_e('Plugin Snapshots', 'plugins-onboard'); ?></h2>
        
        <?php if (!empty($plugins_with_snapshots)) : ?>
        <form method="get" class="onboard-filter-form">
            <input type="hidden" name="page" value="plugins-onboard-backups">
            <label for="plugin"><?php esc_html_e('Filter by Plugin:', 'plugins-onboard'); ?></label>
            <select name="plugin" id="plugin">
                <option value=""><?php esc_html_e('All Plugins', 'plugins-onboard'); ?></option>
                <?php foreach ($plugins_with_snapshots as $plugin) : ?>
                <option value="<?php echo esc_attr($plugin['plugin_slug']); ?>" <?php selected($selected_plugin, $plugin['plugin_slug']); ?>>
                    <?php echo esc_html($plugin['plugin_slug']); ?> (<?php echo $plugin['snapshot_count']; ?>)
                </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="button"><?php esc_html_e('Filter', 'plugins-onboard'); ?></button>
        </form>
        <?php endif; ?>

        <?php if (empty($snapshots)) : ?>
            <p><?php esc_html_e('No snapshots found.', 'plugins-onboard'); ?></p>
        <?php else : ?>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 20%;"><?php esc_html_e('Plugin', 'plugins-onboard'); ?></th>
                        <th style="width: 10%;"><?php esc_html_e('Version', 'plugins-onboard'); ?></th>
                        <th style="width: 15%;"><?php esc_html_e('Backup Date', 'plugins-onboard'); ?></th>
                        <th style="width: 10%;"><?php esc_html_e('Size', 'plugins-onboard'); ?></th>
                        <th style="width: 15%;"><?php esc_html_e('Trigger', 'plugins-onboard'); ?></th>
                        <th style="width: 15%;"><?php esc_html_e('Checksum', 'plugins-onboard'); ?></th>
                        <th style="width: 15%;"><?php esc_html_e('Actions', 'plugins-onboard'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($snapshots as $snapshot) : ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($snapshot['plugin_slug']); ?></strong>
                        </td>
                        <td><?php echo esc_html($snapshot['version']); ?></td>
                        <td><?php echo esc_html(onboard_format_date($snapshot['created_at'])); ?></td>
                        <td><?php echo onboard_format_size($snapshot['file_size']); ?></td>
                        <td>
                            <span class="trigger-badge trigger-<?php echo esc_attr($snapshot['trigger_action']); ?>">
                                <?php echo esc_html($snapshot['trigger_action']); ?>
                            </span>
                        </td>
                        <td>
                            <code title="<?php echo esc_attr($snapshot['checksum']); ?>">
                                <?php echo esc_html(substr($snapshot['checksum'], 0, 12)); ?>...
                            </code>
                        </td>
                        <td>
                            <a href="<?php echo wp_nonce_url(
                                admin_url('admin.php?page=plugins-onboard-backups&action=restore_snapshot&snapshot_id=' . $snapshot['snapshot_id']),
                                'restore_snapshot'
                            ); ?>" class="button button-small" onclick="return confirm('<?php esc_attr_e('Are you sure you want to restore this snapshot?', 'plugins-onboard'); ?>');">
                                <?php esc_html_e('Restore', 'plugins-onboard'); ?>
                            </a>
                            <a href="<?php echo wp_nonce_url(
                                admin_url('admin.php?page=plugins-onboard-backups&action=delete_snapshot&snapshot_id=' . $snapshot['snapshot_id']),
                                'delete_snapshot'
                            ); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this snapshot?', 'plugins-onboard'); ?>');">
                                <?php esc_html_e('Delete', 'plugins-onboard'); ?>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
