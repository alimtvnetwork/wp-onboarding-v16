<?php
/**
 * Admin Error Log Page Template
 *
 * Tabbed interface showing Error Sessions, Log file, Error file, and Stack Trace file.
 * Each file tab has Copy, Download, and Clear actions.
 *
 * @package RiseupAsiaUploader
 * @since   1.28.0
 * @updated 1.30.0 - Added tabbed file viewer with Copy/Download/Clear
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

$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'sessions';
$nonce = wp_create_nonce('riseup_admin_nonce');
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

    <?php if (!empty($db_error_message)): ?>
    <div class="notice notice-warning" style="padding: 12px; margin-bottom: 16px;">
        <p><strong><span class="dashicons dashicons-info" style="color: #dba617;"></span> <?php echo esc_html($db_error_message); ?></strong></p>
        <p class="description"><?php esc_html_e('The file-based log tabs (Log, Error Log, Stack Trace) are still available below.', 'riseup-asia-uploader'); ?></p>
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
        <?php esc_html_e('View all errors, warnings, and log files captured by the plugin.', 'riseup-asia-uploader'); ?>
    </p>

    <!-- Tab Navigation -->
    <nav class="nav-tab-wrapper riseup-tabs">
        <a href="<?php echo esc_url(add_query_arg('tab', 'sessions')); ?>"
           class="nav-tab <?php echo $active_tab === 'sessions' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-database"></span>
            <?php esc_html_e('Error Sessions', 'riseup-asia-uploader'); ?>
            <?php if ($unseen_count > 0): ?>
                <span class="tab-badge"><?php echo esc_html($unseen_count); ?></span>
            <?php endif; ?>
        </a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'log')); ?>"
           class="nav-tab <?php echo $active_tab === 'log' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-text-page"></span>
            <?php esc_html_e('Log', 'riseup-asia-uploader'); ?>
        </a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'error')); ?>"
           class="nav-tab <?php echo $active_tab === 'error' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-warning"></span>
            <?php esc_html_e('Error Log', 'riseup-asia-uploader'); ?>
        </a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'stacktrace')); ?>"
           class="nav-tab <?php echo $active_tab === 'stacktrace' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-editor-code"></span>
            <?php esc_html_e('Stack Trace', 'riseup-asia-uploader'); ?>
        </a>
    </nav>

    <!-- Tab Content -->
    <div class="riseup-tab-content">

    <?php if ($active_tab === 'sessions'): ?>
        <!-- ============================================================ -->
        <!-- ERROR SESSIONS TAB (original table view)                      -->
        <!-- ============================================================ -->

        <!-- Filters -->
        <div class="riseup-filters">
            <form method="get" action="">
                <input type="hidden" name="page" value="riseup-asia-errors">
                <input type="hidden" name="tab" value="sessions">
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
                    <a href="<?php echo esc_url(admin_url('admin.php?page=riseup-asia-errors&tab=sessions')); ?>" class="button"><?php esc_html_e('Reset', 'riseup-asia-uploader'); ?></a>
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
                        'base'      => add_query_arg(array('paged' => '%#%', 'tab' => 'sessions')),
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

    <?php else: ?>
        <!-- ============================================================ -->
        <!-- FILE VIEWER TAB (log, error, stacktrace)                      -->
        <!-- ============================================================ -->
        <?php
        $file_type = $active_tab; // 'log', 'error', or 'stacktrace'
        $file_labels = array(
            'log'        => __('General Log', 'riseup-asia-uploader') . ' (log.txt)',
            'error'      => __('Error Log', 'riseup-asia-uploader') . ' (error.txt)',
            'stacktrace' => __('Stack Trace', 'riseup-asia-uploader') . ' (stacktrace.txt)',
        );
        $file_label = isset($file_labels[$file_type]) ? $file_labels[$file_type] : $file_type;
        ?>

        <div class="riseup-file-viewer-card">
            <div class="file-viewer-header">
                <h2><?php echo esc_html($file_label); ?></h2>
                <div class="file-viewer-actions">
                    <span class="file-size-label" id="file-size-label"></span>

                    <!-- Auto-refresh toggle -->
                    <label class="auto-refresh-toggle" title="<?php esc_attr_e('Auto-refresh every 3 seconds', 'riseup-asia-uploader'); ?>">
                        <input type="checkbox" id="chk-auto-refresh">
                        <span class="auto-refresh-label">
                            <span class="live-dot" id="live-dot"></span>
                            <?php esc_html_e('Live', 'riseup-asia-uploader'); ?>
                        </span>
                    </label>

                    <button type="button" class="button button-small" id="btn-refresh-log" title="<?php esc_attr_e('Refresh', 'riseup-asia-uploader'); ?>">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e('Refresh', 'riseup-asia-uploader'); ?>
                    </button>
                    <button type="button" class="button button-small" id="btn-copy-log" title="<?php esc_attr_e('Copy to Clipboard', 'riseup-asia-uploader'); ?>">
                        <span class="dashicons dashicons-clipboard"></span>
                        <?php esc_html_e('Copy', 'riseup-asia-uploader'); ?>
                    </button>
                    <button type="button" class="button button-small" id="btn-download-log" title="<?php esc_attr_e('Download File', 'riseup-asia-uploader'); ?>">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e('Download', 'riseup-asia-uploader'); ?>
                    </button>
                    <button type="button" class="button button-small button-link-delete" id="btn-clear-log" title="<?php esc_attr_e('Clear File', 'riseup-asia-uploader'); ?>">
                        <span class="dashicons dashicons-trash"></span>
                        <?php esc_html_e('Clear', 'riseup-asia-uploader'); ?>
                    </button>
                </div>
            </div>
            <div class="file-viewer-body">
                <div id="file-loading" class="file-loading">
                    <span class="spinner is-active"></span>
                    <?php esc_html_e('Loading file contents...', 'riseup-asia-uploader'); ?>
                </div>
                <pre id="file-content" class="file-content-pre" style="display: none;"></pre>
                <div id="file-empty" class="file-empty" style="display: none;">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php esc_html_e('File is empty or does not exist yet.', 'riseup-asia-uploader'); ?>
                </div>
            </div>
        </div>

    <?php endif; ?>

    </div><!-- .riseup-tab-content -->

    <!-- Details Modal (for sessions tab) -->
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

    <!-- Copy success toast -->
    <div id="riseup-toast" class="riseup-toast" style="display: none;">
        <span class="dashicons dashicons-yes"></span>
        <span id="riseup-toast-msg"></span>
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

    /* Tab badge */
    .tab-badge {
        display: inline-block;
        background: #dc3545;
        color: #fff;
        font-size: 10px;
        font-weight: 700;
        padding: 0 5px;
        min-width: 16px;
        height: 16px;
        line-height: 16px;
        text-align: center;
        border-radius: 8px;
        margin-left: 4px;
        vertical-align: middle;
    }

    /* Tab navigation enhancements */
    .riseup-tabs .nav-tab {
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .riseup-tabs .nav-tab .dashicons {
        font-size: 16px;
        width: 16px;
        height: 16px;
        line-height: 16px;
    }

    /* Tab content */
    .riseup-tab-content {
        margin-top: 15px;
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

    /* ======== FILE VIEWER ======== */
    .riseup-file-viewer-card {
        background: #fff;
        border: 1px solid #c3c4c7;
        border-radius: 4px;
        overflow: hidden;
    }

    .file-viewer-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 20px;
        border-bottom: 1px solid #dcdcde;
        background: #f6f7f7;
    }
    .file-viewer-header h2 {
        margin: 0;
        padding: 0;
        font-size: 14px;
        font-weight: 600;
        border: none;
    }

    .file-viewer-actions {
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .file-viewer-actions .button {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        font-size: 12px;
    }
    .file-viewer-actions .dashicons {
        font-size: 14px;
        width: 14px;
        height: 14px;
        line-height: 14px;
    }

    .file-size-label {
        font-size: 11px;
        color: #646970;
        margin-right: 6px;
    }

    /* Auto-refresh toggle */
    .auto-refresh-toggle {
        display: inline-flex;
        align-items: center;
        cursor: pointer;
        margin-right: 4px;
    }
    .auto-refresh-toggle input[type="checkbox"] {
        display: none;
    }
    .auto-refresh-label {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        background: #e2e3e5;
        color: #646970;
        transition: all 0.25s ease;
        user-select: none;
    }
    .auto-refresh-toggle input:checked + .auto-refresh-label {
        background: #d1e4dd;
        color: #0a7a4d;
    }
    .live-dot {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #a7aaad;
        transition: background 0.25s ease;
    }
    .auto-refresh-toggle input:checked + .auto-refresh-label .live-dot {
        background: #00a32a;
        animation: livePulse 1.5s ease-in-out infinite;
    }
    @keyframes livePulse {
        0%, 100% { box-shadow: 0 0 0 0 rgba(0, 163, 42, 0.5); }
        50% { box-shadow: 0 0 0 4px rgba(0, 163, 42, 0); }
    }

    .file-viewer-body {
        padding: 0;
    }

    .file-loading {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 30px 20px;
        color: #646970;
    }

    .file-content-pre {
        background: #1e1e1e;
        color: #d4d4d4;
        margin: 0;
        padding: 15px 20px;
        font-size: 12px;
        line-height: 1.6;
        max-height: 600px;
        overflow: auto;
        white-space: pre-wrap;
        word-break: break-word;
        border: none;
        border-radius: 0;
    }

    .file-empty {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 40px 20px;
        color: #646970;
        justify-content: center;
        font-size: 14px;
    }
    .file-empty .dashicons {
        color: #00a32a;
        font-size: 24px;
        width: 24px;
        height: 24px;
    }

    /* Toast */
    .riseup-toast {
        position: fixed;
        bottom: 30px;
        right: 30px;
        background: #1d2327;
        color: #fff;
        padding: 10px 18px;
        border-radius: 6px;
        font-size: 13px;
        display: flex;
        align-items: center;
        gap: 6px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        z-index: 100001;
        animation: toastIn 0.3s ease;
    }
    @keyframes toastIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .riseup-toast .dashicons {
        color: #00a32a;
    }
    </style>

    <script>
    jQuery(document).ready(function($) {
        var nonce = '<?php echo esc_js($nonce); ?>';
        var activeTab = '<?php echo esc_js($active_tab); ?>';

        // ====================================================================
        // Toast helper
        // ====================================================================
        function showToast(msg) {
            $('#riseup-toast-msg').text(msg);
            $('#riseup-toast').show();
            setTimeout(function() { $('#riseup-toast').fadeOut(300); }, 2500);
        }

        // ====================================================================
        // SESSIONS TAB handlers
        // ====================================================================
        // Dismiss flash banner
        $('#riseup-dismiss-flash').on('click', function() {
            var $btn = $(this);
            $btn.prop('disabled', true).text('<?php esc_html_e('Dismissing...', 'riseup-asia-uploader'); ?>');
            $.post(ajaxurl, { action: 'riseup_dismiss_error_flash', nonce: nonce }, function(response) {
                if (response.success) {
                    $('#riseup-flash-banner').slideUp(300);
                    $('.riseup-error-bubble').fadeOut(200);
                    $('.tab-badge').fadeOut(200);
                }
            }).fail(function() {
                $btn.prop('disabled', false).text('<?php esc_html_e('Mark as Seen', 'riseup-asia-uploader'); ?>');
            });
        });

        // Clear all errors
        $('#riseup-clear-errors').on('click', function() {
            if (!confirm('<?php esc_html_e('Delete all error log entries? This cannot be undone.', 'riseup-asia-uploader'); ?>')) return;
            var $btn = $(this);
            $btn.prop('disabled', true);
            $.post(ajaxurl, { action: 'riseup_clear_error_sessions', nonce: nonce }, function(response) {
                if (response.success) location.reload();
            }).fail(function() { $btn.prop('disabled', false); });
        });

        // View error details
        $('.toggle-error-details').on('click', function() {
            var context = $(this).data('context');
            var stack = $(this).attr('data-stack');
            if (context && context !== '{}' && Object.keys(context).length > 0) {
                $('#error-context-content').text(JSON.stringify(context, null, 2));
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
            if (e.target === this) $('#riseup-error-modal').hide();
        });
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') $('#riseup-error-modal').hide();
        });

        // ====================================================================
        // FILE VIEWER TAB handlers
        // ====================================================================
        if (activeTab === 'log' || activeTab === 'error' || activeTab === 'stacktrace') {
            var fileContent = '';
            var lastFileSize = 0;
            var pollTimer = null;
            var isPolling = false;
            var POLL_INTERVAL = 2000; // 2 seconds

            function formatBytes(bytes) {
                if (bytes === 0) return '0 B';
                var k = 1024;
                var sizes = ['B', 'KB', 'MB', 'GB'];
                var i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
            }

            function loadFileContent(silent) {
                if (!silent) {
                    $('#file-loading').show();
                    $('#file-content').hide();
                    $('#file-empty').hide();
                }

                $.post(ajaxurl, {
                    action: 'riseup_read_log_file',
                    nonce: nonce,
                    file_type: activeTab
                }, function(response) {
                    if (!silent) $('#file-loading').hide();
                    if (response.success) {
                        var newContent = response.data.content || '';
                        var newSize = response.data.size || 0;

                        // Only update DOM if content actually changed
                        if (newSize !== lastFileSize || newContent !== fileContent) {
                            fileContent = newContent;
                            lastFileSize = newSize;

                            if (fileContent.length > 0) {
                                var pre = document.getElementById('file-content');
                                var wasAtBottom = pre.scrollTop + pre.clientHeight >= pre.scrollHeight - 20;
                                $('#file-content').text(fileContent).show();
                                $('#file-empty').hide();
                                // Auto-scroll to bottom only if user was already near bottom
                                if (wasAtBottom || !silent) {
                                    pre.scrollTop = pre.scrollHeight;
                                }
                            } else {
                                $('#file-content').hide();
                                $('#file-empty').show();
                            }
                            if (newSize > 0) {
                                $('#file-size-label').text(formatBytes(newSize));
                            } else {
                                $('#file-size-label').text('');
                            }
                        }
                    } else if (!silent) {
                        $('#file-empty').show();
                    }
                }).fail(function() {
                    if (!silent) {
                        $('#file-loading').hide();
                        $('#file-empty').show();
                    }
                });
            }

            // Auto-refresh polling
            function startPolling() {
                if (pollTimer) return;
                isPolling = true;
                pollTimer = setInterval(function() {
                    loadFileContent(true); // silent refresh
                }, POLL_INTERVAL);
            }

            function stopPolling() {
                isPolling = false;
                if (pollTimer) {
                    clearInterval(pollTimer);
                    pollTimer = null;
                }
            }

            $('#chk-auto-refresh').on('change', function() {
                if (this.checked) {
                    startPolling();
                } else {
                    stopPolling();
                }
            });

            // Stop polling when user leaves page
            $(window).on('beforeunload', function() {
                stopPolling();
            });

            // Initial load
            loadFileContent(false);

            // Refresh
            $('#btn-refresh-log').on('click', function() {
                loadFileContent(false);
                showToast('<?php esc_html_e('Refreshed', 'riseup-asia-uploader'); ?>');
            });

            // Copy
            $('#btn-copy-log').on('click', function() {
                if (!fileContent) {
                    showToast('<?php esc_html_e('Nothing to copy', 'riseup-asia-uploader'); ?>');
                    return;
                }
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(fileContent).then(function() {
                        showToast('<?php esc_html_e('Copied to clipboard!', 'riseup-asia-uploader'); ?>');
                    });
                } else {
                    // Fallback
                    var $temp = $('<textarea>');
                    $('body').append($temp);
                    $temp.val(fileContent).select();
                    document.execCommand('copy');
                    $temp.remove();
                    showToast('<?php esc_html_e('Copied to clipboard!', 'riseup-asia-uploader'); ?>');
                }
            });

            // Download
            $('#btn-download-log').on('click', function() {
                if (!fileContent) {
                    showToast('<?php esc_html_e('Nothing to download', 'riseup-asia-uploader'); ?>');
                    return;
                }
                var filenames = {
                    'log': 'log.txt',
                    'error': 'error.txt',
                    'stacktrace': 'stacktrace.txt'
                };
                var blob = new Blob([fileContent], { type: 'text/plain' });
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = filenames[activeTab] || 'log.txt';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                showToast('<?php esc_html_e('Download started', 'riseup-asia-uploader'); ?>');
            });

            // Clear
            $('#btn-clear-log').on('click', function() {
                if (!confirm('<?php esc_html_e('Clear this log file? This cannot be undone.', 'riseup-asia-uploader'); ?>')) return;
                var $btn = $(this);
                $btn.prop('disabled', true);

                $.post(ajaxurl, {
                    action: 'riseup_clear_log_file',
                    nonce: nonce,
                    file_type: activeTab
                }, function(response) {
                    $btn.prop('disabled', false);
                    if (response.success) {
                        fileContent = '';
                        lastFileSize = 0;
                        $('#file-content').hide();
                        $('#file-empty').show();
                        $('#file-size-label').text('');
                        showToast('<?php esc_html_e('File cleared', 'riseup-asia-uploader'); ?>');
                    }
                }).fail(function() {
                    $btn.prop('disabled', false);
                });
            });
        }
    });
    </script>
</div>
