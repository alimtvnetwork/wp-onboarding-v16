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
// HELPER: CONVERT EXCEPTION/BACKTRACE TO FRAMES ARRAY
// =============================================================================

/**
 * Convert a Throwable trace to a structured frames array.
 *
 * @param Throwable $exception The exception/error.
 * @return array Array of frame objects with file, line, function, class.
 */
function riseup_exception_to_frames($exception) {
    $frames = array();
    
    // First frame: the exception location itself
    $frames[] = array(
        'file'     => $exception->getFile(),
        'fileBase' => basename($exception->getFile()),
        'line'     => $exception->getLine(),
        'function' => '',
        'class'    => '',
    );
    
    // Remaining frames from trace
    foreach ($exception->getTrace() as $frame) {
        $frames[] = array(
            'file'     => isset($frame['file']) ? $frame['file'] : '[internal]',
            'fileBase' => isset($frame['file']) ? basename($frame['file']) : '[internal]',
            'line'     => isset($frame['line']) ? $frame['line'] : 0,
            'function' => isset($frame['function']) ? $frame['function'] : '',
            'class'    => isset($frame['class']) ? $frame['class'] : '',
        );
    }
    
    return $frames;
}

/**
 * Convert a debug_backtrace array to a structured frames array.
 *
 * @param array $backtrace The backtrace from debug_backtrace().
 * @return array Array of frame objects.
 */
function riseup_backtrace_to_frames($backtrace) {
    $frames = array();
    foreach ($backtrace as $frame) {
        $frames[] = array(
            'file'     => isset($frame['file']) ? $frame['file'] : '[internal]',
            'fileBase' => isset($frame['file']) ? basename($frame['file']) : '[internal]',
            'line'     => isset($frame['line']) ? $frame['line'] : 0,
            'function' => isset($frame['function']) ? $frame['function'] : '',
            'class'    => isset($frame['class']) ? $frame['class'] : '',
        );
    }
    return $frames;
}

// =============================================================================
// GLOBAL ERROR HANDLER FOR JSON RESPONSES
// =============================================================================

/**
 * Custom error handler to catch fatal errors and return JSON response.
 * This ensures API consumers get proper error responses instead of HTML stack traces.
 * 
 * Enhanced in v1.7.0:
 * - Better output buffer handling
 * - Enhanced stack trace generation
 * - Memory tracking for OOM detection
 * - JSON encoding error handling
 */
function riseup_fatal_error_handler() {
    $error = error_get_last();
    // SAFETY: Use native PHP checks here — RiseupBooleanHelpers may not be loaded yet
    // if the fatal error occurred during class loading.
    if ($error === null) {
        return;
    }
    
    // Only handle fatal errors
    $fatal_types = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR);
    if (!in_array($error['type'], $fatal_types)) {
        return;
    }
    
    // Only handle if this is a REST API request to our namespace
    $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    if (strpos($request_uri, 'riseup-asia-uploader') === false && strpos($request_uri, 'wp-json') === false) {
        return;
    }
    
    // Try to log to file before any output (helps with complete crashes)
    $log_entry = sprintf(
        "[%s] FATAL ERROR in %s:%d - %s (type: %s)\n",
        date('Y-m-d H:i:s'),
        $error['file'],
        $error['line'],
        $error['message'],
        riseup_error_type_to_string($error['type'])
    );
    
    // Attempt to write to a simple log file (even if plugin logger isn't available)
    $uploads = wp_upload_dir();
    $log_file = $uploads['basedir'] . '/riseup-asia-uploader/fatal-errors.log';
    @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    
    // Clean any existing output to ensure pure JSON response
    while (ob_get_level()) {
        @ob_end_clean();
    }
    
    // Set proper headers before any output
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }
    
    // Generate detailed stack trace from error location
    $trace_lines = array();
    $trace_lines[] = sprintf("#0 %s(%d): Fatal error occurred", $error['file'], $error['line']);
    
    // Try to get any available backtrace (may be limited in shutdown)
    if (function_exists('debug_backtrace')) {
        $backtrace = @debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);
        if (is_array($backtrace)) {
            foreach ($backtrace as $i => $frame) {
                $file = isset($frame['file']) ? $frame['file'] : '[internal]';
                $line = isset($frame['line']) ? $frame['line'] : 0;
                $func = isset($frame['function']) ? $frame['function'] : '';
                $class = isset($frame['class']) ? $frame['class'] . $frame['type'] : '';
                $trace_lines[] = sprintf("#%d %s(%d): %s%s()", $i + 1, $file, $line, $class, $func);
            }
        }
    }
    $trace_lines[] = sprintf("#%d [internal function]: PHP shutdown handler", count($trace_lines));
    
    // Generate frames array for structured parsing
    $frames = array(
        array(
            'file'     => $error['file'],
            'fileBase' => basename($error['file']),
            'line'     => $error['line'],
            'function' => 'fatal_error',
            'class'    => '',
        ),
    );
    
    // Add backtrace frames if available
    if (isset($backtrace) && is_array($backtrace)) {
        foreach ($backtrace as $frame) {
            $frames[] = array(
                'file'     => isset($frame['file']) ? $frame['file'] : '[internal]',
                'fileBase' => isset($frame['file']) ? basename($frame['file']) : '[internal]',
                'line'     => isset($frame['line']) ? (int) $frame['line'] : 0,
                'function' => isset($frame['function']) ? $frame['function'] : '',
                'class'    => isset($frame['class']) ? $frame['class'] : '',
            );
        }
    }
    
    $frames[] = array(
        'file'     => '[internal]',
        'fileBase' => '[internal]',
        'line'     => 0,
        'function' => 'shutdown_handler',
        'class'    => 'PHP',
    );
    
    // Build the error response
    $response = array(
        'success' => false,
        'error' => array(
            'code' => 'FATAL_ERROR',
            'message' => 'A fatal error occurred in the plugin: ' . $error['message'],
            'details' => array(
                'type'             => $error['type'],
                'typeName'         => riseup_error_type_to_string($error['type']),
                'message'          => $error['message'],
                'file'             => basename($error['file']),
                'fileFull'         => $error['file'],
                'line'             => $error['line'],
                'stackTrace'       => implode("\n", $trace_lines),
                'stackTraceFrames' => $frames,
                'phpVersion'       => phpversion(),
                'wpVersion'        => defined('WP_VERSION') ? WP_VERSION : 'unknown',
                'memoryUsage'      => memory_get_usage(true),
                'memoryPeak'       => memory_get_peak_usage(true),
                'memoryLimit'      => ini_get('memory_limit'),
                'requestUri'       => $request_uri,
                'requestMethod'    => isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'UNKNOWN',
            ),
        ),
    );
    
    // Attempt JSON encoding with error handling
    $json = @json_encode($response, JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        // JSON encoding failed - return minimal response
        $minimal = array(
            'success' => false,
            'error' => array(
                'code' => 'FATAL_ERROR_ENCODING_FAILED',
                'message' => 'Fatal error occurred and JSON encoding also failed',
                'details' => array(
                    'originalMessage' => substr($error['message'], 0, 500),
                    'file' => basename($error['file']),
                    'line' => $error['line'],
                    'jsonError' => json_last_error_msg(),
                ),
            ),
        );
        echo json_encode($minimal);
    } else {
        echo $json;
    }
    
    exit;
}

/**
 * Convert PHP error type to human-readable string.
 *
 * @param int $type Error type constant.
 * @return string
 */
function riseup_error_type_to_string($type) {
    $types = array(
        E_ERROR             => 'E_ERROR',
        E_PARSE             => 'E_PARSE',
        E_CORE_ERROR        => 'E_CORE_ERROR',
        E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
        E_WARNING           => 'E_WARNING',
        E_NOTICE            => 'E_NOTICE',
        E_STRICT            => 'E_STRICT',
        E_DEPRECATED        => 'E_DEPRECATED',
        E_USER_ERROR        => 'E_USER_ERROR',
        E_USER_WARNING      => 'E_USER_WARNING',
        E_USER_NOTICE       => 'E_USER_NOTICE',
        E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
    );
    return isset($types[$type]) ? $types[$type] : 'UNKNOWN_ERROR_TYPE';
}
register_shutdown_function('riseup_fatal_error_handler');

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

// Error checker (uses RiseupAsia\Enums\ErrorType internally)
require_once __DIR__ . '/includes/Helpers/ErrorChecker.php';
require_once __DIR__ . '/includes/constants.php';
require_once __DIR__ . '/includes/constants-compat.php'; // RISEUP_* aliases — remove after consumer migration
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
// PLUGIN CLASS
// =============================================================================

/**
 * Main plugin class.
 */
class RiseupAsia {

    /**
     * File logger instance.
     *
     * @var RiseupFileLogger
     */
    private $file_logger;

    /**
     * Transaction logger instance.
     *
     * @var RiseupLogger
     */
    private $logger;

    /**
     * Database instance.
     *
     * @var RiseupDatabase
     */
    private $db;

    /**
     * Post manager instance.
     *
     * @var RiseupPostManager
     */
    private $post_manager;

    /**
     * Singleton instance.
     *
     * @var RiseupAsia|null
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return RiseupAsia
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        // Initialize file logger first (it doesn't depend on anything else).
        $this->file_logger = RiseupFileLogger::get_instance();
        $this->file_logger->info('Plugin constructor starting', array('version' => PLUGIN_VERSION));

        // Log dependency loading results from structured loader
        RiseupDependencyLoader::logSummary($this->file_logger);

        // =====================================================================
        // CRITICAL: Register REST routes and lifecycle hooks BEFORE any
        // component initialization. This ensures all API endpoints are
        // available even when optional dependencies (PDO, SQLite) are missing.
        // =====================================================================
        add_action(HookType::RestApiInit->value, array($this, 'register_routes'));
        add_action(HookType::ActivatedPlugin->value, array($this, 'on_plugin_activated'), 10, 2);
        add_action(HookType::DeactivatedPlugin->value, array($this, 'on_plugin_deactivated'), 10, 2);
        add_action(HookType::DeletedPlugin->value, array($this, 'on_plugin_deleted'), 10, 2);

        // Enrich error responses with plugin_version, timestamp, and log_hint
        add_filter(HookType::RestPostDispatch->value, array($this, 'enrich_error_response'), 10, 3);

        $this->file_logger->info('REST routes and lifecycle hooks registered (pre-init)');

        // =====================================================================
        // Component initialization — each wrapped in initComponent so failures
        // are isolated and do not block subsequent components.
        // =====================================================================
        $this->db = RiseupInitHelpers::initComponent('Database', function () {
            $db = RiseupDatabase::get_instance();
            $db_ready = $db->init();
            if (!$db_ready) {
                // PDO/pdo_sqlite unavailable — warning already logged once by initSqliteConnection.
                // Return null gracefully; database-dependent features will be skipped.
                return null;
            }
            return $db;
        });

        $this->logger = RiseupInitHelpers::initComponent('TransactionLogger', function () {
            return RiseupLogger::get_instance();
        });

        $this->post_manager = RiseupInitHelpers::initComponent('PostManager', function () {
            return RiseupPostManager::get_instance();
        });

        RiseupInitHelpers::initComponent('UpdateResolver', function () {
            return RiseupUpdateResolver::get_instance();
        });

        // Only init scheduler if database is available (requires DB for snapshot tracking)
        if ($this->db !== null) {
            RiseupInitHelpers::initComponent('SnapshotScheduler', function () {
                $scheduler = RiseupSnapshotScheduler::getInstance($this->file_logger, $this->db);
                $scheduler->init();
                return $scheduler;
            });
        } else {
            $this->file_logger->info('SnapshotScheduler skipped - database not available');
        }

        // Log structured startup summary
        RiseupInitHelpers::logStartupSummary($this->file_logger);
        $this->file_logger->info('Plugin constructor complete', array(
            'db_available' => $this->db !== null,
        ));
    }

    // =========================================================================
    // WORDPRESS CORE PLUGIN LIFECYCLE HOOKS
    // These methods capture plugin actions from ANY source (dashboard, WP-CLI, etc.)
    // =========================================================================

    /**
     * Handle WordPress core activated_plugin hook.
     * Logs when a plugin is activated from any source (dashboard, WP-CLI, other plugins).
     *
     * @param string $plugin Plugin file path relative to plugins directory (e.g., "akismet/akismet.php").
     * @param bool   $network_wide Whether the plugin was activated for the entire network.
     * @return void
     */
    public function on_plugin_activated($plugin, $network_wide = false) {
        // Skip if this is our own API action (to avoid duplicate logging)
        if ($this->is_rest_request()) {
            return;
        }

        try {
            $slug = $this->extract_plugin_slug($plugin);
            $triggered_by = $this->detect_trigger_source();
            
            $this->file_logger->info('WordPress hook: Plugin activated', array(
                'plugin'       => $plugin,
                'slug'         => $slug,
                'network_wide' => $network_wide,
                'triggered_by' => $triggered_by,
            ));

            $this->logger->log_plugin_action(
                ACTION_ENABLE,
                $slug,
                STATUS_SUCCESS,
                array(
                    'plugin_file'   => $plugin,
                    'network_wide'  => $network_wide,
                    'triggered_by'  => $triggered_by,
                    'hook_source'   => 'activated_plugin',
                )
            );
        } catch (Throwable $e) {
            $this->file_logger->error('Failed to log plugin activation: ' . $e->getMessage());
        }
    }

    /**
     * Handle WordPress core deactivated_plugin hook.
     * Logs when a plugin is deactivated from any source.
     *
     * @param string $plugin Plugin file path relative to plugins directory.
     * @param bool   $network_deactivating Whether deactivating across the network.
     * @return void
     */
    public function on_plugin_deactivated($plugin, $network_deactivating = false) {
        // Skip if this is our own API action (to avoid duplicate logging)
        if ($this->is_rest_request()) {
            return;
        }

        try {
            $slug = $this->extract_plugin_slug($plugin);
            $triggered_by = $this->detect_trigger_source();
            
            $this->file_logger->info('WordPress hook: Plugin deactivated', array(
                'plugin'       => $plugin,
                'slug'         => $slug,
                'network'      => $network_deactivating,
                'triggered_by' => $triggered_by,
            ));

            $this->logger->log_plugin_action(
                ACTION_DISABLE,
                $slug,
                STATUS_SUCCESS,
                array(
                    'plugin_file'          => $plugin,
                    'network_deactivating' => $network_deactivating,
                    'triggered_by'         => $triggered_by,
                    'hook_source'          => 'deactivated_plugin',
                )
            );
        } catch (Throwable $e) {
            $this->file_logger->error('Failed to log plugin deactivation: ' . $e->getMessage());
        }
    }

    /**
     * Handle WordPress core deleted_plugin hook.
     * Logs when a plugin is deleted from any source.
     *
     * @param string $plugin Plugin file path relative to plugins directory.
     * @param bool   $deleted Whether the plugin was successfully deleted.
     * @return void
     */
    public function on_plugin_deleted($plugin, $deleted = true) {
        // Skip if this is our own API action (to avoid duplicate logging)
        if ($this->is_rest_request()) {
            return;
        }

        // Only log successful deletions
        if (!$deleted) {
            return;
        }

        try {
            $slug = $this->extract_plugin_slug($plugin);
            $triggered_by = $this->detect_trigger_source();
            
            $this->file_logger->info('WordPress hook: Plugin deleted', array(
                'plugin'       => $plugin,
                'slug'         => $slug,
                'triggered_by' => $triggered_by,
            ));

            $this->logger->log_plugin_action(
                ACTION_DELETE,
                $slug,
                STATUS_SUCCESS,
                array(
                    'plugin_file'  => $plugin,
                    'triggered_by' => $triggered_by,
                    'hook_source'  => 'deleted_plugin',
                )
            );
        } catch (Throwable $e) {
            $this->file_logger->error('Failed to log plugin deletion: ' . $e->getMessage());
        }
    }

    /**
     * Detect the source that triggered the current action.
     *
     * @return string One of the TRIGGERED_BY_* constants.
     */
    private function detect_trigger_source() {
        // Check if running from WP-CLI
        if (defined('WP_CLI') && WP_CLI) {
            return TRIGGERED_BY_CLI;
        }

        // Check if this is a cron job
        if (defined('DOING_CRON') && DOING_CRON) {
            return TRIGGERED_BY_CRON;
        }

        // Check if this is a REST API request (should be caught earlier, but just in case)
        if ($this->is_rest_request()) {
            return TRIGGERED_BY_API;
        }

        // Default to dashboard (admin UI action)
        return TRIGGERED_BY_DASHBOARD;
    }

    /**
     * Check if the current request is a REST API request.
     *
     * @return bool True if REST request.
     */
    private function is_rest_request() {
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return true;
        }
        
        // Additional check for REST API URL pattern
        if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/wp-json/') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Extract plugin slug from full plugin file path.
     *
     * @param string $plugin_file Plugin file path (e.g., "akismet/akismet.php" or "hello.php").
     * @return string Plugin slug.
     */
    private function extract_plugin_slug($plugin_file) {
        // For directory-based plugins: "plugin-folder/plugin-file.php" -> "plugin-folder"
        if (strpos($plugin_file, '/') !== false) {
            $parts = explode('/', $plugin_file);
            return $parts[0];
        }
        
        // For single-file plugins: "hello.php" -> "hello"
        return str_replace('.php', '', $plugin_file);
    }

    /**
     * Register REST API routes.
     * Using plain endpoint strings (no regex) from constants.
     *
     * @return void
     */
    public function register_routes() {
        $this->file_logger->info('Registering REST API routes', array('namespace' => API_FULL_NAMESPACE));

        $registered = 0;
        $failed = 0;

        // Helper: register a single route in its own try-catch so one failure
        // cannot prevent subsequent routes from registering.
        $safe_register = function ($endpoint_const, $args) use (&$registered, &$failed) {
            try {
                register_rest_route(API_FULL_NAMESPACE, '/' . $endpoint_const, $args);
                $registered++;
            } catch (Throwable $e) {
                $failed++;
                $this->file_logger->error('Failed to register route: ' . $endpoint_const . ' - ' . $e->getMessage());
            }
        };

        $this->register_utility_routes($safe_register);
        $this->register_plugin_routes($safe_register);
        $this->register_post_routes($safe_register);
        $this->register_log_routes($safe_register);
        $this->register_agent_routes($safe_register, $failed);
        $this->register_snapshot_routes($safe_register);
        $this->register_catch_all_route($safe_register);

        $this->file_logger->info("REST API route registration complete: $registered registered, $failed failed");
    }

    /**
     * Register utility routes (status, openapi, opcache-reset).
     *
     * @param callable $safe_register Route registration closure.
     */
    private function register_utility_routes($safe_register) {
        $safe_register(ENDPOINT_STATUS, array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handle_status'),
            'permission_callback' => $this->build_permission_callback('status', array($this, 'check_status_permission')),
        ));

