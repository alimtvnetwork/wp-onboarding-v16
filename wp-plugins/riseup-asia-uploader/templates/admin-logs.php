<?php
/**
 * Admin Logs Page Template
 *
 * @package RiseupAsiaUploader
 * @since   1.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap riseup-admin">
    <h1>
        <span class="dashicons dashicons-list-view"></span>
        <?php esc_html_e('Riseup Asia Uploader - Activity Logs', 'riseup-asia-uploader'); ?>
    </h1>
    
    <p class="description">
        <?php esc_html_e('View all API activity and operations performed through this plugin.', 'riseup-asia-uploader'); ?>
    </p>

    <!-- Filters -->
    <div class="riseup-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="riseup-asia-uploader">
            
            <div class="filter-row">
                <label>
                    <span><?php esc_html_e('Action:', 'riseup-asia-uploader'); ?></span>
                    <select name="filter_action">
                        <option value=""><?php esc_html_e('All Actions', 'riseup-asia-uploader'); ?></option>
                        <?php foreach ($action_labels as $key => $label): ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($filters['action'], $key); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <span><?php esc_html_e('Status:', 'riseup-asia-uploader'); ?></span>
                    <select name="filter_status">
                        <option value=""><?php esc_html_e('All Statuses', 'riseup-asia-uploader'); ?></option>
                        <option value="success" <?php selected($filters['status'], 'success'); ?>><?php esc_html_e('Success', 'riseup-asia-uploader'); ?></option>
                        <option value="failed" <?php selected($filters['status'], 'failed'); ?>><?php esc_html_e('Failed', 'riseup-asia-uploader'); ?></option>
                    </select>
                </label>

                <label>
                    <span><?php esc_html_e('User:', 'riseup-asia-uploader'); ?></span>
                    <input type="text" name="filter_user" value="<?php echo esc_attr($filters['user']); ?>" placeholder="<?php esc_attr_e('Username', 'riseup-asia-uploader'); ?>">
                </label>

                <label>
                    <span><?php esc_html_e('Plugin:', 'riseup-asia-uploader'); ?></span>
                    <input type="text" name="filter_plugin" value="<?php echo esc_attr($filters['plugin']); ?>" placeholder="<?php esc_attr_e('Plugin slug', 'riseup-asia-uploader'); ?>">
                </label>

                <label>
                    <span><?php esc_html_e('From:', 'riseup-asia-uploader'); ?></span>
                    <input type="date" name="filter_from" value="<?php echo esc_attr($filters['from']); ?>">
                </label>

                <label>
                    <span><?php esc_html_e('To:', 'riseup-asia-uploader'); ?></span>
                    <input type="date" name="filter_to" value="<?php echo esc_attr($filters['to']); ?>">
                </label>

                <button type="submit" class="button button-primary"><?php esc_html_e('Filter', 'riseup-asia-uploader'); ?></button>
                <a href="<?php echo esc_url(admin_url('admin.php?page=riseup-asia-uploader')); ?>" class="button"><?php esc_html_e('Reset', 'riseup-asia-uploader'); ?></a>
            </div>
        </form>
    </div>

    <!-- Stats -->
    <div class="riseup-stats">
        <span class="stat-item">
            <strong><?php echo esc_html($total); ?></strong>
            <?php esc_html_e('total records', 'riseup-asia-uploader'); ?>
        </span>
        <?php if ($page > 1 || $page < $total_pages): ?>
            <span class="stat-item">
                <?php esc_html_e('Page', 'riseup-asia-uploader'); ?> <?php echo esc_html($page); ?> 
                <?php esc_html_e('of', 'riseup-asia-uploader'); ?> <?php echo esc_html($total_pages); ?>
            </span>
        <?php endif; ?>
    </div>

    <!-- Logs Table -->
    <table class="wp-list-table widefat fixed striped riseup-logs-table">
        <thead>
            <tr>
                <th class="column-id"><?php esc_html_e('ID', 'riseup-asia-uploader'); ?></th>
                <th class="column-timestamp"><?php esc_html_e('Timestamp', 'riseup-asia-uploader'); ?></th>
                <th class="column-action"><?php esc_html_e('Action', 'riseup-asia-uploader'); ?></th>
                <th class="column-plugin"><?php esc_html_e('Plugin/Target', 'riseup-asia-uploader'); ?></th>
                <th class="column-user"><?php esc_html_e('User', 'riseup-asia-uploader'); ?></th>
                <th class="column-ip"><?php esc_html_e('IP Address', 'riseup-asia-uploader'); ?></th>
                <th class="column-status"><?php esc_html_e('Status', 'riseup-asia-uploader'); ?></th>
                <th class="column-details"><?php esc_html_e('Details', 'riseup-asia-uploader'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="8" class="no-items"><?php esc_html_e('No activity logs found.', 'riseup-asia-uploader'); ?></td>
                </tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td class="column-id"><?php echo esc_html($log['id']); ?></td>
                        <td class="column-timestamp">
                            <span class="timestamp" title="<?php echo esc_attr($log['created_at']); ?>">
                                <?php echo esc_html(date('Y-m-d H:i:s', strtotime($log['created_at']))); ?>
                            </span>
                        </td>
                        <td class="column-action">
                            <span class="action-badge action-<?php echo esc_attr($log['action']); ?>">
                                <?php echo esc_html($action_labels[$log['action']] ?? $log['action']); ?>
                            </span>
                        </td>
                        <td class="column-plugin">
                            <?php if (!empty($log['plugin_slug'])): ?>
                                <code><?php echo esc_html($log['plugin_slug']); ?></code>
                            <?php elseif (!empty($log['post_id'])): ?>
                                <?php esc_html_e('Post:', 'riseup-asia-uploader'); ?> #<?php echo esc_html($log['post_id']); ?>
                            <?php else: ?>
                                <span class="na">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="column-user">
                            <span class="user-info">
                                <?php echo esc_html($log['user_login']); ?>
                                <?php if (!empty($log['user_id'])): ?>
                                    <small>(#<?php echo esc_html($log['user_id']); ?>)</small>
                                <?php endif; ?>
                            </span>
                        </td>
                        <td class="column-ip">
                            <code><?php echo esc_html($log['ip_address']); ?></code>
                        </td>
                        <td class="column-status">
                            <span class="status-badge status-<?php echo esc_attr($log['status']); ?>">
                                <?php echo esc_html(ucfirst($log['status'])); ?>
                            </span>
                        </td>
                        <td class="column-details">
                            <?php if (!empty($log['error_msg'])): ?>
                                <span class="error-msg" title="<?php echo esc_attr($log['error_msg']); ?>">
                                    <?php echo esc_html(wp_trim_words($log['error_msg'], 10)); ?>
                                </span>
                            <?php elseif (!empty($log['details'])): ?>
                                <button type="button" class="button button-small toggle-details" data-details="<?php echo esc_attr(json_encode($log['details'])); ?>">
                                    <?php esc_html_e('View', 'riseup-asia-uploader'); ?>
                                </button>
                            <?php else: ?>
                                <span class="na">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                $pagination_args = array(
                    'base'      => add_query_arg('paged', '%#%'),
                    'format'    => '',
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'total'     => $total_pages,
                    'current'   => $page,
                );
                echo paginate_links($pagination_args);
                ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Details Modal -->
    <div id="riseup-details-modal" class="riseup-modal" style="display: none;">
        <div class="riseup-modal-content">
            <div class="riseup-modal-header">
                <h3><?php esc_html_e('Details', 'riseup-asia-uploader'); ?></h3>
                <button type="button" class="riseup-modal-close">&times;</button>
            </div>
            <div class="riseup-modal-body">
                <pre id="riseup-details-content"></pre>
            </div>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Toggle details modal
        $('.toggle-details').on('click', function() {
            var details = $(this).data('details');
            var formatted = JSON.stringify(details, null, 2);
            $('#riseup-details-content').text(formatted);
            $('#riseup-details-modal').show();
        });

        // Close modal
        $('.riseup-modal-close, .riseup-modal').on('click', function(e) {
            if (e.target === this) {
                $('#riseup-details-modal').hide();
            }
        });

        // ESC to close
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                $('#riseup-details-modal').hide();
            }
        });
    });
    </script>
</div>
