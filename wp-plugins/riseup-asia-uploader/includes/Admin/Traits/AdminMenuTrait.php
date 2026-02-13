<?php
/**
 * AdminMenuTrait — Menu registration, submenus, and asset enqueuing.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\CapabilityType;

trait AdminMenuTrait {

    /**
     * Add admin menu items.
     */
    public function add_admin_menu() {
        $this->registerMainMenu();
        $this->registerSubmenus();
        $this->registerErrorSubmenu();
    }

    /**
     * Register the main admin menu page.
     */
    private function registerMainMenu() {
        add_menu_page(
            __('Riseup Asia Uploader', 'riseup-asia-uploader'),
            __('Riseup Uploader', 'riseup-asia-uploader'),
            CapabilityType::ManageOptions->value,
            'riseup-asia-uploader',
            array($this, 'render_logs_page'),
            'dashicons-upload',
            80
        );
    }

    /**
     * Register standard submenus.
     */
    private function registerSubmenus() {
        $submenus = array(
            array('riseup-asia-uploader', 'Activity Logs', 'render_logs_page'),
            array('riseup-asia-settings', 'Settings', 'render_settings_page'),
            array('riseup-asia-agents', 'Agent Sites', 'render_agents_page'),
            array('riseup-asia-snapshots', 'Snapshots', 'render_snapshots_page'),
        );

        foreach ($submenus as $item) {
            add_submenu_page(
                'riseup-asia-uploader',
                __($item[1], 'riseup-asia-uploader'),
                __($item[1], 'riseup-asia-uploader'),
                CapabilityType::ManageOptions->value,
                $item[0],
                array($this, $item[2])
            );
        }
    }

    /**
     * Register the error log submenu with notification bubble.
     */
    private function registerErrorSubmenu() {
        $error_bubble = $this->buildErrorBubble();

        add_submenu_page(
            'riseup-asia-uploader',
            __('Error Log', 'riseup-asia-uploader'),
            __('Error Log', 'riseup-asia-uploader') . $error_bubble,
            CapabilityType::ManageOptions->value,
            'riseup-asia-errors',
            array($this, 'render_errors_page')
        );
    }

    /**
     * Build the error count bubble HTML.
     *
     * @return string HTML string or empty.
     */
    private function buildErrorBubble(): string {
        $unseen = $this->get_unseen_error_count();
        if ($unseen <= 0) {
            return '';
        }

        return sprintf(' <span class="riseup-error-bubble">%d</span>', $unseen);
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook Current admin page.
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'riseup-asia') === false) {
            return;
        }

        wp_enqueue_style(
            'riseup-admin-styles',
            plugins_url('assets/admin.css', dirname(__FILE__)),
            array(),
            PLUGIN_VERSION
        );
    }
}
