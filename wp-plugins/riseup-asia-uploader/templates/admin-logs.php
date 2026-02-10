<?php
/**
 * Admin Logs Page Template
 *
 * @package RiseupAsiaUploader
 * @since   1.5.0
 * @updated 1.9.0 - Added source machine and triggered_by columns
 */

if (!defined('ABSPATH')) {
    exit;
}

// Trigger source labels for display
$trigger_labels = array(
    'api'        => __('API', 'riseup-asia-uploader'),
    'dashboard'  => __('Dashboard', 'riseup-asia-uploader'),
    'agent_push' => __('Agent Push', 'riseup-asia-uploader'),
    'cron'       => __('Cron', 'riseup-asia-uploader'),
    'cli'        => __('WP-CLI', 'riseup-asia-uploader'),
);

// Trigger source CSS classes for color coding
$trigger_classes = array(
    'api'        => 'trigger-api',
    'dashboard'  => 'trigger-dashboard',
    'agent_push' => 'trigger-agent',
    'cron'       => 'trigger-cron',
    'cli'        => 'trigger-cli',
);

// Upload source labels for display
$upload_source_labels = array(
    'upload_script' => __('Upload Script', 'riseup-asia-uploader'),
    'rest_api'      => __('REST API', 'riseup-asia-uploader'),
    'admin_ui'      => __('Admin UI', 'riseup-asia-uploader'),
    'wp_cli'        => __('WP-CLI', 'riseup-asia-uploader'),
);

