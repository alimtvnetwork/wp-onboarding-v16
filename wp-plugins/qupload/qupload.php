<?php
/**
 * Plugin Name: Quick Upload
 * Plugin URI: https://rasia.pro/alim-r-profile-v1
 * Description: Minimal REST API plugin for remote plugin upload and activation with Application Password authentication.
 * Version: 2.0.2
 * Author: MD ALIM UL KARIM
 * Author URI: https://rasia.pro/alim-r-profile-v1
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: qupload
 * Requires at least: 5.6
 * Requires PHP: 8.1
 *
 * @package QUpload
 */

if (!defined('ABSPATH')) {
    exit;
}

use QUpload\Core\Plugin;
use QUpload\Enums\PluginConfigType;
use QUpload\Helpers\ErrorLogHelper;
use QUpload\Helpers\PathHelper;

// =============================================================================
// PSR-4 AUTOLOADER — all QUpload\ classes resolve automatically
// =============================================================================

require_once __DIR__ . '/includes/Autoloader.php';

/**
 * Boot-level fallback trace so we can see whether QUpload initializes at all.
 */
function qupload_boot_trace(string $stage, array $context = []): void {
    if (!class_exists(PathHelper::class)) {
        error_log('[QUpload Boot] ' . $stage);

        return;
    }

    $baseDir = PathHelper::getBaseDir();
    PathHelper::ensureDirectory($baseDir);
    @file_put_contents(
        PathHelper::getStageTraceFile(),
        '[BOOT] ' . gmdate('c') . ' ' . $stage . (empty($context) ? '' : ' ' . json_encode($context, JSON_UNESCAPED_SLASHES)) . PHP_EOL,
        FILE_APPEND | LOCK_EX,
    );
    @error_log('[QUpload Boot] ' . $stage . (empty($context) ? '' : ' ' . json_encode($context, JSON_UNESCAPED_SLASHES)));
}

/**
 * Initialize the plugin.
 */
function qupload_init(): void {
    qupload_boot_trace('init:start');

    try {
        Plugin::getInstance();
        qupload_boot_trace('init:success');
    } catch (Throwable $e) {
        qupload_boot_trace('init:exception', ['message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
        ErrorLogHelper::log($e, PluginConfigType::LogPrefix->value . ' Plugin init failed:');
    }
}

add_action(\QUpload\Enums\HookType::PluginsLoaded->value, 'qupload_init');

/**
 * Handle plugin deactivation — clear temp files.
 */
function qupload_deactivate(): void {
    qupload_boot_trace('deactivate:start');

    try {
        Plugin::getInstance()->handleDeactivate();
        qupload_boot_trace('deactivate:success');
    } catch (Throwable $e) {
        qupload_boot_trace('deactivate:exception', ['message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
        ErrorLogHelper::log($e, PluginConfigType::LogPrefix->value . ' Deactivation cleanup failed:');
    }
}

register_deactivation_hook(__FILE__, 'qupload_deactivate');
