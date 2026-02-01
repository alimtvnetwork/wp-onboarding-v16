<?php
/**
 * Plugins admin view.
 *
 * @package PluginsOnboard
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap onboard-wrap">
    <h1 class="wp-heading-inline">
        <?php esc_html_e('Plugins Management', 'plugins-onboard'); ?>
    </h1>

    <!-- All Installed Plugins -->
    <div class="onboard-section">
        <h2><?php esc_html_e('All Installed Plugins', 'plugins-onboard'); ?></h2>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 25%;"><?php esc_html_e('Plugin', 'plugins-onboard'); ?></th>
                    <th style="width: 10%;"><?php esc_html_e('Version', 'plugins-onboard'); ?></th>
                    <th style="width: 10%;"><?php esc_html_e('Status', 'plugins-onboard'); ?></th>
                    <th style="width: 10%;"><?php esc_html_e('Snapshots', 'plugins-onboard'); ?></th>
                    <th style="width: 15%;"><?php esc_html_e('Last Backup', 'plugins-onboard'); ?></th>
                    <th style="width: 30%;"><?php esc_html_e('Description', 'plugins-onboard'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($plugins as $plugin) : ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($plugin['name']); ?></strong>
                        <div class="row-actions">
                            <?php if ($plugin['slug'] !== 'plugins-onboard') : ?>
                            <span class="view">
                                <a href="<?php echo admin_url('admin.php?page=plugins-onboard-backups&plugin=' . $plugin['slug']); ?>">
                                    <?php esc_html_e('View Backups', 'plugins-onboard'); ?>
                                </a>
                            </span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td><?php echo esc_html($plugin['version']); ?></td>
                    <td>
                        <?php if ($plugin['is_active']) : ?>
                            <span class="status-badge status-success"><?php esc_html_e('Active', 'plugins-onboard'); ?></span>
                        <?php else : ?>
                            <span class="status-badge status-inactive"><?php esc_html_e('Inactive', 'plugins-onboard'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($plugin['snapshot_count'] > 0) : ?>
                            <a href="<?php echo admin_url('admin.php?page=plugins-onboard-backups&plugin=' . $plugin['slug']); ?>">
                                <?php echo $plugin['snapshot_count']; ?>
                            </a>
                        <?php else : ?>
                            0
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo $plugin['last_backup'] ? esc_html(onboard_format_date($plugin['last_backup'])) : '-'; ?>
                    </td>
                    <td>
                        <small><?php echo esc_html(wp_trim_words($plugin['description'], 20)); ?></small>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Uploaded by Tool -->
    <?php if (!empty($uploaded_plugins)) : ?>
    <div class="onboard-section">
        <h2><?php esc_html_e('Uploaded via Plugins Onboard', 'plugins-onboard'); ?></h2>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Plugin', 'plugins-onboard'); ?></th>
                    <th><?php esc_html_e('Version', 'plugins-onboard'); ?></th>
                    <th><?php esc_html_e('Upload Date', 'plugins-onboard'); ?></th>
                    <th><?php esc_html_e('Uploaded By', 'plugins-onboard'); ?></th>
                    <th><?php esc_html_e('Status', 'plugins-onboard'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($uploaded_plugins as $plugin) : ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($plugin['name']); ?></strong>
                    </td>
                    <td><?php echo esc_html($plugin['version']); ?></td>
                    <td><?php echo esc_html(onboard_format_date($plugin['upload_date'])); ?></td>
                    <td>
                        <?php echo esc_html($plugin['upload_app'] ?: 'Unknown'); ?>
                        <br>
                        <small><?php echo esc_html($plugin['upload_ip']); ?></small>
                    </td>
                    <td>
                        <?php if ($plugin['is_active']) : ?>
                            <span class="status-badge status-success"><?php esc_html_e('Active', 'plugins-onboard'); ?></span>
                        <?php else : ?>
                            <span class="status-badge status-inactive"><?php esc_html_e('Inactive', 'plugins-onboard'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- API Instructions -->
    <div class="onboard-section">
        <h2><?php esc_html_e('API Endpoints', 'plugins-onboard'); ?></h2>
        <p><?php esc_html_e('Use these endpoints to manage plugins remotely:', 'plugins-onboard'); ?></p>
        <table class="widefat fixed">
            <thead>
                <tr>
                    <th><?php esc_html_e('Endpoint', 'plugins-onboard'); ?></th>
                    <th><?php esc_html_e('Method', 'plugins-onboard'); ?></th>
                    <th><?php esc_html_e('Description', 'plugins-onboard'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>/plugins/list</code></td>
                    <td>GET</td>
                    <td><?php esc_html_e('List all plugins with status', 'plugins-onboard'); ?></td>
                </tr>
                <tr>
                    <td><code>/mutations/{token}/plugins/{slug}/enable</code></td>
                    <td>POST</td>
                    <td><?php esc_html_e('Enable a plugin', 'plugins-onboard'); ?></td>
                </tr>
                <tr>
                    <td><code>/mutations/{token}/plugins/{slug}/disable</code></td>
                    <td>POST</td>
                    <td><?php esc_html_e('Disable a plugin', 'plugins-onboard'); ?></td>
                </tr>
                <tr>
                    <td><code>/mutations/{token}/plugins/{slug}/delete</code></td>
                    <td>POST</td>
                    <td><?php esc_html_e('Delete a plugin', 'plugins-onboard'); ?></td>
                </tr>
                <tr>
                    <td><code>/mutations/{token}/plugins/upload</code></td>
                    <td>POST</td>
                    <td><?php esc_html_e('Upload and install a plugin', 'plugins-onboard'); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