// Upload source CSS classes for color coding
$upload_source_classes = array(
    'upload_script' => 'source-script',
    'rest_api'      => 'source-api',
    'admin_ui'      => 'source-admin',
    'wp_cli'        => 'source-cli',
);
?>
<div class="wrap riseup-admin">
    <h1 style="font-family: 'Ubuntu', sans-serif; font-weight: 700; font-size: 26px;">
        <span class="dashicons dashicons-list-view"></span>
        <?php esc_html_e('Riseup Asia Uploader - Activity Logs', 'riseup-asia-uploader'); ?>
        <span class="riseup-version-badge">v<?php echo esc_html(RISEUP_VERSION); ?></span>
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
                    <span><?php esc_html_e('Trigger:', 'riseup-asia-uploader'); ?></span>
                    <select name="filter_triggered_by">
                        <option value=""><?php esc_html_e('All Sources', 'riseup-asia-uploader'); ?></option>
                        <?php foreach ($trigger_labels as $key => $label): ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected(isset($filters['triggered_by']) ? $filters['triggered_by'] : '', $key); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <span><?php esc_html_e('Upload Via:', 'riseup-asia-uploader'); ?></span>
                    <select name="filter_upload_source">
                        <option value=""><?php esc_html_e('All Methods', 'riseup-asia-uploader'); ?></option>
                        <?php foreach ($upload_source_labels as $key => $label): ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected(isset($filters['upload_source']) ? $filters['upload_source'] : '', $key); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
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
                    <span><?php esc_html_e('Source Machine:', 'riseup-asia-uploader'); ?></span>
                    <input type="text" name="filter_source_machine" value="<?php echo esc_attr(isset($filters['source_machine']) ? $filters['source_machine'] : ''); ?>" placeholder="<?php esc_attr_e('Hostname', 'riseup-asia-uploader'); ?>">
                </label>
            </div>
            
            <div class="filter-row filter-row-secondary">
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
                <th class="column-version"><?php esc_html_e('Version', 'riseup-asia-uploader'); ?></th>
                <th class="column-trigger"><?php esc_html_e('Trigger', 'riseup-asia-uploader'); ?></th>
                <th class="column-upload-source"><?php esc_html_e('Upload Via', 'riseup-asia-uploader'); ?></th>
                <th class="column-source"><?php esc_html_e('Source', 'riseup-asia-uploader'); ?></th>
                <th class="column-user"><?php esc_html_e('User', 'riseup-asia-uploader'); ?></th>
                <th class="column-status"><?php esc_html_e('Status', 'riseup-asia-uploader'); ?></th>
                <th class="column-details"><?php esc_html_e('Details', 'riseup-asia-uploader'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="11" class="no-items"><?php esc_html_e('No activity logs found.', 'riseup-asia-uploader'); ?></td>
                </tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                    <?php 
                    $triggered_by = isset($log['triggered_by']) ? $log['triggered_by'] : '';
                    $source_machine = isset($log['source_machine']) ? $log['source_machine'] : '';
                    $plugin_version = isset($log['plugin_version']) ? $log['plugin_version'] : '';
                    $upload_source = isset($log['upload_source']) ? $log['upload_source'] : '';
                    $trigger_class = isset($trigger_classes[$triggered_by]) ? $trigger_classes[$triggered_by] : 'trigger-unknown';
                    $trigger_label = isset($trigger_labels[$triggered_by]) ? $trigger_labels[$triggered_by] : ($triggered_by ?: '—');
                    $upload_source_class = isset($upload_source_classes[$upload_source]) ? $upload_source_classes[$upload_source] : 'source-unknown';
                    $upload_source_label = isset($upload_source_labels[$upload_source]) ? $upload_source_labels[$upload_source] : ($upload_source ?: '—');
                    ?>
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
                                <?php if (!empty($log['plugin_file']) && $log['plugin_file'] !== $log['plugin_slug']): ?>
                                    <br><small class="plugin-file" title="<?php echo esc_attr($log['plugin_file']); ?>"><?php echo esc_html($log['plugin_file']); ?></small>
                                <?php endif; ?>
                            <?php elseif (!empty($log['post_id'])): ?>
                                <?php esc_html_e('Post:', 'riseup-asia-uploader'); ?> #<?php echo esc_html($log['post_id']); ?>
                            <?php else: ?>
                                <span class="na">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="column-version">
                            <?php if (!empty($plugin_version)): ?>
                                <code class="version-badge">v<?php echo esc_html($plugin_version); ?></code>
                            <?php else: ?>
                                <span class="na">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="column-trigger">
                            <?php if (!empty($triggered_by)): ?>
                                <span class="trigger-badge <?php echo esc_attr($trigger_class); ?>" title="<?php echo esc_attr($triggered_by); ?>">
                                    <?php echo esc_html($trigger_label); ?>
                                </span>
                            <?php else: ?>
                                <span class="na">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="column-upload-source">
                            <?php if (!empty($upload_source)): ?>
                                <span class="upload-source-badge <?php echo esc_attr($upload_source_class); ?>" title="<?php echo esc_attr($upload_source); ?>">
                                    <?php echo esc_html($upload_source_label); ?>
                                </span>
                            <?php else: ?>
                                <span class="na">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="column-source">
                            <?php if (!empty($source_machine)): ?>
                                <code class="source-machine" title="<?php esc_attr_e('Management Server Hostname', 'riseup-asia-uploader'); ?>">
                                    <?php echo esc_html($source_machine); ?>
                                </code>
                            <?php else: ?>
                                <span class="na">—</span>
                            <?php endif; ?>
                            <?php if (!empty($log['ip_address']) && $log['ip_address'] !== '0.0.0.0'): ?>
                                <br><small class="ip-address"><?php echo esc_html($log['ip_address']); ?></small>
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

    <style>
    /* Trigger badge colors */
    .trigger-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: 500;
        text-transform: uppercase;
    }
    .trigger-api {
        background: #e3f2fd;
        color: #1565c0;
    }
    .trigger-dashboard {
        background: #e8f5e9;
        color: #2e7d32;
    }
    .trigger-agent {
        background: #fff3e0;
        color: #e65100;
    }
    .trigger-cron {
        background: #f3e5f5;
        color: #7b1fa2;
    }
    .trigger-cli {
        background: #eceff1;
        color: #455a64;
    }
    .trigger-unknown {
        background: #fafafa;
        color: #9e9e9e;
    }

    /* Source machine styling */
    .source-machine {
        background: #263238;
        color: #80cbc4;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 11px;
    }
    .ip-address {
        color: #757575;
        font-family: monospace;
    }
    .plugin-file {
        color: #757575;
        font-family: monospace;
        font-size: 10px;
        word-break: break-all;
    }

    /* Filter row improvements */
    .filter-row {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: flex-end;
        margin-bottom: 10px;
    }
    .filter-row-secondary {
        margin-top: 5px;
    }
    .riseup-filters label span {
        display: block;
        font-size: 11px;
        color: #666;
        margin-bottom: 3px;
    }

    /* Version badge */
    .version-badge {
        background: #e8eaf6;
        color: #283593;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: 600;
    }

    /* Upload source badge */
    .upload-source-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: 500;
        text-transform: uppercase;
    }
    .source-script {
        background: #fff8e1;
        color: #f57f17;
    }
    .source-api {
        background: #e3f2fd;
        color: #1565c0;
    }
    .source-admin {
        background: #e8f5e9;
        color: #2e7d32;
    }
    .source-cli {
        background: #eceff1;
        color: #455a64;
    }
    .source-unknown {
        background: #fafafa;
        color: #9e9e9e;
    }

    /* Column widths */
    .column-id { width: 50px; }
    .column-timestamp { width: 130px; }
    .column-action { width: 95px; }
    .column-plugin { width: 130px; }
    .column-version { width: 70px; }
    .column-trigger { width: 80px; }
    .column-upload-source { width: 95px; }
    .column-source { width: 120px; }
    .column-user { width: 90px; }
    .column-status { width: 65px; }
    .column-details { width: 60px; }
    </style>

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
