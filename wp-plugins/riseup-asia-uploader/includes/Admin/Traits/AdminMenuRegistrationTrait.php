<?php
/**
 * AdminMenuRegistrationTrait — Menu and submenu registration.
 *
 * @package RiseupAsia\Admin\Traits
 * @since   2.37.0
 */

namespace RiseupAsia\Admin\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\AdminPageType;
use RiseupAsia\Enums\CapabilityType;
use RiseupAsia\Enums\PluginConfigType;

trait AdminMenuRegistrationTrait {

    /** Add admin menu items. */
    public function addAdminMenu() {
        $this->registerMainMenu();
        $this->registerSubmenus();
        $this->registerErrorSubmenu();
        $this->registerFeedbackSubmenu();
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
            [$this, 'renderLogsPage'],
            'dashicons-upload',
            80,
        );
    }

    /** Register standard submenus. */
    private function registerSubmenus() {
        $slug = PluginConfigType::Slug->value;
        $pluginSlug = $slug;

        $submenus = [
            [
                $slug,
                'Activity Logs',
                'renderLogsPage',
            ],
            [
                AdminPageType::Settings->value,
                'Settings',
                'renderSettingsPage',
            ],
            [
                AdminPageType::Agents->value,
                'Agent Sites',
                'renderAgentsPage',
            ],
            [
                AdminPageType::Snapshots->value,
                'Snapshots',
                'renderSnapshotsPage',
            ],
            [
                AdminPageType::License->value,
                'License',
                'renderLicensePage',
            ],
        ];

        foreach ($submenus as $item) {
            add_submenu_page(
                $slug,
                __($item[1], $pluginSlug),
                __($item[1], $pluginSlug),
                CapabilityType::ManageOptions->value,
                $item[0],
                [$this, $item[2]],
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
            [$this, 'renderErrorsPage'],
        );
    }

    /** Register the feedback submenu. */
    private function registerFeedbackSubmenu() {
        $slug = PluginConfigType::Slug->value;
        $pluginSlug = $slug;

        add_submenu_page(
            $slug,
            __('Report / Feedback', $pluginSlug),
            __('Report / Feedback', $pluginSlug),
            CapabilityType::ManageOptions->value,
            AdminPageType::Feedback->value,
            [$this, 'renderFeedbackPage'],
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
}
