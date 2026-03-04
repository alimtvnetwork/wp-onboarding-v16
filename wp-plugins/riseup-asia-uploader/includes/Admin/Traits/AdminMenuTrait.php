<?php
/**
 * AdminMenuTrait — Menu registration, submenus, and asset enqueuing.
 *
 * @package RiseupAsia\Admin\Traits
 * @since   2.0.0
 */

namespace RiseupAsia\Admin\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\AdminPageType;
use RiseupAsia\Enums\CapabilityType;
use RiseupAsia\Enums\PluginConfigType;

trait AdminMenuTrait {

    /** Add admin menu items. */
    public function addAdminMenu() {
        $this->registerMainMenu();
        $this->registerSubmenus();
        $this->registerErrorSubmenu();
    }

    /** Register the main admin menu page. */
    private function registerMainMenu() {
        $slug = PluginConfigType::Slug->value;
        $pluginSlug = $slug;

        add_menu_page(
            __('Riseup Asia Uploader', $pluginSlug),
            __('Riseup Uploader', $pluginSlug),
            CapabilityType::ManageOptions->value,
            $slug,
            array($this, 'renderLogsPage'),
            'dashicons-upload',
            80,
        );
    }

    /** Register standard submenus. */
    private function registerSubmenus() {
        $slug = PluginConfigType::Slug->value;
        $pluginSlug = $slug;

        $submenus = array(
            array(
                $slug,
                'Activity Logs',
                'renderLogsPage',
            ),
            array(
                AdminPageType::Settings->value,
                'Settings',
                'renderSettingsPage',
            ),
            array(
                AdminPageType::Agents->value,
                'Agent Sites',
                'renderAgentsPage',
            ),
            array(
                AdminPageType::Snapshots->value,
                'Snapshots',
                'renderSnapshotsPage',
            ),
            array(
                AdminPageType::License->value,
                'License',
                'renderLicensePage',
            ),
        );

        foreach ($submenus as $item) {
            add_submenu_page(
                $slug,
                __($item[1], $pluginSlug),
                __($item[1], $pluginSlug),
                CapabilityType::ManageOptions->value,
                $item[0],
                array($this, $item[2]),
            );
        }
    }

    /** Register the error log submenu with notification bubble. */
    private function registerErrorSubmenu() {
        $slug = PluginConfigType::Slug->value;
        $pluginSlug = $slug;
        $errorBubble = $this->buildErrorBubble();

        add_submenu_page(
            $slug,
            __('Error Log', $pluginSlug),
            __('Error Log', $pluginSlug) . $errorBubble,
            CapabilityType::ManageOptions->value,
            AdminPageType::Errors->value,
            array($this, 'renderErrorsPage'),
        );
    }

    /** Build the error count bubble HTML. */
    private function buildErrorBubble(): string {
        $unseen = $this->getUnseenErrorCount();
        if ($unseen <= 0) {
            return '';
        }

        return sprintf(' <span class="riseup-error-bubble">%d</span>', $unseen);
    }

    /** Enqueue admin assets. */
    public function enqueueAdminAssets($hook) {
        $isNonPluginPage = (strpos($hook, 'riseup-asia') === false);

        if ($isNonPluginPage) {
            return;
        }

        wp_enqueue_style(
            'riseup-admin-styles',
            plugins_url('assets/admin.css', dirname(__FILE__)),
            array(),
            PluginConfigType::Version->value,
        );
    }
}
