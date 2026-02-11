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
<div class="wrap riseup-admin" style="padding: 10px 20px 20px 10px;">
    <h1>
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
                <th class="column-timestamp"><?php esc_html_e('Time', 'riseup-asia-uploader'); ?></th>
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
                <?php 
                $current_date_group = '';
                foreach ($logs as $log): 
                    $triggered_by = isset($log['triggered_by']) ? $log['triggered_by'] : '';
                    $source_machine = isset($log['source_machine']) ? $log['source_machine'] : '';
                    $plugin_version = isset($log['plugin_version']) ? $log['plugin_version'] : '';
                    $upload_source = isset($log['upload_source']) ? $log['upload_source'] : '';
                    $trigger_class = isset($trigger_classes[$triggered_by]) ? $trigger_classes[$triggered_by] : 'trigger-unknown';
                    $trigger_label = isset($trigger_labels[$triggered_by]) ? $trigger_labels[$triggered_by] : ($triggered_by ?: '—');
                    $upload_source_class = isset($upload_source_classes[$upload_source]) ? $upload_source_classes[$upload_source] : 'source-unknown';
                    $upload_source_label = isset($upload_source_labels[$upload_source]) ? $upload_source_labels[$upload_source] : ($upload_source ?: '—');
                    
                    // Date grouping
                    $log_timestamp = strtotime($log['created_at']);
                    $log_date = date('Y-m-d', $log_timestamp);
                    $log_date_display = date('F j, Y', $log_timestamp); // e.g., "February 10, 2026"
                    $log_time_display = date('g:i A', $log_timestamp);  // e.g., "2:30 PM"
                    
                    // Insert date group header when date changes
                    if ($log_date !== $current_date_group):
                        $current_date_group = $log_date;
                        // Check if today/yesterday
                        $today = date('Y-m-d');
                        $yesterday = date('Y-m-d', strtotime('-1 day'));
                        if ($log_date === $today) {
                            $date_label = __('Today', 'riseup-asia-uploader') . ' — ' . $log_date_display;
                        } elseif ($log_date === $yesterday) {
                            $date_label = __('Yesterday', 'riseup-asia-uploader') . ' — ' . $log_date_display;
                        } else {
                            $date_label = $log_date_display;
                        }
                ?>
                    <tr class="date-group-header">
                        <td colspan="11">
                            <span class="date-group-label"><?php echo esc_html($date_label); ?></span>
                        </td>
                    </tr>
                <?php endif; ?>
                    <tr class="riseup-log-row <?php echo (!empty($log['details']) || !empty($log['error_msg'])) ? 'has-details' : ''; ?>" 
                        <?php if (!empty($log['details'])): ?>
                            data-details="<?php echo esc_attr(json_encode($log['details'])); ?>"
                        <?php elseif (!empty($log['error_msg'])): ?>
                            data-details="<?php echo esc_attr(json_encode(array('error' => $log['error_msg']))); ?>"
                        <?php endif; ?>>
                        <td class="column-id"><?php echo esc_html($log['id']); ?></td>
                        <td class="column-timestamp">
                            <span class="timestamp" title="<?php echo esc_attr($log['created_at']); ?>">
                                <?php echo esc_html($log_time_display); ?>
                            </span>
                        </td>
                        <td class="column-action">
                            <span class="action-badge action-<?php echo esc_attr($log['action']); ?>">
                                <?php echo esc_html($action_labels[$log['action']] ?? $log['action']); ?>
                            </span>
                        </td>
                        <td class="column-plugin">
                            <?php if (!empty($log['plugin_slug'])): ?>
                                <span class="plugin-target-badge"><?php echo esc_html($log['plugin_slug']); ?></span>
                                <?php if (!empty($log['plugin_file']) && $log['plugin_file'] !== $log['plugin_slug']): ?>
                                    <br><small class="plugin-file" title="<?php echo esc_attr($log['plugin_file']); ?>"><?php echo esc_html($log['plugin_file']); ?></small>
                                <?php endif; ?>
                            <?php elseif (!empty($log['post_id'])): ?>
                                <span class="plugin-target-badge target-post"><?php esc_html_e('Post', 'riseup-asia-uploader'); ?> #<?php echo esc_html($log['post_id']); ?></span>
                            <?php else: ?>
                                <span class="na">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="column-version">
                            <?php if (!empty($plugin_version)): ?>
                                <?php if ($plugin_version === RISEUP_VERSION): ?>
                                    <code class="version-badge version-current">v<?php echo esc_html($plugin_version); ?></code>
                                <?php else: ?>
                                    <code class="version-badge version-old">v<?php echo esc_html($plugin_version); ?></code>
                                <?php endif; ?>
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
                                <span class="source-badge" title="<?php esc_attr_e('Management Server Hostname', 'riseup-asia-uploader'); ?>">
                                    <?php echo esc_html($source_machine); ?>
                                </span>
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
    /* ================================================================
       MODERN ACTIVITY TABLE STYLES
       ================================================================ */

    /* Trigger badge colors with drop shadows */
    .trigger-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.08);
    }
    .trigger-api {
        background: #e3f2fd;
        color: #1565c0;
        border: 1px solid #90caf9;
    }
    .trigger-dashboard {
        background: #e8f5e9;
        color: #2e7d32;
        border: 1px solid #a5d6a7;
    }
    .trigger-agent {
        background: #fff3e0;
        color: #e65100;
        border: 1px solid #ffcc80;
    }
    .trigger-cron {
        background: #f3e5f5;
        color: #7b1fa2;
        border: 1px solid #ce93d8;
    }
    .trigger-cli {
        background: #eceff1;
        color: #455a64;
        border: 1px solid #b0bec5;
    }
    .trigger-unknown {
        background: #fafafa;
        color: #9e9e9e;
        border: 1px solid #e0e0e0;
    }

    /* Source badge - black bg, white text */
    .source-badge {
        display: inline-block;
        background: #1a1a2e;
        color: #ffffff;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-family: 'SFMono-Regular', 'Consolas', monospace;
        font-weight: 500;
        box-shadow: 0 2px 6px rgba(0,0,0,0.2);
        letter-spacing: 0.3px;
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

    /* Plugin/Target badges - colorful labels */
    .plugin-target-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        font-family: 'SFMono-Regular', 'Consolas', monospace;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #ffffff;
        box-shadow: 0 2px 6px rgba(102, 126, 234, 0.3);
        letter-spacing: 0.3px;
    }
    .target-post {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        box-shadow: 0 2px 6px rgba(245, 87, 108, 0.3);
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

    /* Version badge - base with drop shadow */
    .version-badge {
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .version-current {
        background: #d4edda;
        color: #155724;
        border: 1px solid #a3d9a5;
    }
    .version-old {
        background: #e8eaf6;
        color: #283593;
        border: 1px solid #c5cae9;
    }

    /* Upload source badge with drop shadow */
    .upload-source-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.08);
    }
    /* Upload Script - yellow bg, black text, border, drop shadow */
    .source-script {
        background: #ffd600;
        color: #1a1a1a;
        border: 1px solid #c6a700;
        font-weight: 700;
        box-shadow: 0 2px 8px rgba(255, 214, 0, 0.4);
    }
    .source-api {
        background: #e3f2fd;
        color: #1565c0;
        border: 1px solid #90caf9;
    }
    .source-admin {
        background: #e8f5e9;
        color: #2e7d32;
        border: 1px solid #a5d6a7;
    }
    .source-cli {
        background: #eceff1;
        color: #455a64;
        border: 1px solid #b0bec5;
    }
    .source-unknown {
        background: #fafafa;
        color: #9e9e9e;
        border: 1px solid #e0e0e0;
    }

    /* Table row hover and clickable */
    .riseup-logs-table tbody tr.riseup-log-row {
        transition: background-color 0.15s ease, box-shadow 0.15s ease;
    }
    .riseup-logs-table tbody tr.riseup-log-row:hover {
        background-color: #eef2ff !important;
        box-shadow: inset 3px 0 0 #667eea;
    }
    .riseup-logs-table tbody tr.riseup-log-row.has-details {
        cursor: pointer;
    }
    .riseup-logs-table tbody tr.riseup-log-row.has-details:hover {
        background-color: #e8eeff !important;
    }

    /* Action badges - enhanced with drop shadows */
    .action-badge {
        box-shadow: 0 2px 4px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.06);
        border-radius: 4px;
    }
    .action-upload_initiated {
        background: #e0f7fa;
        color: #00695c;
        border: 1px solid #80cbc4;
    }
    .action-upload,
    .action-upload_active {
        border: 1px solid #6dd297;
    }
    .action-enable {
        border: 1px solid #6dd297;
    }
    .action-disable {
        border: 1px solid #f0c36d;
    }
    .action-delete,
    .action-file_delete {
        border: 1px solid #f5a5a5;
    }
    .action-auth_failed {
        border: 1px solid #f5a5a5;
    }
    .action-post_create,
    .action-post_update,
    .action-category_create {
        border: 1px solid #a1c4fd;
    }
    .action-sync,
    .action-file_replace {
        border: 1px solid #c0c4c9;
    }
    .action-export_self {
        border: 1px solid #c9a7e4;
    }

    /* Snapshot action badges */
    .action-snapshot_create {
        background: rgba(20, 184, 166, 0.15);
        color: #0f766e;
        border: 1px solid rgba(20, 184, 166, 0.3);
    }
    .action-snapshot_restore {
        background: rgba(245, 158, 11, 0.15);
        color: #b45309;
        border: 1px solid rgba(245, 158, 11, 0.3);
    }
    .action-snapshot_delete {
        background: rgba(244, 63, 94, 0.15);
        color: #be123c;
        border: 1px solid rgba(244, 63, 94, 0.3);
    }
    .action-snapshot_export {
        background: rgba(6, 182, 212, 0.15);
        color: #0e7490;
        border: 1px solid rgba(6, 182, 212, 0.3);
    }
    .action-snapshot_import {
        background: rgba(99, 102, 241, 0.15);
        color: #4338ca;
        border: 1px solid rgba(99, 102, 241, 0.3);
    }
    .action-snapshot_cleanup {
        background: rgba(100, 116, 139, 0.15);
        color: #475569;
        border: 1px solid rgba(100, 116, 139, 0.3);
    }
    .action-snapshot_full_backup {
        background: rgba(16, 185, 129, 0.15);
        color: #047857;
        border: 1px solid rgba(16, 185, 129, 0.3);
    }
    .action-snapshot_incremental {
        background: rgba(132, 204, 22, 0.15);
        color: #4d7c0f;
        border: 1px solid rgba(132, 204, 22, 0.3);
    }
    .action-snapshot_restore_pertable {
        background: rgba(217, 119, 6, 0.15);
        color: #92400e;
        border: 1px solid rgba(217, 119, 6, 0.3);
    }
    .action-snapshot_import_pertable {
        background: rgba(79, 70, 229, 0.15);
        color: #3730a3;
        border: 1px solid rgba(79, 70, 229, 0.3);
    }
    .action-snapshot_settings_update {
        background: rgba(168, 85, 247, 0.15);
        color: #7e22ce;
        border: 1px solid rgba(168, 85, 247, 0.3);
    }

    /* Status badges - enhanced */
    .status-badge {
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        border-radius: 4px;
    }
    .status-success {
        border: 1px solid #6dd297;
    }
    .status-failed {
        border: 1px solid #f5a5a5;
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

    /* Date group header */
    .date-group-header td {
        background: linear-gradient(135deg, #f8f9fb 0%, #eef1f6 100%) !important;
        border-top: 2px solid #667eea;
        border-bottom: 1px solid #dcdcde;
        padding: 10px 12px !important;
        font-size: 0;
    }
    .date-group-label {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        font-weight: 700;
        color: #1e2a4a;
        letter-spacing: 0.3px;
        text-transform: none;
    }
    .date-group-label::before {
        content: '📅';
        font-size: 14px;
    }
    .date-group-header:hover td {
        background: linear-gradient(135deg, #f8f9fb 0%, #eef1f6 100%) !important;
    }
    </style>

    <script>
    jQuery(document).ready(function($) {
        // Toggle details modal via button
        $('.toggle-details').on('click', function(e) {
            e.stopPropagation();
            var details = $(this).data('details');
            var formatted = JSON.stringify(details, null, 2);
            $('#riseup-details-content').text(formatted);
            $('#riseup-details-modal').show();
        });

        // Clickable rows - open details modal when row has data
        $('.riseup-log-row.has-details').on('click', function(e) {
            // Don't trigger if clicking a button or link inside the row
            if ($(e.target).is('button, a, .toggle-details') || $(e.target).closest('button, a').length) {
                return;
            }
            var details = $(this).data('details');
            if (details) {
                var formatted = JSON.stringify(details, null, 2);
                $('#riseup-details-content').text(formatted);
                $('#riseup-details-modal').show();
            }
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
