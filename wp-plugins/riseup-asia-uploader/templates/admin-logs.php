<?php
/**
 * Admin Logs Page Template
 *
 * Slim orchestrator — delegates filters and table to partials.
 *
 * @package RiseupAsiaUploader
 * @since   1.5.0
 * @updated 2.33.0 - Split into partials for Phase 11 compliance
 */

use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\TriggerSourceType;
use RiseupAsia\Enums\UploadSourceType;

if (!defined('ABSPATH')) {
    exit;
}

$pluginName = PluginConfigType::Name->value;
$pluginSlug = PluginConfigType::Slug->value;

// Trigger source labels for display
$triggerLabels = [
    TriggerSourceType::Api->value       => __('API', $pluginSlug),
    TriggerSourceType::Dashboard->value => __('Dashboard', $pluginSlug),
    TriggerSourceType::Agent->value     => __('Agent Push', $pluginSlug),
    TriggerSourceType::Cron->value      => __('Cron', $pluginSlug),
    TriggerSourceType::Cli->value       => __('WP-CLI', $pluginSlug),
];

// Trigger source CSS classes for color coding
$triggerClasses = [
    TriggerSourceType::Api->value       => 'trigger-api',
    TriggerSourceType::Dashboard->value => 'trigger-dashboard',
    TriggerSourceType::Agent->value     => 'trigger-agent',
    TriggerSourceType::Cron->value      => 'trigger-cron',
    TriggerSourceType::Cli->value       => 'trigger-cli',
];

// Upload source labels for display
$uploadSourceLabels = [
    UploadSourceType::Script->value  => __('Upload Script', $pluginSlug),
    UploadSourceType::RestApi->value => __('REST API', $pluginSlug),
    UploadSourceType::AdminUi->value => __('Admin UI', $pluginSlug),
    UploadSourceType::WpCli->value   => __('WP-CLI', $pluginSlug),
];

// Upload source CSS classes for color coding
$uploadSourceClasses = [
    UploadSourceType::Script->value  => 'source-script',
    UploadSourceType::RestApi->value => 'source-api',
    UploadSourceType::AdminUi->value => 'source-admin',
    UploadSourceType::WpCli->value   => 'source-cli',
];
?>
<div class="wrap riseup-admin" style="padding: 10px 20px 20px 10px;">
    <?php
    $pageIcon = 'dashicons-list-view';
    $pageTitle = $pluginName . ' - ' . __('Activity Logs', $pluginSlug);
    $pageDescription = __('View all API activity and operations performed through this plugin.', $pluginSlug);
    include __DIR__ . '/partials/shared/page-header.php';
    ?>

    <?php include __DIR__ . '/partials/logs/log-filters.php'; ?>
    <?php include __DIR__ . '/partials/logs/log-table.php'; ?>
</div>
