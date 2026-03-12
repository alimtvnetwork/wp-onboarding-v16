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

use RiseupAsia\Enums\AdminPageType;
use RiseupAsia\Enums\AdminTabType;
use RiseupAsia\Enums\ColorGroupType;
use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Helpers\BooleanHelpers;
use RiseupAsia\Helpers\ColorConfig;
use RiseupAsia\Helpers\DateHelper;

$pluginName = PluginConfigType::Name->value;
$pluginSlug = PluginConfigType::Slug->value;

$levelColors = ColorConfig::getGroup(ColorGroupType::LogLevel);

$activeTab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : AdminTabType::Sessions->value;
?>
<div class="wrap riseup-admin riseup-error-log">

    <?php if ($hasUnseen): ?>
    <div id="riseup-flash-banner" class="riseup-flash-banner">
        <div class="flash-icon">⚠️</div>
        <div class="flash-content">
            <strong><?php echo esc_html(sprintf(
                _n('%d new error since your last visit', '%d new errors since your last visit', $unseenCount, $pluginSlug),
                $unseenCount
            )); ?></strong>
            <span class="flash-time"><?php esc_html_e('Latest:', $pluginSlug); ?> <?php echo esc_html($latestErrorTime); ?></span>
        </div>
        <button type="button" id="riseup-dismiss-flash" class="button button-small flash-dismiss">
            <?php esc_html_e('Mark as Seen', $pluginSlug); ?>
        </button>
    </div>
    <?php endif; ?>

    <?php
    $hasDbErrorMessage = BooleanHelpers::hasValue($dbErrorMessage ?? null);
    if ($hasDbErrorMessage):
    ?>
    <div class="notice notice-warning" style="padding: 12px; margin-bottom: 16px;">
        <p><strong><span class="dashicons dashicons-info" style="color: #dba617;"></span> <?php echo esc_html($dbErrorMessage); ?></strong></p>
        <p class="description"><?php esc_html_e('The file-based log tabs (Log, Error Log, Stack Trace) are still available below.', $pluginSlug); ?></p>
    </div>
    <?php endif; ?>

    <?php
    $pageIcon = 'dashicons-warning';
    $pageTitle = __('Error Log', $pluginSlug);
    $pageDescription = __('View all errors, warnings, and log files captured by the plugin.', $pluginSlug);
    $headerExtra = ($unseenCount > 0) ? '<span class="error-count-badge">' . esc_html($unseenCount) . '</span>' : '';
    $headerExtra .= ' <a href="' . esc_url(\RiseupAsia\Enums\AdminPageType::Feedback->adminUrl()) . '" class="button button-secondary riseup-report-issue-btn"><span class="dashicons dashicons-feedback"></span> ' . esc_html__('Report Issue', $pluginSlug) . '</a>';
    include __DIR__ . '/partials/shared/page-header.php';
    ?>

    <!-- Tab Navigation -->
    <nav class="nav-tab-wrapper riseup-tabs">
        <a href="<?php echo esc_url(add_query_arg('tab', AdminTabType::Sessions->value)); ?>"
           class="nav-tab <?php echo $activeTab === AdminTabType::Sessions->value ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-database"></span>
            <?php esc_html_e('Error Sessions', $pluginSlug); ?>
            <?php if ($unseenCount > 0): ?>
                <span class="tab-badge"><?php echo esc_html($unseenCount); ?></span>
            <?php endif; ?>
        </a>
        <a href="<?php echo esc_url(add_query_arg('tab', AdminTabType::Log->value)); ?>"
           class="nav-tab <?php echo $activeTab === AdminTabType::Log->value ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-text-page"></span>
            <?php esc_html_e('Log', $pluginSlug); ?>
        </a>
        <a href="<?php echo esc_url(add_query_arg('tab', AdminTabType::Error->value)); ?>"
           class="nav-tab <?php echo $activeTab === AdminTabType::Error->value ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-warning"></span>
            <?php esc_html_e('Error Log', $pluginSlug); ?>
        </a>
        <a href="<?php echo esc_url(add_query_arg('tab', AdminTabType::Stacktrace->value)); ?>"
           class="nav-tab <?php echo $activeTab === AdminTabType::Stacktrace->value ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-editor-code"></span>
            <?php esc_html_e('Stack Trace', $pluginSlug); ?>
        </a>
    </nav>

    <!-- Tab Content -->
    <div class="riseup-tab-content">

    <?php if ($activeTab === AdminTabType::Sessions->value): ?>
        <!-- ============================================================ -->
        <!-- ERROR SESSIONS TAB (original table view)                      -->
        <!-- ============================================================ -->

        <!-- Filters -->
        <div class="riseup-filters">
            <form method="get" action="">
                <input type="hidden" name="page" value="<?php echo esc_attr(AdminPageType::Errors->value); ?>">
                <input type="hidden" name="tab" value="<?php echo esc_attr(AdminTabType::Sessions->value); ?>">
                <div class="filter-row">
                    <label>
                        <span><?php esc_html_e('Level:', $pluginSlug); ?></span>
                        <select name="filter_level">
                            <option value=""><?php esc_html_e('All Levels', $pluginSlug); ?></option>
                            <option value="<?php echo esc_attr(LogLevelType::Error->value); ?>" <?php selected($filterLevel, LogLevelType::Error->value); ?>><?php esc_html_e('Error', $pluginSlug); ?></option>
                            <option value="<?php echo esc_attr(LogLevelType::Warn->value); ?>" <?php selected($filterLevel, LogLevelType::Warn->value); ?>><?php esc_html_e('Warning', $pluginSlug); ?></option>
                        </select>
                    </label>
                    <label>
                        <span><?php esc_html_e('Search:', $pluginSlug); ?></span>
                        <input type="text" name="filter_search" value="<?php echo esc_attr($filterSearch); ?>" placeholder="<?php esc_attr_e('Search messages...', $pluginSlug); ?>">
                    </label>
                    <button type="submit" class="button button-primary"><?php esc_html_e('Filter', $pluginSlug); ?></button>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . AdminPageType::Errors->value . '&tab=' . AdminTabType::Sessions->value)); ?>" class="button"><?php esc_html_e('Reset', $pluginSlug); ?></a>
                    <button type="button" id="riseup-clear-errors" class="button button-link-delete" style="margin-left: auto;">
                        <?php esc_html_e('Clear All Errors', $pluginSlug); ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- Stats -->
        <div class="riseup-stats">
            <span class="stat-item">
                <strong><?php echo esc_html($total); ?></strong>
                <?php esc_html_e('total errors', $pluginSlug); ?>
            </span>
            <?php if ($page > 1 || $page < $totalPages): ?>
                <span class="stat-item">
                    <?php esc_html_e('Page', $pluginSlug); ?> <?php echo esc_html($page); ?>
                    <?php esc_html_e('of', $pluginSlug); ?> <?php echo esc_html($totalPages); ?>
                </span>
            <?php endif; ?>
        </div>

        <!-- Error Log Table -->
        <table class="wp-list-table widefat fixed striped riseup-error-table">
            <thead>
                <tr>
                    <th class="column-id" style="width: 50px;"><?php esc_html_e('ID', $pluginSlug); ?></th>
                    <th class="column-timestamp" style="width: 160px;"><?php esc_html_e('Timestamp', $pluginSlug); ?></th>
                    <th class="column-level" style="width: 70px;"><?php esc_html_e('Level', $pluginSlug); ?></th>
                    <th class="column-file" style="width: 180px;"><?php esc_html_e('Source', $pluginSlug); ?></th>
                    <th class="column-message"><?php esc_html_e('Message', $pluginSlug); ?></th>
                    <th class="column-actions" style="width: 80px;"><?php esc_html_e('Details', $pluginSlug); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($errors)): ?>
                    <tr>
                        <td colspan="6" class="no-items"><?php esc_html_e('No errors found. 🎉', $pluginSlug); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($errors as $error): ?>
                        <?php
                        $isNew = ($error['Id'] > $lastSeenId);
                        $level  = $error['Level'];
                        $color  = isset($levelColors[$level]) ? $levelColors[$level] : '#6c757d';
                        $hasFile = BooleanHelpers::hasValue($error['File'] ?? null);
                        $hasContext = BooleanHelpers::hasValue($error['ContextJson'] ?? null);
                        $hasStackTrace = BooleanHelpers::hasValue($error['StackTrace'] ?? null);
                        $hasDetailsData = $hasContext || $hasStackTrace;
                        $sourceDisplay = $hasFile ? basename($error['File']) . ':' . $error['Line'] : '—';
                        ?>
                        <tr class="<?php echo $isNew ? 'error-row-new' : ''; ?>">
                            <td class="column-id">
                                <?php echo esc_html($error['Id']); ?>
                                <?php if ($isNew): ?>
                                    <span class="new-badge">NEW</span>
                                <?php endif; ?>
                            </td>
                            <td class="column-timestamp">
                                <span class="timestamp"><?php echo esc_html(DateHelper::formatLogDisplay(strtotime($error['CreatedAt']))); ?></span>
                            </td>
                            <td class="column-level">
                                <span class="level-badge" style="background: <?php echo esc_attr($color); ?>;">
                                    <?php echo esc_html($level); ?>
                                </span>
                            </td>
                            <td class="column-file">
                                <?php if ($hasFile): ?>
                                    <code class="source-file"><?php echo esc_html(basename($error['File'])); ?>:<?php echo esc_html($error['Line']); ?></code>
                                <?php else: ?>
                                    <span class="na">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="column-message">
                                <span class="error-message"><?php echo esc_html($error['Message']); ?></span>
                            </td>
                            <td class="column-actions">
                                <?php if ($hasDetailsData): ?>
                                    <button type="button" class="button button-small toggle-error-details"
                                        data-context="<?php echo esc_attr($error['ContextJson'] ?: '{}'); ?>"
                                        data-stack="<?php echo esc_attr($error['StackTrace'] ?: ''); ?>"
                                        data-level="<?php echo esc_attr($level); ?>"
                                        data-message="<?php echo esc_attr($error['Message']); ?>"
                                        data-source="<?php echo esc_attr($sourceDisplay); ?>"
                                        data-timestamp="<?php echo esc_attr(DateHelper::formatDatetime(strtotime($error['CreatedAt']))); ?>">
                                        <?php esc_html_e('View', $pluginSlug); ?>
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
        <?php
        $paginationArgs = array(
            'base'      => add_query_arg(array('paged' => '%#%', 'tab' => AdminTabType::Sessions->value)),
            'format'    => '',
            'prev_text' => '&laquo;',
            'next_text' => '&raquo;',
            'total'     => $totalPages,
            'current'   => $page,
        );
        include __DIR__ . '/partials/shared/pagination.php';
        ?>

    <?php else: ?>
        <!-- ============================================================ -->
        <!-- FILE VIEWER TAB (log, error, stacktrace)                      -->
        <!-- ============================================================ -->
        <?php
        $fileType = $activeTab; // 'log', 'error', or 'stacktrace'
        $fileLabels = array(
            AdminTabType::Log->value        => __('General Log', $pluginSlug) . ' (log.txt)',
            AdminTabType::Error->value      => __('Error Log', $pluginSlug) . ' (error.txt)',
            AdminTabType::Stacktrace->value => __('Stack Trace', $pluginSlug) . ' (stacktrace.txt)',
        );
        $fileLabel = isset($fileLabels[$fileType]) ? $fileLabels[$fileType] : $fileType;
        ?>

        <div class="riseup-file-viewer-card">
            <div class="file-viewer-header">
                <h2><?php echo esc_html($fileLabel); ?></h2>
                <div class="file-viewer-actions">
                    <span class="file-size-label" id="file-size-label"></span>

                    <!-- Auto-refresh toggle -->
                    <label class="auto-refresh-toggle" title="<?php esc_attr_e('Auto-refresh every 3 seconds', $pluginSlug); ?>">
                        <input type="checkbox" id="chk-auto-refresh">
                        <span class="auto-refresh-label">
                            <span class="live-dot" id="live-dot"></span>
                            <?php esc_html_e('Live', $pluginSlug); ?>
                        </span>
                    </label>

                    <button type="button" class="button button-small" id="btn-refresh-log" title="<?php esc_attr_e('Refresh', $pluginSlug); ?>">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e('Refresh', $pluginSlug); ?>
                    </button>
                    <button type="button" class="button button-small" id="btn-copy-log" title="<?php esc_attr_e('Copy to Clipboard', $pluginSlug); ?>">
                        <span class="dashicons dashicons-clipboard"></span>
                        <?php esc_html_e('Copy', $pluginSlug); ?>
                    </button>
                    <button type="button" class="button button-small" id="btn-download-log" title="<?php esc_attr_e('Download File', $pluginSlug); ?>">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e('Download', $pluginSlug); ?>
                    </button>
                    <button type="button" class="button button-small button-link-delete" id="btn-clear-log" title="<?php esc_attr_e('Clear File', $pluginSlug); ?>">
                        <span class="dashicons dashicons-trash"></span>
                        <?php esc_html_e('Clear', $pluginSlug); ?>
                    </button>
                </div>
            </div>
            <div class="file-viewer-body">
                <div id="file-loading" class="file-loading">
                    <span class="spinner is-active"></span>
                    <?php esc_html_e('Loading file contents...', $pluginSlug); ?>
                </div>
                <pre id="file-content" class="file-content-pre" style="display: none;"></pre>
                <div id="file-empty" class="file-empty" style="display: none;">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php esc_html_e('File is empty or does not exist yet.', $pluginSlug); ?>
                </div>
            </div>
        </div>

    <?php endif; ?>

    </div><!-- .riseup-tab-content -->

    <!-- Details Modal (for sessions tab) -->
    <div id="riseup-error-modal" class="riseup-modal" style="display: none;">
        <div class="riseup-modal-content riseup-modal-fullscreen">
            <div class="riseup-modal-header">
                <div class="modal-header-left">
                    <span class="dashicons dashicons-warning modal-header-icon"></span>
                    <h3><?php esc_html_e('Error Details', $pluginSlug); ?></h3>
                    <span id="modal-error-level" class="level-badge modal-level-badge"></span>
                </div>
                <div class="modal-header-right">
                    <button type="button" class="button button-small modal-copy-btn" id="modal-copy-all" title="<?php esc_attr_e('Copy All', $pluginSlug); ?>">
                        <span class="dashicons dashicons-clipboard"></span>
                        <?php esc_html_e('Copy All', $pluginSlug); ?>
                    </button>
                    <button type="button" class="riseup-modal-close">&times;</button>
                </div>
            </div>
            <div class="riseup-modal-body">
                <!-- Error summary bar -->
                <div id="error-summary-bar" class="error-summary-bar">
                    <div class="summary-item">
                        <span class="summary-label"><?php esc_html_e('Message', $pluginSlug); ?></span>
                        <span id="summary-message" class="summary-value"></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label"><?php esc_html_e('Source', $pluginSlug); ?></span>
                        <code id="summary-source" class="summary-value source-file"></code>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label"><?php esc_html_e('Timestamp', $pluginSlug); ?></span>
                        <span id="summary-timestamp" class="summary-value"></span>
                    </div>
                </div>

                <!-- Modal tabs -->
                <div class="modal-tabs">
                    <button type="button" class="modal-tab active" data-modal-tab="context">
                        <span class="dashicons dashicons-editor-code"></span>
                        <?php esc_html_e('Context', $pluginSlug); ?>
                    </button>
                    <button type="button" class="modal-tab" data-modal-tab="stack">
                        <span class="dashicons dashicons-editor-alignleft"></span>
                        <?php esc_html_e('Stack Trace', $pluginSlug); ?>
                    </button>
                    <button type="button" class="modal-tab" data-modal-tab="raw">
                        <span class="dashicons dashicons-media-text"></span>
                        <?php esc_html_e('Raw JSON', $pluginSlug); ?>
                    </button>
                </div>

                <!-- Modal tab content -->
                <div class="modal-tab-content">
                    <div id="modal-context-tab" class="modal-tab-pane active">
                        <div id="modal-context-tree" class="context-tree"></div>
                    </div>
                    <div id="modal-stack-tab" class="modal-tab-pane" style="display: none;">
                        <pre id="modal-stack-content" class="stack-trace-pre"></pre>
                    </div>
                    <div id="modal-raw-tab" class="modal-tab-pane" style="display: none;">
                        <pre id="modal-raw-content" class="raw-json-pre"></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

