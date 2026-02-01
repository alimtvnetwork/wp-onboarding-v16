<?php
/**
 * Audit Logs admin view.
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
        <?php esc_html_e('Audit Logs', 'plugins-onboard'); ?>
    </h1>

    <?php if ($success === 'logs_cleared') : ?>
    <div class="notice notice-success is-dismissible">
        <p><?php esc_html_e('Audit logs cleared successfully.', 'plugins-onboard'); ?></p>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="onboard-section">
        <form method="get" class="onboard-filter-form">
            <input type="hidden" name="page" value="plugins-onboard-audit-logs">
            
            <label for="filter_action"><?php esc_html_e('Action:', 'plugins-onboard'); ?></label>
            <select name="filter_action" id="filter_action">
                <option value=""><?php esc_html_e('All Actions', 'plugins-onboard'); ?></option>
                <?php foreach ($unique_actions as $action) : ?>
                <option value="<?php echo esc_attr($action); ?>" <?php selected(isset($filters['action']) && $filters['action'] === $action); ?>>
                    <?php echo esc_html($action); ?>
                </option>
                <?php endforeach; ?>
            </select>

            <label for="filter_status"><?php esc_html_e('Status:', 'plugins-onboard'); ?></label>
            <select name="filter_status" id="filter_status">
                <option value=""><?php esc_html_e('All Statuses', 'plugins-onboard'); ?></option>
                <option value="success" <?php selected(isset($filters['status']) && $filters['status'] === 'success'); ?>><?php esc_html_e('Success', 'plugins-onboard'); ?></option>
                <option value="failed" <?php selected(isset($filters['status']) && $filters['status'] === 'failed'); ?>><?php esc_html_e('Failed', 'plugins-onboard'); ?></option>
                <option value="pending_approval" <?php selected(isset($filters['status']) && $filters['status'] === 'pending_approval'); ?>><?php esc_html_e('Pending', 'plugins-onboard'); ?></option>
            </select>

            <button type="submit" class="button"><?php esc_html_e('Filter', 'plugins-onboard'); ?></button>
            <a href="<?php echo admin_url('admin.php?page=plugins-onboard-audit-logs'); ?>" class="button"><?php esc_html_e('Reset', 'plugins-onboard'); ?></a>
        </form>
    </div>

    <!-- Log Count -->
    <p>
        <?php printf(
            esc_html__('Showing %1$d-%2$d of %3$d logs', 'plugins-onboard'),
            $offset + 1,
            min($offset + $per_page, $total),
            $total
        ); ?>
    </p>

    <!-- Logs Table -->
    <?php if (empty($logs)) : ?>
        <p><?php esc_html_e('No logs found.', 'plugins-onboard'); ?></p>
    <?php else : ?>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 15%;"><?php esc_html_e('Timestamp', 'plugins-onboard'); ?></th>
                    <th style="width: 15%;"><?php esc_html_e('Action', 'plugins-onboard'); ?></th>
                    <th style="width: 12%;"><?php esc_html_e('Plugin', 'plugins-onboard'); ?></th>
                    <th style="width: 10%;"><?php esc_html_e('Status', 'plugins-onboard'); ?></th>
                    <th style="width: 12%;"><?php esc_html_e('App', 'plugins-onboard'); ?></th>
                    <th style="width: 12%;"><?php esc_html_e('IP Address', 'plugins-onboard'); ?></th>
                    <th style="width: 24%;"><?php esc_html_e('Details', 'plugins-onboard'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log) : ?>
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
                    <td><code><?php echo esc_html($log['ip_address'] ?: '-'); ?></code></td>
                    <td>
                        <?php if (!empty($log['error_message'])) : ?>
                            <span class="error-message" title="<?php echo esc_attr($log['error_message']); ?>">
                                <?php echo esc_html(wp_trim_words($log['error_message'], 5)); ?>
                            </span>
                        <?php elseif (!empty($log['details'])) : ?>
                            <small><?php echo esc_html(wp_trim_words(json_encode($log['details']), 10)); ?></small>
                        <?php else : ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($total_pages > 1) : ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <span class="displaying-num"><?php printf(esc_html__('%d items', 'plugins-onboard'), $total); ?></span>
                <span class="pagination-links">
                    <?php if ($page > 1) : ?>
                        <a class="first-page button" href="<?php echo add_query_arg('paged', 1); ?>">
                            <span>&laquo;</span>
                        </a>
                        <a class="prev-page button" href="<?php echo add_query_arg('paged', $page - 1); ?>">
                            <span>&lsaquo;</span>
                        </a>
                    <?php endif; ?>
                    
                    <span class="paging-input">
                        <span class="tablenav-paging-text">
                            <?php echo $page; ?> <?php esc_html_e('of', 'plugins-onboard'); ?> <?php echo $total_pages; ?>
                        </span>
                    </span>
                    
                    <?php if ($page < $total_pages) : ?>
                        <a class="next-page button" href="<?php echo add_query_arg('paged', $page + 1); ?>">
                            <span>&rsaquo;</span>
                        </a>
                        <a class="last-page button" href="<?php echo add_query_arg('paged', $total_pages); ?>">
                            <span>&raquo;</span>
                        </a>
                    <?php endif; ?>
                </span>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Clear Logs -->
    <div class="onboard-section">
        <h3><?php esc_html_e('Clear Logs', 'plugins-onboard'); ?></h3>
        <p><?php esc_html_e('Warning: This action cannot be undone.', 'plugins-onboard'); ?></p>
        <a href="<?php echo wp_nonce_url(
            admin_url('admin.php?page=plugins-onboard-audit-logs&action=clear_logs'),
            'clear_logs'
        ); ?>" class="button button-link-delete" onclick="return confirm('<?php esc_attr_e('Are you sure you want to clear all audit logs?', 'plugins-onboard'); ?>');">
            <?php esc_html_e('Clear All Logs', 'plugins-onboard'); ?>
        </a>
    </div>
</div>
