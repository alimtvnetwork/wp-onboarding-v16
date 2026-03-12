<?php
/**
 * Plugin Name: Riseup Asia Uploader
 * Plugin URI: https://rasia.pro/alim-r-profile-v1
 * Description: Remote plugin management, blog post publishing, delta file sync, auto-update with 301 redirect resolution, and audit logging via REST API with Application Password authentication.
 * Version: 2.4.0
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

use RiseupAsia	hEnums	hHookType;
use RiseupAsia	hEnums	hOptionNameType;
use RiseupAsia	hEnums	hPluginConfigType;
use RiseupAsia	hActivation	hActivationHandler;
use RiseupAsia	hCore	hPlugin;
use RiseupAsia	hAdmin	hAdmin;
use RiseupAsia	hErrorHandling	hBootErrorCollector;
use RiseupAsia	hHelpers	hInitHelpers;

// =============================================================================
// PSR-4 AUTOLOADER — all RiseupAsia\ classes resolve automatically
// =============================================================================

require_once __DIR__ . '/includes/Autoloader.php';

register_activation_hook(__FILE__, [ActivationHandler::class, 'activate']);

/**
 * Initialize the plugin.
 */
function riseup_asia_init(): void {
    riseup_asia_clear_logs_on_version_update();

    try {
        Plugin::getInstance();
    } catch (Throwable $e) {
        BootErrorCollector::getInstance()->addError('plugin_init', $e->getMessage() . "\n" . $e->getTraceAsString());
        InitHelpers::errorLogAndThrow($e, 'RiseUp Uploader: Plugin init failed:');
    }

    if (is_admin()) {
        try {
            Admin::getInstance();
        } catch (Throwable $e) {
            BootErrorCollector::getInstance()->addError('admin_init', $e->getMessage() . "\n" . $e->getTraceAsString());
            InitHelpers::errorLogAndThrow($e, 'RiseUp Uploader: Admin init failed:');
        }
    }
}

/**
 * Clear all log files when the plugin version changes.
 */
function riseup_asia_clear_logs_on_version_update(): void {
    $optionKey = 'riseup_asia_last_version';
    $currentVersion = \RiseupAsia\Enums\PluginConfigType::Version->value;
    $lastVersion = get_option($optionKey, '');

    $isVersionChanged = ($lastVersion !== $currentVersion);

    if ($isVersionChanged === false) {
        return;
    }

    try {
        $logger = \RiseupAsia\Logging\FileLogger::getInstance();
        $logger->clearAllLogFiles();
    } catch (Throwable $e) {
        // Best-effort — don't block boot
    }

    update_option($optionKey, $currentVersion, true);
}

add_action(HookType::PluginsLoaded->value, 'riseup_asia_init');
