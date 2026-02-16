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

use RiseupAsia\Enums\HttpMethodType;
use RiseupAsia\Enums\HookType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\ErrorHandling\FatalErrorHandler;
use RiseupAsia\ErrorHandling\FrameBuilder;
use RiseupAsia\Helpers\InitHelpers;
use RiseupAsia\Logging\FileLogger;
use RiseupAsia\Database\Database;
use RiseupAsia\Logging\Logger;
use RiseupAsia\Post\PostManager;
use RiseupAsia\Update\UpdateResolver;
use RiseupAsia\Snapshot\SnapshotScheduler;
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
require_once __DIR__ . '/includes/constants.php';

require_once __DIR__ . '/includes/Helpers/BooleanHelpers.php';
require_once __DIR__ . '/includes/Helpers/InitHelpers.php';

// Load dependency loader (uses BooleanHelpers, so must be after it).
require_once __DIR__ . '/includes/Helpers/DependencyLoader.php';

// Load all remaining dependencies via structured loader with error tracking.
$__includes = __DIR__ . '/includes';
RiseupDependencyLoader::loadManifest(array(
    // Core infrastructure — PathUtils MUST load before FileLogger to avoid
    // "Class not found" errors. The logger's ensureDirNative() path avoids
    // the circular dependency, but PathUtils must still be available for
    // all subsequent code that calls RiseupPathUtils methods.
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
));
unset($__includes);

// =============================================================================
// LOAD TRAIT FILES (Namespaced)
// =============================================================================

// Note: PSR-4 traits are autoloaded, but non-class traits used via 'use'
// inside the main plugin class still need the files to be available.
// The main class references them by short name if imported, or fully qualified.
// Since the main file is not namespaced, we must import them.

// No manual require_once needed for traits if they are autoloaded via composer/spl_autoload
// However, the main class uses them directly. We'll rely on the autoloader.

// =============================================================================
// PLUGIN CLASS (shell — all logic lives in traits)
// =============================================================================

/**
 * Main plugin class.
 */
class RiseupAsia {

    // Wave 1 traits
    use RiseupAsia\Traits\Core\LifecycleHooksTrait;
    use RiseupAsia\Traits\Route\RouteRegistrationTrait;
    use RiseupAsia\Traits\Plugin\PluginRoutesTrait;
    use RiseupAsia\Traits\Route\InvalidRouteTrait;
    use RiseupAsia\Traits\Auth\AuthTrait;
    use RiseupAsia\Traits\Status\StatusHandlerTrait;

    // Wave 2 traits
    use RiseupAsia\Traits\Upload\UploadPipelineTrait;
    use RiseupAsia\Traits\Upload\UploadExtractionTrait;
    use RiseupAsia\Traits\Plugin\PluginListTrait;
    use RiseupAsia\Traits\Plugin\PluginExportTrait;
    use RiseupAsia\Traits\Core\PostHandlerTrait;
    use RiseupAsia\Traits\Plugin\PluginLifecycleTrait;
    use RiseupAsia\Traits\Sync\SyncHandlerTrait;

    // Wave 3 traits
    use RiseupAsia\Traits\Core\ResponseTrait;
    use RiseupAsia\Traits\Error\ErrorLogTrait;
    use RiseupAsia\Traits\Agent\AgentHandlerTrait;
    use RiseupAsia\Traits\Snapshot\SnapshotCrudTrait;
    use RiseupAsia\Traits\Snapshot\SnapshotExportTrait;
    use RiseupAsia\Traits\Snapshot\SnapshotBackupTrait;
    use RiseupAsia\Traits\FileSystem\FileSystemTrait;

    /** @var FileLogger */
    private $fileLogger;

    /** @var Logger */
    private $logger;

    /** @var Database */
    private $db;

    /** @var PostManager */
    private $postManager;

    /** @var RiseupAsia|null */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return RiseupAsia
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor — registers hooks and initializes components.
     */
    private function __construct() {
        $this->fileLogger = FileLogger::getInstance();
        $this->fileLogger->info('Plugin constructor starting', array('version' => PluginConfigType::Version->value));

        RiseupDependencyLoader::logSummary($this->fileLogger);

        // Register REST routes and lifecycle hooks BEFORE component init
        add_action(HookType::RestApiInit->value, array($this, 'registerRoutes'));
        add_action(HookType::ActivatedPlugin->value, array($this, 'onPluginActivated'), 10, 2);
        add_action(HookType::DeactivatedPlugin->value, array($this, 'onPluginDeactivated'), 10, 2);
        add_action(HookType::DeletedPlugin->value, array($this, 'onPluginDeleted'), 10, 2);
        add_filter(HookType::RestPostDispatch->value, array($this, 'enrichErrorResponse'), 10, 3);

        $this->fileLogger->info('REST routes and lifecycle hooks registered (pre-init)');

        $this->initComponents();

        InitHelpers::logStartupSummary($this->fileLogger);
        $this->fileLogger->info('Plugin constructor complete', array(
            'db_available' => $this->db !== null,
        ));
    }

    /**
     * Initialize all plugin components with isolated error handling.
     */
    private function initComponents() {
        $this->db = InitHelpers::initComponent('Database', function () {
            $db = Database::getInstance();
            return $db->init() ? $db : null;
        });

        $this->logger = InitHelpers::initComponent('TransactionLogger', function () {
            return Logger::getInstance();
        });

        $this->postManager = InitHelpers::initComponent('PostManager', function () {
            return PostManager::getInstance();
        });

        InitHelpers::initComponent('UpdateResolver', function () {
            return UpdateResolver::getInstance();
        });

        if ($this->db !== null) {
            InitHelpers::initComponent('SnapshotScheduler', function () {
                $scheduler = SnapshotScheduler::getInstance($this->fileLogger, $this->db);
                $scheduler->init();
                return $scheduler;
            });
        } else {
            $this->fileLogger->info('SnapshotScheduler skipped - database not available');
        }
    }
}

// =============================================================================
// PLUGIN INITIALIZATION
// =============================================================================

require_once __DIR__ . '/includes/Activation/ActivationHandler.php';

register_activation_hook(__FILE__, [RiseupActivationHandler::class, 'activate']);

/**
 * Initialize the plugin.
 */
function riseup_asia_init() {
    RiseupAsia::getInstance();

    if (is_admin()) {
        Admin::getInstance();
    }
}

add_action(HookType::PluginsLoaded->value, 'riseup_asia_init');
