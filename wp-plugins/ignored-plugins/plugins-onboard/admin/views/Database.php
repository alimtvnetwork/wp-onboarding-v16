<?php
/**
 * Database admin view.
 *
 * @package PluginsOnboard
 */

if (!defined('ABSPATH')) {
    exit;
}

$success = isset($_GET['success']) ? sanitize_text_field($_GET['success']) : '';
?>
<div class="wrap onboard-wrap">
    <h1 class="wp-heading-inline">
        <?php esc_html_e('Database Management', 'plugins-onboard'); ?>
    </h1>

    <?php if ($success) : ?>
    <div class="notice notice-success is-dismissible">
        <p>
            <?php
            switch ($success) {
                case 'temp_cleared':
                    esc_html_e('Temporary files cleared successfully.', 'plugins-onboard');
                    break;
                case 'cleanup_complete':
                    esc_html_e('Cleanup completed successfully.', 'plugins-onboard');
                    break;
                default:
                    esc_html_e('Operation completed successfully.', 'plugins-onboard');
            }
            ?>
        </p>
    </div>
    <?php endif; ?>

    <!-- Database Info -->
    <div class="onboard-section">
        <h2><?php esc_html_e('Database Information', 'plugins-onboard'); ?></h2>
        <table class="widefat fixed striped">
            <tbody>
                <tr>
                    <td><strong><?php esc_html_e('Plugin Manager Database', 'plugins-onboard'); ?></strong></td>
                    <td><?php echo esc_html($db_info['plugin_manager_db']['path']); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Plugin Manager Size', 'plugins-onboard'); ?></strong></td>
                    <td><?php echo onboard_format_size($db_info['plugin_manager_db']['size']); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Plugin Manager Last Modified', 'plugins-onboard'); ?></strong></td>
                    <td><?php echo $db_info['plugin_manager_db']['modified'] ? esc_html($db_info['plugin_manager_db']['modified']) : '-'; ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Audit Database', 'plugins-onboard'); ?></strong></td>
                    <td><?php echo esc_html($db_info['audit_db']['path']); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Audit Database Size', 'plugins-onboard'); ?></strong></td>
                    <td><?php echo onboard_format_size($db_info['audit_db']['size']); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Total Database Size', 'plugins-onboard'); ?></strong></td>
                    <td><?php echo onboard_format_size($db_info['total_size']); ?></td>
                </tr>
            </tbody>
        </table>

        <p class="submit">
            <a href="<?php echo rest_url('onboard-plugin/v1/database/download'); ?>" class="button button-primary" target="_blank">
                <span class="dashicons dashicons-download"></span>
                <?php esc_html_e('Download Databases', 'plugins-onboard'); ?>
            </a>
        </p>
    </div>

    <!-- Temp Files -->
    <div class="onboard-section">
        <h2><?php esc_html_e('Temporary Files', 'plugins-onboard'); ?></h2>
        <table class="widefat fixed striped">
            <tbody>
                <tr>
                    <td><strong><?php esc_html_e('Temp Directory', 'plugins-onboard'); ?></strong></td>
                    <td><?php echo esc_html($temp_info['path']); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Total Size', 'plugins-onboard'); ?></strong></td>
                    <td>
                        <?php echo onboard_format_size($temp_info['total_size']); ?>
                        <?php if ($temp_info['size_warning']) : ?>
                            <span class="dashicons dashicons-warning" style="color: orange;" title="<?php esc_attr_e('Size exceeds warning threshold', 'plugins-onboard'); ?>"></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('File Count', 'plugins-onboard'); ?></strong></td>
                    <td><?php echo $temp_info['file_count']; ?></td>
                </tr>
            </tbody>
        </table>

        <?php if (!empty($temp_info['files'])) : ?>
        <h4><?php esc_html_e('Temp Files', 'plugins-onboard'); ?></h4>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('File', 'plugins-onboard'); ?></th>
                    <th><?php esc_html_e('Size', 'plugins-onboard'); ?></th>
                    <th><?php esc_html_e('Modified', 'plugins-onboard'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($temp_info['files'], 0, 20) as $file) : ?>
                <tr>
                    <td><code><?php echo esc_html(basename($file['path'])); ?></code></td>
                    <td><?php echo onboard_format_size($file['size']); ?></td>
                    <td><?php echo esc_html($file['modified']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <p class="submit">
            <a href="<?php echo wp_nonce_url(
                admin_url('admin.php?page=plugins-onboard-database&action=clear_temp'),
                'clear_temp'
            ); ?>" class="button" onclick="return confirm('<?php esc_attr_e('Are you sure you want to clear all temporary files?', 'plugins-onboard'); ?>');">
                <?php esc_html_e('Clear Temp Files', 'plugins-onboard'); ?>
            </a>
        </p>
    </div>

    <!-- Cleanup Status -->
    <div class="onboard-section">
        <h2><?php esc_html_e('Cleanup Status', 'plugins-onboard'); ?></h2>
        <table class="widefat fixed striped">
            <tbody>
                <tr>
                    <td><strong><?php esc_html_e('Expired Auth Codes', 'plugins-onboard'); ?></strong></td>
                    <td><?php echo $cleanup_status['expired_auth_codes']; ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Expired Mutation Tokens', 'plugins-onboard'); ?></strong></td>
                    <td><?php echo $cleanup_status['expired_mutation_tokens']; ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Expired Approvals', 'plugins-onboard'); ?></strong></td>
                    <td><?php echo $cleanup_status['expired_approvals']; ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Old Temp Files', 'plugins-onboard'); ?></strong></td>
                    <td><?php echo $cleanup_status['temp_files']['old_count']; ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Last Cleanup', 'plugins-onboard'); ?></strong></td>
                    <td><?php echo $cleanup_status['last_cleanup'] ? esc_html($cleanup_status['last_cleanup']) : __('Never', 'plugins-onboard'); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Next Scheduled Cleanup', 'plugins-onboard'); ?></strong></td>
                    <td>
                        <?php
                        if ($cleanup_status['next_scheduled']) {
                            echo esc_html(date('Y-m-d H:i:s', $cleanup_status['next_scheduled']));
                        } else {
                            esc_html_e('Not scheduled', 'plugins-onboard');
                        }
                        ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <p class="submit">
            <a href="<?php echo wp_nonce_url(
                admin_url('admin.php?page=plugins-onboard-database&action=run_cleanup'),
                'run_cleanup'
            ); ?>" class="button button-primary">
                <?php esc_html_e('Run Cleanup Now', 'plugins-onboard'); ?>
            </a>
        </p>
    </div>
</div>
