<?php
/**
 * Plugin Name: Riseup Asia Uploader
 * Plugin URI: https://rasia.pro/alim-r-profile-v1
 * Description: Remote plugin management, blog post publishing, delta file sync, auto-update with 301 redirect resolution, and audit logging via REST API with Application Password authentication.
 * Version: 1.45.0
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

// Foundation: constants, boolean helpers, init helpers (must be loaded raw).
require_once __DIR__ . '/includes/constants.php';
require_once __DIR__ . '/includes/class-boolean-helpers.php';
require_once __DIR__ . '/includes/class-init-helpers.php';

// Load dependency loader (uses BooleanHelpers, so must be after it).
require_once __DIR__ . '/includes/class-dependency-loader.php';

// Load all remaining dependencies via structured loader with error tracking.
$__includes = __DIR__ . '/includes';
RiseupDependencyLoader::loadManifest(array(
    // Core infrastructure — PathUtils MUST load before FileLogger to avoid
    // "Class not found" errors. The logger's ensureDirNative() path avoids
    // the circular dependency, but PathUtils must still be available for
    // all subsequent code that calls RiseupPathUtils methods.
    array('PathUtils',           $__includes . '/class-path-utils.php'),
    array('FileLogger',          $__includes . '/class-file-logger.php'),
    array('ORM',                 $__includes . '/class-orm.php'),
    array('Database',            $__includes . '/class-database.php'),
    array('EnvelopeBuilder',     $__includes . '/class-envelope-builder.php'),
    array('TransactionLogger',   $__includes . '/class-logger.php'),

    // Snapshot system
    array('SnapshotDetector',    $__includes . '/class-snapshot-detector.php'),
    array('SnapshotScheduler',   $__includes . '/class-snapshot-scheduler.php'),
    array('SnapshotCleaner',     $__includes . '/class-snapshot-cleaner.php'),
    array('SnapshotManager',     $__includes . '/class-snapshot-manager.php'),
    array('DependencyAnalyzer',  $__includes . '/class-dependency-analyzer.php'),
    array('RootDb',              $__includes . '/class-root-db.php'),
    array('SnapshotWorker',      $__includes . '/class-snapshot-worker.php'),
    array('SnapshotOrchestrator',$__includes . '/class-snapshot-orchestrator.php'),
    array('IncrementalBackup',   $__includes . '/class-incremental-backup.php'),
    array('RestoreEngine',       $__includes . '/class-restore-engine.php'),
    array('SnapshotImport',      $__includes . '/class-snapshot-import.php'),

    // Sync system
    array('FileCache',           $__includes . '/class-file-cache.php'),

    // Other classes
    array('PostManager',         $__includes . '/class-post-manager.php'),
    array('UploadIgnore',        $__includes . '/class-upload-ignore.php'),
    array('Admin',               $__includes . '/class-admin.php'),
    array('UpdateResolver',      $__includes . '/class-update-resolver.php'),
    array('AgentManager',        $__includes . '/class-agent-manager.php'),
));
unset($__includes);

// =============================================================================
// PLUGIN CLASS
// =============================================================================

/**
 * Main plugin class.
 */
class Riseup_Asia {

    /**
     * File logger instance.
     *
     * @var Riseup_File_Logger
     */
    private $file_logger;

    /**
     * Transaction logger instance.
     *
     * @var Riseup_Logger
     */
    private $logger;

    /**
     * Database instance.
     *
     * @var Riseup_Database
     */
    private $db;

    /**
     * Post manager instance.
     *
     * @var Riseup_Post_Manager
     */
    private $post_manager;

    /**
     * Singleton instance.
     *
     * @var Riseup_Asia|null
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return Riseup_Asia
     */
    public static function get_instance() {
        if (RiseupBooleanHelpers::is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        // Initialize file logger first (it doesn't depend on anything else).
        $this->file_logger = Riseup_File_Logger::get_instance();
        $this->file_logger->info('Plugin constructor starting', array('version' => RISEUP_VERSION));

        // Log dependency loading results from structured loader
        RiseupDependencyLoader::logSummary($this->file_logger);

        // =====================================================================
        // CRITICAL: Register REST routes and lifecycle hooks BEFORE any
        // component initialization. This ensures all API endpoints are
        // available even when optional dependencies (PDO, SQLite) are missing.
        // =====================================================================
        add_action('rest_api_init', array($this, 'register_routes'));
        add_action('activated_plugin', array($this, 'on_plugin_activated'), 10, 2);
        add_action('deactivated_plugin', array($this, 'on_plugin_deactivated'), 10, 2);
        add_action('deleted_plugin', array($this, 'on_plugin_deleted'), 10, 2);
        $this->file_logger->info('REST routes and lifecycle hooks registered (pre-init)');

        // =====================================================================
        // Component initialization — each wrapped in initComponent so failures
        // are isolated and do not block subsequent components.
        // =====================================================================
        $this->db = RiseupInitHelpers::initComponent('Database', function () {
            $db = Riseup_Database::get_instance();
            $db_ready = $db->init();
            if (RiseupBooleanHelpers::is_falsy($db_ready)) {
                // PDO/pdo_sqlite unavailable — warning already logged once by initSqliteConnection.
                // Return null gracefully; database-dependent features will be skipped.
                return null;
            }
            return $db;
        });

        $this->logger = RiseupInitHelpers::initComponent('TransactionLogger', function () {
            return Riseup_Logger::get_instance();
        });

        $this->post_manager = RiseupInitHelpers::initComponent('PostManager', function () {
            return Riseup_Post_Manager::get_instance();
        });

        RiseupInitHelpers::initComponent('UpdateResolver', function () {
            return Riseup_Update_Resolver::get_instance();
        });

        // Only init scheduler if database is available (requires DB for snapshot tracking)
        if (RiseupBooleanHelpers::is_set($this->db)) {
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
            'db_available' => RiseupBooleanHelpers::is_set($this->db),
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
                RISEUP_ACTION_ENABLE,
                $slug,
                RISEUP_STATUS_SUCCESS,
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
                RISEUP_ACTION_DISABLE,
                $slug,
                RISEUP_STATUS_SUCCESS,
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
        if (RiseupBooleanHelpers::is_falsy($deleted)) {
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
                RISEUP_ACTION_DELETE,
                $slug,
                RISEUP_STATUS_SUCCESS,
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
     * @return string One of the RISEUP_TRIGGERED_BY_* constants.
     */
    private function detect_trigger_source() {
        // Check if running from WP-CLI
        if (defined('WP_CLI') && WP_CLI) {
            return RISEUP_TRIGGERED_BY_CLI;
        }

        // Check if this is a cron job
        if (defined('DOING_CRON') && DOING_CRON) {
            return RISEUP_TRIGGERED_BY_CRON;
        }

        // Check if this is a REST API request (should be caught earlier, but just in case)
        if ($this->is_rest_request()) {
            return RISEUP_TRIGGERED_BY_API;
        }

        // Default to dashboard (admin UI action)
        return RISEUP_TRIGGERED_BY_DASHBOARD;
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
        $this->file_logger->info('Registering REST API routes', array('namespace' => RISEUP_API_FULL_NAMESPACE));

        $registered = 0;
        $failed = 0;

        // Helper: register a single route in its own try-catch so one failure
        // cannot prevent subsequent routes from registering.
        // CRITICAL: The $endpoint_const argument is evaluated at the CALL SITE,
        // so undefined constants on PHP 8.0+ throw Error BEFORE this body runs.
        // We wrap each $safe_register() call below in its own try-catch to guard
        // against undefined constants crashing the entire register_routes method.
        $safe_register = function ($endpoint_const, $args) use (&$registered, &$failed) {
            try {
                register_rest_route(RISEUP_API_FULL_NAMESPACE, '/' . $endpoint_const, $args);
                $registered++;
            } catch (Throwable $e) {
                $failed++;
                $this->file_logger->error('Failed to register route: ' . $endpoint_const . ' - ' . $e->getMessage());
            }
        };

        // Status endpoint (authenticated - requires valid credentials).
        $safe_register(RISEUP_ENDPOINT_STATUS, array(
            'methods'             => 'GET',
            'callback'            => array($this, 'handle_status'),
            'permission_callback' => $this->build_permission_callback('status', array($this, 'check_status_permission')),
        ));

        // OpenAPI specification endpoint (authenticated).
        $safe_register(RISEUP_ENDPOINT_OPENAPI, array(
            'methods'             => 'GET',
            'callback'            => array($this, 'handle_openapi'),
            'permission_callback' => $this->build_permission_callback('openapi', array($this, 'check_status_permission')),
        ));

        // OPcache reset endpoint (used by upload script after self-updates).
        $safe_register(RISEUP_ENDPOINT_OPCACHE_RESET, array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_opcache_reset'),
            'permission_callback' => $this->build_permission_callback('opcache_reset', array($this, 'check_plugin_permission')),
        ));

        // Plugin upload endpoint.
        $safe_register(RISEUP_ENDPOINT_UPLOAD, array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_upload'),
            'permission_callback' => $this->build_permission_callback('upload', array($this, 'check_plugin_permission')),
        ));

        // Plugin list endpoint.
        $safe_register(RISEUP_ENDPOINT_PLUGINS, array(
            'methods'             => 'GET',
            'callback'            => array($this, 'handle_list_plugins'),
            'permission_callback' => $this->build_permission_callback('plugins', array($this, 'check_plugin_permission')),
        ));

        // Export-self endpoint.
        $safe_register(RISEUP_ENDPOINT_EXPORT_SELF, array(
            'methods'             => 'GET',
            'callback'            => array($this, 'handle_export_self'),
            'permission_callback' => $this->build_permission_callback('export_self', array($this, 'check_plugin_permission')),
        ));

        // Blog post endpoints.
        $safe_register(RISEUP_ENDPOINT_POSTS, array(
            array(
                'methods'             => 'GET',
                'callback'            => array($this, 'handle_list_posts'),
                'permission_callback' => $this->build_permission_callback('posts', array($this, 'check_post_permission')),
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array($this, 'handle_create_post'),
                'permission_callback' => $this->build_permission_callback('posts', array($this, 'check_post_permission')),
            ),
        ));

        // Category endpoints.
        $safe_register(RISEUP_ENDPOINT_CATEGORIES, array(
            array(
                'methods'             => 'GET',
                'callback'            => array($this, 'handle_list_categories'),
                'permission_callback' => $this->build_permission_callback('categories', array($this, 'check_post_permission')),
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array($this, 'handle_create_category'),
                'permission_callback' => $this->build_permission_callback('categories', array($this, 'check_post_permission')),
            ),
        ));

        // Transaction log endpoints.
        $safe_register(RISEUP_ENDPOINT_LOGS, array(
            'methods'             => 'GET',
            'callback'            => array($this, 'handle_query_logs'),
            'permission_callback' => $this->build_permission_callback('logs', array($this, 'check_logs_permission')),
        ));

        // Logs stats endpoint.
        $safe_register(RISEUP_ENDPOINT_LOGS_STATS, array(
            'methods'             => 'GET',
            'callback'            => array($this, 'handle_logs_stats'),
            'permission_callback' => $this->build_permission_callback('logs_stats', array($this, 'check_logs_permission')),
        ));

        // Plugin files listing endpoint - fixed URL, slug in JSON body.
        $safe_register(RISEUP_ENDPOINT_PLUGIN_FILES, array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_plugin_files'),
            'permission_callback' => $this->build_permission_callback('plugin_files', array($this, 'check_plugin_permission')),
        ));

        // Sync manifest endpoint - fixed URL, slug in JSON body.
        $safe_register(RISEUP_ENDPOINT_SYNC_MANIFEST, array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_sync_manifest'),
            'permission_callback' => $this->build_permission_callback('sync_manifest', array($this, 'check_plugin_permission')),
        ));

        // Sync push endpoint - receives delta files (replacements + deletions).
        $safe_register(RISEUP_ENDPOINT_SYNC, array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_sync_push'),
            'permission_callback' => $this->build_permission_callback('sync_push', array($this, 'check_plugin_permission')),
        ));

