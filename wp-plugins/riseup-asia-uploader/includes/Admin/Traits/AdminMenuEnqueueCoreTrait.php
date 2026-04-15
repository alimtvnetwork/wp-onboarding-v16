<?php
/**
 * AdminMenuEnqueueCoreTrait — Global asset enqueuing and per-page routing.
 *
 * @package RiseupAsia\Admin\Traits
 * @since   2.37.0
 */

namespace RiseupAsia\Admin\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\AdminPageType;
use RiseupAsia\Enums\PluginConfigType;

trait AdminMenuEnqueueCoreTrait {

    /** Enqueue admin assets. */
    public function enqueueAdminAssets($hook) {
        $isNonPluginPage = (strpos($hook, 'riseup-asia') === false);

        if ($isNonPluginPage) {
            return;
        }

        $version = PluginConfigType::Version->value;
        $pluginSlug = PluginConfigType::Slug->value;
        $pluginFile = dirname(__FILE__, 4) . '/' . $pluginSlug . '.php';

        // Global admin CSS (always loaded on plugin pages)
        wp_enqueue_style(
            'riseup-admin-styles',
            plugins_url('assets/admin.css', $pluginFile),
            [],
            $version,
        );

        // Determine current page from query string
        $currentPage = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';

        // Per-page CSS + JS + localize
        $this->enqueuePerPageAssets($currentPage, $pluginFile, $version, $pluginSlug);
    }

    /** Enqueue page-specific CSS, JS, and localized data. */
    private function enqueuePerPageAssets(string $currentPage, string $pluginFile, string $version, string $pluginSlug): void {
        $slug = PluginConfigType::Slug->value;

        switch ($currentPage) {
            case AdminPageType::Errors->value:
                $this->enqueueErrorsAssets($pluginFile, $version, $pluginSlug);
                break;

            case AdminPageType::Settings->value:
                $this->enqueueSettingsAssets($pluginFile, $version, $pluginSlug);
                break;

            case AdminPageType::Agents->value:
                $this->enqueueAgentsAssets($pluginFile, $version, $pluginSlug);
                break;

            case AdminPageType::Snapshots->value:
                $this->enqueueSnapshotsAssets($pluginFile, $version, $pluginSlug);
                break;

            case AdminPageType::License->value:
                $this->enqueueLicenseAssets($pluginFile, $version, $pluginSlug);
                break;

            case AdminPageType::Feedback->value:
                $this->enqueueFeedbackAssets($pluginFile, $version, $pluginSlug);
                break;

            case $slug: // Main page = Activity Logs
                $this->enqueueLogsAssets($pluginFile, $version, $pluginSlug);
                break;
        }
    }
}
