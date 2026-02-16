<?php
/**
 * Plugin Name: Riseup Asia Uploader
 * Plugin URI: https://rasia.pro/alim-r-profile-v1
 * Description: Remote plugin management, blog post publishing, delta file sync, auto-update with 301 redirect resolution, and audit logging via REST API with Application Password authentication.
 * Version: 1.56.0
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
use RiseupAsia\Helpers\DependencyLoader;
use RiseupAsia\Activation\ActivationHandler;
use RiseupAsia\Core\Plugin;
use RiseupAsia\Admin\Admin;

// =============================================================================
// PSR-4 AUTOLOADER
// =============================================================================

require_once __DIR__ . '/includes/Autoloader.php';

// =============================================================================
// ERROR HANDLING (standalone — must load before class dependencies)
// =============================================================================

require_once __DIR__ . '/includes/ErrorHandling/FrameBuilder.php';
require_once __DIR__ . '/includes/ErrorHandling/FatalErrorHandler.php';

// =============================================================================
// LOAD DEPENDENCIES IN ORDER
// =============================================================================

// Enums are now autoloaded via PSR-4 (includes/Autoloader.php).
// Only non-autoloaded legacy files need explicit requires.
require_once __DIR__ . '/includes/Helpers/ErrorChecker.php';

require_once __DIR__ . '/includes/Helpers/BooleanHelpers.php';
require_once __DIR__ . '/includes/Helpers/InitHelpers.php';

// Load dependency loader (uses BooleanHelpers, so must be after it).
require_once __DIR__ . '/includes/Helpers/DependencyLoader.php';

// Load all remaining dependencies via structured loader with error tracking.
$__includes = __DIR__ . '/includes';
DependencyLoader::loadManifest(array(
    // Core infrastructure — PathUtils MUST load before FileLogger to avoid
    // "Class not found" errors. The logger's ensureDirNative() path avoids
    // the circular dependency, but PathUtils must still be available for
    // all subsequent code that calls PathUtils methods.
    array('PathUtils',           $__includes . '/Helpers/PathUtils.php'),
    array('FileLogger',          $__includes . '/Logging/FileLogger.php'),
    array('ORM',                 $__includes . '/Database/Orm.php'),
    array('Database',            $__includes . '/Database/Database.php'),
    array('EnvelopeBuilder',     $__includes . '/Helpers/EnvelopeBuilder.php'),
    array('TransactionLogger',   $__includes . '/Logging/Logger.php'),

    // Snapshot system
    array('SnapshotDetector',    $__includes . '/Snapshot/SnapshotDetector.php'),
    array('SnapshotScheduler',   $__includes . '/Snapshot/SnapshotScheduler.php'),
    array('SnapshotCleaner',     $__includes . '/Snapshot/SnapshotCleaner.php'),
    array('SnapshotManager',     $__includes . '/Snapshot/SnapshotManager.php'),
    array('DependencyAnalyzer',  $__includes . '/Snapshot/DependencyAnalyzer.php'),
    array('RootDb',              $__includes . '/Database/RootDb.php'),
    array('SnapshotWorker',      $__includes . '/Snapshot/SnapshotWorker.php'),
    array('SnapshotOrchestrator',$__includes . '/Snapshot/SnapshotOrchestrator.php'),
    array('IncrementalBackup',   $__includes . '/Snapshot/IncrementalBackup.php'),
    array('RestoreEngine',       $__includes . '/Snapshot/RestoreEngine.php'),
    array('SnapshotImport',      $__includes . '/Snapshot/SnapshotImport.php'),

    // Sync system
    array('FileCache',           $__includes . '/Database/FileCache.php'),

    // Other classes
    array('PostManager',         $__includes . '/Post/PostManager.php'),
    array('UploadIgnore',        $__includes . '/Upload/UploadIgnore.php'),
    array('Admin',               $__includes . '/Admin/Admin.php'),
    array('UpdateResolver',      $__includes . '/Update/UpdateResolver.php'),
    array('AgentManager',        $__includes . '/Agent/AgentManager.php'),

    // Plugin shell (must load after all dependencies)
    array('Plugin',              $__includes . '/Core/Plugin.php'),
));
unset($__includes);

// =============================================================================
// PLUGIN INITIALIZATION
// =============================================================================

require_once __DIR__ . '/includes/Activation/ActivationHandler.php';

register_activation_hook(__FILE__, [ActivationHandler::class, 'activate']);

/**
 * Initialize the plugin.
 */
function riseup_asia_init(): void {
    Plugin::getInstance();

    if (is_admin()) {
        Admin::getInstance();
    }
}

add_action(HookType::PluginsLoaded->value, 'riseup_asia_init');
