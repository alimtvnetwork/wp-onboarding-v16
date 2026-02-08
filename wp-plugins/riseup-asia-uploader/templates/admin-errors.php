<?php
/**
 * Admin Error Log Page Template
 *
 * Displays error sessions from the SQLite database with flash notifications.
 *
 * @package RiseupAsiaUploader
 * @since   1.28.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$level_colors = array(
    'ERROR' => '#dc3545',
    'WARN'  => '#fd7e14',
    'INFO'  => '#0d6efd',
    'DEBUG' => '#6c757d',
);
?>
<div class="wrap riseup-admin riseup-error-log">

    <?php if ($has_unseen): ?>
    <div id="riseup-flash-banner" class="riseup-flash-banner">
        <div class="flash-icon">⚠️</div>
        <div class="flash-content">
            <strong><?php echo esc_html(sprintf(
                _n('%d new error since your last visit', '%d new errors since your last visit', $unseen_count, 'riseup-asia-uploader'),
                $unseen_count
            )); ?></strong>
            <span class="flash-time"><?php esc_html_e('Latest:', 'riseup-asia-uploader'); ?> <?php echo esc_html($latest_error_time); ?></span>
        </div>
        <button type="button" id="riseup-dismiss-flash" class="button button-small flash-dismiss">
            <?php esc_html_e('Mark as Seen', 'riseup-asia-uploader'); ?>
        </button>
    </div>
    <?php endif; ?>

    <h1>
        <span class="dashicons dashicons-warning"></span>
        <?php esc_html_e('Error Log', 'riseup-asia-uploader'); ?>
        <?php if ($unseen_count > 0): ?>
            <span class="error-count-badge"><?php echo esc_html($unseen_count); ?></span>
        <?php endif; ?>
    </h1>

    <p class="description">
        <?php esc_html_e('View all errors and warnings captured by the plugin. New errors trigger a flash notification.', 'riseup-asia-uploader'); ?>
    </p>

    <!-- Filters -->
    <div class="riseup-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="riseup-asia-errors">
            <div class="filter-row">
                <label>
                    <span><?php esc_html_e('Level:', 'riseup-asia-uploader'); ?></span>
                    <select name="filter_level">
                        <option value=""><?php esc_html_e('All Levels', 'riseup-asia-uploader'); ?></option>
                        <option value="ERROR" <?php selected($filter_level, 'ERROR'); ?>><?php esc_html_e('Error', 'riseup-asia-uploader'); ?></option>
                        <option value="WARN" <?php selected($filter_level, 'WARN'); ?>><?php esc_html_e('Warning', 'riseup-asia-uploader'); ?></option>
                    </select>
                </label>

                <label>
                    <span><?php esc_html_e('Search:', 'riseup-asia-uploader'); ?></span>
                    <input type="text" name="filter_search" value="<?php echo esc_attr($filter_search); ?>" placeholder="<?php esc_attr_e('Search messages...', 'riseup-asia-uploader'); ?>">
                </label>

                <button type="submit" class="button button-primary"><?php esc_html_e('Filter', 'riseup-asia-uploader'); ?></button>
                <a href="<?php echo esc_url(admin_url('admin.php?page=riseup-asia-errors')); ?>" class="button"><?php esc_html_e('Reset', 'riseup-asia-uploader'); ?></a>

                <button type="button" id="riseup-clear-errors" class="button button-link-delete" style="margin-left: auto;">
                    <?php esc_html_e('Clear All Errors', 'riseup-asia-uploader'); ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Stats -->
    <div class="riseup-stats">
        <span class="stat-item">
            <strong><?php echo esc_html($total); ?></strong>
            <?php esc_html_e('total errors', 'riseup-asia-uploader'); ?>
        </span>
        <?php if ($page > 1 || $page < $total_pages): ?>
            <span class="stat-item">
                <?php esc_html_e('Page', 'riseup-asia-uploader'); ?> <?php echo esc_html($page); ?>
                <?php esc_html_e('of', 'riseup-asia-uploader'); ?> <?php echo esc_html($total_pages); ?>
            </span>
        <?php endif; ?>
    </div>

    <!-- Error Log Table -->
    <table class="wp-list-table widefat fixed striped riseup-error-table">
        <thead>
            <tr>
                <th class="column-id" style="width: 50px;"><?php esc_html_e('ID', 'riseup-asia-uploader'); ?></th>
                <th class="column-timestamp" style="width: 160px;"><?php esc_html_e('Timestamp', 'riseup-asia-uploader'); ?></th>
                <th class="column-level" style="width: 70px;"><?php esc_html_e('Level', 'riseup-asia-uploader'); ?></th>
                <th class="column-file" style="width: 180px;"><?php esc_html_e('Source', 'riseup-asia-uploader'); ?></th>
                <th class="column-message"><?php esc_html_e('Message', 'riseup-asia-uploader'); ?></th>
                <th class="column-actions" style="width: 80px;"><?php esc_html_e('Details', 'riseup-asia-uploader'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($errors)): ?>
                <tr>
                    <td colspan="6" class="no-items"><?php esc_html_e('No errors found. 🎉', 'riseup-asia-uploader'); ?></td>
                </tr>
            <?php else: ?>
                <?php foreach ($errors as $error): ?>
                    <?php
                    $is_new = ($error['id'] > $last_seen_id);
                    $level  = strtoupper($error['level']);
                    $color  = isset($level_colors[$level]) ? $level_colors[$level] : '#6c757d';
                    ?>
                    <tr class="<?php echo $is_new ? 'error-row-new' : ''; ?>">
                        <td class="column-id">
                            <?php echo esc_html($error['id']); ?>
                            <?php if ($is_new): ?>
                                <span class="new-badge">NEW</span>
                            <?php endif; ?>
                        </td>
                        <td class="column-timestamp">
                            <span class="timestamp"><?php echo esc_html(date('Y-m-d H:i:s', strtotime($error['created_at']))); ?></span>
                        </td>
                        <td class="column-level">
                            <span class="level-badge" style="background: <?php echo esc_attr($color); ?>;">
                                <?php echo esc_html($level); ?>
                            </span>
                        </td>
                        <td class="column-file">
                            <?php if (!empty($error['file'])): ?>
                                <code class="source-file"><?php echo esc_html(basename($error['file'])); ?>:<?php echo esc_html($error['line']); ?></code>
                            <?php else: ?>
                                <span class="na">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="column-message">
                            <span class="error-message"><?php echo esc_html($error['message']); ?></span>
                        </td>
                        <td class="column-actions">
                            <?php if (!empty($error['context_json']) || !empty($error['stack_trace'])): ?>
                                <button type="button" class="button button-small toggle-error-details"
                                    data-context="<?php echo esc_attr($error['context_json'] ?: '{}'); ?>"
                                    data-stack="<?php echo esc_attr($error['stack_trace'] ?: ''); ?>">
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
                echo paginate_links(array(
                    'base'      => add_query_arg('paged', '%#%'),
                    'format'    => '',
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'total'     => $total_pages,
                    'current'   => $page,
                ));
                ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Details Modal -->
    <div id="riseup-error-modal" class="riseup-modal" style="display: none;">
        <div class="riseup-modal-content" style="max-width: 800px;">
            <div class="riseup-modal-header">
                <h3><?php esc_html_e('Error Details', 'riseup-asia-uploader'); ?></h3>
                <button type="button" class="riseup-modal-close">&times;</button>
            </div>
            <div class="riseup-modal-body">
                <div id="error-context-section" style="display:none;">
                    <h4><?php esc_html_e('Context', 'riseup-asia-uploader'); ?></h4>
                    <pre id="error-context-content" class="error-detail-pre"></pre>
                </div>
                <div id="error-stack-section" style="display:none;">
                    <h4><?php esc_html_e('Stack Trace', 'riseup-asia-uploader'); ?></h4>
                    <pre id="error-stack-content" class="error-detail-pre"></pre>
                </div>
            </div>
        </div>
    </div>

    <style>
    /* Flash banner */
    .riseup-flash-banner {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 20px;
        margin-bottom: 20px;
        background: linear-gradient(135deg, #ff4444 0%, #cc0000 100%);
        color: #fff;
        border-radius: 6px;
        box-shadow: 0 4px 15px rgba(220, 53, 69, 0.4);
        animation: flashPulse 2s ease-in-out 3;
    }
    @keyframes flashPulse {
        0%, 100% { box-shadow: 0 4px 15px rgba(220, 53, 69, 0.4); }
        50% { box-shadow: 0 4px 25px rgba(220, 53, 69, 0.8); }
    }
    .flash-icon { font-size: 24px; }
    .flash-content { flex: 1; }
    .flash-content strong { display: block; font-size: 14px; }
    .flash-time { font-size: 12px; opacity: 0.85; }
    .flash-dismiss {
        background: rgba(255,255,255,0.2) !important;
        color: #fff !important;
        border-color: rgba(255,255,255,0.3) !important;
    }
    .flash-dismiss:hover {
        background: rgba(255,255,255,0.35) !important;
    }

    /* Error count badge in title */
    .error-count-badge {
        display: inline-block;
        background: #dc3545;
        color: #fff;
        font-size: 12px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 10px;
        vertical-align: middle;
        margin-left: 6px;
    }

    /* New error row highlight */
    .error-row-new {
        background: #fff8f8 !important;
        border-left: 3px solid #dc3545;
    }
    .new-badge {
        display: inline-block;
        background: #dc3545;
        color: #fff;
        font-size: 9px;
        font-weight: 700;
        padding: 1px 5px;
        border-radius: 3px;
        margin-left: 4px;
        vertical-align: middle;
    }

    /* Level badge */
    .level-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: 600;
        color: #fff;
        text-transform: uppercase;
    }

    /* Source file */
    .source-file {
        background: #f1f3f5;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 11px;
        word-break: break-all;
    }

    /* Error message */
    .error-message {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        font-size: 13px;
        word-break: break-word;
    }

    /* Detail pre blocks */
    .error-detail-pre {
        background: #1e1e1e;
        color: #d4d4d4;
        padding: 15px;
        border-radius: 4px;
        font-size: 12px;
        max-height: 400px;
        overflow: auto;
        white-space: pre-wrap;
        word-break: break-word;
    }

    /* Menu notification bubble */
    .riseup-error-bubble {
        display: inline-block;
        background: #dc3545;
        color: #fff;
        font-size: 9px;
        font-weight: 700;
        padding: 0 5px;
        min-width: 16px;
        height: 16px;
        line-height: 16px;
        text-align: center;
        border-radius: 8px;
        margin-left: 5px;
        vertical-align: middle;
    }
    </style>

    <script>
    jQuery(document).ready(function($) {
        // Dismiss flash banner
        $('#riseup-dismiss-flash').on('click', function() {
            var $btn = $(this);
            $btn.prop('disabled', true).text('<?php esc_html_e('Dismissing...', 'riseup-asia-uploader'); ?>');

            $.post(ajaxurl, {
                action: 'riseup_dismiss_error_flash',
                nonce: '<?php echo wp_create_nonce('riseup_admin_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    $('#riseup-flash-banner').slideUp(300);
                    // Remove menu bubble
                    $('.riseup-error-bubble').fadeOut(200);
                }
            }).fail(function() {
                $btn.prop('disabled', false).text('<?php esc_html_e('Mark as Seen', 'riseup-asia-uploader'); ?>');
            });
        });

        // Clear all errors
        $('#riseup-clear-errors').on('click', function() {
            if (!confirm('<?php esc_html_e('Delete all error log entries? This cannot be undone.', 'riseup-asia-uploader'); ?>')) {
                return;
            }
            var $btn = $(this);
            $btn.prop('disabled', true);

            $.post(ajaxurl, {
                action: 'riseup_clear_error_sessions',
                nonce: '<?php echo wp_create_nonce('riseup_admin_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                }
            }).fail(function() {
                $btn.prop('disabled', false);
            });
        });

        // View error details
        $('.toggle-error-details').on('click', function() {
            var context = $(this).data('context');
            var stack = $(this).attr('data-stack');

            if (context && context !== '{}' && Object.keys(context).length > 0) {
                var formatted = JSON.stringify(context, null, 2);
                $('#error-context-content').text(formatted);
                $('#error-context-section').show();
            } else {
                $('#error-context-section').hide();
            }

            if (stack && stack.length > 0) {
                $('#error-stack-content').text(stack);
                $('#error-stack-section').show();
            } else {
                $('#error-stack-section').hide();
            }

            $('#riseup-error-modal').show();
        });

        // Close modal
        $('.riseup-modal-close, .riseup-modal').on('click', function(e) {
            if (e.target === this) {
                $('#riseup-error-modal').hide();
            }
        });
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                $('#riseup-error-modal').hide();
            }
        });
    });
    </script>
</div>
