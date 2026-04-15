<?php
/**
 * Admin Snapshots Dashboard Template
 *
 * Slim orchestrator — delegates all sections to partials.
 *
 * @package RiseupAsiaUploader
 * @since   1.9.0
 * @updated 2.33.0 - Split into partials for Phase 11 compliance
 */

use RiseupAsia\Enums\PluginConfigType;

if (!defined('ABSPATH')) {
    exit;
}

$pluginName = PluginConfigType::Name->value;
$pluginSlug = PluginConfigType::Slug->value;
?>
<div class="wrap riseup-admin">
    <?php
    $pageIcon = 'dashicons-database';
    $pageTitle = __('Database Snapshots', $pluginSlug);
    $pageDescription = __('Create, manage, and restore database snapshots. Snapshots are stored as SQLite files and can be exported/imported as ZIP archives.', $pluginSlug);
    include __DIR__ . '/partials/shared/page-header.php';
    ?>

    <?php include __DIR__ . '/partials/snapshots/actions-bar.php'; ?>
    <?php include __DIR__ . '/partials/snapshots/progress-panel.php'; ?>
    <?php include __DIR__ . '/partials/snapshots/snapshot-list.php'; ?>
    <?php include __DIR__ . '/partials/snapshots/analytics-row.php'; ?>
    <?php include __DIR__ . '/partials/snapshots/snapshot-settings.php'; ?>
    <?php include __DIR__ . '/partials/snapshots/providers-info.php'; ?>
</div>

<?php include __DIR__ . '/partials/snapshots/modals.php'; ?>
