<?php
/**
 * AdminMenuTrait — Menu registration, page rendering, and asset enqueuing.
 *
 * @package QUpload\Admin\Traits
 * @since   2.1.0
 */

namespace QUpload\Admin\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use QUpload\Enums\AdminPageType;
use QUpload\Enums\AdminTabType;
use QUpload\Enums\AjaxActionType;
use QUpload\Enums\CapabilityType;
use QUpload\Enums\EndpointType;
use QUpload\Enums\NonceType;
use QUpload\Enums\PathLogFileType;
use QUpload\Enums\PluginConfigType;
use QUpload\Enums\ResponseKeyType;
use QUpload\Helpers\DateHelper;
use QUpload\Helpers\PathHelper;
use QUpload\Logging\FileLogger;

trait AdminMenuTrait {

    /** Add admin menu items. */
    public function addAdminMenu(): void {
        $slug = AdminPageType::Dashboard->value;

        add_menu_page(
            PluginConfigType::Name->value,
            PluginConfigType::ShortName->value,
            CapabilityType::ManageOptions->value,
            $slug,
            [$this, 'renderDashboardPage'],
            'dashicons-upload',
            81,
        );

        add_submenu_page(
            $slug,
            __('Dashboard', PluginConfigType::Slug->value),
            __('Dashboard', PluginConfigType::Slug->value),
            CapabilityType::ManageOptions->value,
            $slug,
            [$this, 'renderDashboardPage'],
        );

        add_submenu_page(
            $slug,
            __('Error Logs', PluginConfigType::Slug->value),
            __('Error Logs', PluginConfigType::Slug->value),
            CapabilityType::ManageOptions->value,
            AdminPageType::Errors->value,
            [$this, 'renderErrorsPage'],
        );
    }

    /** Render the dashboard page. */
    public function renderDashboardPage(): void {
        include dirname(__FILE__, 4) . '/templates/admin-dashboard.php';
    }

    /** Render the error logs page. */
    public function renderErrorsPage(): void {
        include dirname(__FILE__, 4) . '/templates/admin-errors.php';
    }

    /** Enqueue admin assets. */
    public function enqueueAdminAssets(string $hook): void {
        $isNonPluginPage = (strpos($hook, 'qupload') === false);

        if ($isNonPluginPage) {
            return;
        }

        $version = PluginConfigType::Version->value;
        $pluginSlug = PluginConfigType::Slug->value;
        $pluginDir = dirname(__FILE__, 4);

        // Shared CSS (variables, keyframes, modals)
        wp_enqueue_style(
            'qupload-admin-shared',
            plugins_url('assets/css/admin-shared.css', $pluginDir . '/' . $pluginSlug . '.php'),
            [],
            $version,
        );

        // Global admin CSS
        wp_enqueue_style(
            'qupload-admin-styles',
            plugins_url('assets/css/admin.css', $pluginDir . '/' . $pluginSlug . '.php'),
            ['qupload-admin-shared'],
            $version,
        );

        $currentPage = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';

        if ($currentPage === AdminPageType::Errors->value) {
            $this->enqueueErrorsAssets($pluginDir, $version, $pluginSlug);
        }
    }

    /** Enqueue Error Logs page assets. */
    private function enqueueErrorsAssets(string $pluginDir, string $version, string $pluginSlug): void {
        wp_enqueue_style(
            'qupload-admin-errors',
            plugins_url('assets/css/admin-errors.css', $pluginDir . '/' . $pluginSlug . '.php'),
            ['qupload-admin-shared', 'qupload-admin-styles'],
            $version,
        );

        wp_enqueue_script(
            'qupload-admin-errors',
            plugins_url('assets/js/admin-errors.js', $pluginDir . '/' . $pluginSlug . '.php'),
            ['jquery'],
            $version,
            true,
        );

        $activeTab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : AdminTabType::Log->value;

        wp_localize_script('qupload-admin-errors', 'QUploadErrors', [
            'nonce'     => wp_create_nonce(NonceType::Admin->value),
            'activeTab' => $activeTab,
            'actions'   => [
                'readLogFile'  => AjaxActionType::ReadLogFile->value,
                'clearLogFile' => AjaxActionType::ClearLogFile->value,
            ],
            'tabs'      => [
                'log'        => AdminTabType::Log->value,
                'error'      => AdminTabType::Error->value,
                'stacktrace' => AdminTabType::Stacktrace->value,
            ],
            'i18n'      => [
                'copied'          => __('Copied!', $pluginSlug),
                'confirmClearLog' => __('Are you sure you want to clear this log file?', $pluginSlug),
                'clearFailed'     => __('Failed to clear log file.', $pluginSlug),
            ],
        ]);
    }
}
