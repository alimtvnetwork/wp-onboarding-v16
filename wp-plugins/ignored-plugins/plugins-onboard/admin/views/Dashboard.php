<?php
/**
 * Dashboard admin view.
 *
 * @package PluginsOnboard
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap onboard-wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-admin-plugins"></span>
        <?php esc_html_e('Plugins Onboard Dashboard', 'plugins-onboard'); ?>
    </h1>

    <?php if (!empty($pending_approvals)) : ?>
    <div class="notice notice-warning">
        <p>
            <strong><?php esc_html_e('Pending IP Approvals:', 'plugins-onboard'); ?></strong>
            <?php echo count($pending_approvals); ?> 
            <a href="<?php echo admin_url('admin.php?page=plugins-onboard-applications'); ?>">
                <?php esc_html_e('Review Now', 'plugins-onboard'); ?>
            </a>
        </p>
    </div>
    <?php endif; ?>

    <!-- Overview Cards -->
    <div class="onboard-cards">
        <div class="onboard-card">
            <div class="card-icon dashicons dashicons-admin-plugins"></div>
            <div class="card-content">
                <h3><?php echo count($plugins); ?></h3>
                <p><?php esc_html_e('Total Plugins', 'plugins-onboard'); ?></p>
                <small><?php echo $active_count; ?> <?php esc_html_e('active', 'plugins-onboard'); ?></small>
            </div>
        </div>

        <div class="onboard-card">
            <div class="card-icon dashicons dashicons-backup"></div>
            <div class="card-content">
                <h3><?php echo $snapshot_count; ?></h3>
                <p><?php esc_html_e('Snapshots', 'plugins-onboard'); ?></p>
                <small><?php echo onboard_format_size($snapshot_size); ?></small>
            </div>
        </div>

        <div class="onboard-card">
            <div class="card-icon dashicons dashicons-chart-bar"></div>
            <div class="card-content">
                <h3><?php echo $audit_stats['today']; ?></h3>
                <p><?php esc_html_e('Actions Today', 'plugins-onboard'); ?></p>
                <small><?php echo $audit_stats['total']; ?> <?php esc_html_e('total', 'plugins-onboard'); ?></small>
            </div>
        </div>

        <div class="onboard-card <?php echo $maintenance_status['maintenance_mode'] ? 'card-warning' : ''; ?>">
            <div class="card-icon dashicons dashicons-admin-tools"></div>
            <div class="card-content">
                <h3><?php echo $maintenance_status['maintenance_mode'] ? __('ON', 'plugins-onboard') : __('OFF', 'plugins-onboard'); ?></h3>
                <p><?php esc_html_e('Maintenance', 'plugins-onboard'); ?></p>
                <small><?php echo $debug_status['debug'] ? __('Debug: ON', 'plugins-onboard') : __('Debug: OFF', 'plugins-onboard'); ?></small>
            </div>
        </div>
    </div>

    <!-- System Status -->
    <div class="onboard-section">
        <h2><?php esc_html_e('System Status', 'plugins-onboard'); ?></h2>
        <table class="widefat fixed striped">
            <tbody>
                <tr>
                    <td><strong><?php esc_html_e('Plugin Version', 'plugins-onboard'); ?></strong></td>
                    <td><?php echo ONBOARD_PLUGIN_VERSION; ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('WordPress Version', 'plugins-onboard'); ?></strong></td>
                    <td><?php echo get_bloginfo('version'); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('PHP Version', 'plugins-onboard'); ?></strong></td>
                    <td><?php echo phpversion(); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('SQLite Extension', 'plugins-onboard'); ?></strong></td>
                    <td>
                        <?php if (extension_loaded('sqlite3') || extension_loaded('pdo_sqlite')) : ?>
                            <span class="dashicons dashicons-yes-alt" style="color: green;"></span> <?php esc_html_e('Available', 'plugins-onboard'); ?>
                        <?php else : ?>
                            <span class="dashicons dashicons-dismiss" style="color: red;"></span> <?php esc_html_e('Not Available', 'plugins-onboard'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('ZipArchive Extension', 'plugins-onboard'); ?></strong></td>
                    <td>
                        <?php if (class_exists('ZipArchive')) : ?>
                            <span class="dashicons dashicons-yes-alt" style="color: green;"></span> <?php esc_html_e('Available', 'plugins-onboard'); ?>
                        <?php else : ?>
                            <span class="dashicons dashicons-dismiss" style="color: red;"></span> <?php esc_html_e('Not Available', 'plugins-onboard'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Snapshot Directory', 'plugins-onboard'); ?></strong></td>
                    <td>
                        <?php if (OnboardPaths::is_directory_writable(OnboardPaths::DIR_PLUGIN_SNAPSHOTS)) : ?>
                            <span class="dashicons dashicons-yes-alt" style="color: green;"></span> <?php esc_html_e('Writable', 'plugins-onboard'); ?>
                        <?php else : ?>
                            <span class="dashicons dashicons-dismiss" style="color: red;"></span> <?php esc_html_e('Not Writable', 'plugins-onboard'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Debug Mode', 'plugins-onboard'); ?></strong></td>
                    <td>
                        <?php echo $debug_status['debug'] ? '<span class="status-badge status-warning">' . __('Enabled', 'plugins-onboard') . '</span>' : '<span class="status-badge status-inactive">' . __('Disabled', 'plugins-onboard') . '</span>'; ?>
                    </td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Maintenance Mode', 'plugins-onboard'); ?></strong></td>
                    <td>
                        <?php echo $maintenance_status['maintenance_mode'] ? '<span class="status-badge status-warning">' . __('Enabled', 'plugins-onboard') . '</span>' : '<span class="status-badge status-inactive">' . __('Disabled', 'plugins-onboard') . '</span>'; ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Recent Actions -->
    <div class="onboard-section">
        <h2><?php esc_html_e('Recent Actions', 'plugins-onboard'); ?></h2>
        <?php if (empty($recent_logs)) : ?>
            <p><?php esc_html_e('No recent actions.', 'plugins-onboard'); ?></p>
        <?php else : ?>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Time', 'plugins-onboard'); ?></th>
                        <th><?php esc_html_e('Action', 'plugins-onboard'); ?></th>
                        <th><?php esc_html_e('Plugin', 'plugins-onboard'); ?></th>
                        <th><?php esc_html_e('Status', 'plugins-onboard'); ?></th>
                        <th><?php esc_html_e('App', 'plugins-onboard'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_logs as $log) : ?>
                    <tr>
                        <td><?php echo esc_html(onboard_format_date($log['timestamp'])); ?></td>
                        <td><code><?php echo esc_html($log['action']); ?></code></td>
                        <td><?php echo esc_html($log['plugin_slug'] ?: '-'); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo esc_attr($log['status']); ?>">
                                <?php echo esc_html($log['status']); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($log['app_name'] ?: 'Admin'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p>
                <a href="<?php echo admin_url('admin.php?page=plugins-onboard-audit-logs'); ?>">
                    <?php esc_html_e('View All Logs', 'plugins-onboard'); ?> &rarr;
                </a>
            </p>
        <?php endif; ?>
    </div>

    <!-- Quick Links -->
    <div class="onboard-section">
        <h2><?php esc_html_e('Quick Actions', 'plugins-onboard'); ?></h2>
        <div class="onboard-quick-links">
            <a href="<?php echo admin_url('admin.php?page=plugins-onboard-applications'); ?>" class="button button-primary">
                <span class="dashicons dashicons-plus-alt"></span>
                <?php esc_html_e('Create Application', 'plugins-onboard'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=plugins-onboard-backups'); ?>" class="button">
                <span class="dashicons dashicons-download"></span>
                <?php esc_html_e('Manage Backups', 'plugins-onboard'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=plugins-onboard-settings'); ?>" class="button">
                <span class="dashicons dashicons-admin-settings"></span>
                <?php esc_html_e('Settings', 'plugins-onboard'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=plugins-onboard-tests'); ?>" class="button">
                <span class="dashicons dashicons-performance"></span>
                <?php esc_html_e('Run Tests', 'plugins-onboard'); ?>
            </a>
        </div>
    </div>
</div>
