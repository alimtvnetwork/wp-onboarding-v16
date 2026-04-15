<?php
/**
 * Admin Settings Page Template
 *
 * Slim orchestrator — delegates all sections to partials.
 *
 * @package RiseupAsiaUploader
 * @since   1.5.0
 * @updated 2.33.0 - Split into partials for Phase 11 compliance
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\PluginConfigType;

$pluginName = PluginConfigType::Name->value;
$pluginSlug = PluginConfigType::Slug->value;
?>
<div class="wrap riseup-admin">
    <?php
    $pageIcon = 'dashicons-admin-settings';
    $pageTitle = $pluginName . ' - ' . __('Settings', $pluginSlug);
    $pageDescription = __('Configure API endpoints, authentication requirements, and auto-update settings.', $pluginSlug);
    include __DIR__ . '/partials/shared/page-header.php';
    ?>

    <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated']): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Settings saved successfully.', $pluginSlug); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="options.php">
        <?php settings_fields(PluginConfigType::SettingsGroup->value); ?>

        <?php include __DIR__ . '/partials/settings/section-plugin-info.php'; ?>
        <?php include __DIR__ . '/partials/settings/section-rest-endpoints.php'; ?>
        <?php include __DIR__ . '/partials/settings/section-auto-update.php'; ?>
        <?php include __DIR__ . '/partials/settings/section-endpoint-config.php'; ?>
        <?php include __DIR__ . '/partials/settings/section-snapshot-settings.php'; ?>
        <?php include __DIR__ . '/partials/settings/section-log-retrieval.php'; ?>
        <?php include __DIR__ . '/partials/settings/section-support-feedback.php'; ?>

        <?php submit_button(__('Save Settings', $pluginSlug)); ?>
    </form>
</div>
