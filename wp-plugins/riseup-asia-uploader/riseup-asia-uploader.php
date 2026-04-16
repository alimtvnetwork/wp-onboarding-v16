<?php
/**
 * Plugin Name: Riseup Asia Uploader
 * Plugin URI: https://rasia.pro/alim-r-profile-v1
 * Description: Remote plugin management, blog post publishing, delta file sync, auto-update with 301 redirect resolution, and audit logging via REST API with Application Password authentication.
 * Version: 2.45.0
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
use RiseupAsia\Enums\OptionNameType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Activation\ActivationHandler;
use RiseupAsia\Core\Plugin;
use RiseupAsia\Admin\Admin;
use RiseupAsia\ErrorHandling\BootErrorCollector;
use RiseupAsia\Helpers\InitHelpers;
use RiseupAsia\Helpers\PathHelper;

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
function riseup_asia_clear_logs_on_version_update(bool $force = false): void {
    $optionKey = OptionNameType::LastPluginVersion->value;
    $currentVersion = PluginConfigType::Version->value;
    $lastVersion = get_option($optionKey, '');

    $isVersionChanged = ($lastVersion !== $currentVersion);

    if ($force === false && $isVersionChanged === false) {
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

/**
 * Force log cleanup after plugin update completion.
 *
 * @param mixed $upgrader    WP_Upgrader instance (unused).
 * @param array $hookExtra   Upgrader metadata.
 */
function riseup_asia_handle_plugin_update_complete($upgrader, array $hookExtra): void {
    $isPluginUpdate = (($hookExtra['action'] ?? '') === 'update') && (($hookExtra['type'] ?? '') === 'plugin');

    if ($isPluginUpdate === false) {
        return;
    }

    $updatedPluginsRaw = $hookExtra['plugins'] ?? [];
    $updatedPlugins = gettype($updatedPluginsRaw) === 'array' ? $updatedPluginsRaw : [];
    $singleUpdatedPlugin = isset($hookExtra['plugin']) && gettype($hookExtra['plugin']) === 'string'
        ? $hookExtra['plugin']
        : '';

    $normalizedUpdatedPlugins = [];

    foreach ($updatedPlugins as $updatedPlugin) {
        if (gettype($updatedPlugin) === 'string' && $updatedPlugin !== '') {
            $normalizedUpdatedPlugins[] = $updatedPlugin;
        }
    }

    if ($singleUpdatedPlugin !== '') {
        $normalizedUpdatedPlugins[] = $singleUpdatedPlugin;
    }

    if (empty($normalizedUpdatedPlugins)) {
        return;
    }

    $currentPluginBasename = plugin_basename(PathHelper::getPluginMainFile());
    $isCurrentPluginUpdated = in_array($currentPluginBasename, $normalizedUpdatedPlugins, true);

    if ($isCurrentPluginUpdated === false) {
        return;
    }

    riseup_asia_clear_logs_on_version_update(true);
}

/**
 * Handle plugin deactivation — clear cron schedules and temp files.
 */
function riseup_asia_deactivate(): void {
    try {
        // Clear cloud backup cron events
        $fullCronHook = 'riseup_cloud_full_backup';
        $incrCronHook = 'riseup_cloud_incremental_backup';

        $fullTimestamp = wp_next_scheduled($fullCronHook);
        $isFullScheduled = ($fullTimestamp !== false);

        if ($isFullScheduled) {
            wp_unschedule_event($fullTimestamp, $fullCronHook);
        }

        $incrTimestamp = wp_next_scheduled($incrCronHook);
        $isIncrScheduled = ($incrTimestamp !== false);

        if ($isIncrScheduled) {
            wp_unschedule_event($incrTimestamp, $incrCronHook);
        }

        // Skip temp cleanup if an upload is in progress (self-update scenario).
        // The upload pipeline sets $isUploadInProgress = true before deactivating,
        // and the temp dir contains the ZIP + backup needed for extraction/rollback.
        $pluginClass = 'RiseupAsia\\Core\\Plugin';
        $isUploadRunning = class_exists($pluginClass, false) && $pluginClass::isUploadInProgress();

        if ($isUploadRunning) {
            error_log('RiseUp Uploader: Deactivation during upload — skipping temp cleanup');

            return;
        }

        // Clear any temp directories
        $uploadDir = wp_upload_dir();
        $tempDir = rtrim($uploadDir['basedir'], '/') . '/riseup-asia-uploader/temp';
        $isTempDirPresent = is_dir($tempDir);

        if ($isTempDirPresent) {
            riseup_asia_delete_temp_dir($tempDir);
        }
    } catch (\Throwable $e) {
        error_log('RiseUp Uploader: Deactivation cleanup failed: ' . $e->getMessage());
    }
}

/**
 * Recursively delete a temp directory.
 */
function riseup_asia_delete_temp_dir(string $dir): void {
    $isDirMissing = !is_dir($dir);

    if ($isDirMissing) {
        return;
    }

    $items = scandir($dir);
    $isReadFailed = ($items === false);

    if ($isReadFailed) {
        return;
    }

    foreach ($items as $item) {
        $isNavEntry = ($item === '.' || $item === '..');

        if ($isNavEntry) {
            continue;
        }

        $path = $dir . '/' . $item;
        $isDirectory = is_dir($path);

        if ($isDirectory) {
            riseup_asia_delete_temp_dir($path);
        } else {
            @unlink($path);
        }
    }

    @rmdir($dir);
}

register_deactivation_hook(__FILE__, 'riseup_asia_deactivate');

add_action(HookType::PluginsLoaded->value, 'riseup_asia_init');
add_action(HookType::UpgraderProcessComplete->value, 'riseup_asia_handle_plugin_update_complete', 10, 2);