        $safe_register(ENDPOINT_OPENAPI, array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handle_openapi'),
            'permission_callback' => $this->build_permission_callback('openapi', array($this, 'check_status_permission')),
        ));

        $safe_register(ENDPOINT_OPCACHE_RESET, array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handle_opcache_reset'),
            'permission_callback' => $this->build_permission_callback('opcache_reset', array($this, 'check_plugin_permission')),
        ));
    }

    /**
     * Register plugin management routes (upload, list, enable, disable, delete, export, exists, files, sync).
     *
     * @param callable $safe_register Route registration closure.
     */
    private function register_plugin_routes($safe_register) {
        $safe_register(ENDPOINT_UPLOAD, array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handle_upload'),
            'permission_callback' => $this->build_permission_callback('upload', array($this, 'check_plugin_permission')),
        ));

        $safe_register(ENDPOINT_PLUGINS, array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handle_list_plugins'),
            'permission_callback' => $this->build_permission_callback('plugins', array($this, 'check_plugin_permission')),
        ));

        $safe_register(ENDPOINT_EXPORT_SELF, array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handle_export_self'),
            'permission_callback' => $this->build_permission_callback('export_self', array($this, 'check_plugin_permission')),
        ));

        $safe_register(ENDPOINT_PLUGIN_FILES, array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handle_plugin_files'),
            'permission_callback' => $this->build_permission_callback('plugin_files', array($this, 'check_plugin_permission')),
        ));

        $safe_register(ENDPOINT_SYNC_MANIFEST, array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handle_sync_manifest'),
            'permission_callback' => $this->build_permission_callback('sync_manifest', array($this, 'check_plugin_permission')),
        ));

        $safe_register(ENDPOINT_SYNC, array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handle_sync_push'),
            'permission_callback' => $this->build_permission_callback('sync_push', array($this, 'check_plugin_permission')),
        ));

        $safe_register(ENDPOINT_PLUGIN_FILE, array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handle_plugin_file_content'),
            'permission_callback' => $this->build_permission_callback('plugin_file', array($this, 'check_plugin_permission')),
        ));

        $safe_register(ENDPOINT_PLUGIN_EXISTS, array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handle_plugin_exists'),
            'permission_callback' => $this->build_permission_callback('plugins', array($this, 'check_plugin_permission')),
        ));

        $safe_register(ENDPOINT_PLUGIN_ENABLE, array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handle_enable_plugin'),
            'permission_callback' => $this->build_permission_callback('plugins', array($this, 'check_plugin_permission')),
        ));

        $safe_register(ENDPOINT_PLUGIN_DISABLE, array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handle_disable_plugin'),
            'permission_callback' => $this->build_permission_callback('plugins', array($this, 'check_plugin_permission')),
        ));

        $safe_register(ENDPOINT_PLUGIN_DELETE, array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handle_delete_plugin'),
            'permission_callback' => $this->build_permission_callback('plugins', array($this, 'check_plugin_permission')),
        ));

        $safe_register(ENDPOINT_PLUGIN_EXPORT, array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handle_export_plugin'),
            'permission_callback' => $this->build_permission_callback('plugin_export', array($this, 'check_plugin_permission')),
        ));

        // Media upload endpoint (optional — ENDPOINT_MEDIA may not be defined)
        try {
            if (defined('ENDPOINT_MEDIA')) {
                $safe_register(ENDPOINT_MEDIA, array(
                    'methods'             => HttpMethodType::Post->value,
                    'callback'            => array($this, 'handle_media_upload'),
                    'permission_callback' => $this->build_permission_callback('media', array($this, 'check_plugin_permission')),
                ));
            }
        } catch (Throwable $e) {
            // Optional endpoint, ignore
        }
    }

    /**
     * Register post and category routes.
     *
     * @param callable $safe_register Route registration closure.
     */
    private function register_post_routes($safe_register) {
        $safe_register(ENDPOINT_POSTS, array(
            array(
                'methods'             => HttpMethodType::Get->value,
                'callback'            => array($this, 'handle_list_posts'),
                'permission_callback' => $this->build_permission_callback('posts', array($this, 'check_post_permission')),
            ),
            array(
                'methods'             => HttpMethodType::Post->value,
                'callback'            => array($this, 'handle_create_post'),
                'permission_callback' => $this->build_permission_callback('posts', array($this, 'check_post_permission')),
            ),
        ));

        $safe_register(ENDPOINT_CATEGORIES, array(
            array(
                'methods'             => HttpMethodType::Get->value,
                'callback'            => array($this, 'handle_list_categories'),
                'permission_callback' => $this->build_permission_callback('categories', array($this, 'check_post_permission')),
            ),
            array(
                'methods'             => HttpMethodType::Post->value,
                'callback'            => array($this, 'handle_create_category'),
                'permission_callback' => $this->build_permission_callback('categories', array($this, 'check_post_permission')),
            ),
        ));
    }

    /**
     * Register log and error session routes.
     *
     * @param callable $safe_register Route registration closure.
     */
    private function register_log_routes($safe_register) {
        $safe_register(ENDPOINT_LOGS, array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handle_query_logs'),
            'permission_callback' => $this->build_permission_callback('logs', array($this, 'check_logs_permission')),
        ));

        $safe_register(ENDPOINT_LOGS_STATS, array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handle_logs_stats'),
            'permission_callback' => $this->build_permission_callback('logs_stats', array($this, 'check_logs_permission')),
        ));

        $safe_register(ENDPOINT_ERROR_LOGS, array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handle_error_logs'),
            'permission_callback' => $this->build_permission_callback('error_logs', array($this, 'check_logs_permission')),
        ));

        $safe_register(ENDPOINT_ERROR_SESSIONS, array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handle_error_sessions'),
            'permission_callback' => $this->build_permission_callback('error_logs', array($this, 'check_logs_permission')),
        ));
    }

    /**
     * Register agent management routes.
     *
     * Each call wrapped in try-catch to guard against undefined
     * constants on PHP 8.0+ (Error thrown at argument evaluation).
     *
     * @param callable $safe_register Route registration closure.
     * @param int      &$failed      Failed registration counter (by reference).
     */
    private function register_agent_routes($safe_register, &$failed) {
        $agent_routes = array(
            array('const' => 'ENDPOINT_AGENTS_LIST',    'method' => HttpMethodType::Get,  'handler' => 'handle_list_agents'),
            array('const' => 'ENDPOINT_AGENTS_ADD',     'method' => HttpMethodType::Post, 'handler' => 'handle_add_agent'),
            array('const' => 'ENDPOINT_AGENTS_REMOVE',  'method' => HttpMethodType::Post, 'handler' => 'handle_remove_agent'),
            array('const' => 'ENDPOINT_AGENTS_TEST',    'method' => HttpMethodType::Post, 'handler' => 'handle_test_agent'),
            array('const' => 'ENDPOINT_AGENTS_SYNC',    'method' => HttpMethodType::Post, 'handler' => 'handle_sync_to_agent'),
            array('const' => 'ENDPOINT_AGENTS_PLUGINS', 'method' => HttpMethodType::Post, 'handler' => 'handle_agent_plugin_action'),
        );

        foreach ($agent_routes as $route) {
            try {
                $endpoint = constant($route['const']);
                $safe_register($endpoint, array(
                    'methods'             => $route['method']->value,
                    'callback'            => array($this, $route['handler']),
                    'permission_callback' => $this->build_permission_callback('agents', array($this, 'check_plugin_permission')),
                ));
            } catch (Throwable $e) {
                $failed++;
                $this->file_logger->error('Agent route ' . $route['const'] . ' failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Register snapshot management routes.
     *
     * @param callable $safe_register Route registration closure.
     */
    private function register_snapshot_routes($safe_register) {
        $perm = $this->build_permission_callback('snapshots', array($this, 'check_plugin_permission'));

        $safe_register(ENDPOINT_SNAPSHOT_LIST, array(
            'methods' => HttpMethodType::Get->value, 'callback' => array($this, 'handle_list_snapshots'), 'permission_callback' => $perm,
        ));

        $safe_register(ENDPOINT_SNAPSHOT_SCHEDULE, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handle_schedule_snapshot'), 'permission_callback' => $perm,
        ));

        $safe_register(ENDPOINT_SNAPSHOT_INFO, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handle_snapshot_info'), 'permission_callback' => $perm,
        ));

        $safe_register(ENDPOINT_SNAPSHOT_DELETE, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handle_delete_snapshot'), 'permission_callback' => $perm,
        ));

        $safe_register(ENDPOINT_SNAPSHOT_RESTORE, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handle_restore_snapshot'), 'permission_callback' => $perm,
        ));

        $safe_register(ENDPOINT_SNAPSHOT_EXPORT, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handle_export_snapshot'), 'permission_callback' => $perm,
        ));

        $safe_register(ENDPOINT_SNAPSHOT_IMPORT, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handle_import_snapshot'), 'permission_callback' => $perm,
        ));

        $safe_register(ENDPOINT_SNAPSHOT_SETTINGS, array(
            array(
                'methods' => HttpMethodType::Get->value, 'callback' => array($this, 'handle_get_snapshot_settings'), 'permission_callback' => $perm,
            ),
            array(
                'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handle_update_snapshot_settings'), 'permission_callback' => $perm,
            ),
        ));

        $safe_register(ENDPOINT_SNAPSHOT_PROVIDERS, array(
            'methods' => HttpMethodType::Get->value, 'callback' => array($this, 'handle_list_snapshot_providers'), 'permission_callback' => $perm,
        ));

        $safe_register(ENDPOINT_SNAPSHOT_TABLES, array(
            'methods' => HttpMethodType::Get->value, 'callback' => array($this, 'handle_list_snapshot_tables'), 'permission_callback' => $perm,
        ));

        $safe_register('snapshots/dependencies', array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handle_analyze_dependencies'), 'permission_callback' => $perm,
        ));

        $safe_register('snapshots/export-pertable', array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handle_export_pertable'), 'permission_callback' => $perm,
        ));

        $safe_register(ENDPOINT_SNAPSHOT_FULL_BACKUP, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handle_full_backup'), 'permission_callback' => $perm,
        ));

        $safe_register(ENDPOINT_SNAPSHOT_INCREMENTAL, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handle_incremental_backup'), 'permission_callback' => $perm,
        ));

        $safe_register(ENDPOINT_SNAPSHOT_CLEANUP, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handle_snapshot_cleanup'), 'permission_callback' => $perm,
        ));

        $safe_register(ENDPOINT_SNAPSHOT_PROGRESS, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handle_snapshot_progress'), 'permission_callback' => $perm,
        ));

        $safe_register(ENDPOINT_SNAPSHOT_DOWNLOAD, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handle_snapshot_download'), 'permission_callback' => $perm,
        ));

        $safe_register(ENDPOINT_SNAPSHOT_DOWNLOAD_FILE, array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handle_snapshot_download_file'),
            'permission_callback' => '__return_true', // Nonce-validated in handler
        ));
    }

    /**
     * Register catch-all route for invalid paths.
     *
     * @param callable $safe_register Route registration closure.
     */
    private function register_catch_all_route($safe_register) {
        $safe_register('(?P<invalid_path>.+)', array(
            'methods'             => array(HttpMethodType::Get->value, HttpMethodType::Post->value, HttpMethodType::Put->value, HttpMethodType::Patch->value, HttpMethodType::Delete->value),
            'callback'            => array($this, 'handle_invalid_route'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Handle requests to invalid/unrecognized routes within our namespace.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Structured 404 error response.
     */
    public function handle_invalid_route($request) {
        $invalid_path = $request->get_param('invalid_path');
        $method = $request->get_method();

        $this->file_logger->warn('Invalid route requested', array(
            'path'   => $invalid_path,
            'method' => $method,
        ));

        // Generate stack trace for diagnostics
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        $frames = function_exists('riseup_backtrace_to_frames')
            ? riseup_backtrace_to_frames($backtrace)
            : array();

        $trace_lines = array();
        foreach ($backtrace as $i => $frame) {
            $file = isset($frame['file']) ? basename($frame['file']) : '[internal]';
            $line = isset($frame['line']) ? $frame['line'] : '?';
            $func = isset($frame['function']) ? $frame['function'] : '';
            $class = isset($frame['class']) ? $frame['class'] . $frame['type'] : '';
            $trace_lines[] = "#{$i} {$file}({$line}): {$class}{$func}()";
        }

        return RiseupEnvelopeBuilder::error(
            "No endpoint found for: {$method} /{$invalid_path}",
            HTTP_NOT_FOUND
        )
            ->setRequestedAt($_SERVER['REQUEST_URI'] ?? '')
            ->setErrors(array(
                'BackendMessage'              => "Route not found: {$method} /{$invalid_path}",
                'DelegatedServiceErrorStack'  => $trace_lines,
                'Backend'                     => array_map(function($f) {
                    $file = isset($f['fileBase']) ? $f['fileBase'] : '';
                    $line = isset($f['line']) ? $f['line'] : 0;
                    $fn = isset($f['function']) ? $f['function'] : '';
                    $cls = isset($f['class']) ? $f['class'] . '::' : '';
                    return "{$file}:{$line} {$cls}{$fn}";
                }, $frames),
                'Frontend'                    => array(),
            ))
            ->toResponse();
    }

    /**
     * Enrich error responses from our namespace with plugin metadata.
     *
     * Adds plugin_version, timestamp, and log_hint to all 4xx/5xx responses
     * from the riseup-asia-uploader REST namespace.
     *
     * @param WP_REST_Response $response Response object.
     * @param WP_REST_Server   $server   REST server.
     * @param WP_REST_Request  $request  Request object.
     * @return WP_REST_Response Modified response.
     */
    public function enrich_error_response($response, $server, $request) {
        // Only process our namespace
        $route = $request->get_route();
        if (strpos($route, '/' . API_FULL_NAMESPACE) === false) {
            return $response;
        }

        $status = $response->get_status();
        if ($status < 400) {
            return $response;
        }

        $data = $response->get_data();
        if (!is_array($data)) {
            return $response;
        }

        // Inject metadata into the response
        if (!isset($data['plugin_version'])) {
            $data['plugin_version'] = PLUGIN_VERSION;
        }
        if (!isset($data['timestamp'])) {
            $data['timestamp'] = gmdate('c');
        }
        if (!isset($data['log_hint'])) {
            $data['log_hint'] = 'Check the plugin error logs or the Activity Logs page for details.';
        }

        // Log the error for audit trail
        $this->file_logger->error('REST API error response', array(
            'route'          => $route,
            'status'         => $status,
            'message'        => isset($data['message']) ? $data['message'] : (isset($data['Status']['Message']) ? $data['Status']['Message'] : 'Unknown'),
            'plugin_version' => PLUGIN_VERSION,
        ));

        $response->set_data($data);
        return $response;
    }

    // =========================================================================
    // PERMISSION CALLBACKS
    // =========================================================================

    /**
     * Check if an endpoint is enabled via settings.
     *
     * @param string $endpoint Endpoint key (e.g., 'status', 'upload').
     * @return bool True if enabled.
     */
    private function is_endpoint_enabled($endpoint) {
        return RiseupAdmin::is_endpoint_enabled($endpoint);
    }

    /**
     * Check if an endpoint requires authentication via settings.
     *
     * @param string $endpoint Endpoint key.
     * @return bool True if auth required.
     */
    private function is_auth_required($endpoint) {
        return RiseupAdmin::is_auth_required($endpoint);
    }

    /**
     * Build permission callback with optional auth bypass.
     *
     * @param string   $endpoint   Endpoint key for settings lookup.
     * @param callable $auth_check The actual auth check function.
     * @return callable Permission callback.
     */
    private function build_permission_callback($endpoint, $auth_check) {
        return function($request) use ($endpoint, $auth_check) {
            // Check if endpoint is enabled
            if (!$this->is_endpoint_enabled($endpoint)) {
                return new WP_Error(
                    'rest_disabled',
                    'This endpoint is disabled',
                    array('status' => 403)
                );
            }
            
            // Check if auth is required
            if (!$this->is_auth_required($endpoint)) {
                return true; // Allow without auth
            }
            
            // Perform normal auth check
            return call_user_func($auth_check, $request);
        };
    }

    /**
     * Check plugin management permission.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return bool|WP_Error True if authorized, WP_Error otherwise.
     */
    public function check_plugin_permission($request) {
        $this->file_logger->debug('Checking plugin permission');
        return $this->check_authenticated_capability($request, CAP_MANAGE_PLUGINS);
    }

    /**
     * Check post management permission.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return bool|WP_Error True if authorized, WP_Error otherwise.
     */
    public function check_post_permission($request) {
        $this->file_logger->debug('Checking post permission');
        return $this->check_authenticated_capability($request, CAP_MANAGE_POSTS);
    }

    /**
     * Check logs view permission.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return bool|WP_Error True if authorized, WP_Error otherwise.
     */
    public function check_logs_permission($request) {
        $this->file_logger->debug('Checking logs permission');
        return $this->check_authenticated_capability($request, CAP_VIEW_LOGS);
    }

    /**
     * Check status/openapi permission (requires any authenticated user).
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return bool|WP_Error True if authorized, WP_Error otherwise.
     */
    public function check_status_permission($request) {
        $this->file_logger->debug('Checking status permission');
        return $this->check_authenticated_only($request);
    }

    /**
     * Resolve the Authorization header from the request.
     *
     * Tries WP_REST_Request first, then falls back to $_SERVER
     * and getallheaders() for CGI/FastCGI compatibility.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return string|null Authorization header value, or null if missing.
     */
    private function resolve_auth_header($request) {
        $auth_header = $request->get_header('Authorization');

        if (!empty($auth_header)) {
            return $auth_header;
        }

        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            return $_SERVER['HTTP_AUTHORIZATION'];
        }

        if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        $hasGetAllHeaders = function_exists('getallheaders');
        if (!$hasGetAllHeaders) {
            return null;
        }

        $headers = getallheaders();

        if (isset($headers['Authorization'])) {
            return $headers['Authorization'];
        }

        if (isset($headers['authorization'])) {
            return $headers['authorization'];
        }

        return null;
    }

    /**
     * Parse Basic auth header and authenticate the user.
     *
     * @param string $auth_header Raw Authorization header value.
     *
     * @return WP_User|WP_Error Authenticated user, or WP_Error on failure.
     */
    private function authenticate_user($auth_header) {
        $isBasicAuth = (strpos($auth_header, 'Basic ') === 0);
        if (!$isBasicAuth) {
            $this->file_logger->warn('Invalid Authorization header format');
            $this->logger->log_auth_failure('Invalid Authorization header format');

            return new WP_Error(
                'rest_forbidden',
                MSG_UNAUTHORIZED,
                array('status' => HTTP_UNAUTHORIZED)
            );
        }

        $credentials = base64_decode(substr($auth_header, 6));
        $hasDelimiter = ($credentials && strpos($credentials, ':') !== false);
        if (!$hasDelimiter) {
            $this->file_logger->warn('Invalid credentials format');
            $this->logger->log_auth_failure('Invalid credentials format');

            return new WP_Error(
                'rest_forbidden',
                MSG_UNAUTHORIZED,
                array('status' => HTTP_UNAUTHORIZED)
            );
        }

        list($username, $password) = explode(':', $credentials, 2);
        $this->file_logger->debug('Authenticating user', array('username' => $username));

        $user = wp_authenticate_application_password(null, $username, $password);

        $isAuthFailed = (is_wp_error($user) || !$user);
        if ($isAuthFailed) {
            $this->file_logger->warn('Invalid credentials', array('username' => $username));
            $this->logger->log_auth_failure(
                'Invalid credentials',
                array('username' => $username)
            );

            return new WP_Error(
                'rest_forbidden',
                MSG_UNAUTHORIZED,
                array('status' => HTTP_UNAUTHORIZED)
            );
        }

        wp_set_current_user($user->ID);

        return $user;
    }

    /**
     * Build a WP_Error for missing Authorization header with diagnostic context.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_Error
     */
    private function build_missing_auth_error($request) {
        $this->file_logger->warn('Missing Authorization header', array(
            'reason'          => 'Missing Authorization header',
            'method'          => $request->get_method(),
            'endpoint'        => $request->get_route(),
            'ip'              => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown',
            'user_agent'      => $request->get_header('user-agent') ?: 'unknown',
            'server_software' => isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'unknown',
        ));
        $this->logger->log_auth_failure('Missing Authorization header');

        return new WP_Error(
            'rest_forbidden',
            MSG_UNAUTHORIZED,
            array(
                'status'  => HTTP_UNAUTHORIZED,
                'headers' => array('WWW-Authenticate' => 'Basic realm="WordPress Application Password"'),
            )
        );
    }

    /**
     * Verify authentication only (no capability check).
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return bool|WP_Error True if authorized, WP_Error otherwise.
     */
    private function check_authenticated_only($request) {
        $this->file_logger->debug('Authenticating request (any user)');

        try {
            $auth_header = $this->resolve_auth_header($request);
            if (empty($auth_header)) {
                return $this->build_missing_auth_error($request);
            }

            $user = $this->authenticate_user($auth_header);
            if (is_wp_error($user)) {
                return $user;
            }

            $this->file_logger->info('Request authorized (status)', array('username' => $user->user_login));

            return true;
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'Authentication error');

            return new WP_Error(
                'rest_forbidden',
                MSG_UNAUTHORIZED,
                array('status' => HTTP_UNAUTHORIZED)
            );
        }
    }

    /**
     * Verify authentication and capability.
     *
     * @param WP_REST_Request $request    Request object.
     * @param string          $capability Required capability.
     *
     * @return bool|WP_Error True if authorized, WP_Error otherwise.
     */
    private function check_authenticated_capability($request, $capability) {
        $this->file_logger->debug('Authenticating request', array('capability' => $capability));

        try {
            $auth_header = $this->resolve_auth_header($request);
            if (empty($auth_header)) {
                return $this->build_missing_auth_error($request);
            }

            $user = $this->authenticate_user($auth_header);
            if (is_wp_error($user)) {
                return $user;
            }

            $this->file_logger->debug('User authenticated', array('user_id' => $user->ID));

            if (!current_user_can($capability)) {
                $this->file_logger->warn('Insufficient permissions', array(
                    'username'     => $user->user_login,
                    'required_cap' => $capability,
                ));
                $this->logger->log_auth_failure(
                    'Insufficient permissions',
                    array('username' => $user->user_login, 'required_cap' => $capability)
                );

                return new WP_Error(
                    'rest_forbidden',
                    MSG_FORBIDDEN,
                    array('status' => HTTP_FORBIDDEN)
                );
            }

            $this->file_logger->info('Request authorized', array('username' => $user->user_login));

            return true;
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'Authentication error');

            return new WP_Error(
                'rest_forbidden',
                MSG_UNAUTHORIZED,
                array('status' => HTTP_UNAUTHORIZED)
            );
        }
    }

    // =========================================================================
    // STATUS HANDLERS
    // =========================================================================

    /**
     * Handle status check.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_status($request) {
        $this->file_logger->info('Status endpoint called');

        // Collect all registered REST routes for our namespace
        $registered_routes = array();
        $rest_server = rest_get_server();
        $all_routes = $rest_server->get_routes();
        $ns_prefix = '/' . API_FULL_NAMESPACE;

        foreach ($all_routes as $route => $handlers) {
            if (strpos($route, $ns_prefix) === 0) {
                $methods = array();
                foreach ($handlers as $handler) {
                    if (isset($handler['methods'])) {
                        if (is_array($handler['methods'])) {
                            $methods = array_merge($methods, array_keys($handler['methods']));
                        } elseif (is_string($handler['methods'])) {
                            $methods[] = $handler['methods'];
                        }
                    }
                }
                $registered_routes[] = array(
                    'route'   => $route,
                    'methods' => array_values(array_unique($methods)),
                );
            }
        }

        // Load endpoints.json reference
        $endpoints_ref = null;
        $endpoints_file = plugin_dir_path(__FILE__) . 'data/endpoints.json';
        if (file_exists($endpoints_file)) {
            $endpoints_content = @file_get_contents($endpoints_file);
            if ($endpoints_content !== false) {
                $endpoints_ref = json_decode($endpoints_content, true);
            }
        }

        // =====================================================================
        // VERSION DETECTION — Read from the actual plugin file on disk to
        // avoid stale PLUGIN_VERSION constant after self-updates. OPcache
        // may cache the old constants.php bytecode across requests.
        // =====================================================================
        $live_version = PLUGIN_VERSION; // default fallback
        $main_plugin_file = WP_PLUGIN_DIR . '/' . PLUGIN_SLUG . '/' . PLUGIN_SLUG . '.php';
        clearstatcache(true, $main_plugin_file);
        if (file_exists($main_plugin_file)) {
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($main_plugin_file, true);
                $constants_file = WP_PLUGIN_DIR . '/' . PLUGIN_SLUG . '/includes/constants.php';
                if (file_exists($constants_file)) {
                    opcache_invalidate($constants_file, true);
                }
            }
            $header_content = file_get_contents($main_plugin_file, false, null, 0, 8192);
            if ($header_content !== false && preg_match('/Version:\s*([0-9]+\.[0-9]+\.[0-9]+)/', $header_content, $ver_matches)) {
                $live_version = $ver_matches[1];
            }
        }

        // Gather additional diagnostic details
        $db_available = $this->db !== null;
        $site_url = get_site_url();
        $plugin_file = plugin_basename(__FILE__);
        $active_plugins = get_option('active_plugins', array());
        $is_active = in_array($plugin_file, $active_plugins, true);

        return RiseupEnvelopeBuilder::success()
            ->setRequestedAt('/' . API_FULL_NAMESPACE . ENDPOINT_STATUS)
            ->setSingleResult(array(
                'Plugin'           => PLUGIN_NAME,
                'Version'          => $live_version,
                'Slug'             => PLUGIN_SLUG,
                'Api'              => API_FULL_NAMESPACE,
                'SiteUrl'          => $site_url,
                'Wp'               => get_bloginfo('version'),
                'Php'              => PHP_VERSION,
                'IsActive'         => $is_active,
                'DbAvailable'      => $db_available,
                'ServerTime'       => gmdate('c'),
                'Timezone'         => wp_timezone_string(),
                'Features'         => array(
                    'PluginUpload'   => true,
                    'PluginManage'   => true,
                    'FileOperations' => true,
                    'DeltaSync'      => true,
                    'PostPublish'    => true,
                    'CategoryManage' => true,
                    'TransactionLog' => $db_available,
                    'ExportSelf'     => true,
                    'Snapshots'      => $db_available,
                    'Agents'         => $db_available,
                ),
                'RegisteredRoutes' => $registered_routes,
                'EndpointsRef'     => $endpoints_ref,
            ))
            ->toResponse();
    }

    /**
     * Handle OpenAPI specification request.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_openapi($request) {
        $this->file_logger->info('OpenAPI endpoint called');

        // Read the OpenAPI spec from the data directory.
        $spec_file = plugin_dir_path(__FILE__) . 'data/openapi.json';
        
        if (RiseupBooleanHelpers::is_file_missing($spec_file)) {
            $this->file_logger->error('OpenAPI spec file not found', array('path' => $spec_file));
            return new WP_REST_Response(array(
                'success' => false,
                'error'   => 'OpenAPI specification file not found',
            ), HTTP_NOT_FOUND);
        }

        $spec_content = file_get_contents($spec_file);
        if ($spec_content === false) {
            $this->file_logger->error('Failed to read OpenAPI spec file');
            return new WP_REST_Response(array(
                'success' => false,
                'error'   => 'Failed to read OpenAPI specification',
            ), HTTP_SERVER_ERROR);
        }

        $spec = json_decode($spec_content, true);
        if ($spec === null) {
            $this->file_logger->error('Invalid JSON in OpenAPI spec file');
            return new WP_REST_Response(array(
                'success' => false,
                'error'   => 'Invalid OpenAPI specification format',
            ), HTTP_SERVER_ERROR);
        }

        // Update the server URL dynamically.
        $spec['servers'][0]['variables']['baseUrl']['default'] = get_site_url();

        return new WP_REST_Response($spec, HTTP_OK);
    }

    // =========================================================================
    // OPCACHE RESET HANDLER
    // =========================================================================

    /**
     * Handle OPcache reset request.
     *
     * Called by the upload script after a self-update to flush stale bytecode
     * so the next request serves new plugin code.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function handle_opcache_reset($request) {
        $this->file_logger->info('OPcache reset endpoint called');

        $result = array(
            'success'           => true,
            'opcache_available' => function_exists('opcache_reset'),
            'opcache_reset'     => false,
            'files_invalidated' => 0,
            'timestamp'         => gmdate('c'),
        );

        // Full OPcache reset
        if (function_exists('opcache_reset')) {
            $result['opcache_reset'] = opcache_reset();
            $this->file_logger->info('OPcache reset executed', array('result' => $result['opcache_reset']));
        }

        // Also invalidate specific plugin files
        $plugin_dir = WP_PLUGIN_DIR . '/' . PLUGIN_SLUG;
        $invalidated = 0;
        if (function_exists('opcache_invalidate')) {
            $files_to_invalidate = array(
                $plugin_dir . '/' . PLUGIN_SLUG . '.php',
                $plugin_dir . '/includes/constants.php',
            );
            foreach ($files_to_invalidate as $file) {
                if (file_exists($file)) {
                    clearstatcache(true, $file);
                    opcache_invalidate($file, true);
                    $invalidated++;
                }
            }
        }
        $result['files_invalidated'] = $invalidated;

        // Clear WordPress plugin cache
        wp_cache_delete('plugins', 'plugins');

        return RiseupEnvelopeBuilder::success('OPcache reset complete')
            ->setRequestedAt('/' . API_FULL_NAMESPACE . '/' . ENDPOINT_OPCACHE_RESET)
            ->setSingleResult($result)
            ->toResponse();
    }

    // =========================================================================
    // PLUGIN HANDLERS
    // =========================================================================

    /**
     * Handle plugin upload (multipart or base64 ZIP).
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_upload($request) {
        $this->file_logger->info('Upload endpoint called');

        try {
            $input = $this->parse_upload_input($request);
            if ($input instanceof WP_REST_Response) {
                return $input;
            }

            $zip_content          = $input['zip_content'];
            $slug                 = $input['slug'];
            $activate             = $input['activate'];
            $upload_source        = $input['upload_source'];
            $client_plugin_version = $input['client_plugin_version'];

            // Log "Upload Initiated" before any processing
            if (!empty($slug)) {
                $this->logger->log_upload_initiated($slug, array(
                    'activate'       => $activate,
                    'upload_source'  => $upload_source,
                    'client_version' => $client_plugin_version,
                    'file_size'      => strlen($zip_content),
                ), array(
                    'plugin_version' => $client_plugin_version ?: PLUGIN_VERSION,
                    'upload_source'  => $upload_source,
                ));
            }

            $zip_result = $this->validate_and_write_zip($zip_content, $slug);
            if ($zip_result instanceof WP_REST_Response) {
                return $zip_result;
            }

            $temp_file = $zip_result['temp_file'];
            $slug      = $zip_result['slug'];

            $plugins_dir = WP_PLUGIN_DIR;
            $target_dir  = $plugins_dir . '/' . $slug;
            $is_update   = is_dir($target_dir);

            $this->remove_duplicate_plugins($slug, $plugins_dir);

            // Self-update pre-logging
            $is_self_update = ($slug === PLUGIN_SLUG && $is_update);
            if ($is_self_update) {
                $this->pre_log_self_update($slug, $upload_source, $client_plugin_version, strlen($zip_content));
            }

            $was_active = $this->deactivate_if_updating($slug, $is_update, $target_dir);

            $extract_result = $this->extract_to_plugins_dir($temp_file, $slug, $target_dir);
            if ($extract_result instanceof WP_REST_Response) {
                return $extract_result;
            }

            $plugin_file = $this->reset_opcache_and_find_plugin($slug);
            if ($plugin_file instanceof WP_REST_Response) {
                return $plugin_file;
            }

            $activation = $this->activate_if_needed($plugin_file, $slug, $activate, $was_active, $is_update);
            if ($activation instanceof WP_REST_Response) {
                return $activation;
            }

            $activated = $activation['activated'];

            $version_info = $this->detect_installed_version($plugin_file, $slug, $is_self_update, $client_plugin_version);
            $plugin_version = $version_info['version'];

            // Log final result (skip if self-update was pre-logged)
            if (!$is_self_update) {
                $this->logger->log_upload($slug, array(
                    'is_update'      => $is_update,
                    'activated'      => $activated,
                    'file_size'      => strlen($zip_content),
                    'plugin_version' => $plugin_version,
                ), array(
                    'plugin_version' => $plugin_version,
                    'upload_source'  => $upload_source,
                ));
            }

            $this->file_logger->info('Upload complete', array(
                'slug'           => $slug,
                'is_update'      => $is_update,
                'activated'      => $activated,
                'plugin_version' => $plugin_version,
                'upload_source'  => $upload_source,
            ));

            return RiseupEnvelopeBuilder::success()
                ->setRequestedAt('/' . API_FULL_NAMESPACE . '/' . ENDPOINT_UPLOAD)
                ->setSingleResult(array(
                    'plugin_slug'    => $slug,
                    'is_update'      => $is_update,
                    'activated'      => $activated,
                    'plugin_version' => $plugin_version,
                    'upload_source'  => $upload_source,
                ))
                ->toResponse();
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'Upload error');

            return $this->error_response('Upload failed: ' . $e->getMessage(), HTTP_SERVER_ERROR, $e);
        }
    }

    /**
     * Parse upload input from multipart or base64 JSON request.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return array|WP_REST_Response Parsed input array, or error response.
     */
    private function parse_upload_input($request) {
        $files = $request->get_file_params();
        $is_multipart = !empty($files['plugin_zip']);

        if ($is_multipart) {
            return $this->parse_multipart_input($files, $request);
        }

        return $this->parse_base64_input($request);
    }

    /**
     * Parse multipart/form-data upload.
     *
     * @param array           $files   File params from request.
     * @param WP_REST_Request $request Request object.
     *
     * @return array|WP_REST_Response Parsed input or error response.
     */
    private function parse_multipart_input($files, $request) {
        $this->file_logger->info('Processing multipart upload');
        $upload = $files['plugin_zip'];

        if ($upload['error'] !== UPLOAD_ERR_OK) {
            $this->file_logger->error('Multipart upload error', array('code' => $upload['error']));

            return $this->error_response('File upload failed (error code: ' . $upload['error'] . ')', HTTP_BAD_REQUEST);
        }

        $zip_content = file_get_contents($upload['tmp_name']);
        if ($zip_content === false) {
            $this->file_logger->error('Failed to read uploaded file');

            return $this->error_response('Failed to read uploaded file', HTTP_SERVER_ERROR);
        }

        $body_params = $request->get_body_params();

        return $this->build_upload_params($zip_content, $body_params);
    }

    /**
     * Parse base64 JSON upload (legacy).
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return array|WP_REST_Response Parsed input or error response.
     */
    private function parse_base64_input($request) {
        $data = $request->get_json_params();

        if (empty($data['plugin_zip'])) {
            $this->file_logger->warn('Upload failed: plugin_zip required');

            return $this->error_response(MSG_INVALID_REQUEST . ': plugin_zip is required (send as multipart file or base64 JSON)', HTTP_BAD_REQUEST);
        }

        $this->file_logger->info('Processing base64 JSON upload');
        $zip_content = base64_decode($data['plugin_zip']);
        if ($zip_content === false) {
            $this->file_logger->error('Invalid base64 data');

            return $this->error_response('Invalid base64 data', HTTP_BAD_REQUEST);
        }

        return $this->build_upload_params($zip_content, $data);
    }

    /**
     * Build normalized upload parameters from raw data.
     *
     * @param string $zip_content Raw ZIP bytes.
     * @param array  $data        Form/JSON params.
     *
     * @return array Normalized upload parameters.
     */
    private function build_upload_params($zip_content, $data) {
        $slug     = sanitize_file_name($data['slug'] ?? '');
        $activate = !empty($data['activate']);
        $upload_source = isset($data['upload_source']) ? sanitize_text_field($data['upload_source']) : UPLOAD_SOURCE_REST_API;
        $client_plugin_version = isset($data['plugin_version']) ? sanitize_text_field($data['plugin_version']) : '';

        $valid_sources = json_decode(UPLOAD_SOURCES_VALID, true);
        if (!in_array($upload_source, $valid_sources, true)) {
            $upload_source = UPLOAD_SOURCE_REST_API;
        }

        $this->file_logger->debug('Upload parameters', array(
            'slug'           => $slug,
            'activate'       => $activate,
            'upload_source'  => $upload_source,
            'client_version' => $client_plugin_version,
            'file_size'      => strlen($zip_content),
        ));

        return array(
            'zip_content'          => $zip_content,
            'slug'                 => $slug,
            'activate'             => $activate,
            'upload_source'        => $upload_source,
            'client_plugin_version' => $client_plugin_version,
        );
    }

    /**
     * Write ZIP content to temp file and validate its structure.
     *
     * @param string $zip_content Raw ZIP bytes.
     * @param string $slug        Optional slug hint.
     *
     * @return array|WP_REST_Response Array with temp_file and slug, or error response.
     */
    private function validate_and_write_zip($zip_content, $slug) {
        $temp_dir  = $this->get_temp_dir();
        $temp_file = $temp_dir . '/' . ($slug ?: 'plugin_' . time()) . '.zip';

        $this->file_logger->debug('Writing temp file', array('path' => $temp_file));
        if (file_put_contents($temp_file, $zip_content) === false) {
            $this->file_logger->error('Failed to write temp file');
            $this->logger->log_upload_failed($slug, 'Failed to write temp file');

            return $this->error_response(MSG_UPLOAD_FAILED, HTTP_SERVER_ERROR);
        }

        $this->file_logger->debug('Validating ZIP archive');
        $zip = new ZipArchive();
        if ($zip->open($temp_file) !== true) {
            @unlink($temp_file);
            $this->file_logger->error('Invalid ZIP archive');
            $this->logger->log_upload_failed($slug, 'Invalid ZIP archive');

            return $this->error_response('Invalid ZIP archive', HTTP_BAD_REQUEST);
        }

        $detected_slug = $this->detect_plugin_slug_from_zip($zip);
        $zip->close();

        if (!$detected_slug) {
            @unlink($temp_file);
            $this->file_logger->error('Could not detect plugin in ZIP');
            $this->logger->log_upload_failed($slug, 'Could not detect plugin in ZIP');

            return $this->error_response('Could not detect plugin in ZIP', HTTP_BAD_REQUEST);
        }

        if (empty($slug)) {
            $slug = $detected_slug;
        }

        $this->file_logger->info('Plugin slug determined', array('slug' => $slug));

        return array('temp_file' => $temp_file, 'slug' => $slug);
    }

    /**
     * Remove duplicate plugin folders that share the same slug or TextDomain.
     *
     * @param string $slug        Target plugin slug.
     * @param string $plugins_dir Absolute path to wp-content/plugins.
     *
     * @return int Number of duplicates removed.
     */
    private function remove_duplicate_plugins($slug, $plugins_dir) {
        if (RiseupBooleanHelpers::is_func_missing('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        $duplicates_removed = 0;

        foreach ($all_plugins as $pfile => $pdata) {
            $pdir = dirname($pfile);
            $isSkippable = ($pdir === '.' || $pdir === $slug);
            if ($isSkippable) {
                continue;
            }

            $hasMatchingTextDomain = (isset($pdata['TextDomain']) && $pdata['TextDomain'] === $slug);
            $hasMatchingSlugInPath = (isset($pdata['Name']) && strpos(strtolower($pfile), $slug) !== false);
            $isDuplicate = ($hasMatchingTextDomain || $hasMatchingSlugInPath);
            if (!$isDuplicate) {
                continue;
            }

            $dup_dir = $plugins_dir . '/' . $pdir;
            $this->file_logger->warn('Duplicate plugin folder detected', array(
                'duplicate_dir' => $pdir,
                'duplicate_ver' => isset($pdata['Version']) ? $pdata['Version'] : 'unknown',
                'target_slug'   => $slug,
            ));

            if (is_plugin_active($pfile)) {
                deactivate_plugins($pfile);
                $this->file_logger->info('Deactivated duplicate plugin', array('file' => $pfile));
            }

            if (is_dir($dup_dir)) {
                $this->delete_directory($dup_dir);
                $this->file_logger->info('Removed duplicate plugin folder', array('dir' => $dup_dir));
                $duplicates_removed++;
            }
        }

        if ($duplicates_removed > 0) {
            wp_cache_delete('plugins', 'plugins');
            $this->file_logger->info('Duplicate cleanup complete', array('removed' => $duplicates_removed));
        }

        return $duplicates_removed;
    }

    /**
     * Pre-log self-update activity before files are replaced.
     *
     * @param string $slug            Plugin slug.
     * @param string $upload_source   Upload source identifier.
     * @param string $client_version  Client-reported version.
     * @param int    $file_size       ZIP file size in bytes.
     */
    private function pre_log_self_update($slug, $upload_source, $client_version, $file_size) {
        $old_version = PLUGIN_VERSION;

        $this->file_logger->info('Self-update detected, pre-logging activity', array(
            'old_version'   => $old_version,
            'upload_source' => $upload_source,
        ));

        $this->logger->log_plugin_action(
            ACTION_UPLOAD,
            $slug,
            STATUS_SUCCESS,
            array(
                'is_update'      => true,
                'is_self_update' => true,
                'old_version'    => $old_version,
                'new_version'    => $client_version,
                'file_size'      => $file_size,
                'note'           => 'Pre-logged before self-update to ensure audit trail',
            ),
            null,
            array(
                'plugin_version' => $client_version ?: $old_version,
                'upload_source'  => $upload_source,
            )
        );
    }

    /**
     * Deactivate plugin and remove old directory if this is an update.
     *
     * @param string $slug       Plugin slug.
     * @param bool   $is_update  Whether this is an update.
     * @param string $target_dir Absolute path to plugin directory.
     *
     * @return bool Whether the plugin was previously active.
     */
    private function deactivate_if_updating($slug, $is_update, $target_dir) {
        $this->file_logger->info($is_update ? 'Updating existing plugin' : 'Installing new plugin', array(
            'slug'       => $slug,
            'target_dir' => $target_dir,
        ));

        if (!$is_update) {
            return false;
        }

        $plugin_file = $this->find_plugin_file($slug);
        $was_active = false;

        if ($plugin_file) {
            $was_active = is_plugin_active($plugin_file);
            if ($was_active) {
                $this->file_logger->debug('Deactivating plugin before update', array('plugin_file' => $plugin_file));
                deactivate_plugins($plugin_file);
            }
        }

        $this->file_logger->debug('Removing old plugin version', array('target_dir' => $target_dir));
        $this->delete_directory($target_dir);

        return $was_active;
    }

    /**
     * Extract ZIP to a temp directory, then move to the correct plugin location.
     *
     * @param string $temp_file  Path to the temp ZIP file.
     * @param string $slug       Plugin slug.
     * @param string $target_dir Target plugin directory.
     *
     * @return true|WP_REST_Response True on success, or error response.
     */
    private function extract_to_plugins_dir($temp_file, $slug, $target_dir) {
        $temp_extract_dir = $this->get_temp_dir() . '/extract_' . uniqid();
        wp_mkdir_p($temp_extract_dir);

        $this->file_logger->debug('Extracting ZIP to temp directory', array(
            'temp_dir'  => $temp_extract_dir,
            'temp_file' => $temp_file,
        ));

        $zip = new ZipArchive();
        if ($zip->open($temp_file) !== true) {
            @unlink($temp_file);
            $this->delete_directory($temp_extract_dir);
            $this->file_logger->error('Failed to open ZIP for extraction');

            return $this->error_response('Failed to open ZIP for extraction', HTTP_SERVER_ERROR);
        }

        $zip->extractTo($temp_extract_dir);
        $zip->close();
        @unlink($temp_file);

        $extracted_folders = glob($temp_extract_dir . '/*', GLOB_ONLYDIR);
        if (empty($extracted_folders)) {
            $this->delete_directory($temp_extract_dir);
            $this->file_logger->error('No folder found in extracted ZIP');
            $this->logger->log_upload_failed($slug, 'No folder found in extracted ZIP');

            return $this->error_response('No folder found in extracted ZIP', HTTP_SERVER_ERROR);
        }

        $extracted_folder = $extracted_folders[0];
        $this->file_logger->debug('Found extracted folder', array(
            'extracted_name' => basename($extracted_folder),
            'target_slug'    => $slug,
            'needs_rename'   => basename($extracted_folder) !== $slug,
        ));

        if (rename($extracted_folder, $target_dir)) {
            $this->file_logger->info('Plugin installed to correct location', array(
                'from' => $extracted_folder,
                'to'   => $target_dir,
            ));
        } else {
            $this->file_logger->warn('Rename failed, attempting copy', array(
                'from' => $extracted_folder,
                'to'   => $target_dir,
            ));
            $this->copy_directory($extracted_folder, $target_dir);
            $this->delete_directory($extracted_folder);
        }

        $this->delete_directory($temp_extract_dir);

        return true;
    }

    /**
     * Reset OPcache and locate the plugin's main file.
     *
     * @param string $slug Plugin slug.
     *
     * @return string|WP_REST_Response Plugin file path, or error response.
     */
    private function reset_opcache_and_find_plugin($slug) {
        if (function_exists('opcache_reset')) {
            opcache_reset();
            $this->file_logger->info('Full OPcache reset after plugin extraction');
        }

        $plugin_file = $this->find_plugin_file($slug);

        if (!empty($plugin_file)) {
            $full_plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;

            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($full_plugin_path, true);
                $this->file_logger->debug('OPcache invalidated for plugin file', array('path' => $full_plugin_path));
            }

            $constants_file = WP_PLUGIN_DIR . '/' . $slug . '/includes/constants.php';
            $hasConstantsFile = (file_exists($constants_file) && function_exists('opcache_invalidate'));
            if ($hasConstantsFile) {
                opcache_invalidate($constants_file, true);
                $this->file_logger->debug('OPcache invalidated for constants file', array('path' => $constants_file));
            }

            wp_cache_delete('plugins', 'plugins');
        }

        if (!$plugin_file) {
            $this->file_logger->error('Could not find plugin file after extraction', array('slug' => $slug));
            $this->logger->log_upload_failed($slug, 'Could not find plugin file after extraction');

            return $this->error_response('Could not find plugin file after extraction', HTTP_SERVER_ERROR);
        }

        $this->file_logger->info('Plugin file found', array('plugin_file' => $plugin_file));

        return $plugin_file;
    }

    /**
     * Activate the plugin if requested or if it was previously active.
     *
     * @param string $plugin_file Plugin file relative path.
     * @param string $slug        Plugin slug.
     * @param bool   $activate    Whether activation was requested.
     * @param bool   $was_active  Whether the plugin was previously active.
     * @param bool   $is_update   Whether this is an update.
     *
     * @return array|WP_REST_Response Array with 'activated' key, or partial-success response.
     */
    private function activate_if_needed($plugin_file, $slug, $activate, $was_active, $is_update) {
        $shouldActivate = ($activate || $was_active);
        if (!$shouldActivate) {
            return array('activated' => false);
        }

        $this->file_logger->debug('Activating plugin');
        $result = activate_plugin($plugin_file);

        if (is_wp_error($result)) {
            $error_msg = $result->get_error_message();
            $this->file_logger->warn('Activation failed', array('error' => $error_msg));
            $this->logger->log_upload_failed($slug, MSG_ACTIVATION_FAILED . ': ' . $error_msg);

            return RiseupEnvelopeBuilder::success('Plugin uploaded but activation failed', HTTP_OK)
                ->setRequestedAt('/' . API_FULL_NAMESPACE . '/' . ENDPOINT_UPLOAD)
                ->setSingleResult(array(
                    'plugin_slug'      => $slug,
                    'is_update'        => $is_update,
                    'activated'        => false,
                    'activation_error' => $error_msg,
                ))
                ->toResponse();
        }

        $this->file_logger->info('Plugin activated successfully');

        return array('activated' => true);
    }

    /**
     * Detect the installed plugin version from disk.
     *
     * @param string $plugin_file     Plugin file relative path.
     * @param string $slug            Plugin slug.
     * @param bool   $is_self_update  Whether this is a self-update.
     * @param string $client_version  Client-reported version.
     *
     * @return array Array with 'version' and 'source' keys.
     */
    private function detect_installed_version($plugin_file, $slug, $is_self_update, $client_version) {
        $installed_version = '';
        $full_path = WP_PLUGIN_DIR . '/' . $plugin_file;

        clearstatcache(true, $full_path);
        if (file_exists($full_path)) {
            $file_contents = file_get_contents($full_path, false, null, 0, 8192);
            $hasVersionHeader = ($file_contents !== false && preg_match('/Version:\s*([0-9]+\.[0-9]+\.[0-9]+)/', $file_contents, $matches));
            if ($hasVersionHeader) {
                $installed_version = $matches[1];
                $this->file_logger->debug('Version read directly from file header', array('version' => $installed_version));
            }
        }

        // Fallback: try get_plugin_data (may be cached by OPcache)
        if (empty($installed_version)) {
            $plugin_data = get_plugin_data($full_path, false, false);
            if (!empty($plugin_data['Version'])) {
                $installed_version = $plugin_data['Version'];
            }
        }

        // Self-update: client_version > installed > constant
        // Other plugins: installed > client_version > constant
        if ($is_self_update) {
            $version = $client_version ?: ($installed_version ?: PLUGIN_VERSION);
            $source  = !empty($client_version) ? 'client (self-update)' : ($installed_version ? 'file_header' : 'constant');
        } else {
            $version = $installed_version ?: ($client_version ?: PLUGIN_VERSION);
            $source  = $installed_version ? 'file_header' : (!empty($client_version) ? 'client' : 'constant');
        }

        $this->file_logger->info('Plugin version determined', array(
            'version'          => $version,
            'installed_version' => $installed_version,
            'client_version'   => $client_version,
            'constant_version' => PLUGIN_VERSION,
            'is_self_update'   => $is_self_update,
            'source'           => $source,
        ));

        return array('version' => $version, 'source' => $source);
    }

    /**
     * Handle list plugins.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_list_plugins($request) {
        $this->file_logger->info('List plugins endpoint called');

        try {
            if (RiseupBooleanHelpers::is_func_missing('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $all_plugins    = get_plugins();
            $active_plugins = get_option('active_plugins', array());
            $plugins        = array();

            foreach ($all_plugins as $plugin_file => $plugin_data) {
                $slug = dirname($plugin_file);
                if ($slug === '.') {
                    $slug = basename($plugin_file, '.php');
                }

                $plugins[] = array(
                    'slug'        => $slug,
                    'name'        => $plugin_data['Name'],
                    'version'     => $plugin_data['Version'],
                    'author'      => $plugin_data['Author'],
                    'description' => $plugin_data['Description'],
                    'active'      => in_array($plugin_file, $active_plugins, true),
                    'plugin_file' => $plugin_file,
                );
            }

            $this->file_logger->debug('Plugins listed', array('count' => count($plugins)));

            return RiseupEnvelopeBuilder::success()
                ->setRequestedAt('/' . API_FULL_NAMESPACE . ENDPOINT_PLUGINS)
                ->setResults($plugins)
                ->toResponse();
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'List plugins error');
            return $this->error_response('Failed to list plugins: ' . $e->getMessage(), HTTP_SERVER_ERROR, $e);
        }
    }

    /**
     * Handle plugin files listing (for diff preview).
     * Returns all files in a plugin directory with their MD5 hashes.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_plugin_files($request) {
        $body = $request->get_json_params();
        $slug = isset($body['plugin']) ? sanitize_text_field($body['plugin']) : $request->get_param('slug');
        if (empty($slug)) {
            return $this->error_response('Plugin slug is required in JSON body', HTTP_BAD_REQUEST);
        }
        $this->file_logger->info('Plugin files endpoint called', array('slug' => $slug));

        try {
            if (RiseupBooleanHelpers::is_func_missing('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            // Find the plugin directory
            $plugin_dir = WP_PLUGIN_DIR . '/' . $slug;
            
            if (RiseupBooleanHelpers::is_dir_missing($plugin_dir)) {
                $this->file_logger->warn('Plugin directory not found', array('slug' => $slug, 'path' => $plugin_dir));
                return $this->error_response(MSG_PLUGIN_NOT_FOUND . ': ' . $slug, HTTP_NOT_FOUND);
            }

            // Load uploadignore patterns if available
            $ignore = RiseupUploadIgnore::from_directory($plugin_dir);

            // Use file cache for efficient hash computation
            $fileCache = RiseupFileCache::getInstance($this->file_logger, $this->db);
            $result = $fileCache->getManifest($slug, $plugin_dir, $ignore);

            $this->file_logger->info('Plugin files scanned', array(
                'slug'     => $slug,
                'count'    => count($result['files']),
                'cached'   => $result['cached'],
                'computed' => $result['computed'],
            ));

            return new WP_REST_Response(array(
                'success'    => true,
                'plugin'     => $slug,
                'totalFiles' => count($result['files']),
                'files'      => $result['files'],
            ), HTTP_OK);
        } catch (Throwable $e) {
            return $this->error_response('Failed to list plugin files: ' . $e->getMessage(), HTTP_SERVER_ERROR, $e);
        }
    }

    /**
     * Handle sync manifest endpoint.
     * Returns cached file hashes optimized for sync comparison.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function handle_sync_manifest($request) {
        $body = $request->get_json_params();
        $slug = isset($body['plugin']) ? sanitize_text_field($body['plugin']) : $request->get_param('slug');
        if (empty($slug)) {
            return $this->error_response('Plugin slug is required in JSON body', HTTP_BAD_REQUEST);
        }
        $this->file_logger->info('Sync manifest endpoint called', array('slug' => $slug));

        try {
            if (RiseupBooleanHelpers::is_func_missing('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $plugin_dir = WP_PLUGIN_DIR . '/' . $slug;
            
            if (RiseupBooleanHelpers::is_dir_missing($plugin_dir)) {
                $this->file_logger->warn('Plugin directory not found', array('slug' => $slug, 'path' => $plugin_dir));
                return $this->error_response(MSG_PLUGIN_NOT_FOUND . ': ' . $slug, HTTP_NOT_FOUND);
            }

            $ignore = RiseupUploadIgnore::from_directory($plugin_dir);

            $fileCache = RiseupFileCache::getInstance($this->file_logger, $this->db);
            $result = $fileCache->getManifest($slug, $plugin_dir, $ignore);

            return new WP_REST_Response(array(
                'success' => true,
                'data'    => array(
                    'plugin'      => $slug,
                    'fileCount'   => count($result['files']),
                    'generatedAt' => gmdate('c'),
                    'cached'      => $result['cached'] > 0,
                    'cacheStats'  => array(
                        'fromCache' => $result['cached'],
                        'computed'  => $result['computed'],
                        'removed'   => $result['removed'],
                    ),
                    'files'       => $result['files'],
                ),
            ), HTTP_OK);
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'Sync manifest error');
            return $this->error_response('Failed to generate sync manifest: ' . $e->getMessage(), HTTP_SERVER_ERROR, $e);
        }
    }

    /**
     * Handle sync push endpoint.
     * Receives delta files (replacements and deletions) from the Go backend.
     *
     * Expected JSON body: { "plugin": "slug", "files": [{ "path": "...", "content": "base64", "action": "replace|delete" }] }
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function handle_sync_push($request) {
        $body = $request->get_json_params();
        $slug = isset($body['plugin']) ? sanitize_text_field($body['plugin']) : '';
        $files = isset($body['files']) ? $body['files'] : array();

        if (empty($slug)) {
            return $this->error_response('Plugin slug is required in JSON body', HTTP_BAD_REQUEST);
        }
        if (empty($files) || !is_array($files)) {
            return $this->error_response('Files array is required', HTTP_BAD_REQUEST);
        }

        $this->file_logger->info('Sync push endpoint called', array('slug' => $slug, 'fileCount' => count($files)));

        try {
            $plugin_dir = WP_PLUGIN_DIR . '/' . $slug;
            if (RiseupBooleanHelpers::is_dir_missing($plugin_dir)) {
                return $this->error_response(MSG_PLUGIN_NOT_FOUND . ': ' . $slug, HTTP_NOT_FOUND);
            }

            $ignore = RiseupUploadIgnore::from_directory($plugin_dir);
            $results = array();
            $files_updated = 0;
            $files_deleted = 0;
            $files_ignored = 0;
            $ignored_files = array();

            foreach ($files as $file) {
                $path = isset($file['path']) ? $file['path'] : '';
                $action = isset($file['action']) ? $file['action'] : '';
                $content = isset($file['content']) ? $file['content'] : '';

                if (empty($path) || empty($action)) {
                    $results[] = array('path' => $path, 'action' => $action, 'status' => 'skipped', 'reason' => 'Missing path or action');
                    continue;
                }

                // Check ignore patterns
                if ($ignore && $ignore->is_ignored($path)) {
                    $files_ignored++;
                    $ignored_files[] = $path;
                    $results[] = array('path' => $path, 'action' => $action, 'status' => 'ignored', 'reason' => MSG_FILE_IGNORED);
                    continue;
                }

                $full_path = $plugin_dir . '/' . $path;

                // Security: prevent path traversal
                $real_plugin_dir = realpath($plugin_dir);
                $resolved = realpath(dirname($full_path));
                if ($resolved === false) {
                    // Directory doesn't exist yet for new files
                    $resolved = $plugin_dir;
                }
                if (strpos($resolved, $real_plugin_dir) !== 0 && $action !== SYNC_ACTION_DELETE) {
                    $results[] = array('path' => $path, 'action' => $action, 'status' => 'error', 'reason' => 'Path traversal detected');
                    continue;
                }

                if ($action === SYNC_ACTION_REPLACE) {
                    // Decode base64 content and write file
                    $decoded = base64_decode($content, true);
                    if ($decoded === false) {
                        $results[] = array('path' => $path, 'action' => $action, 'status' => 'error', 'reason' => 'Invalid base64 content');
                        continue;
                    }
                    $dir = dirname($full_path);
                    if (RiseupBooleanHelpers::is_dir_missing($dir)) {
                        RiseupPathUtils::ensureDir($dir);
                    }
                    if (file_put_contents($full_path, $decoded) !== false) {
                        $files_updated++;
                        $results[] = array('path' => $path, 'action' => $action, 'status' => 'success');
                    } else {
                        $results[] = array('path' => $path, 'action' => $action, 'status' => 'error', 'reason' => 'Failed to write file');
                    }

                } elseif ($action === SYNC_ACTION_DELETE) {
                    // Delete the file from remote
                    if (file_exists($full_path)) {
                        // Log deletion to audit trail
                        $this->file_logger->info('Sync delete', array('slug' => $slug, 'path' => $path));
                        if ($this->db) {
                            $this->db->log_transaction(
                                ACTION_SYNC_DELETE,
                                $slug,
                                STATUS_SUCCESS,
                                'Deleted via sync: ' . $path,
                                null,
                                null,
                                TRIGGERED_BY_API
                            );
                        }
                        if (unlink($full_path)) {
                            $files_deleted++;
                            $results[] = array('path' => $path, 'action' => $action, 'status' => 'success');
                            // Clean up empty parent directories
                            $parent = dirname($full_path);
                            while ($parent !== $plugin_dir && is_dir($parent) && count(scandir($parent)) <= 2) {
                                rmdir($parent);
                                $parent = dirname($parent);
                            }
                        } else {
                            $results[] = array('path' => $path, 'action' => $action, 'status' => 'error', 'reason' => 'Failed to delete file');
                        }
                    } else {
                        // File already doesn't exist — treat as success
                        $files_deleted++;
                        $results[] = array('path' => $path, 'action' => $action, 'status' => 'success', 'reason' => 'Already absent');
                    }
                } else {
                    $results[] = array('path' => $path, 'action' => $action, 'status' => 'error', 'reason' => 'Unknown action: ' . $action);
                }
            }

            // Log overall sync operation
            if ($this->db) {
                $this->db->log_transaction(
                    ACTION_SYNC,
                    $slug,
                    STATUS_SUCCESS,
                    sprintf('Sync: %d updated, %d deleted, %d ignored', $files_updated, $files_deleted, $files_ignored),
                    null,
                    null,
                    TRIGGERED_BY_API
                );
            }

            // Invalidate file cache after sync
            $fileCache = RiseupFileCache::getInstance($this->file_logger, $this->db);
            $fileCache->invalidate($slug);

            return new WP_REST_Response(array(
                'success'       => true,
                'files_updated' => $files_updated,
                'files_deleted' => $files_deleted,
                'files_ignored' => $files_ignored,
                'ignored_files' => $ignored_files,
                'results'       => $results,
            ), HTTP_OK);
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'Sync push error');
            return $this->error_response('Sync push failed: ' . $e->getMessage(), HTTP_SERVER_ERROR, $e);
        }
    }

    /**
     * Recursively scan a directory and collect file info with hashes.
     *
     * @param string               $base_dir The base directory for relative paths.
     * @param string               $dir      Current directory to scan.
     * @param RiseupUploadIgnore   $ignore   Ignore patterns.
     * @param array                $files    Reference to files array to populate.
     *
     * @return void
     */
    private function scan_directory_for_files($base_dir, $dir, $ignore, &$files) {
        $items = @scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full_path = $dir . '/' . $item;
            $rel_path  = ltrim(str_replace($base_dir, '', $full_path), '/\\');

            // Check ignore patterns
            if ($ignore->should_ignore($rel_path)) {
                continue;
            }

            if (is_dir($full_path)) {
                // Recurse into subdirectory
                $this->scan_directory_for_files($base_dir, $full_path, $ignore, $files);
            } else {
                // Calculate MD5 hash
                $hash = @md5_file($full_path);
                $size = @filesize($full_path);
                $mtime = @filemtime($full_path);

                $files[] = array(
                    'path'       => str_replace('\\', '/', $rel_path),
                    'hash'       => $hash ?: '',
                    'size'       => $size ?: 0,
                    'modifiedAt' => $mtime ? gmdate('c', $mtime) : null,
                );
            }
        }
    }

    /**
     * Handle getting content of a single file from a plugin.
     * Used for diff viewing in the UI.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_plugin_file_content($request) {
        $json = $request->get_json_params();
        $slug = isset($json['plugin']) ? sanitize_text_field($json['plugin']) : $request->get_param('slug');
        $file_path = isset($json['path']) ? $json['path'] : null;
        if (empty($slug)) {
            return $this->error_response('Plugin slug is required in JSON body', HTTP_BAD_REQUEST);
        }

        $this->file_logger->info('Plugin file content endpoint called', array(
            'slug' => $slug,
            'path' => $file_path,
        ));

        try {
            if (empty($file_path)) {
                return $this->error_response('File path is required', HTTP_BAD_REQUEST);
            }

            // Sanitize path - prevent directory traversal
            $file_path = ltrim($file_path, '/\\');
            if (strpos($file_path, '..') !== false) {
                return $this->error_response('Invalid file path', HTTP_BAD_REQUEST);
            }

            $plugin_dir = WP_PLUGIN_DIR . '/' . $slug;
            $full_path = $plugin_dir . '/' . $file_path;

            if (RiseupBooleanHelpers::is_dir_missing($plugin_dir)) {
                return $this->error_response(MSG_PLUGIN_NOT_FOUND . ': ' . $slug, HTTP_NOT_FOUND);
            }

            // Verify the file is within the plugin directory
            $real_plugin_dir = realpath($plugin_dir);
            $real_file_path = realpath($full_path);

            if ($real_file_path === false || strpos($real_file_path, $real_plugin_dir) !== 0) {
                return $this->error_response('File not found or invalid path', HTTP_NOT_FOUND);
            }

            if (!is_file($real_file_path)) {
                return $this->error_response('File not found', HTTP_NOT_FOUND);
            }

            $content = @file_get_contents($real_file_path);
            if ($content === false) {
                return $this->error_response('Failed to read file', HTTP_SERVER_ERROR);
            }

            $this->file_logger->info('File content read', array(
                'slug' => $slug,
                'path' => $file_path,
                'size' => strlen($content),
            ));

            return new WP_REST_Response(array(
                'success' => true,
                'path'    => $file_path,
                'content' => $content,
            ), HTTP_OK);
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'Plugin file content error');
            return $this->error_response('Failed to read file: ' . $e->getMessage(), HTTP_SERVER_ERROR, $e);
        }
    }

    /**
     * Handle export-self (export this plugin as ZIP).
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_export_self($request) {
        $this->file_logger->info('Export-self endpoint called');

        try {
            $plugin_dir = dirname(__FILE__);
            $temp_dir   = $this->get_temp_dir();
            $zip_file   = $temp_dir . '/' . PLUGIN_SLUG . '.zip';

            $this->file_logger->debug('Creating ZIP', array('source' => $plugin_dir, 'target' => $zip_file));

            // Create ZIP.
            $zip = new ZipArchive();
            if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                $this->file_logger->error('Failed to create ZIP file');
                return $this->error_response('Failed to create ZIP file', HTTP_SERVER_ERROR);
            }

            // Load uploadignore.
            $ignore = RiseupUploadIgnore::from_directory($plugin_dir);
            $this->file_logger->debug('Uploadignore loaded', array('has_patterns' => $ignore->is_loaded()));

            // Add files recursively.
            $this->add_dir_to_zip($zip, $plugin_dir, PLUGIN_SLUG, $ignore);
            $zip->close();

            // Read and encode.
            $zip_content = file_get_contents($zip_file);
            @unlink($zip_file);

            if ($zip_content === false) {
                $this->file_logger->error('Failed to read ZIP file');
                return $this->error_response('Failed to read ZIP file', HTTP_SERVER_ERROR);
            }

            $this->file_logger->info('Export-self complete', array('size' => strlen($zip_content)));

            // Log the export.
            $this->logger->log_plugin_action(ACTION_EXPORT_SELF, PLUGIN_SLUG, STATUS_SUCCESS, array(
                'size' => strlen($zip_content),
            ));

            return new WP_REST_Response(array(
                'success'    => true,
                'plugin_zip' => base64_encode($zip_content),
                'slug'       => PLUGIN_SLUG,
                'version'    => PLUGIN_VERSION,
            ), HTTP_OK);
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'Export-self error');
            return $this->error_response('Export failed: ' . $e->getMessage(), HTTP_SERVER_ERROR, $e);
        }
    }

    /**
     * Export any installed plugin as a base64-encoded ZIP.
     * Used by the Go backend for pre-publish backup / rollback.
     */
    public function handle_export_plugin($request) {
        $body = $request->get_json_params();
        $slug = isset($body['plugin']) ? sanitize_text_field($body['plugin']) : $request->get_param('slug');
        if (empty($slug)) {
            return $this->error_response('Plugin slug is required in JSON body', HTTP_BAD_REQUEST);
        }
        $this->file_logger->info('Export-plugin endpoint called', array('slug' => $slug));

        return $this->safe_execute(function () use ($slug) {
            $plugins_dir = WP_PLUGIN_DIR;
            $plugin_dir  = RiseupPathUtils::join($plugins_dir, $slug);

            if (!RiseupPathUtils::dirExists($plugin_dir)) {
                return $this->error_response('Plugin not found: ' . $slug, HTTP_NOT_FOUND);
            }

            // Safety: prevent path traversal
            if (!RiseupPathUtils::isSafePath($plugin_dir, $plugins_dir)) {
                return $this->error_response('Invalid plugin slug', HTTP_BAD_REQUEST);
            }

            $temp_dir = $this->get_temp_dir();
            $zip_file = RiseupPathUtils::join($temp_dir, $slug . '-backup.zip');

            $zip = new ZipArchive();
            if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                $this->file_logger->error('Failed to create export ZIP');
                return $this->error_response('Failed to create ZIP file', HTTP_SERVER_ERROR);
            }

            // Add all files recursively
            $ignore = RiseupUploadIgnore::from_directory($plugin_dir);
            $this->add_dir_to_zip($zip, $plugin_dir, $slug, $ignore);
            $file_count = $zip->numFiles;
            $zip->close();

            $zip_content = file_get_contents($zip_file);
            @unlink($zip_file);

            if ($zip_content === false) {
                return $this->error_response('Failed to read ZIP file', HTTP_SERVER_ERROR);
            }

            $this->file_logger->info('Export-plugin complete', array(
                'slug' => $slug,
                'size' => strlen($zip_content),
                'files' => $file_count,
            ));

            $this->logger->log_plugin_action(ACTION_EXPORT_PLUGIN, $slug, STATUS_SUCCESS, array(
                'size'  => strlen($zip_content),
                'files' => $file_count,
            ));

            return new WP_REST_Response(array(
                'success'    => true,
                'plugin_zip' => base64_encode($zip_content),
                'slug'       => $slug,
                'file_count' => $file_count,
                'size'       => strlen($zip_content),
            ), HTTP_OK);
        });
    }

    // =========================================================================
    // POST HANDLERS
    // =========================================================================

    /**
     * Handle list posts.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_list_posts($request) {
        $this->file_logger->debug('List posts endpoint called');

        $result = $this->post_manager->list_posts(array(
            'status' => $request->get_param('status'),
            'limit'  => $request->get_param('limit'),
            'offset' => $request->get_param('offset'),
            'search' => $request->get_param('search'),
        ));

        return new WP_REST_Response($result, $result['success'] ? HTTP_OK : HTTP_SERVER_ERROR);
    }

    /**
     * Handle create post.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_create_post($request) {
        $this->file_logger->info('Create post endpoint called');

        $data   = $request->get_json_params();
        $result = $this->post_manager->create_post($data);

        return new WP_REST_Response($result, $result['success'] ? HTTP_CREATED : HTTP_BAD_REQUEST);
    }

    /**
     * Handle list categories.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_list_categories($request) {
        $this->file_logger->debug('List categories endpoint called');

        $result = $this->post_manager->list_categories(array(
            'limit'  => $request->get_param('limit'),
            'offset' => $request->get_param('offset'),
            'search' => $request->get_param('search'),
        ));

        return new WP_REST_Response($result, $result['success'] ? HTTP_OK : HTTP_SERVER_ERROR);
    }

    /**
     * Handle create category.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_create_category($request) {
        $this->file_logger->info('Create category endpoint called');

        $data   = $request->get_json_params();
        $result = $this->post_manager->create_category($data);

        return new WP_REST_Response($result, $result['success'] ? HTTP_CREATED : HTTP_BAD_REQUEST);
    }

    // =========================================================================
    // LOG HANDLERS
    // =========================================================================

    /**
     * Handle query logs.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_query_logs($request) {
        $this->file_logger->debug('Query logs endpoint called');

        try {
            // Initialize database if not already done.
            $this->db->init();

            $filters = array(
                'plugin' => $request->get_param('plugin'),
                'action' => $request->get_param('action'),
                'user'   => $request->get_param('user'),
                'status' => $request->get_param('status'),
                'from'   => $request->get_param('from'),
                'to'     => $request->get_param('to'),
            );

            $limit  = $request->get_param('limit') ?? DEFAULT_LIMIT;
            $offset = $request->get_param('offset') ?? 0;

            $result = $this->db->query_transactions($filters, $limit, $offset);

            $total = $result['total'];
            $per_page = (int) $limit;

            return RiseupEnvelopeBuilder::success()
                ->setRequestedAt('/' . API_FULL_NAMESPACE . '/' . ENDPOINT_LOGS)
                ->setResults($result['logs'])
                ->setPagination($total, $per_page, $per_page > 0 ? (int) floor($offset / $per_page) + 1 : 1)
                ->toResponse();
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'Query logs error');
            return $this->error_response('Failed to query logs: ' . $e->getMessage(), HTTP_SERVER_ERROR, $e);
        }
    }

    /**
     * Handle logs stats.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_logs_stats($request) {
        $this->file_logger->debug('Logs stats endpoint called');

        try {
            // Initialize database if not already done.
            $this->db->init();

            $stats = $this->db->get_stats();

            return RiseupEnvelopeBuilder::success()
                ->setRequestedAt('/' . API_FULL_NAMESPACE . '/' . ENDPOINT_LOGS_STATS)
                ->setSingleResult($stats)
                ->toResponse();
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'Logs stats error');
            return $this->error_response('Failed to get stats: ' . $e->getMessage(), HTTP_SERVER_ERROR, $e);
        }
    }

    // =========================================================================
    // PLUGIN LIFECYCLE HANDLERS (Exists/Enable/Disable/Delete)
    // =========================================================================

    /**
     * Handle plugin existence check (lightweight pre-flight).
     * Returns whether a plugin slug is installed without performing any mutations.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_plugin_exists($request) {
        $body = $request->get_json_params();
        $slug = isset($body['plugin']) ? sanitize_text_field($body['plugin']) : $request->get_param('slug');
        if (empty($slug)) {
            return $this->error_response('Plugin slug is required in JSON body', HTTP_BAD_REQUEST);
        }

        try {
            $plugin_file = $this->find_plugin_file($slug);
            $exists = (bool) $plugin_file;

            if ($exists) {
                $status = is_plugin_active($plugin_file) ? 'active' : 'inactive';
            } else {
                $status = 'not_installed';
            }

            return RiseupEnvelopeBuilder::success()
                ->setRequestedAt('/' . API_FULL_NAMESPACE . '/' . ENDPOINT_PLUGIN_EXISTS)
                ->setSingleResult(array(
                    'plugin_slug'  => $slug,
                    'exists'       => $exists,
                    'status'       => $status,
                    'plugin_file'  => $exists ? $plugin_file : null,
                    'requestUrl'   => $_SERVER['REQUEST_URI'] ?? '',
                    'responseUrl'  => home_url(),
                ))
                ->toResponse();
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'handle_plugin_exists: Failed');
            return $this->error_response(
                'Failed to check plugin existence: ' . $e->getMessage(),
                HTTP_SERVER_ERROR,
                $e
            );
        }
    }

    // -------------------------------------------------------------------------
    // Shared lifecycle helpers
    // -------------------------------------------------------------------------

    /**
     * Load WordPress plugin admin functions if not already available.
     *
     * @param bool $include_file_functions Whether to also load file.php (for delete).
     * @return WP_REST_Response|null Error response on failure, null on success.
     */
    private function load_plugin_functions($include_file_functions = false) {
        try {
            if (RiseupBooleanHelpers::is_func_missing('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            if ($include_file_functions && RiseupBooleanHelpers::is_func_missing('delete_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }

            return null;
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'Failed to load plugin functions');

            return $this->error_response(
                'Failed to load WordPress plugin functions: ' . $e->getMessage(),
                HTTP_SERVER_ERROR,
                $e
            );
        }
    }

    /**
     * Resolve and validate a plugin slug from a REST request body.
     *
     * @param WP_REST_Request $request REST request.
     * @return array{slug: string, plugin_file: string}|WP_REST_Response Resolved info or error response.
     */
    private function resolve_plugin_from_request($request) {
        $body = $request->get_json_params();
        $slug = isset($body['plugin']) ? sanitize_text_field($body['plugin']) : $request->get_param('slug');

        if (empty($slug)) {
            return $this->error_response('Plugin slug is required in JSON body', HTTP_BAD_REQUEST);
        }

        try {
            $plugin_file = $this->find_plugin_file($slug);

            if (!$plugin_file) {
                $this->file_logger->warn('Plugin not found', array('slug' => $slug));

                return $this->error_response(MSG_PLUGIN_NOT_FOUND . ': ' . $slug, HTTP_NOT_FOUND);
            }

            $this->file_logger->debug('Plugin file found', array('plugin_file' => $plugin_file));

            return array('slug' => $slug, 'plugin_file' => $plugin_file);
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'Failed to find plugin file');

            return $this->error_response(
                'Failed to locate plugin: ' . $e->getMessage(),
                HTTP_SERVER_ERROR,
                $e
            );
        }
    }

    /**
     * Log a plugin lifecycle action, swallowing failures.
     *
     * @param string $action  Action constant (ACTION_ENABLE, etc.).
     * @param string $slug    Plugin slug.
     * @param string $status  Status constant (STATUS_SUCCESS, STATUS_FAILED).
     * @param array  $extra   Optional extra context.
     */
    private function log_plugin_lifecycle($action, $slug, $status, $extra = array()) {
        try {
            $this->logger->log_plugin_action($action, $slug, $status, $extra);
        } catch (Throwable $e) {
            $this->file_logger->warn('Failed to log plugin action', array('error' => $e->getMessage()));
        }
    }

    // -------------------------------------------------------------------------
    // Lifecycle handlers (Enable / Disable / Delete)
    // -------------------------------------------------------------------------

    /**
     * Handle enable (activate) plugin request.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function handle_enable_plugin($request) {
        $load_error = $this->load_plugin_functions();
        if ($load_error) {
            return $load_error;
        }

        $resolved = $this->resolve_plugin_from_request($request);
        if ($resolved instanceof WP_REST_Response) {
            return $resolved;
        }

        $slug        = $resolved['slug'];
        $plugin_file = $resolved['plugin_file'];
        $this->file_logger->info('Enable plugin endpoint called', array('slug' => $slug));

        // Already active — early return
        if (is_plugin_active($plugin_file)) {
            $this->file_logger->info('Plugin already active', array('slug' => $slug));

            return RiseupEnvelopeBuilder::success('Plugin was already active')
                ->setRequestedAt('/' . API_FULL_NAMESPACE . '/' . ENDPOINT_PLUGIN_ENABLE)
                ->setSingleResult(array('plugin_slug' => $slug, 'activated' => true))
                ->toResponse();
        }

        // Activate
        try {
            $result = activate_plugin($plugin_file);

            if (is_wp_error($result)) {
                $this->log_plugin_lifecycle(ACTION_ENABLE, $slug, STATUS_FAILED, array('error' => $result->get_error_message()));

                return $this->error_response(MSG_ACTIVATION_FAILED . ': ' . $result->get_error_message(), HTTP_SERVER_ERROR);
            }
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'Exception during plugin activation');
            $this->log_plugin_lifecycle(ACTION_ENABLE, $slug, STATUS_FAILED, array('exception' => $e->getMessage()));

            return $this->error_response('Exception during activation: ' . $e->getMessage(), HTTP_SERVER_ERROR, $e);
        }

        $this->log_plugin_lifecycle(ACTION_ENABLE, $slug, STATUS_SUCCESS);
        $this->file_logger->info('Plugin activated successfully', array('slug' => $slug));

        return RiseupEnvelopeBuilder::success()
            ->setRequestedAt('/' . API_FULL_NAMESPACE . '/' . ENDPOINT_PLUGIN_ENABLE)
            ->setSingleResult(array('plugin_slug' => $slug, 'activated' => true))
            ->toResponse();
    }

    /**
     * Handle disable (deactivate) plugin request.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function handle_disable_plugin($request) {
        $load_error = $this->load_plugin_functions();
        if ($load_error) {
            return $load_error;
        }

        $resolved = $this->resolve_plugin_from_request($request);
        if ($resolved instanceof WP_REST_Response) {
            return $resolved;
        }

        $slug        = $resolved['slug'];
        $plugin_file = $resolved['plugin_file'];
        $this->file_logger->info('Disable plugin endpoint called', array('slug' => $slug));

        // Already inactive — early return
        if (!is_plugin_active($plugin_file)) {
            $this->file_logger->info('Plugin already inactive', array('slug' => $slug));

            return RiseupEnvelopeBuilder::success('Plugin was already inactive')
                ->setRequestedAt('/' . API_FULL_NAMESPACE . '/' . ENDPOINT_PLUGIN_DISABLE)
                ->setSingleResult(array('plugin_slug' => $slug, 'deactivated' => true))
                ->toResponse();
        }

        // Deactivate
        try {
            deactivate_plugins($plugin_file);
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'Exception during plugin deactivation');
            $this->log_plugin_lifecycle(ACTION_DISABLE, $slug, STATUS_FAILED, array('exception' => $e->getMessage()));

            return $this->error_response('Exception during deactivation: ' . $e->getMessage(), HTTP_SERVER_ERROR, $e);
        }

        // Verify
        if (is_plugin_active($plugin_file)) {
            $this->log_plugin_lifecycle(ACTION_DISABLE, $slug, STATUS_FAILED, array('error' => 'Plugin remained active'));

            return $this->error_response(MSG_DEACTIVATION_FAILED . ': Plugin remained active', HTTP_SERVER_ERROR);
        }

        $this->log_plugin_lifecycle(ACTION_DISABLE, $slug, STATUS_SUCCESS);
        $this->file_logger->info('Plugin deactivated successfully', array('slug' => $slug));

        return RiseupEnvelopeBuilder::success()
            ->setRequestedAt('/' . API_FULL_NAMESPACE . '/' . ENDPOINT_PLUGIN_DISABLE)
            ->setSingleResult(array('plugin_slug' => $slug, 'deactivated' => true))
            ->toResponse();
    }

    /**
     * Handle delete plugin request.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function handle_delete_plugin($request) {
        $load_error = $this->load_plugin_functions(true);
        if ($load_error) {
            return $load_error;
        }

        $resolved = $this->resolve_plugin_from_request($request);
        if ($resolved instanceof WP_REST_Response) {
            return $resolved;
        }

        $slug        = $resolved['slug'];
        $plugin_file = $resolved['plugin_file'];
        $this->file_logger->info('Delete plugin endpoint called', array('slug' => $slug));

        // Deactivate if active
        try {
            if (is_plugin_active($plugin_file)) {
                $this->file_logger->debug('Deactivating plugin before deletion', array('plugin_file' => $plugin_file));
                deactivate_plugins($plugin_file);
            }
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'Failed to deactivate plugin before deletion');

            return $this->error_response('Failed to deactivate plugin before deletion: ' . $e->getMessage(), HTTP_SERVER_ERROR, $e);
        }

        // Delete
        try {
            $result = delete_plugins(array($plugin_file));

            if (is_wp_error($result)) {
                $this->log_plugin_lifecycle(ACTION_DELETE, $slug, STATUS_FAILED, array('error' => $result->get_error_message()));

                return $this->error_response(MSG_DELETE_FAILED . ': ' . $result->get_error_message(), HTTP_SERVER_ERROR);
            }

            if ($result === false) {
                $this->log_plugin_lifecycle(ACTION_DELETE, $slug, STATUS_FAILED, array('error' => 'delete_plugins returned false'));

                return $this->error_response(MSG_DELETE_FAILED . ': Unknown error', HTTP_SERVER_ERROR);
            }
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'Exception during plugin deletion');
            $this->log_plugin_lifecycle(ACTION_DELETE, $slug, STATUS_FAILED, array('exception' => $e->getMessage()));

            return $this->error_response('Exception during deletion: ' . $e->getMessage(), HTTP_SERVER_ERROR, $e);
        }

        $this->log_plugin_lifecycle(ACTION_DELETE, $slug, STATUS_SUCCESS);
        $this->file_logger->info('Plugin deleted successfully', array('slug' => $slug));

        return RiseupEnvelopeBuilder::success()
            ->setRequestedAt('/' . API_FULL_NAMESPACE . '/' . ENDPOINT_PLUGIN_DELETE)
            ->setSingleResult(array('plugin_slug' => $slug, 'deleted' => true))
            ->toResponse();
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Safely execute a callable with comprehensive error handling.
     * Catches both Exception and Error (Throwable) for complete coverage.
     * 
     * @param callable $callback    The function to execute.
     * @param string   $context     Description of the operation for error messages.
     * @param array    $log_context Additional context for logging.
     * 
     * @return WP_REST_Response|mixed The result of the callback or an error response.
     */
    private function safe_execute($callback, $context = 'operation', $log_context = array()) {
        try {
            return call_user_func($callback);
        } catch (Throwable $e) {
            // Catch both Exception and Error (PHP 7+)
            $this->file_logger->log_exception($e, "Throwable in {$context}");
            
            // Log additional context
            $this->file_logger->error("safe_execute caught Throwable", array_merge($log_context, array(
                'context'   => $context,
                'exception' => get_class($e),
                'message'   => $e->getMessage(),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
            )));
            
            return $this->error_response(
                "Error in {$context}: " . $e->getMessage(),
                HTTP_SERVER_ERROR,
                $e
            );
        }
    }

    // =========================================================================
    // ERROR LOGS RETRIEVAL
    // =========================================================================

    /**
     * Handle error-logs endpoint.
     *
     * Returns the content of error.txt and/or log.txt as JSON,
     * controlled by the log_retrieval settings in the admin panel.
     * Includes stackTraceFrames for structured parsing.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function handle_error_logs($request) {
        return $this->safe_execute(function() use ($request) {
            $this->file_logger->info('Error logs endpoint called');

            // Get log retrieval settings
            $settings      = RiseupAdmin::get_settings();
            $log_settings  = isset($settings['log_retrieval']) ? $settings['log_retrieval'] : array();
            $include_error      = isset($log_settings['include_error_log']) ? (bool) $log_settings['include_error_log'] : true;
            $include_full       = isset($log_settings['include_full_log']) ? (bool) $log_settings['include_full_log'] : false;
            $include_stacktrace = isset($log_settings['include_stacktrace']) ? (bool) $log_settings['include_stacktrace'] : true;
            $max_lines          = isset($log_settings['max_lines']) ? (int) $log_settings['max_lines'] : 500;

            // Allow query param overrides (bounded by settings)
            if ($request->get_param('include_error_log') !== null) {
                $include_error = (bool) $request->get_param('include_error_log');
            }
            if ($request->get_param('include_full_log') !== null) {
                $include_full = (bool) $request->get_param('include_full_log');
            }
            if ($request->get_param('include_stacktrace') !== null) {
                $include_stacktrace = (bool) $request->get_param('include_stacktrace');
            }
            if ($request->get_param('max_lines') !== null) {
                $max_lines = max(10, min(5000, (int) $request->get_param('max_lines')));
            }

            $result = array(
                'version'  => PLUGIN_VERSION,
                'settings' => array(
                    'include_error_log'  => $include_error,
                    'include_full_log'   => $include_full,
                    'include_stacktrace' => $include_stacktrace,
                    'max_lines'          => $max_lines,
                ),
            );

            // Read error log
            if ($include_error) {
                $error_path = $this->file_logger->get_error_file();
                $result['error_log'] = $this->read_log_tail($error_path, $max_lines);
            }

            // Read full log
            if ($include_full) {
                $log_path = $this->file_logger->get_log_file();
                $result['full_log'] = $this->read_log_tail($log_path, $max_lines);
            }

            // Read stacktrace log
            if ($include_stacktrace) {
                $stacktrace_path = $this->file_logger->get_stacktrace_file();
                $result['stacktrace_log'] = $this->read_log_tail($stacktrace_path, $max_lines);
            }

            return RiseupEnvelopeBuilder::success()
                ->autoDetectRequestedAt()
                ->setSingleResult($result)
                ->toResponse();
        }, 'error_logs');
    }

    /**
     * Handle error-sessions endpoint.
     *
     * Returns structured error entries from the error_sessions SQLite table.
     * Supports filtering by level, search, pagination, and since_id.
     * Each entry includes stackTraceFrames when available.
     *
     * Query params:
     *   - level: Filter by level (ERROR, WARN)
     *   - search: Full-text search on message
     *   - since_id: Only return entries with id > since_id (for incremental polling)
     *   - limit: Max entries to return (default 100, max 1000)
     *   - offset: Pagination offset
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function handle_error_sessions($request) {
        return $this->safe_execute(function() use ($request) {
            $this->file_logger->info('Error sessions endpoint called');

            $db = RiseupDatabase::get_instance();
            $pdo = $db->get_pdo();

            if (!$pdo) {
                return $this->error_response(
                    'Database not available (PDO/pdo_sqlite extension may not be installed)',
                    HTTP_SERVER_ERROR
                );
            }

            // Check if error_sessions table exists
            $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='error_sessions'");
            if (!$check->fetchColumn()) {
                return RiseupEnvelopeBuilder::success('error_sessions table does not exist yet (migration v9 not applied)')
                    ->autoDetectRequestedAt()
                    ->setResults(array())
                    ->toResponse();
            }

            // Parse query params
            $level    = sanitize_text_field($request->get_param('level') ?: '');
            $search   = sanitize_text_field($request->get_param('search') ?: '');
            $since_id = (int) ($request->get_param('since_id') ?: 0);
            $limit    = max(1, min(1000, (int) ($request->get_param('limit') ?: 100)));
            $offset   = max(0, (int) ($request->get_param('offset') ?: 0));

            // Build query
            $where  = array();
            $params = array();

            if (!empty($level)) {
                $where[]  = 'level = ?';
                $params[] = strtoupper($level);
            }
            if (!empty($search)) {
                $where[]  = 'message LIKE ?';
                $params[] = '%' . $search . '%';
            }
            if ($since_id > 0) {
                $where[]  = 'id > ?';
                $params[] = $since_id;
            }

            $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

            // Count total
            $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM error_sessions {$where_sql}");
            $count_stmt->execute($params);
            $total = (int) $count_stmt->fetchColumn();

            // Fetch entries
            $query_sql = "SELECT * FROM error_sessions {$where_sql} ORDER BY id DESC LIMIT ? OFFSET ?";
            $stmt = $pdo->prepare($query_sql);
            $query_params = array_merge($params, array($limit, $offset));
            $stmt->execute($query_params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Enrich entries: parse context_json back to object
            $entries = array();
            foreach ($rows as $row) {
                $entry = array(
                    'id'          => (int) $row['id'],
                    'level'       => $row['level'],
                    'message'     => $row['message'],
                    'file'        => $row['file'],
                    'fileBase'    => $row['file'] ? basename($row['file']) : null,
                    'line'        => $row['line'] ? (int) $row['line'] : null,
                    'stackTrace'  => $row['stack_trace'],
                    'context'     => null,
                    'created_at'  => $row['created_at'],
                );

                // Parse context JSON
                if (!empty($row['context_json'])) {
                    $decoded = json_decode($row['context_json'], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $entry['context'] = $decoded;
                    } else {
                        $entry['context'] = $row['context_json'];
                    }
                }

                // Generate stackTraceFrames from stack trace string if available
                if (!empty($row['stack_trace'])) {
                    $entry['stackTraceFrames'] = $this->parse_stack_trace_string($row['stack_trace']);
                }

                $entries[] = $entry;
            }

            // Flash state
            $last_seen_id = 0;
            $has_unseen   = false;
            try {
                $fs = $pdo->query("SELECT key, value FROM flash_state");
                if ($fs) {
                    while ($frow = $fs->fetch(PDO::FETCH_ASSOC)) {
                        if ($frow['key'] === 'last_seen_error_id') {
                            $last_seen_id = (int) $frow['value'];
                        }
                        if ($frow['key'] === 'has_unseen_errors') {
                            $has_unseen = ($frow['value'] === '1');
                        }
                    }
                }
            } catch (Throwable $e) {
                // flash_state table may not exist
            }

            return RiseupEnvelopeBuilder::success()
                ->autoDetectRequestedAt()
                ->setResults($entries)
                ->setPagination($total, $limit, $limit > 0 ? (int) floor($offset / $limit) + 1 : 1)
                ->toResponse();
        }, 'error_sessions');
    }

    /**
     * Count errors with id > last_seen_id.
     *
     * @param PDO $pdo         Database connection.
     * @param int $last_seen_id Last seen error ID.
     * @return int
     */
    private function count_unseen_errors($pdo, $last_seen_id) {
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM error_sessions WHERE id > ?');
            $stmt->execute(array($last_seen_id));
            return (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * Parse a PHP stack trace string into structured frames.
     *
     * @param string $trace_string Stack trace as a string (from getTraceAsString()).
     * @return array Array of frame objects.
     */
    private function parse_stack_trace_string($trace_string) {
        $frames = array();
        $lines  = explode("\n", $trace_string);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            // Match: #0 /path/to/file.php(123): ClassName->method()
            if (preg_match('/^#\d+\s+(.+?)\((\d+)\):\s*(.*)$/', $line, $m)) {
                $func_part = $m[3];
                $class    = '';
                $function = $func_part;
                if (strpos($func_part, '->') !== false) {
                    list($class, $function) = explode('->', $func_part, 2);
                } elseif (strpos($func_part, '::') !== false) {
                    list($class, $function) = explode('::', $func_part, 2);
                }
                $function = rtrim($function, '()');

                $frames[] = array(
                    'file'     => $m[1],
                    'fileBase' => basename($m[1]),
                    'line'     => (int) $m[2],
                    'function' => $function,
                    'class'    => $class,
                );
            }
        }

        return $frames;
    }

    /**
     * Read the last N lines of a log file.
     *
     * @param string $file_path Path to the log file.
     * @param int    $max_lines Maximum number of lines to return.
     * @return array Log data with content, line count, file size, and path info.
     */
    private function read_log_tail($file_path, $max_lines) {
        $result = array(
            'exists'     => false,
            'file'       => basename($file_path),
            'path'       => $file_path,
            'content'    => '',
            'lines'      => 0,
            'total_size' => 0,
            'truncated'  => false,
        );

        $isFileUnreadable = RiseupBooleanHelpers::is_file_missing($file_path) || !is_readable($file_path);
        if ($isFileUnreadable) {
            return $result;
        }

        $result['exists']     = true;
        $result['total_size'] = filesize($file_path);

        // Read file and get last N lines
        $all_lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($all_lines === false) {
            $result['content'] = 'Failed to read file';
            return $result;
        }

        $total_lines = count($all_lines);
        $result['truncated'] = ($total_lines > $max_lines);

        if ($total_lines > $max_lines) {
            $lines = array_slice($all_lines, -$max_lines);
        } else {
            $lines = $all_lines;
        }

        $result['lines']       = count($lines);
        $result['total_lines'] = $total_lines;
        $result['content']     = implode("\n", $lines);

        return $result;
    }

    /**
     * Create an error response with optional exception details.
     *
     * @param string         $message   Error message.
     * @param int            $status    HTTP status code.
     * @param Throwable|null $exception Optional exception for stack trace.
     *
     * @return WP_REST_Response
     */
    private function error_response($message, $status, $exception = null) {
        // Log the error
        if ($exception instanceof Throwable) {
            $this->file_logger->log_exception($exception, $message);
        } else {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 0);
            $this->file_logger->error('Error response', array(
                'message'    => $message,
                'status'     => $status,
                'stackTrace' => implode("\n", array_map(function($i, $f) {
                    $file = isset($f['file']) ? basename($f['file']) : '[internal]';
                    $line = isset($f['line']) ? $f['line'] : '?';
                    $func = isset($f['function']) ? $f['function'] : '';
                    $class = isset($f['class']) ? $f['class'] . $f['type'] : '';
                    return "#{$i} {$file}({$line}): {$class}{$func}()";
                }, array_keys($backtrace), $backtrace)),
            ));
        }

        // Auto-detect requested endpoint
        $requested_at = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

        return RiseupEnvelopeBuilder::error($message, $status, $exception)
            ->setRequestedAt($requested_at)
            ->setDelegatedAt(home_url())
            ->toResponse();
    }

    /**
     * Get a meaningful error code from an exception.
     *
     * @param Throwable $exception The exception.
     *
     * @return string
     */
    private function get_exception_code($exception) {
        $code = $exception->getCode();
        if (is_int($code) && $code > 0) {
            return 'E' . $code;
        }
        
        // Generate code from class name
        $class = get_class($exception);
        $short = str_replace(array('Exception', 'Error'), '', $class);
        if (empty($short)) {
            return 'EXCEPTION';
        }
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '_', $short));
    }

    /**
     * Get temp directory path.
     *
     * @return string
     */
    private function get_temp_dir() {
        $temp_dir = RiseupPathUtils::getTempDir();
        RiseupPathUtils::ensureDir($temp_dir);
        return $temp_dir;
    }

    /**
     * Find plugin file by slug.
     *
     * @param string $slug Plugin slug.
     *
     * @return string|null Plugin file or null.
     */
    private function find_plugin_file($slug) {
        // Safe-load plugin functions with try-catch to prevent crashes during early loading
        try {
            if (RiseupBooleanHelpers::is_func_missing('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'find_plugin_file: Failed to load plugin.php');
            return null;
        }

        // Force-clear the WordPress plugin cache to avoid stale results
        try {
            if (function_exists('wp_clean_plugins_cache')) {
                wp_clean_plugins_cache(true);
            } else {
                wp_cache_delete('plugins', 'plugins');
            }
        } catch (Throwable $e) {
            $this->file_logger->warn('find_plugin_file: Failed to clear plugin cache', array(
                'error' => $e->getMessage(),
            ));
            // Non-fatal — continue with potentially cached data
        }

        // Safe-call get_plugins() — may return empty during early WordPress loading
        try {
            $all_plugins = get_plugins();
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'find_plugin_file: get_plugins() threw an exception');
            return null;
        }

        if (empty($all_plugins)) {
            $this->file_logger->warn('find_plugin_file: get_plugins() returned empty — trying filesystem fallback', array(
                'requested_slug' => $slug,
            ));
            return $this->find_plugin_file_from_filesystem($slug);
        }

        $available_slugs = array();
        foreach ($all_plugins as $plugin_file => $plugin_data) {
            $plugin_slug = dirname($plugin_file);
            if ($plugin_slug === '.') {
                $plugin_slug = basename($plugin_file, '.php');
            }
            if ($plugin_slug === $slug) {
                return $plugin_file;
            }
            $available_slugs[] = $plugin_slug;
        }

        // Not found in get_plugins() — try filesystem fallback before giving up
        $this->file_logger->warn('Plugin slug not found via get_plugins(), trying filesystem fallback', array(
            'requested_slug'  => $slug,
            'available_slugs' => $available_slugs,
            'total_plugins'   => count($all_plugins),
        ));

        return $this->find_plugin_file_from_filesystem($slug);
    }

    /**
     * Filesystem fallback to locate a plugin file when get_plugins() fails or returns stale data.
     *
     * @param string $slug Plugin slug.
     *
     * @return string|null Plugin file path (e.g. "slug/slug.php") or null.
     */
    private function find_plugin_file_from_filesystem($slug) {
        try {
            $plugin_dir = WP_PLUGIN_DIR . '/' . $slug;

            // Check for directory-based plugin (slug/slug.php)
            if (is_dir($plugin_dir)) {
                $main_file = $plugin_dir . '/' . $slug . '.php';
                if (file_exists($main_file)) {
                    $this->file_logger->info('find_plugin_file_from_filesystem: Found directory plugin', array(
                        'plugin_file' => $slug . '/' . $slug . '.php',
                    ));
                    return $slug . '/' . $slug . '.php';
                }

                // Scan for any PHP file with Plugin Name header in the directory
                $php_files = glob($plugin_dir . '/*.php');
                if ($php_files) {
                    foreach ($php_files as $file) {
                        $header = @file_get_contents($file, false, null, 0, 8192);
                        if ($header !== false && stripos($header, 'Plugin Name:') !== false) {
                            $relative = $slug . '/' . basename($file);
                            $this->file_logger->info('find_plugin_file_from_filesystem: Found plugin via header scan', array(
                                'plugin_file' => $relative,
                            ));
                            return $relative;
                        }
                    }
                }
            }

            // Check for single-file plugin (slug.php in plugins root)
            $single_file = WP_PLUGIN_DIR . '/' . $slug . '.php';
            if (file_exists($single_file)) {
                $this->file_logger->info('find_plugin_file_from_filesystem: Found single-file plugin', array(
                    'plugin_file' => $slug . '.php',
                ));
                return $slug . '.php';
            }

            $this->file_logger->warn('find_plugin_file_from_filesystem: Plugin not found on filesystem', array(
                'requested_slug' => $slug,
                'checked_dir'    => $plugin_dir,
                'checked_file'   => $single_file,
            ));
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'find_plugin_file_from_filesystem: Filesystem scan failed');
        }

        return null;
    }

    /**
     * Detect plugin slug from ZIP file.
     *
     * @param ZipArchive $zip ZIP archive.
     *
     * @return string|null Plugin slug or null.
     */
    private function detect_plugin_slug_from_zip($zip) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            // Look for a PHP file with a plugin header.
            if (preg_match('/^([^\/]+)\/[^\/]+\.php$/', $name, $matches)) {
                $content = $zip->getFromIndex($i);
                if ($content && strpos($content, 'Plugin Name:') !== false) {
                    return $matches[1];
                }
            }
        }
        return null;
    }

    /**
     * Delete a directory recursively.
     *
     * @param string $dir Directory path.
     *
     * @return bool Success.
     */
    private function delete_directory($dir) {
        if (RiseupBooleanHelpers::is_dir_missing($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->delete_directory($path);
            } else {
                @unlink($path);
            }
        }

        return @rmdir($dir);
    }

    /**
     * Copy a directory recursively.
     * Used as fallback when rename fails (cross-device move).
     *
     * @param string $src Source directory path.
     * @param string $dst Destination directory path.
     *
     * @return bool Success.
     */
    private function copy_directory($src, $dst) {
        if (RiseupBooleanHelpers::is_dir_missing($src)) {
            return false;
        }

        if (RiseupBooleanHelpers::is_dir_missing($dst)) {
            wp_mkdir_p($dst);
        }

        $files = array_diff(scandir($src), array('.', '..'));
        foreach ($files as $file) {
            $src_path = $src . '/' . $file;
            $dst_path = $dst . '/' . $file;
            if (is_dir($src_path)) {
                $this->copy_directory($src_path, $dst_path);
            } else {
                copy($src_path, $dst_path);
            }
        }

        return true;
    }

    /**
     * Add directory to ZIP recursively.
     *
     * @param ZipArchive           $zip      ZIP archive.
     * @param string               $src_dir  Source directory.
     * @param string               $zip_dir  Directory name in ZIP.
     * @param RiseupUploadIgnore   $ignore   Upload ignore parser.
     *
     * @return void
     */
    private function add_dir_to_zip($zip, $src_dir, $zip_dir, $ignore) {
        $dir = opendir($src_dir);
        if (!$dir) {
            return;
        }

        $zip->addEmptyDir($zip_dir);

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $src_path = $src_dir . '/' . $file;
            $zip_path = $zip_dir . '/' . $file;

            // Check if should be ignored.
            $relative = str_replace($src_dir . '/', '', $src_path);
            if ($ignore->should_ignore($relative)) {
                continue;
            }

            if (is_dir($src_path)) {
                $this->add_dir_to_zip($zip, $src_path, $zip_path, $ignore);
            } else {
                $zip->addFile($src_path, $zip_path);
            }
        }

        closedir($dir);
    }

    // =========================================================================
    // AGENT MANAGEMENT HANDLERS
    // =========================================================================

    /**
     * Handle listing all agent sites.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_list_agents($request) {
        return $this->safe_execute(function() use ($request) {
            $this->file_logger->info('Listing agent sites');
            
            $status = $request->get_param('status');
            $limit = $request->get_param('limit') ?: 100;
            $offset = $request->get_param('offset') ?: 0;
            
            $filters = array();
            if ($status) {
                $filters['status'] = sanitize_key($status);
            }
            
            $manager = RiseupAgentManager::get_instance();
            $result = $manager->list_agents($filters, $limit, $offset);
            
            return new WP_REST_Response(array(
                'success' => true,
                'total'   => $result['total'],
                'agents'  => $result['agents'],
            ), 200);
        }, 'list_agents');
    }

    /**
     * Handle adding a new agent site.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_add_agent($request) {
        return $this->safe_execute(function() use ($request) {
            $this->file_logger->info('Adding agent site');
            
            $data = array(
                'name'         => $request->get_param('name'),
                'url'          => $request->get_param('url'),
                'username'     => $request->get_param('username'),
                'app_password' => $request->get_param('app_password'),
                'redirect_url' => $request->get_param('redirect_url'),
            );
            
            $manager = RiseupAgentManager::get_instance();
            $result = $manager->add_agent($data);
            
            if (is_wp_error($result)) {
                return $this->error_response($result->get_error_message(), 400);
            }
            
            return new WP_REST_Response(array(
                'success'  => true,
                'agent_id' => $result,
                'message'  => 'Agent site added successfully',
            ), 201);
        }, 'add_agent');
    }

    /**
     * Handle getting a single agent site.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_get_agent($request) {
        return $this->safe_execute(function() use ($request) {
            $id = (int) $request->get_param('id');
            $this->file_logger->info('Getting agent site', array('id' => $id));
            
            $manager = RiseupAgentManager::get_instance();
            $agent = $manager->get_agent($id, false);
            
            if (!$agent) {
                return $this->error_response('Agent site not found', 404);
            }
            
            return new WP_REST_Response(array(
                'success' => true,
                'agent'   => $agent,
            ), 200);
        }, 'get_agent');
    }

    /**
     * Handle removing an agent site.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_remove_agent($request) {
        return $this->safe_execute(function() use ($request) {
            $id = (int) $request->get_param('id');
            $this->file_logger->info('Removing agent site', array('id' => $id));
            
            $manager = RiseupAgentManager::get_instance();
            $result = $manager->remove_agent($id);
            
            if (is_wp_error($result)) {
                return $this->error_response($result->get_error_message(), 400);
            }
            
            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Agent site removed successfully',
            ), 200);
        }, 'remove_agent');
    }

    /**
     * Handle testing agent connection.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_test_agent($request) {
        return $this->safe_execute(function() use ($request) {
            $id = (int) $request->get_param('id');
            $this->file_logger->info('Testing agent connection', array('id' => $id));
            
            $manager = RiseupAgentManager::get_instance();
            $result = $manager->test_connection($id);
            
            $status_code = $result['success'] ? 200 : 400;
            return new WP_REST_Response($result, $status_code);
        }, 'test_agent');
    }

    /**
     * Handle syncing plugins from agent.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_sync_agent($request) {
        return $this->safe_execute(function() use ($request) {
            $id = (int) $request->get_param('id');
            $this->file_logger->info('Syncing plugins from agent', array('id' => $id));
            
            $manager = RiseupAgentManager::get_instance();
            $result = $manager->sync_plugins($id);
            
            if (is_wp_error($result)) {
                return $this->error_response($result->get_error_message(), 400);
            }
            
            return new WP_REST_Response(array(
                'success' => true,
                'plugins' => $result,
                'count'   => count($result),
            ), 200);
        }, 'sync_agent');
    }

    /**
     * Handle executing action on agent plugin.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_agent_action($request) {
        return $this->safe_execute(function() use ($request) {
            $id = (int) $request->get_param('id');
            $action = sanitize_key($request->get_param('action'));
            $slug = sanitize_text_field($request->get_param('slug'));
            
            $this->file_logger->info('Executing agent action', array(
                'id'     => $id,
                'action' => $action,
                'slug'   => $slug,
            ));
            
            // Validate action
            $allowed_actions = array('enable', 'disable', 'delete');
            if (!in_array($action, $allowed_actions)) {
                return $this->error_response('Invalid action. Allowed: ' . implode(', ', $allowed_actions), 400);
            }
            
            if (empty($slug)) {
                return $this->error_response('Plugin slug is required', 400);
            }
            
            $manager = RiseupAgentManager::get_instance();
            $result = $manager->execute_plugin_action($id, $action, $slug);
            
            if (is_wp_error($result)) {
                return $this->error_response($result->get_error_message(), 400);
            }
            
            return new WP_REST_Response($result, 200);
        }, 'agent_action');
    }

    /**
     * Handle getting agent action history.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_agent_history($request) {
        return $this->safe_execute(function() use ($request) {
            $id = (int) $request->get_param('id');
            $limit = $request->get_param('limit') ?: 50;
            $offset = $request->get_param('offset') ?: 0;
            
            $this->file_logger->info('Getting agent action history', array('id' => $id));
            
            $manager = RiseupAgentManager::get_instance();
            $result = $manager->get_action_history($id, $limit, $offset);
            
            return new WP_REST_Response(array(
                'success' => true,
                'total'   => $result['total'],
                'actions' => $result['actions'],
            ), 200);
        }, 'agent_history');
    }

    // =========================================================================
    // SNAPSHOT HANDLERS
    // =========================================================================

    /**
     * Handle listing snapshots.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_list_snapshots($request) {
        return $this->safe_execute(function() use ($request) {
            $limit = (int) ($request->get_param('limit') ?: 50);
            $offset = (int) ($request->get_param('offset') ?: 0);

            $manager = RiseupSnapshotManager::getInstance($this->file_logger, $this->db);
            $snapshots = $manager->listSnapshots($limit, $offset);

            return new WP_REST_Response(array(
                'success'   => true,
                'snapshots' => $snapshots['snapshots'],
                'total'     => $snapshots['total'],
            ), 200);
        }, 'list_snapshots');
    }

    /**
     * Handle scheduling a snapshot (alias for handle_create_snapshot).
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_schedule_snapshot($request) {
        return $this->handle_create_snapshot($request);
    }

    /**
     * Handle creating/scheduling a snapshot.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_create_snapshot($request) {
        return $this->safe_execute(function() use ($request) {
            $body = $request->get_json_params();
            $scope = isset($body['scope']) ? sanitize_key($body['scope']) : 'all';

            // Log activity: snapshot creation initiated
            $this->logger->log_plugin_action(
                ACTION_SNAPSHOT_CREATE,
                'snapshot',
                STATUS_SUCCESS,
                array('scope' => $scope, 'trigger' => 'api', 'phase' => 'initiated')
            );

            $manager = RiseupSnapshotManager::getInstance($this->file_logger, $this->db);
            $settings = $manager->getSettings();

            // Route to full orchestrator when mode is per_table
            if (($settings['mode'] ?? 'per_table') === 'per_table') {
                $orchestrator = RiseupSnapshotOrchestrator::getInstance($this->file_logger, $this->db, $manager);
                $result = $orchestrator->executeFullBackup(array(
                    'title'            => $body['title'] ?? null,
                    'scope'            => $scope,
                    'include_plugins'  => $body['include_plugins'] ?? null,
                    'plugin_selection' => $body['plugin_selection'] ?? null,
                    'compression'      => $body['compression'] ?? null,
                ));

                // Log result
                $this->logger->log_plugin_action(
                    ACTION_SNAPSHOT_CREATE,
                    'snapshot',
                    $result['success'] ? STATUS_SUCCESS : STATUS_FAILED,
                    array('scope' => $scope, 'mode' => 'per_table', 'phase' => 'complete'),
                    $result['success'] ? null : ($result['error'] ?? 'Unknown error')
                );

                $status_code = $result['success'] ? 201 : 500;
                return new WP_REST_Response($result, $status_code);
            }

            // Legacy single-db mode via provider
            $options = array(
                'scope'   => $scope,
                'trigger' => SNAPSHOT_TRIGGER_API,
                'tables'  => isset($body['tables']) ? array_map('sanitize_text_field', (array) $body['tables']) : array(),
            );

            $this->file_logger->info('Creating snapshot via API (legacy mode)', array('scope' => $options['scope']));

            $result = $manager->createSnapshot($options);

            // Log result
            $this->logger->log_plugin_action(
                ACTION_SNAPSHOT_CREATE,
                'snapshot',
                $result['success'] ? STATUS_SUCCESS : STATUS_FAILED,
                array('scope' => $scope, 'mode' => 'legacy', 'phase' => 'complete'),
                $result['success'] ? null : ($result['error'] ?? 'Unknown error')
            );

            $status_code = $result['success'] ? 201 : 500;
            return new WP_REST_Response($result, $status_code);
        }, 'create_snapshot');
    }

    /**
     * Handle getting a single snapshot.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_get_snapshot($request) {
        return $this->safe_execute(function() use ($request) {
            $body = $request->get_json_params();
            $id = isset($body['id']) ? (int) $body['id'] : (int) $request->get_param('id');

            $manager = RiseupSnapshotManager::getInstance($this->file_logger, $this->db);
            $provider = $manager->getProvider();
            if (!$provider) {
                return $this->error_response('No snapshot provider available', 500);
            }

            $snapshot = $provider->getSnapshot($id);
            if (!$snapshot) {
                return $this->error_response('Snapshot not found', 404);
            }

            return new WP_REST_Response(array(
                'success'  => true,
                'snapshot' => $snapshot,
            ), 200);
        }, 'get_snapshot');
    }

    /**
     * Handle deleting a snapshot.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_delete_snapshot($request) {
        return $this->safe_execute(function() use ($request) {
            $body = $request->get_json_params();
            $id = isset($body['id']) ? (int) $body['id'] : (int) $request->get_param('id');
            $this->file_logger->info('Deleting snapshot', array('id' => $id));

            // Log activity: delete initiated
            $this->logger->log_plugin_action(
                ACTION_SNAPSHOT_DELETE,
                'snapshot',
                STATUS_SUCCESS,
                array('snapshot_id' => $id, 'trigger' => 'api', 'phase' => 'initiated')
            );

            $manager = RiseupSnapshotManager::getInstance($this->file_logger, $this->db);
            $result = $manager->deleteSnapshot($id);

            // Log activity: delete complete
            $this->logger->log_plugin_action(
                ACTION_SNAPSHOT_DELETE,
                'snapshot',
                $result['success'] ? STATUS_SUCCESS : STATUS_FAILED,
                array('snapshot_id' => $id, 'trigger' => 'api', 'phase' => 'complete'),
                $result['success'] ? null : ($result['error'] ?? 'Delete failed')
            );

            $status_code = $result['success'] ? 200 : 400;
            return new WP_REST_Response($result, $status_code);
        }, 'delete_snapshot');
    }

    /**
     * Handle restoring a snapshot.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_restore_snapshot($request) {
        return $this->safe_execute(function() use ($request) {
            $body = $request->get_json_params();
            $id = isset($body['id']) ? (int) $body['id'] : (int) $request->get_param('id');

            $options = array(
                'confirm'            => !empty($body['confirm']),
                'create_backup'      => isset($body['createBackup']) ? (bool) $body['createBackup'] : true,
                'require_backup'     => !empty($body['requireBackup']),
                'mode'               => isset($body['mode']) ? sanitize_key($body['mode']) : 'full',
                'tables'             => isset($body['tables']) ? array_map('sanitize_text_field', (array) $body['tables']) : array(),
                'strict'             => !empty($body['strict']),
                'apply_incrementals' => isset($body['applyIncrementals']) ? (bool) $body['applyIncrementals'] : true,
            );

            // Log activity: restore initiated
            $this->logger->log_plugin_action(
                ACTION_SNAPSHOT_RESTORE,
                'snapshot',
                STATUS_SUCCESS,
                array('snapshot_id' => $id, 'mode' => $options['mode'], 'phase' => 'initiated')
            );

            $this->file_logger->info('Restoring snapshot', array('id' => $id, 'mode' => $options['mode']));

            $manager = RiseupSnapshotManager::getInstance($this->file_logger, $this->db);

            // Check if this is a per-table snapshot (has a snapshot directory with a-root.db)
            $snapshot = $manager->getSnapshotById($id);
            if ($snapshot && $this->isPerTableSnapshot($snapshot)) {
                // Route through the new per-table Restore Engine
                $snapshot_dir = $this->resolveSnapshotDir($snapshot);
                if ($snapshot_dir && file_exists($snapshot_dir . '/a-root.db')) {
                    $orchestrator = RiseupSnapshotOrchestrator::getInstance($this->file_logger, $this->db, $manager);
                    $engine = RiseupRestoreEngine::getInstance($this->file_logger, $this->db, $orchestrator);
                    $result = $engine->execute($snapshot_dir, $options);

                    // Log result
                    $this->logger->log_plugin_action(
                        ACTION_SNAPSHOT_RESTORE,
                        'snapshot',
                        $result['success'] ? STATUS_SUCCESS : STATUS_FAILED,
                        array('snapshot_id' => $id, 'mode' => 'per_table', 'phase' => 'complete'),
                        $result['success'] ? null : ($result['error'] ?? 'Restore failed')
                    );

                    $status_code = $result['success'] ? 200 : 400;
                    return new WP_REST_Response($result, $status_code);
                }
            }

            // Fallback to legacy single-file restore
            $result = $manager->restoreSnapshot($id, $options);

            // Log result
            $this->logger->log_plugin_action(
                ACTION_SNAPSHOT_RESTORE,
                'snapshot',
                $result['success'] ? STATUS_SUCCESS : STATUS_FAILED,
                array('snapshot_id' => $id, 'mode' => 'legacy', 'phase' => 'complete'),
                $result['success'] ? null : ($result['error'] ?? 'Restore failed')
            );

            $status_code = $result['success'] ? 200 : 400;
            return new WP_REST_Response($result, $status_code);
        }, 'restore_snapshot');
    }

    /**
     * Check if a snapshot is a per-table snapshot (has a directory with a-root.db).
     *
     * @param array $snapshot Snapshot record.
     * @return bool True if per-table snapshot.
     */
    private function isPerTableSnapshot($snapshot) {
        // Per-table snapshots store a directory path rather than a single SQLite file
        $filepath = $snapshot['filepath'] ?? '';
        if (is_dir($filepath)) {
            return file_exists($filepath . '/a-root.db');
        }
        // Check if there's a directory alongside the file
        $dir = $snapshot['directory'] ?? '';
        if (!empty($dir) && is_dir($dir)) {
            return file_exists($dir . '/a-root.db');
        }
        return false;
    }

    /**
     * Resolve the snapshot directory path from a snapshot record.
     *
     * @param array $snapshot Snapshot record.
     * @return string|null Directory path or null.
     */
    private function resolveSnapshotDir($snapshot) {
        // Direct directory path
        $filepath = $snapshot['filepath'] ?? '';
        if (is_dir($filepath)) {
            return $filepath;
        }
        // Check directory field
        $dir = $snapshot['directory'] ?? '';
        if (!empty($dir) && is_dir($dir)) {
            return $dir;
        }
        // Try deriving from filepath (strip filename)
        if (!empty($filepath) && file_exists(dirname($filepath) . '/a-root.db')) {
            return dirname($filepath);
        }
        return null;
    }

    /**
     * Handle exporting a snapshot as ZIP.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response.
     */
    public function handle_export_snapshot($request) {
        return $this->safe_execute(function() use ($request) {
            $body = $request->get_json_params();
            $id = isset($body['id']) ? (int) $body['id'] : (int) $request->get_param('id');
            $this->file_logger->info('Exporting snapshot', array('id' => $id));

            // Log activity: export initiated
            $this->logger->log_plugin_action(
                ACTION_SNAPSHOT_EXPORT,
                'snapshot',
                STATUS_SUCCESS,
                array('snapshot_id' => $id, 'trigger' => 'api', 'phase' => 'initiated')
            );

            $manager = RiseupSnapshotManager::getInstance($this->file_logger, $this->db);
            $result = $manager->exportSnapshot($id);

            if (!$result['success']) {
                // Log failure
                $this->logger->log_plugin_action(
                    ACTION_SNAPSHOT_EXPORT,
                    'snapshot',
                    STATUS_FAILED,
                    array('snapshot_id' => $id),
                    $result['error'] ?? 'Export failed'
                );
                return $this->error_response($result['error'], 400);
            }

            // Stream the ZIP file as download
            $filepath = $result['filepath'];
            if (RiseupBooleanHelpers::is_file_missing($filepath)) {
                $this->logger->log_plugin_action(
                    ACTION_SNAPSHOT_EXPORT,
                    'snapshot',
                    STATUS_FAILED,
                    array('snapshot_id' => $id),
                    'Export file not found'
                );
                return $this->error_response('Export file not found', 500);
            }

            // Log success
            $this->logger->log_plugin_action(
                ACTION_SNAPSHOT_EXPORT,
                'snapshot',
                STATUS_SUCCESS,
                array('snapshot_id' => $id, 'filename' => $result['filename'], 'size' => $result['size'])
            );

            // Return file info for client-side download
            return new WP_REST_Response(array(
                'success'  => true,
                'filename' => $result['filename'],
                'size'     => $result['size'],
                'downloadUrl' => rest_url(API_FULL_NAMESPACE . '/' . ENDPOINT_SNAPSHOTS . '/' . $id . '/download'),
            ), 200);
        }, 'export_snapshot');
    }

    /**
     * Handle ZIP download request (Feature D).
     *
     * Checks for a cached ZIP or builds a new one. Returns a download URL.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_snapshot_download($request) {
        return $this->safe_execute(function() use ($request) {
            $body = $request->get_json_params();
            $snapshotId = isset($body['snapshot_id']) ? (int) $body['snapshot_id'] : 0;

            if ($snapshotId <= 0) {
                return $this->error_response('Missing or invalid snapshot_id', 400);
            }

            $this->file_logger->info('Snapshot download requested', array('snapshot_id' => $snapshotId));

            // Log activity: download initiated
            $this->logger->log_plugin_action(
                ACTION_SNAPSHOT_ZIP_DOWNLOAD,
                'snapshot',
                STATUS_SUCCESS,
                array('snapshot_id' => $snapshotId, 'phase' => 'initiated')
            );

            require_once dirname(__FILE__) . '/includes/Snapshot/SnapshotExporter.php';
            $exporter = RiseupSnapshotExporter::getInstance($this->file_logger, $this->db);
            $result = $exporter->getOrBuildZip($snapshotId);

            if (!$result['success']) {
                $this->logger->log_plugin_action(
                    ACTION_SNAPSHOT_ZIP_DOWNLOAD,
                    'snapshot',
                    STATUS_FAILED,
                    array('snapshot_id' => $snapshotId),
                    $result['error'] ?? 'Download failed'
                );
                return $this->error_response($result['error'] ?? 'Export failed', 400);
            }

            $export = $result['export'];
            $downloadUrl = $exporter->getDownloadUrl((int) $export['id']);

            $this->logger->log_plugin_action(
                ACTION_SNAPSHOT_ZIP_DOWNLOAD,
                'snapshot',
                STATUS_SUCCESS,
                array(
                    'snapshot_id' => $snapshotId,
                    'cached'      => $result['cached'] ?? false,
                    'size'        => $export['zip_size'] ?? 0,
                    'filename'    => $export['zip_filename'] ?? '',
                )
            );

            return RiseupEnvelopeBuilder::success()
                ->setResults(array(array(
                    'url'               => $downloadUrl,
                    'filename'          => $export['zip_filename'],
                    'size'              => (int) $export['zip_size'],
                    'cached'            => $result['cached'] ?? false,
                    'included_ids'      => json_decode($export['included_ids'] ?? '[]', true),
                    'incremental_count' => (int) ($export['incremental_count'] ?? 0),
                    'created_at'        => $export['created_at'] ?? '',
                    'status'            => $export['status'] ?? 'valid',
                )))
                ->setRequestedAt('/' . ENDPOINT_SNAPSHOT_DOWNLOAD)
                ->toResponse();
        }, 'snapshot_download');
    }

    /**
     * Handle ZIP file download (Feature D).
     *
     * Validates nonce token and streams the ZIP file to the client.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|void Response or direct file stream.
     */
    public function handle_snapshot_download_file($request) {
        $exportId = (int) $request->get_param('id');
        $token    = sanitize_text_field($request->get_param('token'));

        if ($exportId <= 0 || empty($token)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error'   => 'Missing id or token parameter',
                'code'    => ERR_EXPORT_TOKEN_INVALID,
            ), 400);
        }

        require_once dirname(__FILE__) . '/includes/Snapshot/SnapshotExporter.php';
        $exporter = RiseupSnapshotExporter::getInstance($this->file_logger, $this->db);
        $export = $exporter->validateDownloadToken($exportId, $token);

        if (!$export) {
            return new WP_REST_Response(array(
                'success' => false,
                'error'   => 'Invalid or expired download token',
                'code'    => ERR_EXPORT_TOKEN_INVALID,
            ), 403);
        }

        $filepath = $export['zip_path'];
        $filename = $export['zip_filename'];
        $filesize = filesize($filepath);

        // Log the download
        $this->logger->log_plugin_action(
            ACTION_SNAPSHOT_ZIP_DOWNLOAD,
            'snapshot',
            STATUS_SUCCESS,
            array('export_id' => $exportId, 'filename' => $filename, 'size' => $filesize, 'phase' => 'streaming')
        );

        // Stream the file
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . $filesize);
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');

        // Flush output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Stream file to client
        $handle = fopen($filepath, 'rb');
        if ($handle) {
            while (!feof($handle)) {
                echo fread($handle, 8192);
                flush();
            }
            fclose($handle);
        } else {
            // Fallback
            readfile($filepath);
        }

        exit; // Stop WordPress from processing further
    }

    /**
     * Handle importing a snapshot from ZIP upload.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_import_snapshot($request) {
        return $this->safe_execute(function() use ($request) {
            $files = $request->get_file_params();

            if (empty($files['file']['tmp_name'])) {
                return $this->error_response('No file uploaded', 400);
            }

            $tmp_file = $files['file']['tmp_name'];
            $original_name = $files['file']['name'] ?? 'unknown';
            $this->file_logger->info('Importing snapshot from uploaded ZIP', array(
                'originalName' => $original_name,
                'size'         => $files['file']['size'],
            ));

            // Log activity: import initiated
            $this->logger->log_plugin_action(
                ACTION_SNAPSHOT_IMPORT,
                'snapshot',
                STATUS_SUCCESS,
                array('filename' => $original_name, 'size' => $files['file']['size'], 'phase' => 'initiated')
            );

            // Use enhanced import engine that handles both legacy and per-table formats
            $manager = RiseupSnapshotManager::getInstance($this->file_logger, $this->db);
            $importer = new RiseupSnapshotImport($this->file_logger, $this->db, $manager);
            $result = $importer->import($tmp_file);

            // Log result
            $this->logger->log_plugin_action(
                ACTION_SNAPSHOT_IMPORT,
                'snapshot',
                $result['success'] ? STATUS_SUCCESS : STATUS_FAILED,
                array('filename' => $original_name, 'phase' => 'complete'),
                $result['success'] ? null : ($result['error'] ?? 'Import failed')
            );

            $status_code = $result['success'] ? 201 : 400;
            return new WP_REST_Response($result, $status_code);
        }, 'import_snapshot');
    }

    /**
     * Handle getting snapshot settings.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_get_snapshot_settings($request) {
        return $this->safe_execute(function() {
            $manager = RiseupSnapshotManager::getInstance($this->file_logger, $this->db);
            $settings = $manager->getSettings();

            return new WP_REST_Response(array(
                'success'  => true,
                'settings' => $settings,
            ), 200);
        }, 'get_snapshot_settings');
    }

    /**
     * Handle updating snapshot settings.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_update_snapshot_settings($request) {
        return $this->safe_execute(function() use ($request) {
            $body = $request->get_json_params();
            $this->file_logger->info('Updating snapshot settings', array('keys' => array_keys($body)));

            $manager = RiseupSnapshotManager::getInstance($this->file_logger, $this->db);
            $updated = $manager->updateSettings($body);

            // Log settings update
            $this->logger->log_plugin_action(
                'snapshot_settings_update',
                'snapshot',
                STATUS_SUCCESS,
                array('keys' => array_keys($body))
            );

            return new WP_REST_Response(array(
                'success'  => true,
                'settings' => $updated,
            ), 200);
        }, 'update_snapshot_settings');
    }

    /**
     * Handle listing snapshot providers.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_list_snapshot_providers($request) {
        return $this->safe_execute(function() {
            $manager = RiseupSnapshotManager::getInstance($this->file_logger, $this->db);
            $providers = $manager->getProviders();

            return new WP_REST_Response(array(
                'success'   => true,
                'providers' => $providers,
            ), 200);
        }, 'list_snapshot_providers');
    }

    /**
     * Handle listing available database tables.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_list_snapshot_tables($request) {
        return $this->safe_execute(function() {
            $manager = RiseupSnapshotManager::getInstance($this->file_logger, $this->db);
            $tables = $manager->getAvailableTables();

            return new WP_REST_Response(array(
                'success' => true,
                'tables'  => $tables,
            ), 200);
        }, 'list_snapshot_tables');
    }

    /**
     * Handle dependency analysis request.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_analyze_dependencies($request) {
        return $this->safe_execute(function() use ($request) {
            $body = $request->get_json_params();
            $scope = isset($body['scope']) ? $body['scope'] : 'all';

            $analyzer = RiseupDependencyAnalyzer::getInstance($this->file_logger);
            $analysis = $analyzer->analyze($scope);

            return new WP_REST_Response(array(
                'success'      => true,
                'tables'       => $analysis['tables'],
                'dependencies' => $analysis['dependencies'],
                'seed_order'   => $analysis['seed_order'],
                'table_count'  => $analysis['table_count'],
                'dep_count'    => $analysis['dep_count'],
            ), 200);
        }, 'analyze_dependencies');
    }

    /**
     * Handle per-table snapshot export.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_export_pertable($request) {
        return $this->safe_execute(function() use ($request) {
            $body = $request->get_json_params();

            $analyzer = RiseupDependencyAnalyzer::getInstance($this->file_logger);
            $rootDb = RiseupRootDb::getInstance($this->file_logger, $analyzer);
            $worker = RiseupSnapshotWorker::getInstance($this->file_logger, $this->db, $rootDb, $analyzer);

            $result = $worker->execute(array(
                'title'    => $body['title'] ?? null,
                'scope'    => $body['scope'] ?? 'wordpress',
                'type'     => $body['type'] ?? 'full',
                'settings' => $body['settings'] ?? null,
            ));

            $status = $result['success'] ? 200 : 500;

            return new WP_REST_Response(array(
                'success'    => $result['success'],
                'directory'  => $result['directory'] ?? null,
                'tables'     => $result['tables'] ?? 0,
                'total_rows' => $result['total_rows'] ?? 0,
                'errors'     => $result['errors'] ?? array(),
                'duration'   => $result['duration'] ?? 0,
                'error'      => $result['error'] ?? null,
            ), $status);
        }, 'export_pertable');
    }

    /**
     * Handle full end-to-end backup (Phase 5 orchestration).
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_full_backup($request) {
        return $this->safe_execute(function() use ($request) {
            $body = $request->get_json_params();

            // Log activity: full backup initiated (Phase 6)
            $this->logger->log_plugin_action(
                ACTION_SNAPSHOT_FULL_BACKUP,
                'snapshot',
                STATUS_SUCCESS,
                array('title' => $body['title'] ?? null, 'scope' => $body['scope'] ?? null, 'phase' => 'initiated')
            );

            $manager = RiseupSnapshotManager::getInstance($this->file_logger, $this->db);
            $orchestrator = RiseupSnapshotOrchestrator::getInstance($this->file_logger, $this->db, $manager);

            $result = $orchestrator->executeFullBackup(array(
                'title'            => $body['title'] ?? null,
                'scope'            => $body['scope'] ?? null,
                'include_plugins'  => $body['include_plugins'] ?? null,
                'plugin_selection' => $body['plugin_selection'] ?? null,
                'compression'      => $body['compression'] ?? null,
            ));

            // Log activity: full backup result (Phase 6)
            $this->logger->log_plugin_action(
                ACTION_SNAPSHOT_FULL_BACKUP,
                'snapshot',
                $result['success'] ? STATUS_SUCCESS : STATUS_FAILED,
                array(
                    'snapshot_id' => $result['snapshot_id'] ?? null,
                    'tables'      => $result['tables'] ?? 0,
                    'total_rows'  => $result['total_rows'] ?? 0,
                    'duration'    => $result['duration'] ?? 0,
                    'phase'       => 'complete',
                ),
                $result['success'] ? null : ($result['error'] ?? 'Full backup failed')
            );

            $status = $result['success'] ? 201 : 500;

            return new WP_REST_Response(array(
                'success'     => $result['success'],
                'snapshot_id' => $result['snapshot_id'] ?? null,
                'directory'   => $result['directory'] ?? null,
                'tables'      => $result['tables'] ?? 0,
                'total_rows'  => $result['total_rows'] ?? 0,
                'plugins'     => $result['plugins'] ?? 0,
                'zip_size'    => $result['zip_size'] ?? 0,
                'duration'    => $result['duration'] ?? 0,
                'errors'      => $result['errors'] ?? array(),
                'error'       => $result['error'] ?? null,
                'phase'       => $result['phase'] ?? null,
            ), $status);
        }, 'full_backup');
    }

    /**
     * Handle incremental backup (Phase 6).
     *
     * Creates a delta snapshot relative to the latest master (full) backup.
     * Only exports rows with ID > last_max_id from the master snapshot.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_incremental_backup($request) {
        return $this->safe_execute(function() use ($request) {
            $body = $request->get_json_params();

            // Log activity: incremental backup initiated (Phase 6)
            $this->logger->log_plugin_action(
                ACTION_SNAPSHOT_INCREMENTAL,
                'snapshot',
                STATUS_SUCCESS,
                array('title' => $body['title'] ?? null, 'phase' => 'initiated')
            );

            $manager = RiseupSnapshotManager::getInstance($this->file_logger, $this->db);
            $rootDb = RiseupRootDb::getInstance($this->file_logger, RiseupDependencyAnalyzer::getInstance($this->file_logger));
            $incremental = RiseupIncrementalBackup::getInstance($this->file_logger, $this->db, $rootDb);

            // Determine master directory
            $master_dir = $body['master_dir'] ?? null;
            if (!$master_dir) {
                // Auto-detect latest full backup
                $master_dir = $incremental->findLatestMasterSnapshot();
            }

            if (!$master_dir || RiseupBooleanHelpers::is_dir_missing($master_dir)) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'error'   => 'No master (full) snapshot found. Create a full backup first.',
                ), 400);
            }

            $result = $incremental->execute($master_dir, array(
                'title' => $body['title'] ?? null,
            ));

            // Log activity: incremental backup result (Phase 6)
            $this->logger->log_plugin_action(
                ACTION_SNAPSHOT_INCREMENTAL,
                'snapshot',
                $result['success'] ? STATUS_SUCCESS : STATUS_FAILED,
                array(
                    'snapshot_id'    => $result['snapshot_id'] ?? null,
                    'tables_changed' => $result['tables_changed'] ?? 0,
                    'total_new_rows' => $result['total_new_rows'] ?? 0,
                    'duration'       => $result['duration'] ?? 0,
                    'phase'          => 'complete',
                ),
                $result['success'] ? null : ($result['error'] ?? 'Incremental backup failed')
            );

            $status = $result['success'] ? 201 : 500;

            return new WP_REST_Response(array(
                'success'        => $result['success'],
                'snapshot_id'    => $result['snapshot_id'] ?? null,
                'sequence'       => $result['sequence'] ?? null,
                'folder_name'    => $result['folder_name'] ?? null,
                'tables_changed' => $result['tables_changed'] ?? 0,
                'total_new_rows' => $result['total_new_rows'] ?? 0,
                'tables'         => $result['tables'] ?? array(),
                'duration'       => $result['duration'] ?? 0,
                'errors'         => $result['errors'] ?? array(),
                'error'          => $result['error'] ?? null,
            ), $status);
        }, 'incremental_backup');
    }

    /**
     * Handle snapshot cleanup (Phase 10).
     *
     * Runs retention-based cleanup, orphan detection, and stuck snapshot handling.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_snapshot_cleanup($request) {
        return $this->safe_execute(function() use ($request) {
            $body = $request->get_json_params();

            $cleaner = RiseupSnapshotFactory::cleaner($this->file_logger, $this->db);

            $result = $cleaner->execute(array(
                'retention_type'  => $body['retention_type'] ?? null,
                'retention_days'  => $body['retention_days'] ?? null,
                'retention_count' => $body['retention_count'] ?? null,
                'dry_run'         => $body['dry_run'] ?? false,
            ));

            // Log activity: cleanup result (Phase 6)
            if (!($body['dry_run'] ?? false)) {
                $this->logger->log_plugin_action(
                    ACTION_SNAPSHOT_CLEANUP,
                    'snapshot',
                    $result['success'] ? STATUS_SUCCESS : STATUS_FAILED,
                    array(
                        'retention_removed' => $result['retention']['deleted'] ?? 0,
                        'orphans_removed'   => $result['orphans']['removed'] ?? 0,
                        'stuck_marked'      => $result['stuck']['cleaned'] ?? 0,
                        'duration'          => $result['duration'] ?? 0,
                    ),
                    $result['success'] ? null : 'Cleanup encountered errors'
                );
            }

            return new WP_REST_Response(array(
                'success'   => $result['success'],
                'retention' => $result['retention'],
                'orphans'   => $result['orphans'],
                'stuck'     => $result['stuck'],
                'duration'  => $result['duration'],
                'dry_run'   => $result['dry_run'],
                'errors'    => $result['errors'],
            ), 200);
        }, 'snapshot_cleanup');
    }

    /**
     * Handle snapshot job progress polling (Phase 4).
     *
     * Returns real-time progress for a background snapshot job including
     * batch status, table-by-table progress, and percentage complete.
     *
     * @param WP_REST_Request $request Request object with job_id in JSON body.
     * @return WP_REST_Response Response with progress data.
     */
    public function handle_snapshot_progress($request) {
        return $this->safe_execute(function() use ($request) {
            $body = $request->get_json_params();
            $job_id = $body['job_id'] ?? null;

            if (empty($job_id)) {
                return new WP_REST_Response(array(
                    'IsSuccess'    => false,
                    'HasAnyErrors' => true,
                    'error'        => 'Missing required field: job_id',
                ), HTTP_BAD_REQUEST);
            }

            require_once dirname(__FILE__) . '/includes/Snapshot/SnapshotFactory.php';
            $rootDb = RiseupRootDb::getInstance($this->file_logger, RiseupDependencyAnalyzer::getInstance($this->file_logger));
            $worker = RiseupSnapshotWorker::getInstance(
                $this->file_logger,
                $this->db,
                $rootDb,
                RiseupDependencyAnalyzer::getInstance($this->file_logger)
            );

            $progress = $worker->getJobProgress((int) $job_id);

            if (!$progress) {
                return new WP_REST_Response(array(
                    'IsSuccess'    => false,
                    'HasAnyErrors' => true,
                    'error'        => 'Job not found',
                    'code'         => 'JOB_NOT_FOUND',
                ), HTTP_NOT_FOUND);
            }

            return new WP_REST_Response(array(
                'IsSuccess'       => true,
                'HasAnyErrors'    => false,
                'job_id'          => $progress['job_id'],
                'status'          => $progress['status'],
                'total_tables'    => $progress['total_tables'],
                'tables_exported' => $progress['tables_exported'],
                'total_rows'      => $progress['total_rows'],
                'pool_size'       => $progress['pool_size'],
                'total_batches'   => $progress['total_batches'],
                'current_batch'   => $progress['current_batch'],
                'percent'         => $progress['percent'],
                'errors'          => $progress['errors'],
                'table_progress'  => $progress['table_progress'],
                'created_at'      => $progress['created_at'],
                'updated_at'      => $progress['updated_at'],
                'completed_at'    => $progress['completed_at'],
            ), HTTP_OK);
        }, 'snapshot_progress');
    }
}

