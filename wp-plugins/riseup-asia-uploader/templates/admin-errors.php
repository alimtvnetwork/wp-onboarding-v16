<?php
/**
 * Admin Error Log Page Template
 *
 * Slim orchestrator — delegates tabs and modal to partials.
 *
 * @package RiseupAsiaUploader
 * @since   1.28.0
 * @updated 2.33.0 - Split into partials for Phase 11 compliance
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\AdminPageType;
use RiseupAsia\Enums\AdminTabType;
use RiseupAsia\Enums\ColorGroupType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Helpers\BooleanHelpers;
use RiseupAsia\Helpers\ColorConfig;

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
    $headerExtra .= ' <button type="button" id="btn-clear-all-logs" class="button button-link-delete" style="margin-left: 8px;"><span class="dashicons dashicons-trash" style="vertical-align: middle;"></span> ' . esc_html__('Clear All Logs', $pluginSlug) . '</button>';
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
        <?php include __DIR__ . '/partials/errors/sessions-tab.php'; ?>
    <?php else: ?>
        <?php include __DIR__ . '/partials/errors/file-viewer-tab.php'; ?>
    <?php endif; ?>

    </div><!-- .riseup-tab-content -->

    <?php include __DIR__ . '/partials/errors/error-details-modal.php'; ?>
</div>