        // Plugin file content endpoint - fixed URL, slug in JSON body.
        $safe_register(RISEUP_ENDPOINT_PLUGIN_FILE, array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_plugin_file_content'),
            'permission_callback' => $this->build_permission_callback('plugin_file', array($this, 'check_plugin_permission')),
        ));

        // Plugin existence check endpoint (lightweight pre-flight) - slug in JSON body.
        $safe_register(RISEUP_ENDPOINT_PLUGIN_EXISTS, array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_plugin_exists'),
            'permission_callback' => $this->build_permission_callback('plugins', array($this, 'check_plugin_permission')),
        ));

        // Plugin enable endpoint (activate plugin) - slug in JSON body.
        $safe_register(RISEUP_ENDPOINT_PLUGIN_ENABLE, array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_enable_plugin'),
            'permission_callback' => $this->build_permission_callback('plugins', array($this, 'check_plugin_permission')),
        ));

        // Plugin disable endpoint (deactivate plugin) - slug in JSON body.
        $safe_register(RISEUP_ENDPOINT_PLUGIN_DISABLE, array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_disable_plugin'),
            'permission_callback' => $this->build_permission_callback('plugins', array($this, 'check_plugin_permission')),
        ));

        // Plugin delete endpoint (remove plugin) - slug in JSON body.
        $safe_register(RISEUP_ENDPOINT_PLUGIN_DELETE, array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_delete_plugin'),
            'permission_callback' => $this->build_permission_callback('plugins', array($this, 'check_plugin_permission')),
        ));

        // Plugin export endpoint - fixed URL, slug in JSON body.
        $safe_register(RISEUP_ENDPOINT_PLUGIN_EXPORT, array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_export_plugin'),
            'permission_callback' => $this->build_permission_callback('plugin_export', array($this, 'check_plugin_permission')),
        ));

        // =================================================================
        // AGENT MANAGEMENT ENDPOINTS
        // Each call wrapped in try-catch to guard against undefined
        // constants on PHP 8.0+ (Error thrown at argument evaluation).
        // =================================================================

        try { $safe_register(RISEUP_ENDPOINT_AGENTS_LIST, array(
            'methods'             => 'GET',
            'callback'            => array($this, 'handle_list_agents'),
            'permission_callback' => $this->build_permission_callback('agents', array($this, 'check_plugin_permission')),
        )); } catch (Throwable $e) { $failed++; $this->file_logger->error('Agent route AGENTS_LIST failed: ' . $e->getMessage()); }

        try { $safe_register(RISEUP_ENDPOINT_AGENTS_ADD, array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_add_agent'),
            'permission_callback' => $this->build_permission_callback('agents', array($this, 'check_plugin_permission')),
        )); } catch (Throwable $e) { $failed++; $this->file_logger->error('Agent route AGENTS_ADD failed: ' . $e->getMessage()); }

        try { $safe_register(RISEUP_ENDPOINT_AGENTS_REMOVE, array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_remove_agent'),
            'permission_callback' => $this->build_permission_callback('agents', array($this, 'check_plugin_permission')),
        )); } catch (Throwable $e) { $failed++; $this->file_logger->error('Agent route AGENTS_REMOVE failed: ' . $e->getMessage()); }

        try { $safe_register(RISEUP_ENDPOINT_AGENTS_TEST, array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_test_agent'),
            'permission_callback' => $this->build_permission_callback('agents', array($this, 'check_plugin_permission')),
        )); } catch (Throwable $e) { $failed++; $this->file_logger->error('Agent route AGENTS_TEST failed: ' . $e->getMessage()); }

        try { $safe_register(RISEUP_ENDPOINT_AGENTS_SYNC, array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_sync_to_agent'),
            'permission_callback' => $this->build_permission_callback('agents', array($this, 'check_plugin_permission')),
        )); } catch (Throwable $e) { $failed++; $this->file_logger->error('Agent route AGENTS_SYNC failed: ' . $e->getMessage()); }

        try { $safe_register(RISEUP_ENDPOINT_AGENTS_PLUGINS, array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_agent_plugin_action'),
            'permission_callback' => $this->build_permission_callback('agents', array($this, 'check_plugin_permission')),
        )); } catch (Throwable $e) { $failed++; $this->file_logger->error('Agent route AGENTS_PLUGINS failed: ' . $e->getMessage()); }

        // =================================================================
        // SNAPSHOT ENDPOINTS
        // =================================================================

        // List snapshots
        $safe_register(RISEUP_ENDPOINT_SNAPSHOT_LIST, array(
            'methods'             => 'GET',
            'callback'            => array($this, 'handle_list_snapshots'),
            'permission_callback' => $this->build_permission_callback('snapshots', array($this, 'check_plugin_permission')),
        ));

        // Schedule snapshot
        $safe_register(RISEUP_ENDPOINT_SNAPSHOT_SCHEDULE, array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_schedule_snapshot'),
            'permission_callback' => $this->build_permission_callback('snapshots', array($this, 'check_plugin_permission')),
        ));

        // Get snapshot info (fixed endpoint, ID in JSON body)
        $safe_register(RISEUP_ENDPOINT_SNAPSHOT_INFO, array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_snapshot_info'),
            'permission_callback' => $this->build_permission_callback('snapshots', array($this, 'check_plugin_permission')),
        ));

        // Delete snapshot (fixed endpoint, ID in JSON body)
        $safe_register(RISEUP_ENDPOINT_SNAPSHOT_DELETE, array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_delete_snapshot'),
            'permission_callback' => $this->build_permission_callback('snapshots', array($this, 'check_plugin_permission')),
        ));

        // Restore snapshot (fixed endpoint, ID in JSON body)
        $safe_register(RISEUP_ENDPOINT_SNAPSHOT_RESTORE, array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_restore_snapshot'),
            'permission_callback' => $this->build_permission_callback('snapshots', array($this, 'check_plugin_permission')),
        ));

        // Export snapshot as ZIP (fixed endpoint, ID in JSON body)
        $safe_register(RISEUP_ENDPOINT_SNAPSHOT_EXPORT, array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_export_snapshot'),
            'permission_callback' => $this->build_permission_callback('snapshots', array($this, 'check_plugin_permission')),
        ));

        // Import snapshot from ZIP
        $safe_register(RISEUP_ENDPOINT_SNAPSHOT_IMPORT, array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_import_snapshot'),
            'permission_callback' => $this->build_permission_callback('snapshots', array($this, 'check_plugin_permission')),
        ));

        // Snapshot settings
        $safe_register(RISEUP_ENDPOINT_SNAPSHOT_SETTINGS, array(
            array(
                'methods'             => 'GET',
                'callback'            => array($this, 'handle_get_snapshot_settings'),
                'permission_callback' => $this->build_permission_callback('snapshots', array($this, 'check_plugin_permission')),
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array($this, 'handle_update_snapshot_settings'),
                'permission_callback' => $this->build_permission_callback('snapshots', array($this, 'check_plugin_permission')),
            ),
        ));

        // Snapshot providers
        $safe_register(RISEUP_ENDPOINT_SNAPSHOT_PROVIDERS, array(
            'methods'             => 'GET',
            'callback'            => array($this, 'handle_list_snapshot_providers'),
            'permission_callback' => $this->build_permission_callback('snapshots', array($this, 'check_plugin_permission')),
        ));

        // Available tables
        $safe_register(RISEUP_ENDPOINT_SNAPSHOT_TABLES, array(
            'methods'             => 'GET',
            'callback'            => array($this, 'handle_list_snapshot_tables'),
            'permission_callback' => $this->build_permission_callback('snapshots', array($this, 'check_plugin_permission')),
        ));

        // Dependency analysis endpoint
        $safe_register('snapshots/dependencies', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_analyze_dependencies'),
            'permission_callback' => $this->build_permission_callback('snapshots', array($this, 'check_plugin_permission')),
        ));

        // Per-table snapshot export endpoint
        $safe_register('snapshots/export-pertable', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_export_pertable'),
            'permission_callback' => $this->build_permission_callback('snapshots', array($this, 'check_plugin_permission')),
        ));

        // Full backup endpoint (end-to-end orchestration)
        $safe_register(RISEUP_ENDPOINT_SNAPSHOT_FULL_BACKUP, array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_full_backup'),
            'permission_callback' => $this->build_permission_callback('snapshots', array($this, 'check_plugin_permission')),
        ));

        // Incremental backup endpoint
        $safe_register(RISEUP_ENDPOINT_SNAPSHOT_INCREMENTAL, array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_incremental_backup'),
            'permission_callback' => $this->build_permission_callback('snapshots', array($this, 'check_plugin_permission')),
        ));

        // Snapshot cleanup endpoint
        $safe_register(RISEUP_ENDPOINT_SNAPSHOT_CLEANUP, array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_snapshot_cleanup'),
            'permission_callback' => $this->build_permission_callback('snapshots', array($this, 'check_plugin_permission')),
        ));

        // Media upload endpoint
        try {
            if (defined('RISEUP_ENDPOINT_MEDIA')) {
                $safe_register(RISEUP_ENDPOINT_MEDIA, array(
                    'methods'             => 'POST',
                    'callback'            => array($this, 'handle_media_upload'),
                    'permission_callback' => $this->build_permission_callback('media', array($this, 'check_plugin_permission')),
                ));
            }
        } catch (Throwable $e) {
            // Optional endpoint, ignore
        }

        // Error logs retrieval endpoint - returns error.txt and log.txt as JSON
        $safe_register(RISEUP_ENDPOINT_ERROR_LOGS, array(
            'methods'             => 'GET',
            'callback'            => array($this, 'handle_error_logs'),
            'permission_callback' => $this->build_permission_callback('error_logs', array($this, 'check_logs_permission')),
        ));

        // Error sessions endpoint - returns structured error entries from SQLite DB
        $safe_register(RISEUP_ENDPOINT_ERROR_SESSIONS, array(
            'methods'             => 'GET',
            'callback'            => array($this, 'handle_error_sessions'),
            'permission_callback' => $this->build_permission_callback('error_logs', array($this, 'check_logs_permission')),
        ));

        $this->file_logger->info("REST API route registration complete: $registered registered, $failed failed");
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
        return Riseup_Admin::is_endpoint_enabled($endpoint);
    }

    /**
     * Check if an endpoint requires authentication via settings.
     *
     * @param string $endpoint Endpoint key.
     * @return bool True if auth required.
     */
    private function is_auth_required($endpoint) {
        return Riseup_Admin::is_auth_required($endpoint);
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
            if (RiseupBooleanHelpers::is_falsy($this->is_endpoint_enabled($endpoint))) {
                return new WP_Error(
                    'rest_disabled',
                    'This endpoint is disabled',
                    array('status' => 403)
                );
            }
            
            // Check if auth is required
            if (RiseupBooleanHelpers::is_falsy($this->is_auth_required($endpoint))) {
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
        return $this->check_authenticated_capability($request, RISEUP_CAP_MANAGE_PLUGINS);
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
        return $this->check_authenticated_capability($request, RISEUP_CAP_MANAGE_POSTS);
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
        return $this->check_authenticated_capability($request, RISEUP_CAP_VIEW_LOGS);
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
     * Verify authentication only (no capability check).
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return bool|WP_Error True if authorized, WP_Error otherwise.
     */
    private function check_authenticated_only($request) {
        $this->file_logger->debug('Authenticating request (any user)');

        try {
            // Get Authorization header — try multiple sources for compatibility
            // with CGI/FastCGI and proxy configurations.
            $auth_header = $request->get_header('Authorization');

            // Fallback: check $_SERVER for CGI/FastCGI environments
            if (RiseupBooleanHelpers::is_empty($auth_header)) {
                if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
                    $auth_header = $_SERVER['HTTP_AUTHORIZATION'];
                } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                    $auth_header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
                } elseif (function_exists('getallheaders')) {
                    $headers = getallheaders();
                    if (isset($headers['Authorization'])) {
                        $auth_header = $headers['Authorization'];
                    } elseif (isset($headers['authorization'])) {
                        $auth_header = $headers['authorization'];
                    }
                }
            }

            if (RiseupBooleanHelpers::is_empty($auth_header)) {
                $this->file_logger->warn('Missing Authorization header', array(
                    'reason'     => 'Missing Authorization header',
                    'method'     => $request->get_method(),
                    'endpoint'   => $request->get_route(),
                    'ip'         => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown',
                    'user_agent' => $request->get_header('user-agent') ?: 'unknown',
                    'server_software' => isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'unknown',
                ));
                $this->logger->log_auth_failure('Missing Authorization header');
                return new WP_Error(
                    'rest_forbidden',
                    RISEUP_MSG_UNAUTHORIZED,
                    array(
                        'status' => RISEUP_HTTP_UNAUTHORIZED,
                        'headers' => array('WWW-Authenticate' => 'Basic realm="WordPress Application Password"'),
                    )
                );
            }

            // Parse Basic auth.
            if (strpos($auth_header, 'Basic ') !== 0) {
                $this->file_logger->warn('Invalid Authorization header format');
                $this->logger->log_auth_failure('Invalid Authorization header format');
                return new WP_Error(
                    'rest_forbidden',
                    RISEUP_MSG_UNAUTHORIZED,
                    array('status' => RISEUP_HTTP_UNAUTHORIZED)
                );
            }

            $credentials = base64_decode(substr($auth_header, 6));
            if (RiseupBooleanHelpers::is_falsy($credentials) || strpos($credentials, ':') === false) {
                $this->file_logger->warn('Invalid credentials format');
                $this->logger->log_auth_failure('Invalid credentials format');
                return new WP_Error(
                    'rest_forbidden',
                    RISEUP_MSG_UNAUTHORIZED,
                    array('status' => RISEUP_HTTP_UNAUTHORIZED)
                );
            }

            list($username, $password) = explode(':', $credentials, 2);
            $this->file_logger->debug('Authenticating user', array('username' => $username));

            // Authenticate using application password.
            $user = wp_authenticate_application_password(null, $username, $password);

            if (is_wp_error($user) || RiseupBooleanHelpers::is_falsy($user)) {
                $this->file_logger->warn('Invalid credentials', array('username' => $username));
                $this->logger->log_auth_failure(
                    'Invalid credentials',
                    array('username' => $username)
                );
                return new WP_Error(
                    'rest_forbidden',
                    RISEUP_MSG_UNAUTHORIZED,
                    array('status' => RISEUP_HTTP_UNAUTHORIZED)
                );
            }

            // Set current user.
            wp_set_current_user($user->ID);
            $this->file_logger->info('Request authorized (status)', array('username' => $username));
            return true;
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'Authentication error');
            return new WP_Error(
                'rest_forbidden',
                RISEUP_MSG_UNAUTHORIZED,
                array('status' => RISEUP_HTTP_UNAUTHORIZED)
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
            // Get Authorization header — try multiple sources for compatibility
            $auth_header = $request->get_header('Authorization');

            // Fallback: check $_SERVER for CGI/FastCGI environments
            if (RiseupBooleanHelpers::is_empty($auth_header)) {
                if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
                    $auth_header = $_SERVER['HTTP_AUTHORIZATION'];
                } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                    $auth_header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
                } elseif (function_exists('getallheaders')) {
                    $headers = getallheaders();
                    if (isset($headers['Authorization'])) {
                        $auth_header = $headers['Authorization'];
                    } elseif (isset($headers['authorization'])) {
                        $auth_header = $headers['authorization'];
                    }
                }
            }

            if (RiseupBooleanHelpers::is_empty($auth_header)) {
                $this->file_logger->warn('Missing Authorization header', array(
                    'reason'     => 'Missing Authorization header',
                    'method'     => $request->get_method(),
                    'endpoint'   => $request->get_route(),
                    'ip'         => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown',
                    'user_agent' => $request->get_header('user-agent') ?: 'unknown',
                    'server_software' => isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'unknown',
                ));
                $this->logger->log_auth_failure('Missing Authorization header');
                return new WP_Error(
                    'rest_forbidden',
                    RISEUP_MSG_UNAUTHORIZED,
                    array('status' => RISEUP_HTTP_UNAUTHORIZED)
                );
            }

            // Parse Basic auth.
            if (strpos($auth_header, 'Basic ') !== 0) {
                $this->file_logger->warn('Invalid Authorization header format');
                $this->logger->log_auth_failure('Invalid Authorization header format');
                return new WP_Error(
                    'rest_forbidden',
                    RISEUP_MSG_UNAUTHORIZED,
                    array('status' => RISEUP_HTTP_UNAUTHORIZED)
                );
            }

            $credentials = base64_decode(substr($auth_header, 6));
            if (RiseupBooleanHelpers::is_falsy($credentials) || strpos($credentials, ':') === false) {
                $this->file_logger->warn('Invalid credentials format');
                $this->logger->log_auth_failure('Invalid credentials format');
                return new WP_Error(
                    'rest_forbidden',
                    RISEUP_MSG_UNAUTHORIZED,
                    array('status' => RISEUP_HTTP_UNAUTHORIZED)
                );
            }

            list($username, $password) = explode(':', $credentials, 2);
            $this->file_logger->debug('Authenticating user', array('username' => $username));

            // Authenticate using application password.
            $user = wp_authenticate_application_password(null, $username, $password);

            if (is_wp_error($user) || RiseupBooleanHelpers::is_falsy($user)) {
                $this->file_logger->warn('Invalid credentials', array('username' => $username));
                $this->logger->log_auth_failure(
                    'Invalid credentials',
                    array('username' => $username)
                );
                return new WP_Error(
                    'rest_forbidden',
                    RISEUP_MSG_UNAUTHORIZED,
                    array('status' => RISEUP_HTTP_UNAUTHORIZED)
                );
            }

            // Set current user for further capability checks.
            wp_set_current_user($user->ID);
            $this->file_logger->debug('User authenticated', array('user_id' => $user->ID));

            // Check capability.
            if (RiseupBooleanHelpers::is_falsy(current_user_can($capability))) {
                $this->file_logger->warn('Insufficient permissions', array(
                    'username'     => $username,
                    'required_cap' => $capability,
                ));
                $this->logger->log_auth_failure(
                    'Insufficient permissions',
                    array('username' => $username, 'required_cap' => $capability)
                );
                return new WP_Error(
                    'rest_forbidden',
                    RISEUP_MSG_FORBIDDEN,
                    array('status' => RISEUP_HTTP_FORBIDDEN)
                );
            }

            $this->file_logger->info('Request authorized', array('username' => $username));
            return true;
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'Authentication error');
            return new WP_Error(
                'rest_forbidden',
                RISEUP_MSG_UNAUTHORIZED,
                array('status' => RISEUP_HTTP_UNAUTHORIZED)
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
        $ns_prefix = '/' . RISEUP_API_FULL_NAMESPACE;

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
        // avoid stale RISEUP_VERSION constant after self-updates. OPcache
        // may cache the old constants.php bytecode across requests.
        // =====================================================================
        $live_version = RISEUP_VERSION; // default fallback
        $main_plugin_file = WP_PLUGIN_DIR . '/' . RISEUP_SLUG . '/' . RISEUP_SLUG . '.php';
        clearstatcache(true, $main_plugin_file);
        if (file_exists($main_plugin_file)) {
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($main_plugin_file, true);
                $constants_file = WP_PLUGIN_DIR . '/' . RISEUP_SLUG . '/includes/constants.php';
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
        $db_available = RiseupBooleanHelpers::is_set($this->db);
        $site_url = get_site_url();
        $plugin_file = plugin_basename(__FILE__);
        $active_plugins = get_option('active_plugins', array());
        $is_active = in_array($plugin_file, $active_plugins, true);

        return RiseupEnvelopeBuilder::success()
            ->setRequestedAt('/' . RISEUP_API_FULL_NAMESPACE . RISEUP_ENDPOINT_STATUS)
            ->setSingleResult(array(
                'Plugin'           => RISEUP_NAME,
                'Version'          => $live_version,
                'Slug'             => RISEUP_SLUG,
                'Api'              => RISEUP_API_FULL_NAMESPACE,
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
            ), RISEUP_HTTP_NOT_FOUND);
        }

        $spec_content = file_get_contents($spec_file);
        if ($spec_content === false) {
            $this->file_logger->error('Failed to read OpenAPI spec file');
            return new WP_REST_Response(array(
                'success' => false,
                'error'   => 'Failed to read OpenAPI specification',
            ), RISEUP_HTTP_SERVER_ERROR);
        }

        $spec = json_decode($spec_content, true);
        if (RiseupBooleanHelpers::is_null($spec)) {
            $this->file_logger->error('Invalid JSON in OpenAPI spec file');
            return new WP_REST_Response(array(
                'success' => false,
                'error'   => 'Invalid OpenAPI specification format',
            ), RISEUP_HTTP_SERVER_ERROR);
        }

        // Update the server URL dynamically.
        $spec['servers'][0]['variables']['baseUrl']['default'] = get_site_url();

        return new WP_REST_Response($spec, RISEUP_HTTP_OK);
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
        $plugin_dir = WP_PLUGIN_DIR . '/' . RISEUP_SLUG;
        $invalidated = 0;
        if (function_exists('opcache_invalidate')) {
            $files_to_invalidate = array(
                $plugin_dir . '/' . RISEUP_SLUG . '.php',
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
            ->setRequestedAt('/' . RISEUP_API_FULL_NAMESPACE . '/' . RISEUP_ENDPOINT_OPCACHE_RESET)
            ->setSingleResult($result)
            ->toResponse();
    }

    // =========================================================================
    // PLUGIN HANDLERS
    // =========================================================================

    /**
     * Handle plugin upload (Base64 ZIP).
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_upload($request) {
        $this->file_logger->info('Upload endpoint called');

        try {
            $data = $request->get_json_params();

            if (RiseupBooleanHelpers::is_empty($data['plugin_zip'])) {
                $this->file_logger->warn('Upload failed: plugin_zip required');
                return $this->error_response(RISEUP_MSG_INVALID_REQUEST . ': plugin_zip is required', RISEUP_HTTP_BAD_REQUEST);
            }

            // Decode base64.
            $this->file_logger->debug('Decoding base64 ZIP data');
            $zip_content = base64_decode($data['plugin_zip']);
            if ($zip_content === false) {
                $this->file_logger->error('Invalid base64 data');
                return $this->error_response('Invalid base64 data', RISEUP_HTTP_BAD_REQUEST);
            }

            // Get optional parameters.
            $slug     = sanitize_file_name($data['slug'] ?? '');
            $activate = RiseupBooleanHelpers::has_content($data['activate']);
            $upload_source = isset($data['upload_source']) ? sanitize_text_field($data['upload_source']) : UPLOAD_SOURCE_REST_API;
            $client_plugin_version = isset($data['plugin_version']) ? sanitize_text_field($data['plugin_version']) : '';
            
            // Validate upload_source against allowed enum values
            $valid_sources = json_decode(UPLOAD_SOURCES_VALID, true);
            if (!in_array($upload_source, $valid_sources, true)) {
                $upload_source = UPLOAD_SOURCE_REST_API;
            }
            
            $this->file_logger->debug('Upload parameters', array('slug' => $slug, 'activate' => $activate, 'upload_source' => $upload_source, 'client_version' => $client_plugin_version));

            // =====================================================================
            // ACTIVITY STATE 1: Log "Upload Initiated" before any processing
            // This ensures we have a record even if the upload fails partway through
            // =====================================================================
            if (RiseupBooleanHelpers::has_content($slug)) {
                $this->logger->log_upload_initiated($slug, array(
                    'activate'       => $activate,
                    'upload_source'  => $upload_source,
                    'client_version' => $client_plugin_version,
                    'file_size'      => strlen($zip_content),
                ), array(
                    'plugin_version' => $client_plugin_version ?: RISEUP_VERSION,
                    'upload_source'  => $upload_source,
                ));
            }

            // Create temp file.
            $temp_dir  = $this->get_temp_dir();
            $temp_file = $temp_dir . '/' . ($slug ?: 'plugin_' . time()) . '.zip';

            $this->file_logger->debug('Writing temp file', array('path' => $temp_file));
            if (file_put_contents($temp_file, $zip_content) === false) {
                $this->file_logger->error('Failed to write temp file');
                $this->logger->log_upload_failed($slug, 'Failed to write temp file');
                return $this->error_response(RISEUP_MSG_UPLOAD_FAILED, RISEUP_HTTP_SERVER_ERROR);
            }

            // Validate ZIP.
            $this->file_logger->debug('Validating ZIP archive');
            $zip = new ZipArchive();
            if ($zip->open($temp_file) !== true) {
                @unlink($temp_file);
                $this->file_logger->error('Invalid ZIP archive');
                $this->logger->log_upload_failed($slug, 'Invalid ZIP archive');
                return $this->error_response('Invalid ZIP archive', RISEUP_HTTP_BAD_REQUEST);
            }

            // Determine plugin slug from ZIP.
            $detected_slug = $this->detect_plugin_slug_from_zip($zip);
            $zip->close();

            if (RiseupBooleanHelpers::is_falsy($detected_slug)) {
                @unlink($temp_file);
                $this->file_logger->error('Could not detect plugin in ZIP');
                $this->logger->log_upload_failed($slug, 'Could not detect plugin in ZIP');
                return $this->error_response('Could not detect plugin in ZIP', RISEUP_HTTP_BAD_REQUEST);
            }

            // Use detected slug if not provided.
            if (RiseupBooleanHelpers::is_empty($slug)) {
                $slug = $detected_slug;
            }
            $this->file_logger->info('Plugin slug determined', array('slug' => $slug));

            // Extract to plugins directory.
            $plugins_dir  = WP_PLUGIN_DIR;
            $target_dir   = $plugins_dir . '/' . $slug;
            $is_update    = is_dir($target_dir);

            // =====================================================================
            // SELF-UPDATE PRE-LOGGING: If the plugin is updating itself, log the
            // activity BEFORE the files are replaced. Otherwise, the log entry
            // might not be created if the new code changes the logging behavior.
            // =====================================================================
            $is_self_update = ($slug === RISEUP_SLUG && $is_update);
            if ($is_self_update) {
                // Detect current (old) version before replacement
                $old_plugin_file = $this->find_plugin_file($slug);
                $old_version = RISEUP_VERSION;
                
                $this->file_logger->info('Self-update detected, pre-logging activity', array(
                    'old_version'   => $old_version,
                    'upload_source' => $upload_source,
                ));
                
                $this->logger->log_plugin_action(
                    RISEUP_ACTION_UPLOAD,
                    $slug,
                    RISEUP_STATUS_SUCCESS,
                    array(
                        'is_update'       => true,
                        'is_self_update'  => true,
                        'old_version'     => $old_version,
                        'file_size'       => strlen($zip_content),
                        'note'            => 'Pre-logged before self-update to ensure audit trail',
                    ),
                    null,
                    array(
                        'plugin_version' => $old_version,
                        'upload_source'  => $upload_source,
                    )
                );
            }

            $this->file_logger->info($is_update ? 'Updating existing plugin' : 'Installing new plugin', array(
                'slug'       => $slug,
                'target_dir' => $target_dir,
            ));

            // If updating, check if currently active.
            $was_active = false;
            if ($is_update) {
                $plugin_file = $this->find_plugin_file($slug);
                if ($plugin_file) {
                    $was_active = is_plugin_active($plugin_file);
                    if ($was_active) {
                        $this->file_logger->debug('Deactivating plugin before update', array('plugin_file' => $plugin_file));
                        deactivate_plugins($plugin_file);
                    }
                }
                // Remove old version.
                $this->file_logger->debug('Removing old plugin version', array('target_dir' => $target_dir));
                $this->delete_directory($target_dir);
            }

            // =================================================================
            // EXTRACT TO TEMP FIRST TO AVOID FOLDER NAME MISMATCHES
            // This prevents duplicate plugins when ZIP folder name differs from slug
            // =================================================================
            $temp_extract_dir = $this->get_temp_dir() . '/extract_' . uniqid();
            wp_mkdir_p($temp_extract_dir);

            $this->file_logger->debug('Extracting ZIP to temp directory', array(
                'temp_dir'   => $temp_extract_dir,
                'temp_file'  => $temp_file,
            ));

            $zip = new ZipArchive();
            if ($zip->open($temp_file) !== true) {
                @unlink($temp_file);
                $this->delete_directory($temp_extract_dir);
                $this->file_logger->error('Failed to open ZIP for extraction');
                return $this->error_response('Failed to open ZIP for extraction', RISEUP_HTTP_SERVER_ERROR);
            }
            $zip->extractTo($temp_extract_dir);
            $zip->close();
            @unlink($temp_file);

            // Find the extracted folder (should be exactly one directory).
            $extracted_folders = glob($temp_extract_dir . '/*', GLOB_ONLYDIR);
            if (RiseupBooleanHelpers::is_empty($extracted_folders)) {
                $this->delete_directory($temp_extract_dir);
                $this->file_logger->error('No folder found in extracted ZIP');
                $this->logger->log_upload_failed($slug, 'No folder found in extracted ZIP');
                return $this->error_response('No folder found in extracted ZIP', RISEUP_HTTP_SERVER_ERROR);
            }

            $extracted_folder = $extracted_folders[0];
            $extracted_name   = basename($extracted_folder);
            $this->file_logger->debug('Found extracted folder', array(
                'extracted_name' => $extracted_name,
                'target_slug'    => $slug,
                'needs_rename'   => $extracted_name !== $slug,
            ));

            // Move the extracted folder to the plugins directory with the correct slug name.
            // This ensures the folder name always matches the slug, preventing duplicates.
            if (rename($extracted_folder, $target_dir)) {
                $this->file_logger->info('Plugin installed to correct location', array(
                    'from' => $extracted_folder,
                    'to'   => $target_dir,
                ));
            } else {
                // Fallback: copy files if rename fails (cross-device move).
                $this->file_logger->warn('Rename failed, attempting copy', array(
                    'from' => $extracted_folder,
                    'to'   => $target_dir,
                ));
                $this->copy_directory($extracted_folder, $target_dir);
                $this->delete_directory($extracted_folder);
            }

            // Cleanup temp extraction directory.
            $this->delete_directory($temp_extract_dir);

            // =================================================================
            // OPCACHE RESET — critical for self-updates
            // After replacing files, do a FULL opcache_reset() so the NEXT
            // HTTP request serves the new bytecode. Individual invalidate()
            // calls are unreliable on many hosts. The full reset ensures
            // the /status endpoint (called next by the upload script) will
            // execute the NEW code and return the correct version.
            // =================================================================
            if (function_exists('opcache_reset')) {
                opcache_reset();
                $this->file_logger->info('Full OPcache reset after plugin extraction');
            }

            $plugin_file = $this->find_plugin_file($slug);
            if (RiseupBooleanHelpers::has_content($plugin_file)) {
                $full_plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;
                
                // Also invalidate specific files as belt-and-suspenders
                if (function_exists('opcache_invalidate')) {
                    opcache_invalidate($full_plugin_path, true);
                    $this->file_logger->debug('OPcache invalidated for plugin file', array('path' => $full_plugin_path));
                }
                
                $constants_file = WP_PLUGIN_DIR . '/' . $slug . '/includes/constants.php';
                if (file_exists($constants_file) && function_exists('opcache_invalidate')) {
                    opcache_invalidate($constants_file, true);
                    $this->file_logger->debug('OPcache invalidated for constants file', array('path' => $constants_file));
                }
                
                // Clear WordPress plugin cache
                wp_cache_delete('plugins', 'plugins');
            }

            if (RiseupBooleanHelpers::is_falsy($plugin_file)) {
                $this->file_logger->error('Could not find plugin file after extraction', array(
                    'slug'       => $slug,
                    'target_dir' => $target_dir,
                ));
                $this->logger->log_upload_failed($slug, 'Could not find plugin file after extraction');
                return $this->error_response('Could not find plugin file after extraction', RISEUP_HTTP_SERVER_ERROR);
            }

            $this->file_logger->info('Plugin file found', array('plugin_file' => $plugin_file));

            // Activate if requested or was previously active.
            $activated = false;
            if ($activate || $was_active) {
                $this->file_logger->debug('Activating plugin');
                $result = activate_plugin($plugin_file);
                if (is_wp_error($result)) {
                    $error_msg = $result->get_error_message();
                    $this->file_logger->warn('Activation failed', array('error' => $error_msg));
                    $this->logger->log_upload_failed($slug, RISEUP_MSG_ACTIVATION_FAILED . ': ' . $error_msg);

                    // Capture backtrace for activation failure diagnostics
                    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 0);

                    // Plugin uploaded but activation failed — include full diagnostic metadata
                    return RiseupEnvelopeBuilder::success('Plugin uploaded but activation failed', RISEUP_HTTP_OK)
                        ->setRequestedAt('/' . RISEUP_API_FULL_NAMESPACE . '/' . RISEUP_ENDPOINT_UPLOAD)
                        ->setSingleResult(array(
                            'plugin_slug'      => $slug,
                            'is_update'        => $is_update,
                            'activated'        => false,
                            'activation_error' => $error_msg,
                        ))
                        ->toResponse();
                }
                $activated = true;
                $this->file_logger->info('Plugin activated successfully');
            }

            // =================================================================
            // VERSION DETECTION — Always read from the NEWLY INSTALLED files
            // The client-sent version is used as a fallback, but we prefer
            // reading the actual installed file to confirm what's on disk.
            // This avoids the self-update chicken-and-egg problem where
            // RISEUP_VERSION constant still holds the old value.
            // =================================================================
            $installed_version = '';
            if (RiseupBooleanHelpers::has_content($plugin_file)) {
                // Force re-read from disk (not cached)
                $full_path = WP_PLUGIN_DIR . '/' . $plugin_file;
                // Clear PHP's stat cache so file_exists/file_get_contents read fresh data
                clearstatcache(true, $full_path);
                if (file_exists($full_path)) {
                    // Read file header directly to bypass any caching
                    $file_contents = file_get_contents($full_path, false, null, 0, 8192);
                    if ($file_contents !== false && preg_match('/Version:\s*([0-9]+\.[0-9]+\.[0-9]+)/', $file_contents, $matches)) {
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
            }

            // Priority depends on whether this is a self-update:
            // - Self-update: client_version > installed_version > RISEUP_VERSION
            //   (running PHP process has stale constants and OPcache may serve old file content)
            // - Other plugins: installed_version > client_version > RISEUP_VERSION
            if ($is_self_update) {
                $plugin_version = $client_plugin_version ?: ($installed_version ?: RISEUP_VERSION);
                $version_source = !empty($client_plugin_version) ? 'client (self-update)' : ($installed_version ? 'file_header' : 'constant');
            } else {
                $plugin_version = $installed_version ?: ($client_plugin_version ?: RISEUP_VERSION);
                $version_source = $installed_version ? 'file_header' : (!empty($client_plugin_version) ? 'client' : 'constant');
            }
            
            $this->file_logger->info('Plugin version determined', array(
                'version'           => $plugin_version,
                'installed_version' => $installed_version,
                'client_version'    => $client_plugin_version,
                'constant_version'  => RISEUP_VERSION,
                'is_self_update'    => $is_self_update,
                'source'            => $version_source,
            ));

            // =====================================================================
            // ACTIVITY STATE 2: Log "Upload Success/Failed" after processing
            // (skip if self-update was pre-logged)
            // =====================================================================
            if (!$is_self_update) {
                $this->logger->log_upload($slug, array(
                    'is_update' => $is_update,
                    'activated' => $activated,
                    'file_size' => strlen($zip_content),
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
                ->setRequestedAt('/' . RISEUP_API_FULL_NAMESPACE . '/' . RISEUP_ENDPOINT_UPLOAD)
                ->setSingleResult(array(
                    'plugin_slug'    => $slug,
                    'is_update'      => $is_update,
                    'activated'      => $activated,
                    'plugin_version' => $plugin_version,
                    'upload_source'  => $upload_source,
                ))
                ->toResponse();
        } catch (Throwable $e) {
            // Catch both Exception and Error (PHP 7+) for complete coverage
            $this->file_logger->log_exception($e, 'Upload error');
            return $this->error_response('Upload failed: ' . $e->getMessage(), RISEUP_HTTP_SERVER_ERROR, $e);
        }
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
                ->setRequestedAt('/' . RISEUP_API_FULL_NAMESPACE . RISEUP_ENDPOINT_PLUGINS)
                ->setResults($plugins)
                ->toResponse();
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'List plugins error');
            return $this->error_response('Failed to list plugins: ' . $e->getMessage(), RISEUP_HTTP_SERVER_ERROR, $e);
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
        if (RiseupBooleanHelpers::is_empty($slug)) {
            return $this->error_response('Plugin slug is required in JSON body', RISEUP_HTTP_BAD_REQUEST);
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
                return $this->error_response(RISEUP_MSG_PLUGIN_NOT_FOUND . ': ' . $slug, RISEUP_HTTP_NOT_FOUND);
            }

            // Load uploadignore patterns if available
            $ignore = Riseup_Upload_Ignore::from_directory($plugin_dir);

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
            ), RISEUP_HTTP_OK);
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'Plugin files error');
            return $this->error_response('Failed to list plugin files: ' . $e->getMessage(), RISEUP_HTTP_SERVER_ERROR, $e);
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
        if (RiseupBooleanHelpers::is_empty($slug)) {
            return $this->error_response('Plugin slug is required in JSON body', RISEUP_HTTP_BAD_REQUEST);
        }
        $this->file_logger->info('Sync manifest endpoint called', array('slug' => $slug));

        try {
            if (RiseupBooleanHelpers::is_func_missing('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $plugin_dir = WP_PLUGIN_DIR . '/' . $slug;
            
            if (RiseupBooleanHelpers::is_dir_missing($plugin_dir)) {
                $this->file_logger->warn('Plugin directory not found', array('slug' => $slug, 'path' => $plugin_dir));
                return $this->error_response(RISEUP_MSG_PLUGIN_NOT_FOUND . ': ' . $slug, RISEUP_HTTP_NOT_FOUND);
            }

            $ignore = Riseup_Upload_Ignore::from_directory($plugin_dir);

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
            ), RISEUP_HTTP_OK);
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'Sync manifest error');
            return $this->error_response('Failed to generate sync manifest: ' . $e->getMessage(), RISEUP_HTTP_SERVER_ERROR, $e);
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

        if (RiseupBooleanHelpers::is_empty($slug)) {
            return $this->error_response('Plugin slug is required in JSON body', RISEUP_HTTP_BAD_REQUEST);
        }
        if (RiseupBooleanHelpers::is_empty($files) || RiseupBooleanHelpers::is_falsy(is_array($files))) {
            return $this->error_response('Files array is required', RISEUP_HTTP_BAD_REQUEST);
        }

        $this->file_logger->info('Sync push endpoint called', array('slug' => $slug, 'fileCount' => count($files)));

        try {
            $plugin_dir = WP_PLUGIN_DIR . '/' . $slug;
            if (RiseupBooleanHelpers::is_dir_missing($plugin_dir)) {
                return $this->error_response(RISEUP_MSG_PLUGIN_NOT_FOUND . ': ' . $slug, RISEUP_HTTP_NOT_FOUND);
            }

            $ignore = Riseup_Upload_Ignore::from_directory($plugin_dir);
            $results = array();
            $files_updated = 0;
            $files_deleted = 0;
            $files_ignored = 0;
            $ignored_files = array();

            foreach ($files as $file) {
                $path = isset($file['path']) ? $file['path'] : '';
                $action = isset($file['action']) ? $file['action'] : '';
                $content = isset($file['content']) ? $file['content'] : '';

                if (RiseupBooleanHelpers::is_empty($path) || RiseupBooleanHelpers::is_empty($action)) {
                    $results[] = array('path' => $path, 'action' => $action, 'status' => 'skipped', 'reason' => 'Missing path or action');
                    continue;
                }

                // Check ignore patterns
                if ($ignore && $ignore->is_ignored($path)) {
                    $files_ignored++;
                    $ignored_files[] = $path;
                    $results[] = array('path' => $path, 'action' => $action, 'status' => 'ignored', 'reason' => RISEUP_MSG_FILE_IGNORED);
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
                if (strpos($resolved, $real_plugin_dir) !== 0 && $action !== RISEUP_SYNC_ACTION_DELETE) {
                    $results[] = array('path' => $path, 'action' => $action, 'status' => 'error', 'reason' => 'Path traversal detected');
                    continue;
                }

                if ($action === RISEUP_SYNC_ACTION_REPLACE) {
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

                } elseif ($action === RISEUP_SYNC_ACTION_DELETE) {
                    // Delete the file from remote
                    if (file_exists($full_path)) {
                        // Log deletion to audit trail
                        $this->file_logger->info('Sync delete', array('slug' => $slug, 'path' => $path));
                        if ($this->db) {
                            $this->db->log_transaction(
                                RISEUP_ACTION_SYNC_DELETE,
                                $slug,
                                RISEUP_STATUS_SUCCESS,
                                'Deleted via sync: ' . $path,
                                null,
                                null,
                                RISEUP_TRIGGERED_BY_API
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
                    RISEUP_ACTION_SYNC,
                    $slug,
                    RISEUP_STATUS_SUCCESS,
                    sprintf('Sync: %d updated, %d deleted, %d ignored', $files_updated, $files_deleted, $files_ignored),
                    null,
                    null,
                    RISEUP_TRIGGERED_BY_API
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
            ), RISEUP_HTTP_OK);
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'Sync push error');
            return $this->error_response('Sync push failed: ' . $e->getMessage(), RISEUP_HTTP_SERVER_ERROR, $e);
        }
    }

    /**
     * Recursively scan a directory and collect file info with hashes.
     *
     * @param string               $base_dir The base directory for relative paths.
     * @param string               $dir      Current directory to scan.
     * @param Riseup_Upload_Ignore $ignore   Ignore patterns.
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
        if (RiseupBooleanHelpers::is_empty($slug)) {
            return $this->error_response('Plugin slug is required in JSON body', RISEUP_HTTP_BAD_REQUEST);
        }

        $this->file_logger->info('Plugin file content endpoint called', array(
            'slug' => $slug,
            'path' => $file_path,
        ));

        try {
            if (RiseupBooleanHelpers::is_empty($file_path)) {
                return $this->error_response('File path is required', RISEUP_HTTP_BAD_REQUEST);
            }

            // Sanitize path - prevent directory traversal
            $file_path = ltrim($file_path, '/\\');
            if (strpos($file_path, '..') !== false) {
                return $this->error_response('Invalid file path', RISEUP_HTTP_BAD_REQUEST);
            }

            $plugin_dir = WP_PLUGIN_DIR . '/' . $slug;
            $full_path = $plugin_dir . '/' . $file_path;

            if (RiseupBooleanHelpers::is_dir_missing($plugin_dir)) {
                return $this->error_response(RISEUP_MSG_PLUGIN_NOT_FOUND . ': ' . $slug, RISEUP_HTTP_NOT_FOUND);
            }

            // Verify the file is within the plugin directory
            $real_plugin_dir = realpath($plugin_dir);
            $real_file_path = realpath($full_path);

            if ($real_file_path === false || strpos($real_file_path, $real_plugin_dir) !== 0) {
                return $this->error_response('File not found or invalid path', RISEUP_HTTP_NOT_FOUND);
            }

            if (RiseupBooleanHelpers::is_falsy(is_file($real_file_path))) {
                return $this->error_response('File not found', RISEUP_HTTP_NOT_FOUND);
            }

            $content = @file_get_contents($real_file_path);
            if ($content === false) {
                return $this->error_response('Failed to read file', RISEUP_HTTP_SERVER_ERROR);
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
            ), RISEUP_HTTP_OK);
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'Plugin file content error');
            return $this->error_response('Failed to read file: ' . $e->getMessage(), RISEUP_HTTP_SERVER_ERROR, $e);
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
            $zip_file   = $temp_dir . '/' . RISEUP_SLUG . '.zip';

            $this->file_logger->debug('Creating ZIP', array('source' => $plugin_dir, 'target' => $zip_file));

            // Create ZIP.
            $zip = new ZipArchive();
            if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                $this->file_logger->error('Failed to create ZIP file');
                return $this->error_response('Failed to create ZIP file', RISEUP_HTTP_SERVER_ERROR);
            }

            // Load uploadignore.
            $ignore = Riseup_Upload_Ignore::from_directory($plugin_dir);
            $this->file_logger->debug('Uploadignore loaded', array('has_patterns' => $ignore->is_loaded()));

            // Add files recursively.
            $this->add_dir_to_zip($zip, $plugin_dir, RISEUP_SLUG, $ignore);
            $zip->close();

            // Read and encode.
            $zip_content = file_get_contents($zip_file);
            @unlink($zip_file);

            if ($zip_content === false) {
                $this->file_logger->error('Failed to read ZIP file');
                return $this->error_response('Failed to read ZIP file', RISEUP_HTTP_SERVER_ERROR);
            }

            $this->file_logger->info('Export-self complete', array('size' => strlen($zip_content)));

            // Log the export.
            $this->logger->log_plugin_action(RISEUP_ACTION_EXPORT_SELF, RISEUP_SLUG, RISEUP_STATUS_SUCCESS, array(
                'size' => strlen($zip_content),
            ));

            return new WP_REST_Response(array(
                'success'    => true,
                'plugin_zip' => base64_encode($zip_content),
                'slug'       => RISEUP_SLUG,
                'version'    => RISEUP_VERSION,
            ), RISEUP_HTTP_OK);
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'Export-self error');
            return $this->error_response('Export failed: ' . $e->getMessage(), RISEUP_HTTP_SERVER_ERROR, $e);
        }
    }

    /**
     * Export any installed plugin as a base64-encoded ZIP.
     * Used by the Go backend for pre-publish backup / rollback.
     */
    public function handle_export_plugin($request) {
        $body = $request->get_json_params();
        $slug = isset($body['plugin']) ? sanitize_text_field($body['plugin']) : $request->get_param('slug');
        if (RiseupBooleanHelpers::is_empty($slug)) {
            return $this->error_response('Plugin slug is required in JSON body', RISEUP_HTTP_BAD_REQUEST);
        }
        $this->file_logger->info('Export-plugin endpoint called', array('slug' => $slug));

        return $this->safe_execute(function () use ($slug) {
            $plugins_dir = WP_PLUGIN_DIR;
            $plugin_dir  = RiseupPathUtils::join($plugins_dir, $slug);

            if (!RiseupPathUtils::dirExists($plugin_dir)) {
                return $this->error_response('Plugin not found: ' . $slug, RISEUP_HTTP_NOT_FOUND);
            }

            // Safety: prevent path traversal
            if (!RiseupPathUtils::isSafePath($plugin_dir, $plugins_dir)) {
                return $this->error_response('Invalid plugin slug', RISEUP_HTTP_BAD_REQUEST);
            }

            $temp_dir = $this->get_temp_dir();
            $zip_file = RiseupPathUtils::join($temp_dir, $slug . '-backup.zip');

            $zip = new ZipArchive();
            if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                $this->file_logger->error('Failed to create export ZIP');
                return $this->error_response('Failed to create ZIP file', RISEUP_HTTP_SERVER_ERROR);
            }

            // Add all files recursively
            $ignore = Riseup_Upload_Ignore::from_directory($plugin_dir);
            $this->add_dir_to_zip($zip, $plugin_dir, $slug, $ignore);
            $file_count = $zip->numFiles;
            $zip->close();

            $zip_content = file_get_contents($zip_file);
            @unlink($zip_file);

            if ($zip_content === false) {
                return $this->error_response('Failed to read ZIP file', RISEUP_HTTP_SERVER_ERROR);
            }

            $this->file_logger->info('Export-plugin complete', array(
                'slug' => $slug,
                'size' => strlen($zip_content),
                'files' => $file_count,
            ));

            $this->logger->log_plugin_action(RISEUP_ACTION_EXPORT_PLUGIN, $slug, RISEUP_STATUS_SUCCESS, array(
                'size'  => strlen($zip_content),
                'files' => $file_count,
            ));

            return new WP_REST_Response(array(
                'success'    => true,
                'plugin_zip' => base64_encode($zip_content),
                'slug'       => $slug,
                'file_count' => $file_count,
                'size'       => strlen($zip_content),
            ), RISEUP_HTTP_OK);
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

        return new WP_REST_Response($result, $result['success'] ? RISEUP_HTTP_OK : RISEUP_HTTP_SERVER_ERROR);
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

        return new WP_REST_Response($result, $result['success'] ? RISEUP_HTTP_CREATED : RISEUP_HTTP_BAD_REQUEST);
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

        return new WP_REST_Response($result, $result['success'] ? RISEUP_HTTP_OK : RISEUP_HTTP_SERVER_ERROR);
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

        return new WP_REST_Response($result, $result['success'] ? RISEUP_HTTP_CREATED : RISEUP_HTTP_BAD_REQUEST);
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

            $limit  = $request->get_param('limit') ?? RISEUP_DEFAULT_LIMIT;
            $offset = $request->get_param('offset') ?? 0;

            $result = $this->db->query_transactions($filters, $limit, $offset);

            $total = $result['total'];
            $per_page = (int) $limit;

            return RiseupEnvelopeBuilder::success()
                ->setRequestedAt('/' . RISEUP_API_FULL_NAMESPACE . '/' . RISEUP_ENDPOINT_LOGS)
                ->setResults($result['logs'])
                ->setPagination($total, $per_page, $per_page > 0 ? (int) floor($offset / $per_page) + 1 : 1)
                ->toResponse();
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'Query logs error');
            return $this->error_response('Failed to query logs: ' . $e->getMessage(), RISEUP_HTTP_SERVER_ERROR, $e);
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
                ->setRequestedAt('/' . RISEUP_API_FULL_NAMESPACE . '/' . RISEUP_ENDPOINT_LOGS_STATS)
                ->setSingleResult($stats)
                ->toResponse();
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'Logs stats error');
            return $this->error_response('Failed to get stats: ' . $e->getMessage(), RISEUP_HTTP_SERVER_ERROR, $e);
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
        if (RiseupBooleanHelpers::is_empty($slug)) {
            return $this->error_response('Plugin slug is required in JSON body', RISEUP_HTTP_BAD_REQUEST);
        }

        try {
            $plugin_file = $this->find_plugin_file($slug);
            $exists = RiseupBooleanHelpers::is_truthy($plugin_file);

            if ($exists) {
                $status = is_plugin_active($plugin_file) ? 'active' : 'inactive';
            } else {
                $status = 'not_installed';
            }

            return RiseupEnvelopeBuilder::success()
                ->setRequestedAt('/' . RISEUP_API_FULL_NAMESPACE . '/' . RISEUP_ENDPOINT_PLUGIN_EXISTS)
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
                RISEUP_HTTP_SERVER_ERROR,
                $e
            );
        }
    }

    /**
     * Handle enable (activate) plugin request.
     * Granular try-catch for detailed error reporting.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_enable_plugin($request) {
        // Read slug from JSON body (fixed endpoint design)
        $body = $request->get_json_params();
        $slug = isset($body['plugin']) ? sanitize_text_field($body['plugin']) : $request->get_param('slug');
        if (RiseupBooleanHelpers::is_empty($slug)) {
            return $this->error_response('Plugin slug is required in JSON body', RISEUP_HTTP_BAD_REQUEST);
        }
        $this->file_logger->info('Enable plugin endpoint called', array('slug' => $slug));

        // Step 1: Load plugin functions
        try {
            if (RiseupBooleanHelpers::is_func_missing('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'Failed to load plugin functions');
            return $this->error_response(
                'Failed to load WordPress plugin functions: ' . $e->getMessage(),
                RISEUP_HTTP_SERVER_ERROR,
                $e
            );
        }

        // Step 2: Find the plugin file
        $plugin_file = null;
        try {
            $plugin_file = $this->find_plugin_file($slug);
            if (RiseupBooleanHelpers::is_falsy($plugin_file)) {
                $this->file_logger->warn('Plugin not found', array('slug' => $slug));
                return $this->error_response(
                    RISEUP_MSG_PLUGIN_NOT_FOUND . ': ' . $slug,
                    RISEUP_HTTP_NOT_FOUND
                );
            }
            $this->file_logger->debug('Plugin file found', array('plugin_file' => $plugin_file));
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'Failed to find plugin file');
            return $this->error_response(
                'Failed to locate plugin: ' . $e->getMessage(),
                RISEUP_HTTP_SERVER_ERROR,
                $e
            );
        }

        // Step 3: Check if already active
        try {
            if (is_plugin_active($plugin_file)) {
                $this->file_logger->info('Plugin already active', array('slug' => $slug));
                return RiseupEnvelopeBuilder::success('Plugin was already active')
                    ->setRequestedAt('/' . RISEUP_API_FULL_NAMESPACE . '/' . RISEUP_ENDPOINT_PLUGIN_ENABLE)
                    ->setSingleResult(array(
                        'plugin_slug' => $slug,
                        'activated'   => true,
                    ))
                    ->toResponse();
            }
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'Failed to check plugin status');
            return $this->error_response(
                'Failed to check plugin status: ' . $e->getMessage(),
                RISEUP_HTTP_SERVER_ERROR,
                $e
            );
        }

        // Step 4: Activate the plugin
        try {
            $result = activate_plugin($plugin_file);
            if (is_wp_error($result)) {
                $error_msg = $result->get_error_message();
                $this->file_logger->warn('Plugin activation failed', array(
                    'slug'        => $slug,
                    'plugin_file' => $plugin_file,
                    'error'       => $error_msg,
                ));
                
                // Log the failure
                $this->logger->log_plugin_action(RISEUP_ACTION_ENABLE, $slug, RISEUP_STATUS_FAILED, array(
                    'error' => $error_msg,
                ));

                return $this->error_response(
                    RISEUP_MSG_ACTIVATION_FAILED . ': ' . $error_msg,
                    RISEUP_HTTP_SERVER_ERROR
                );
            }
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'Exception during plugin activation');
            $this->logger->log_plugin_action(RISEUP_ACTION_ENABLE, $slug, RISEUP_STATUS_FAILED, array(
                'exception' => $e->getMessage(),
            ));
            return $this->error_response(
                'Exception during activation: ' . $e->getMessage(),
                RISEUP_HTTP_SERVER_ERROR,
                $e
            );
        }

        // Step 5: Log success
        try {
            $this->logger->log_plugin_action(RISEUP_ACTION_ENABLE, $slug, RISEUP_STATUS_SUCCESS);
        } catch (Throwable $e) {
            $this->file_logger->warn('Failed to log activation success', array('error' => $e->getMessage()));
        }

        $this->file_logger->info('Plugin activated successfully', array('slug' => $slug));

        return RiseupEnvelopeBuilder::success()
            ->setRequestedAt('/' . RISEUP_API_FULL_NAMESPACE . '/' . RISEUP_ENDPOINT_PLUGIN_ENABLE)
            ->setSingleResult(array(
                'plugin_slug' => $slug,
                'activated'   => true,
            ))
            ->toResponse();
    }

    /**
     * Handle disable (deactivate) plugin request.
     * Granular try-catch for detailed error reporting.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_disable_plugin($request) {
        // Read slug from JSON body (fixed endpoint design)
        $body = $request->get_json_params();
        $slug = isset($body['plugin']) ? sanitize_text_field($body['plugin']) : $request->get_param('slug');
        if (RiseupBooleanHelpers::is_empty($slug)) {
            return $this->error_response('Plugin slug is required in JSON body', RISEUP_HTTP_BAD_REQUEST);
        }
        $this->file_logger->info('Disable plugin endpoint called', array('slug' => $slug));

        // Step 1: Load plugin functions
        try {
            if (RiseupBooleanHelpers::is_func_missing('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'Failed to load plugin functions');
            return $this->error_response(
                'Failed to load WordPress plugin functions: ' . $e->getMessage(),
                RISEUP_HTTP_SERVER_ERROR,
                $e
            );
        }

        // Step 2: Find the plugin file
        $plugin_file = null;
        try {
            $plugin_file = $this->find_plugin_file($slug);
            if (RiseupBooleanHelpers::is_falsy($plugin_file)) {
                $this->file_logger->warn('Plugin not found', array('slug' => $slug));
                return $this->error_response(
                    RISEUP_MSG_PLUGIN_NOT_FOUND . ': ' . $slug,
                    RISEUP_HTTP_NOT_FOUND
                );
            }
            $this->file_logger->debug('Plugin file found', array('plugin_file' => $plugin_file));
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'Failed to find plugin file');
            return $this->error_response(
                'Failed to locate plugin: ' . $e->getMessage(),
                RISEUP_HTTP_SERVER_ERROR,
                $e
            );
        }

        // Step 3: Check if already inactive
        try {
            if (RiseupBooleanHelpers::is_falsy(is_plugin_active($plugin_file))) {
                $this->file_logger->info('Plugin already inactive', array('slug' => $slug));
                return RiseupEnvelopeBuilder::success('Plugin was already inactive')
                    ->setRequestedAt('/' . RISEUP_API_FULL_NAMESPACE . '/' . RISEUP_ENDPOINT_PLUGIN_DISABLE)
                    ->setSingleResult(array(
                        'plugin_slug'  => $slug,
                        'deactivated'  => true,
                    ))
                    ->toResponse();
            }
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'Failed to check plugin status');
            return $this->error_response(
                'Failed to check plugin status: ' . $e->getMessage(),
                RISEUP_HTTP_SERVER_ERROR,
                $e
            );
        }

        // Step 4: Deactivate the plugin
        try {
            deactivate_plugins($plugin_file);
            $this->file_logger->debug('deactivate_plugins called', array('plugin_file' => $plugin_file));
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'Exception during plugin deactivation');
            $this->logger->log_plugin_action(RISEUP_ACTION_DISABLE, $slug, RISEUP_STATUS_FAILED, array(
                'exception' => $e->getMessage(),
            ));
            return $this->error_response(
                'Exception during deactivation: ' . $e->getMessage(),
                RISEUP_HTTP_SERVER_ERROR,
                $e
            );
        }

        // Step 5: Verify deactivation
        try {
            if (is_plugin_active($plugin_file)) {
                $this->file_logger->warn('Plugin still active after deactivation attempt', array('slug' => $slug));
                $this->logger->log_plugin_action(RISEUP_ACTION_DISABLE, $slug, RISEUP_STATUS_FAILED, array(
                    'error' => 'Plugin remained active after deactivation',
                ));
                return $this->error_response(
                    RISEUP_MSG_DEACTIVATION_FAILED . ': Plugin remained active',
                    RISEUP_HTTP_SERVER_ERROR
                );
            }
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'Failed to verify deactivation');
            return $this->error_response(
                'Failed to verify deactivation: ' . $e->getMessage(),
                RISEUP_HTTP_SERVER_ERROR,
                $e
            );
        }

        // Step 6: Log success
        try {
            $this->logger->log_plugin_action(RISEUP_ACTION_DISABLE, $slug, RISEUP_STATUS_SUCCESS);
        } catch (Throwable $e) {
            $this->file_logger->warn('Failed to log deactivation success', array('error' => $e->getMessage()));
        }

        $this->file_logger->info('Plugin deactivated successfully', array('slug' => $slug));

        return RiseupEnvelopeBuilder::success()
            ->setRequestedAt('/' . RISEUP_API_FULL_NAMESPACE . '/' . RISEUP_ENDPOINT_PLUGIN_DISABLE)
            ->setSingleResult(array(
                'plugin_slug'  => $slug,
                'deactivated'  => true,
            ))
            ->toResponse();
    }

    /**
     * Handle delete plugin request.
     * Granular try-catch for detailed error reporting.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_delete_plugin($request) {
        // Read slug from JSON body (fixed endpoint design)
        $body = $request->get_json_params();
        $slug = isset($body['plugin']) ? sanitize_text_field($body['plugin']) : $request->get_param('slug');
        if (RiseupBooleanHelpers::is_empty($slug)) {
            return $this->error_response('Plugin slug is required in JSON body', RISEUP_HTTP_BAD_REQUEST);
        }
        $this->file_logger->info('Delete plugin endpoint called', array('slug' => $slug));

        // Step 1: Load plugin functions
        try {
            if (RiseupBooleanHelpers::is_func_missing('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            if (RiseupBooleanHelpers::is_func_missing('delete_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'Failed to load plugin functions');
            return $this->error_response(
                'Failed to load WordPress plugin functions: ' . $e->getMessage(),
                RISEUP_HTTP_SERVER_ERROR,
                $e
            );
        }

        // Step 2: Find the plugin file
        $plugin_file = null;
        try {
            $plugin_file = $this->find_plugin_file($slug);
            if (RiseupBooleanHelpers::is_falsy($plugin_file)) {
                $this->file_logger->warn('Plugin not found', array('slug' => $slug));
                return $this->error_response(
                    RISEUP_MSG_PLUGIN_NOT_FOUND . ': ' . $slug,
                    RISEUP_HTTP_NOT_FOUND
                );
            }
            $this->file_logger->debug('Plugin file found', array('plugin_file' => $plugin_file));
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'Failed to find plugin file');
            return $this->error_response(
                'Failed to locate plugin: ' . $e->getMessage(),
                RISEUP_HTTP_SERVER_ERROR,
                $e
            );
        }

        // Step 3: Deactivate if active
        try {
            if (is_plugin_active($plugin_file)) {
                $this->file_logger->debug('Deactivating plugin before deletion', array('plugin_file' => $plugin_file));
                deactivate_plugins($plugin_file);
            }
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'Failed to deactivate plugin before deletion');
            return $this->error_response(
                'Failed to deactivate plugin before deletion: ' . $e->getMessage(),
                RISEUP_HTTP_SERVER_ERROR,
                $e
            );
        }

        // Step 4: Delete the plugin
        try {
            $result = delete_plugins(array($plugin_file));
            
            if (is_wp_error($result)) {
                $error_msg = $result->get_error_message();
                $this->file_logger->warn('Plugin deletion failed', array(
                    'slug'        => $slug,
                    'plugin_file' => $plugin_file,
                    'error'       => $error_msg,
                ));
                
                $this->logger->log_plugin_action(RISEUP_ACTION_DELETE, $slug, RISEUP_STATUS_FAILED, array(
                    'error' => $error_msg,
                ));

                return $this->error_response(
                    RISEUP_MSG_DELETE_FAILED . ': ' . $error_msg,
                    RISEUP_HTTP_SERVER_ERROR
                );
            }

            if ($result === false) {
                $this->file_logger->warn('Plugin deletion returned false', array('slug' => $slug));
                $this->logger->log_plugin_action(RISEUP_ACTION_DELETE, $slug, RISEUP_STATUS_FAILED, array(
                    'error' => 'delete_plugins returned false',
                ));

                return $this->error_response(
                    RISEUP_MSG_DELETE_FAILED . ': Unknown error',
                    RISEUP_HTTP_SERVER_ERROR
                );
            }
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'Exception during plugin deletion');
            $this->logger->log_plugin_action(RISEUP_ACTION_DELETE, $slug, RISEUP_STATUS_FAILED, array(
                'exception' => $e->getMessage(),
            ));
            return $this->error_response(
                'Exception during deletion: ' . $e->getMessage(),
                RISEUP_HTTP_SERVER_ERROR,
                $e
            );
        }

        // Step 5: Log success
        try {
            $this->logger->log_plugin_action(RISEUP_ACTION_DELETE, $slug, RISEUP_STATUS_SUCCESS);
        } catch (Throwable $e) {
            $this->file_logger->warn('Failed to log deletion success', array('error' => $e->getMessage()));
        }

        $this->file_logger->info('Plugin deleted successfully', array('slug' => $slug));

        return RiseupEnvelopeBuilder::success()
            ->setRequestedAt('/' . RISEUP_API_FULL_NAMESPACE . '/' . RISEUP_ENDPOINT_PLUGIN_DELETE)
            ->setSingleResult(array(
                'plugin_slug' => $slug,
                'deleted'     => true,
            ))
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
                RISEUP_HTTP_SERVER_ERROR,
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
            $settings      = Riseup_Admin::get_settings();
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
                'version'  => RISEUP_VERSION,
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

            $db = Riseup_Database::get_instance();
            $pdo = $db->get_pdo();

            if (!$pdo) {
                return $this->error_response(
                    'Database not available (PDO/pdo_sqlite extension may not be installed)',
                    RISEUP_HTTP_SERVER_ERROR
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

        if (!file_exists($file_path) || !is_readable($file_path)) {
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
        if (RiseupBooleanHelpers::is_empty($short)) {
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
                if (RiseupBooleanHelpers::is_truthy($php_files)) {
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
     * @param Riseup_Upload_Ignore $ignore   Upload ignore parser.
     *
     * @return void
     */
    private function add_dir_to_zip($zip, $src_dir, $zip_dir, $ignore) {
        $dir = opendir($src_dir);
        if (RiseupBooleanHelpers::is_falsy($dir)) {
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
            
            $manager = Riseup_Agent_Manager::get_instance();
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
            
            $manager = Riseup_Agent_Manager::get_instance();
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
            
            $manager = Riseup_Agent_Manager::get_instance();
            $agent = $manager->get_agent($id, false);
            
            if (RiseupBooleanHelpers::is_falsy($agent)) {
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
            
            $manager = Riseup_Agent_Manager::get_instance();
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
            
            $manager = Riseup_Agent_Manager::get_instance();
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
            
            $manager = Riseup_Agent_Manager::get_instance();
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
            if (RiseupBooleanHelpers::is_falsy(in_array($action, $allowed_actions))) {
                return $this->error_response('Invalid action. Allowed: ' . implode(', ', $allowed_actions), 400);
            }
            
            if (RiseupBooleanHelpers::is_empty($slug)) {
                return $this->error_response('Plugin slug is required', 400);
            }
            
            $manager = Riseup_Agent_Manager::get_instance();
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
            
            $manager = Riseup_Agent_Manager::get_instance();
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
     * Handle creating/scheduling a snapshot.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_create_snapshot($request) {
        return $this->safe_execute(function() use ($request) {
            $body = $request->get_json_params();

            $manager = RiseupSnapshotManager::getInstance($this->file_logger, $this->db);
            $settings = $manager->getSettings();

            // Route to full orchestrator when mode is per_table
            if (($settings['mode'] ?? 'per_table') === 'per_table') {
                $orchestrator = RiseupSnapshotOrchestrator::getInstance($this->file_logger, $this->db, $manager);
                $result = $orchestrator->executeFullBackup(array(
                    'title'            => $body['title'] ?? null,
                    'scope'            => isset($body['scope']) ? sanitize_key($body['scope']) : null,
                    'include_plugins'  => $body['include_plugins'] ?? null,
                    'plugin_selection' => $body['plugin_selection'] ?? null,
                    'compression'      => $body['compression'] ?? null,
                ));
                $status_code = $result['success'] ? 201 : 500;
                return new WP_REST_Response($result, $status_code);
            }

            // Legacy single-db mode via provider
            $options = array(
                'scope'   => isset($body['scope']) ? sanitize_key($body['scope']) : RISEUP_SNAPSHOT_SCOPE_WORDPRESS,
                'trigger' => RISEUP_SNAPSHOT_TRIGGER_API,
                'tables'  => isset($body['tables']) ? array_map('sanitize_text_field', (array) $body['tables']) : array(),
            );

            $this->file_logger->info('Creating snapshot via API (legacy mode)', array('scope' => $options['scope']));

            $result = $manager->createSnapshot($options);

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
            if (RiseupBooleanHelpers::is_falsy($provider)) {
                return $this->error_response('No snapshot provider available', 500);
            }

            $snapshot = $provider->getSnapshot($id);
            if (RiseupBooleanHelpers::is_falsy($snapshot)) {
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

            $manager = RiseupSnapshotManager::getInstance($this->file_logger, $this->db);
            $result = $manager->deleteSnapshot($id);

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
                'confirm'            => RiseupBooleanHelpers::has_content($body['confirm']),
                'create_backup'      => isset($body['createBackup']) ? (bool) $body['createBackup'] : true,
                'require_backup'     => RiseupBooleanHelpers::has_content($body['requireBackup']),
                'mode'               => isset($body['mode']) ? sanitize_key($body['mode']) : 'full',
                'tables'             => isset($body['tables']) ? array_map('sanitize_text_field', (array) $body['tables']) : array(),
                'strict'             => RiseupBooleanHelpers::has_content($body['strict']),
                'apply_incrementals' => isset($body['applyIncrementals']) ? (bool) $body['applyIncrementals'] : true,
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
                    $status_code = $result['success'] ? 200 : 400;
                    return new WP_REST_Response($result, $status_code);
                }
            }

            // Fallback to legacy single-file restore
            $result = $manager->restoreSnapshot($id, $options);

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
        if (RiseupBooleanHelpers::has_content($dir) && is_dir($dir)) {
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
        if (RiseupBooleanHelpers::has_content($dir) && is_dir($dir)) {
            return $dir;
        }
        // Try deriving from filepath (strip filename)
        if (RiseupBooleanHelpers::has_content($filepath) && file_exists(dirname($filepath) . '/a-root.db')) {
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

            $manager = RiseupSnapshotManager::getInstance($this->file_logger, $this->db);
            $result = $manager->exportSnapshot($id);

            if (RiseupBooleanHelpers::is_falsy($result['success'])) {
                return $this->error_response($result['error'], 400);
            }

            // Stream the ZIP file as download
            $filepath = $result['filepath'];
            if (RiseupBooleanHelpers::is_file_missing($filepath)) {
                return $this->error_response('Export file not found', 500);
            }

            // Return file info for client-side download
            return new WP_REST_Response(array(
                'success'  => true,
                'filename' => $result['filename'],
                'size'     => $result['size'],
                'downloadUrl' => rest_url(RISEUP_API_FULL_NAMESPACE . '/' . RISEUP_ENDPOINT_SNAPSHOTS . '/' . $id . '/download'),
            ), 200);
        }, 'export_snapshot');
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

            if (RiseupBooleanHelpers::is_empty($files['file']['tmp_name'])) {
                return $this->error_response('No file uploaded', 400);
            }

            $tmp_file = $files['file']['tmp_name'];
            $this->file_logger->info('Importing snapshot from uploaded ZIP', array(
                'originalName' => $files['file']['name'],
                'size'         => $files['file']['size'],
            ));

            // Use enhanced import engine that handles both legacy and per-table formats
            $manager = RiseupSnapshotManager::getInstance($this->file_logger, $this->db);
            $importer = new RiseupSnapshotImport($this->file_logger, $this->db, $manager);
            $result = $importer->import($tmp_file);

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

            $manager = RiseupSnapshotManager::getInstance($this->file_logger, $this->db);
            $orchestrator = RiseupSnapshotOrchestrator::getInstance($this->file_logger, $this->db, $manager);

            $result = $orchestrator->executeFullBackup(array(
                'title'            => $body['title'] ?? null,
                'scope'            => $body['scope'] ?? null,
                'include_plugins'  => $body['include_plugins'] ?? null,
                'plugin_selection' => $body['plugin_selection'] ?? null,
                'compression'      => $body['compression'] ?? null,
            ));

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

            $manager = RiseupSnapshotManager::getInstance($this->file_logger, $this->db);
            $rootDb = RiseupRootDb::getInstance($this->file_logger, RiseupDependencyAnalyzer::getInstance($this->file_logger));
            $incremental = RiseupIncrementalBackup::getInstance($this->file_logger, $this->db, $rootDb);

            // Determine master directory
            $master_dir = $body['master_dir'] ?? null;
            if (RiseupBooleanHelpers::is_falsy($master_dir)) {
                // Auto-detect latest full backup
                $master_dir = $incremental->findLatestMasterSnapshot();
            }

            if (RiseupBooleanHelpers::is_falsy($master_dir) || RiseupBooleanHelpers::is_dir_missing($master_dir)) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'error'   => 'No master (full) snapshot found. Create a full backup first.',
                ), 400);
            }

            $result = $incremental->execute($master_dir, array(
                'title' => $body['title'] ?? null,
            ));

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

            $cleanup = RiseupSnapshotCleanup::getInstance($this->file_logger, $this->db);

            $result = $cleanup->execute(array(
                'retention_type'  => $body['retention_type'] ?? null,
                'retention_days'  => $body['retention_days'] ?? null,
                'retention_count' => $body['retention_count'] ?? null,
                'dry_run'         => $body['dry_run'] ?? false,
            ));

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

        // Load boolean helpers for path utils
        $helpers_file = __DIR__ . '/includes/class-boolean-helpers.php';
        if (file_exists($helpers_file)) {
            require_once $helpers_file;
        }

        // Load init helpers for directory creation
        $init_file = __DIR__ . '/includes/class-init-helpers.php';
        if (file_exists($init_file)) {
            require_once $init_file;
        }

        // Resolve base directory and create logs folder
        $upload_dir = wp_upload_dir();
        if (!isset($upload_dir['error']) || !$upload_dir['error']) {
            $base_dir = $upload_dir['basedir'] . '/' . RISEUP_UPLOADS_SUBDIR;
            $logs_dir = $base_dir . '/' . RISEUP_LOGS_SUBDIR;

            // Create base + logs directories
            if (!is_dir($base_dir)) {
                wp_mkdir_p($base_dir);
            }
            if (!is_dir($logs_dir)) {
                wp_mkdir_p($logs_dir);
            }

            // Write activation marker to log file
            $log_file = $logs_dir . '/' . RISEUP_LOG_FILENAME;
            $timestamp = gmdate('Y-m-d\TH:i:s') . 'Z';
            $version = defined('RISEUP_VERSION') ? RISEUP_VERSION : 'unknown';
            $entry = sprintf(
                "[%s] [INFO] Plugin activated (activation hook) (riseup-asia-uploader.php:0) {\"version\":\"%s\",\"php\":\"%s\",\"wp\":\"%s\"}\n",
                $timestamp,
                $version,
                phpversion(),
                get_bloginfo('version')
            );
            @file_put_contents($log_file, $entry, FILE_APPEND | LOCK_EX);

            // Also write to error log for visibility
            $error_file = $logs_dir . '/' . RISEUP_ERROR_LOG_FILENAME;
            @file_put_contents($error_file, sprintf(
                "[%s] [INFO] Plugin activated — error log initialized (v%s)\n",
                $timestamp,
                $version
            ), FILE_APPEND | LOCK_EX);

            // Initialize stacktrace.txt
            $stacktrace_file = $logs_dir . '/' . RISEUP_STACKTRACE_FILENAME;
            if (!file_exists($stacktrace_file)) {
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
                if (!file_exists($htaccess)) {
                    @file_put_contents($htaccess, "# Riseup Asia Uploader - Security\nOrder Deny,Allow\nDeny from all\n");
                }
                $index = $base_dir . '/index.php';
                if (!file_exists($index)) {
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
    Riseup_Asia::get_instance();
    
    // Initialize admin pages (only in admin context)
    if (is_admin()) {
        Riseup_Admin::get_instance();
    }
}

// Initialize on plugins_loaded hook.
add_action('plugins_loaded', 'riseup_asia_init');
