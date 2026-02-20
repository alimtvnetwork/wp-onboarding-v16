<?php
/**
 * Plugin Name: Riseup Asia Uploader
 * Plugin URI: https://rasia.pro/alim-r-profile-v1
 * Description: Remote plugin management, blog post publishing, delta file sync, auto-update with 301 redirect resolution, and audit logging via REST API with Application Password authentication.
 * Version: 1.61.0
 * Author: MD ALIM UL KARIM
 * Author URI: https://rasia.pro/alim-r-profile-v1
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: riseup-asia-uploader
 * Requires at least: 5.6
 * Requires PHP: 8.2
 *
 * @package RiseupAsiaUploader
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\HookType;

use RiseupAsia\Activation\ActivationHandler;
use RiseupAsia\Core\Plugin;
use RiseupAsia\Admin\Admin;
use RiseupAsia\ErrorHandling\BootErrorCollector;

// =============================================================================
// PSR-4 AUTOLOADER — all RiseupAsia\ classes resolve automatically
// =============================================================================

require_once __DIR__ . '/includes/Autoloader.php';

register_activation_hook(__FILE__, [ActivationHandler::class, 'activate']);

/**
 * Initialize the plugin.
 */
function riseup_asia_init(): void {
    try {
        Plugin::getInstance();
    } catch (\Throwable $e) {
        BootErrorCollector::getInstance()->addError('plugin_init', $e->getMessage());
    }

    if (is_admin()) {
        try {
            Admin::getInstance();
        } catch (\Throwable $e) {
            BootErrorCollector::getInstance()->addError('admin_init', $e->getMessage());
        }
    }
}

add_action(HookType::PluginsLoaded->value, 'riseup_asia_init');
