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
 * Requires PHP: 7.4
 *
 * @package RiseupAsiaUploader
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\HttpMethodType;
use RiseupAsia\Enums\HookType;

// =============================================================================
// ERROR HANDLING (standalone functions — must load before class)
// =============================================================================

require_once __DIR__ . '/includes/ErrorHandling/FrameBuilder.php';
require_once __DIR__ . '/includes/ErrorHandling/FatalErrorHandler.php';

// =============================================================================
// LOAD DEPENDENCIES IN ORDER
// =============================================================================

// Foundation: PSR-4 namespaced enums (must load before constants.php and all classes)
require_once __DIR__ . '/includes/Enums/UploadSourceType.php';
require_once __DIR__ . '/includes/Enums/CapabilityType.php';
require_once __DIR__ . '/includes/Enums/HttpMethodType.php';
require_once __DIR__ . '/includes/Enums/HookType.php';
require_once __DIR__ . '/includes/Enums/PathConst.php';
require_once __DIR__ . '/includes/Enums/ErrorType.php';
require_once __DIR__ . '/includes/Enums/LogLevelType.php';
require_once __DIR__ . '/includes/Enums/ActionType.php';

// Error checker (uses RiseupAsia\Enums\ErrorType internally)
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
// LOAD TRAIT FILES
// =============================================================================

$__traits = __DIR__ . '/includes/Traits';
require_once $__traits . '/Core/LifecycleHooksTrait.php';
require_once $__traits . '/Route/RouteRegistrationTrait.php';
require_once $__traits . '/Plugin/PluginRoutesTrait.php';
require_once $__traits . '/Route/InvalidRouteTrait.php';
require_once $__traits . '/Auth/AuthTrait.php';
require_once $__traits . '/Status/StatusHandlerTrait.php';
require_once $__traits . '/Upload/UploadPipelineTrait.php';
require_once $__traits . '/Upload/UploadExtractionTrait.php';
require_once $__traits . '/Plugin/PluginListTrait.php';
require_once $__traits . '/Plugin/PluginExportTrait.php';
require_once $__traits . '/Core/PostHandlerTrait.php';
require_once $__traits . '/Plugin/PluginLifecycleTrait.php';
require_once $__traits . '/Sync/SyncHandlerTrait.php';
require_once $__traits . '/Core/ResponseTrait.php';
require_once $__traits . '/Error/ErrorLogTrait.php';
require_once $__traits . '/Agent/AgentHandlerTrait.php';
require_once $__traits . '/Snapshot/SnapshotCrudTrait.php';
require_once $__traits . '/Snapshot/SnapshotExportTrait.php';
require_once $__traits . '/Snapshot/SnapshotBackupTrait.php';
require_once $__traits . '/FileSystem/FileSystemTrait.php';
unset($__traits);

// =============================================================================
// PLUGIN CLASS (shell — all logic lives in traits)
// =============================================================================

/**
 * Main plugin class.
 */
class RiseupAsia {

    // Wave 1 traits
    use LifecycleHooksTrait;
    use RouteRegistrationTrait;
    use PluginRoutesTrait;
    use InvalidRouteTrait;
    use AuthTrait;
    use StatusHandlerTrait;

    // Wave 2 traits
    use UploadPipelineTrait;
    use UploadExtractionTrait;
    use PluginListTrait;
    use PluginExportTrait;
    use PostHandlerTrait;
    use PluginLifecycleTrait;
    use SyncHandlerTrait;

    // Wave 3 traits
    use ResponseTrait;
    use ErrorLogTrait;
    use AgentHandlerTrait;
    use SnapshotCrudTrait;
    use SnapshotExportTrait;
    use SnapshotBackupTrait;
    use FileSystemTrait;

    /** @var RiseupFileLogger */
    private $fileLogger;

    /** @var RiseupLogger */
    private $logger;

    /** @var RiseupDatabase */
    private $db;

    /** @var RiseupPostManager */
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
        $this->fileLogger = RiseupFileLogger::getInstance();
        $this->fileLogger->info('Plugin constructor starting', array('version' => PLUGIN_VERSION));

        RiseupDependencyLoader::logSummary($this->fileLogger);

        // Register REST routes and lifecycle hooks BEFORE component init
        add_action(HookType::RestApiInit->value, array($this, 'registerRoutes'));
        add_action(HookType::ActivatedPlugin->value, array($this, 'onPluginActivated'), 10, 2);
        add_action(HookType::DeactivatedPlugin->value, array($this, 'onPluginDeactivated'), 10, 2);
        add_action(HookType::DeletedPlugin->value, array($this, 'onPluginDeleted'), 10, 2);
        add_filter(HookType::RestPostDispatch->value, array($this, 'enrichErrorResponse'), 10, 3);

        $this->fileLogger->info('REST routes and lifecycle hooks registered (pre-init)');

        $this->initComponents();

        RiseupInitHelpers::logStartupSummary($this->fileLogger);
        $this->fileLogger->info('Plugin constructor complete', array(
            'db_available' => $this->db !== null,
        ));
    }

    /**
     * Initialize all plugin components with isolated error handling.
     */
    private function initComponents() {
        $this->db = RiseupInitHelpers::initComponent('Database', function () {
            $db = RiseupDatabase::getInstance();
            return $db->init() ? $db : null;
        });

        $this->logger = RiseupInitHelpers::initComponent('TransactionLogger', function () {
            return RiseupLogger::getInstance();
        });

        $this->postManager = RiseupInitHelpers::initComponent('PostManager', function () {
            return RiseupPostManager::getInstance();
        });

        RiseupInitHelpers::initComponent('UpdateResolver', function () {
            return RiseupUpdateResolver::getInstance();
        });

        if ($this->db !== null) {
            RiseupInitHelpers::initComponent('SnapshotScheduler', function () {
                $scheduler = RiseupSnapshotScheduler::getInstance($this->fileLogger, $this->db);
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

register_activation_hook(__FILE__, 'riseup_asia_activate');

/**
 * Initialize the plugin.
 */
function riseup_asia_init() {
    RiseupAsia::getInstance();

    if (is_admin()) {
        RiseupAdmin::getInstance();
    }
}

add_action(HookType::PluginsLoaded->value, 'riseup_asia_init');
