<?php
/**
 * Plugin Name: Quick Upload
 * Plugin URI: https://rasia.pro/alim-r-profile-v1
 * Description: Minimal REST API plugin for remote plugin upload and activation with Application Password authentication.
 * Version: 2.2.0
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

use QUpload\Admin\Admin;
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
    $suffix = empty($context) ? '' : ' ' . json_encode($context, JSON_UNESCAPED_SLASHES);

    if (!class_exists(PathHelper::class)) {
        error_log('[QUpload Boot] ' . $stage . $suffix);

        return;
    }

    $baseDir = PathHelper::getBaseDir();
    $traceFile = PathHelper::getStageTraceFile();
    $isBaseReady = PathHelper::ensureDirectory($baseDir);
    $isTraceParentReady = PathHelper::ensureFileParentDirectory($traceFile);

    if ($isBaseReady === false || $isTraceParentReady === false) {
        error_log('[QUpload Boot] trace-dir-create-failed ' . json_encode(['baseDir' => $baseDir, 'traceFile' => $traceFile], JSON_UNESCAPED_SLASHES));
        error_log('[QUpload Boot] ' . $stage . $suffix);

        return;
    }

    $line = '[BOOT] ' . gmdate('c') . ' ' . $stage . $suffix . PHP_EOL;
    $isWritten = @file_put_contents($traceFile, $line, FILE_APPEND | LOCK_EX);

    if ($isWritten === false) {
        error_log('[QUpload Boot] trace-write-failed ' . json_encode(['traceFile' => $traceFile, 'stage' => $stage], JSON_UNESCAPED_SLASHES));
    }

    @error_log('[QUpload Boot] ' . $stage . $suffix);
}

/** Capture fatal boot errors that bypass normal exception handling. */
function qupload_register_boot_fatal_handler(): void {
    register_shutdown_function(static function (): void {
        $error = error_get_last();
        $isFatal = is_array($error) && in_array($error['type'] ?? 0, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true);

        if ($isFatal === false) {
            return;
        }

        qupload_boot_trace('boot:fatal', [
            'message' => $error['message'] ?? '',
            'file' => $error['file'] ?? '',
            'line' => $error['line'] ?? 0,
            'type' => $error['type'] ?? 0,
        ]);
    });
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

    if (is_admin()) {
        try {
            Admin::getInstance();
            qupload_boot_trace('admin:success');
        } catch (Throwable $e) {
            qupload_boot_trace('admin:exception', ['message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
            ErrorLogHelper::log($e, PluginConfigType::LogPrefix->value . ' Admin init failed:');
        }
    }
}

qupload_register_boot_fatal_handler();

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