// =============================================================================
// PLUGIN INITIALIZATION
// =============================================================================

/**
 * Activation hook: ensure log directories and files exist on first activation.
 * This runs before plugins_loaded, guaranteeing the logs folder is ready.
 */
function riseup_asia_activate() {
    try {
        // Ensure constants and helpers are loaded
        $constants_file = __DIR__ . '/includes/constants.php';
        if (file_exists($constants_file)) {
            require_once $constants_file;
        }

        // Load boolean helpers (foundation file — raw require_once allowed)
        $helpers_file = __DIR__ . '/includes/Helpers/BooleanHelpers.php';
        if (file_exists($helpers_file)) {
            require_once $helpers_file;
        }

        // Resolve base directory and create logs folder
        $upload_dir = wp_upload_dir();
        if (!isset($upload_dir['error']) || !$upload_dir['error']) {
            $base_dir = $upload_dir['basedir'] . '/' . UPLOADS_SUBDIR;
            $logs_dir = $base_dir . '/' . LOGS_SUBDIR;

            // Create base + logs directories
            if (RiseupBooleanHelpers::is_dir_missing($base_dir)) {
                wp_mkdir_p($base_dir);
            }
            if (RiseupBooleanHelpers::is_dir_missing($logs_dir)) {
                wp_mkdir_p($logs_dir);
            }

            // Write activation marker to log file
            $log_file = $logs_dir . '/' . LOG_FILENAME;
            $timestamp = gmdate('Y-m-d\TH:i:s') . 'Z';
            $version = defined('PLUGIN_VERSION') ? PLUGIN_VERSION : 'unknown';
            $entry = sprintf(
                "[%s] [INFO] Plugin activated (activation hook) (riseup-asia-uploader.php:0) {\"version\":\"%s\",\"php\":\"%s\",\"wp\":\"%s\"}\n",
                $timestamp,
                $version,
                phpversion(),
                get_bloginfo('version')
            );
            @file_put_contents($log_file, $entry, FILE_APPEND | LOCK_EX);

            // Also write to error log for visibility
            $error_file = $logs_dir . '/' . ERROR_LOG_FILENAME;
            @file_put_contents($error_file, sprintf(
                "[%s] [INFO] Plugin activated — error log initialized (v%s)\n",
                $timestamp,
                $version
            ), FILE_APPEND | LOCK_EX);

            // Initialize stacktrace.txt
            $stacktrace_file = $logs_dir . '/' . STACKTRACE_FILENAME;
            if (RiseupBooleanHelpers::is_file_missing($stacktrace_file)) {
                @file_put_contents($stacktrace_file, sprintf(
                    "# Riseup Asia Uploader - Stack Trace Log (initialized %s)\n\n",
                    $timestamp
                ));
            }

            // Add security files
            if (class_exists('RiseupInitHelpers')) {
                RiseupInitHelpers::addSecurityFiles($base_dir);
            } else {
                // Manual security files
                $htaccess = $base_dir . '/.htaccess';
                if (RiseupBooleanHelpers::is_file_missing($htaccess)) {
                    @file_put_contents($htaccess, "# Riseup Asia Uploader - Security\nOrder Deny,Allow\nDeny from all\n");
                }
                $index = $base_dir . '/index.php';
                if (RiseupBooleanHelpers::is_file_missing($index)) {
                    @file_put_contents($index, "<?php\n// Silence is golden.\n");
                }
            }
        }
    } catch (\Throwable $e) {
        error_log('[Riseup Asia] Activation hook failed: ' . $e->getMessage());
    }
}

register_activation_hook(__FILE__, 'riseup_asia_activate');

/**
 * Initialize the plugin.
 */
function riseup_asia_init() {
    // Initialize main plugin class
    RiseupAsia::get_instance();
    
    // Initialize admin pages (only in admin context)
    if (is_admin()) {
        RiseupAdmin::get_instance();
    }
}

// Initialize on plugins_loaded hook.
add_action(HookType::PluginsLoaded->value, 'riseup_asia_init');
