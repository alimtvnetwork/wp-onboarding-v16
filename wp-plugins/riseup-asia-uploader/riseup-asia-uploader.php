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

// Foundation: PSR-4 namespaced enums (must load before constants.php and all classes)
require_once __DIR__ . '/includes/Enums/UploadSourceType.php';
require_once __DIR__ . '/includes/Enums/CapabilityType.php';
require_once __DIR__ . '/includes/Enums/HttpMethodType.php';
require_once __DIR__ . '/includes/Enums/HookType.php';
require_once __DIR__ . '/includes/Enums/PathSubdirType.php';
require_once __DIR__ . '/includes/Enums/PathDatabaseType.php';
require_once __DIR__ . '/includes/Enums/PathLogFileType.php';
require_once __DIR__ . '/includes/Enums/PathConfigType.php';
require_once __DIR__ . '/includes/Enums/EndpointType.php';
require_once __DIR__ . '/includes/Enums/TableType.php';
require_once __DIR__ . '/includes/Enums/ErrorType.php';
require_once __DIR__ . '/includes/Enums/LogLevelType.php';
require_once __DIR__ . '/includes/Enums/ActionType.php';
require_once __DIR__ . '/includes/Enums/StatusType.php';
require_once __DIR__ . '/includes/Enums/PostStatusType.php';
require_once __DIR__ . '/includes/Enums/SnapshotStatusType.php';
require_once __DIR__ . '/includes/Enums/SnapshotJobStatusType.php';
require_once __DIR__ . '/includes/Enums/SnapshotScopeType.php';
require_once __DIR__ . '/includes/Enums/SnapshotFrequencyType.php';
require_once __DIR__ . '/includes/Enums/SnapshotProviderType.php';
require_once __DIR__ . '/includes/Enums/SnapshotTriggerType.php';
require_once __DIR__ . '/includes/Enums/SnapshotExportStatusType.php';
require_once __DIR__ . '/includes/Enums/SnapshotModeType.php';
require_once __DIR__ . '/includes/Enums/RetentionType.php';
require_once __DIR__ . '/includes/Enums/AgentStatusType.php';
require_once __DIR__ . '/includes/Enums/TriggerSourceType.php';
require_once __DIR__ . '/includes/Enums/SyncActionType.php';
require_once __DIR__ . '/includes/Enums/ResponseMessageType.php';
require_once __DIR__ . '/includes/Enums/HttpStatusType.php';
require_once __DIR__ . '/includes/Enums/SnapshotErrorType.php';

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

register_activation_hook(__FILE__, [RiseupActivationHandler::class, 'activate']);

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
