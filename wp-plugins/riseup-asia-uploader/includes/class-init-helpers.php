<?php
/**
 * Riseup Asia Uploader - Initialization Helpers
 *
 * Provides idempotent helpers for directory creation, security file setup,
 * database initialization, and structured component startup tracking.
 * Ported from OnboardInitHelpers for consistency across plugins.
 *
 * PHP class naming follows PascalCase convention without underscores.
 *
 * @package RiseupAsiaUploader
 * @since   1.19.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class RiseupInitHelpers
 *
 * Centralized initialization utilities for idempotent setup operations.
 * All methods are static and safe to call multiple times.
 */
class RiseupInitHelpers {

    // =========================================================================
    // TRACKING STATE
    // =========================================================================

    /**
     * Registry of directories already ensured this request.
     * Prevents redundant filesystem checks within the same PHP process.
     *
     * @var array<string, bool>
     */
    private static $ensured_dirs = array();

    /**
     * Whether the PDO-unavailable warning has already been logged this request.
     *
     * @var bool
     */
    private static $pdo_unavailable_warned = false;

    /**
     * Component startup results for structured dependency loading.
     * Each entry: array('name' => string, 'success' => bool, 'error' => string|null, 'time_ms' => float)
     *
     * @var array
     */
    private static $startup_results = array();

    // =========================================================================
    // IDEMPOTENT DIRECTORY SETUP
    // =========================================================================

    /**
     * Ensure a directory exists with optional security files.
     *
     * This is idempotent: calling it multiple times with the same path
     * is a no-op after the first successful call within the same request.
     *
     * @param string $path   Directory path to ensure.
     * @param bool   $secure Whether to add .htaccess and index.php security files.
     * @return bool True if directory exists (or was created), false on failure.
     */
    public static function ensureDir($path, $secure = false) {
        // Normalize path for consistent cache keys
        $normalized = str_replace('\\', '/', $path);

        // Already ensured this request? Skip filesystem check.
        if (isset(self::$ensured_dirs[$normalized])) {
            return self::$ensured_dirs[$normalized];
        }

        // Delegate to RiseupPathUtils if available (preferred)
        if (RiseupBooleanHelpers::is_class_exists('RiseupPathUtils')) {
            $result = RiseupPathUtils::ensureDir($path, $secure);
            self::$ensured_dirs[$normalized] = $result;
            return $result;
        }

        // Fallback: native PHP (for very early loading before PathUtils)
        $result = self::ensureDirNative($path, $secure);
        self::$ensured_dirs[$normalized] = $result;
        return $result;
    }

    /**
     * Native PHP directory creation fallback.
     * Used when RiseupPathUtils is not yet loaded.
     *
     * @param string $path   Directory path.
     * @param bool   $secure Add security files.
     * @return bool True on success.
     */
    public static function ensureDirNative($path, $secure = false) {
        if (empty($path)) {
            return false;
        }

        if (RiseupBooleanHelpers::is_dir_missing($path)) {
            if (!@mkdir($path, 0755, true)) {
                // Try wp_mkdir_p as fallback
                if (function_exists('wp_mkdir_p') && !wp_mkdir_p($path)) {
                    return false;
                }
            }
        }

        if ($secure) {
            self::addSecurityFiles($path);
        }

        return true;
    }

    /**
     * Add .htaccess and index.php security files to a directory.
     *
     * Idempotent: only writes files if they don't already exist.
     *
     * @param string $path Directory path.
     * @return bool True if all files exist or were created.
     */
    public static function addSecurityFiles($path) {
        $success = true;

        // .htaccess
        $htaccess = $path . '/.htaccess';
        if (RiseupBooleanHelpers::is_file_missing($htaccess)) {
            if (@file_put_contents($htaccess, "# Riseup Asia Uploader - Security\nOrder Deny,Allow\nDeny from all\n") === false) {
                $success = false;
            }
        }

        // index.php
        $index = $path . '/index.php';
        if (RiseupBooleanHelpers::is_file_missing($index)) {
            if (@file_put_contents($index, "<?php\n// Silence is golden.\n") === false) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Ensure a directory tree (base + subdirectory) exists with security.
     *
     * Convenience method that ensures the base directory first, then the subdirectory.
     *
     * @param string $base_dir   Base directory path.
     * @param string $sub_dir    Subdirectory name/path.
     * @param bool   $secure     Add security files to base directory.
     * @return string|false Full path to subdirectory on success, false on failure.
     */
    public static function ensureSubDir($base_dir, $sub_dir, $secure = false) {
        // Ensure base
        if (!self::ensureDir($base_dir, $secure)) {
            return false;
        }

        // Build and ensure sub path
        $full_path = rtrim($base_dir, '/') . '/' . ltrim($sub_dir, '/');
        if (!self::ensureDir($full_path, false)) {
            return false;
        }

        return $full_path;
    }

    /**
     * Resolve the plugin's base uploads directory.
     *
     * Uses wp_upload_dir() if available, falls back to plugin directory.
     *
     * @return string Base directory path.
     */
    public static function resolveBaseDir() {
        if (RiseupBooleanHelpers::is_func_missing('wp_upload_dir')) {
            return dirname(__DIR__) . '/data';
        }

        $upload_dir = wp_upload_dir();
        if (isset($upload_dir['error']) && $upload_dir['error']) {
            return dirname(__DIR__) . '/data';
        }

        return $upload_dir['basedir'] . '/' . RISEUP_UPLOADS_SUBDIR;
    }

    // =========================================================================
    // DATABASE INITIALIZATION
    // =========================================================================

    /**
     * Initialize a PDO SQLite connection with standard settings.
     *
     * Checks PDO/pdo_sqlite availability, creates connection, enables WAL mode,
     * and sets standard attributes. Returns null on failure with error details.
     *
     * @param string              $db_path     Path to SQLite database file.
     * @param Riseup_File_Logger  $logger      Logger for diagnostics.
     * @return PDO|null PDO instance on success, null on failure.
     */
    public static function initSqliteConnection($db_path, $logger) {
        // Check PDO availability (warn only once per request to avoid log spam)
        if (RiseupBooleanHelpers::is_class_missing('PDO')) {
            if (!self::$pdo_unavailable_warned) {
                $logger->warn('[INIT] PDO class not found - PHP PDO extension not installed. Database features will be skipped.');
                self::$pdo_unavailable_warned = true;
            }
            return null;
        }

        // Check SQLite driver (warn only once)
        if (RiseupBooleanHelpers::is_extension_missing('pdo_sqlite')) {
            if (!self::$pdo_unavailable_warned) {
                $logger->warn('[INIT] PDO SQLite extension not loaded. Database features will be skipped.');
                self::$pdo_unavailable_warned = true;
            }
            return null;
        }

        try {
            $pdo = new PDO('sqlite:' . $db_path);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // Enable WAL mode for better concurrency
            if (defined('RISEUP_DB_WAL_MODE') && RISEUP_DB_WAL_MODE) {
                $pdo->exec('PRAGMA journal_mode = WAL');
            }

            // Enable incremental auto-vacuum
            $pdo->exec('PRAGMA auto_vacuum = INCREMENTAL');

            $logger->info('[INIT] SQLite connection established', array('path' => $db_path));
            return $pdo;

        } catch (PDOException $e) {
            $logger->error('[INIT] SQLite connection failed: ' . $e->getMessage(), array(
                'path' => $db_path,
                'code' => $e->getCode(),
            ));
            return null;
        }
    }

    // =========================================================================
    // COMPONENT STARTUP TRACKING
    // =========================================================================

    /**
     * Execute a component initialization with timing and error tracking.
     *
     * Wraps a callable in try/catch, records timing, and stores the result
     * for later inspection. The callable should return the initialized component
     * or throw on failure.
     *
     * @param string   $name     Component name (e.g., 'FileLogger', 'Database').
     * @param callable $init_fn  Initialization callable. Receives no arguments.
     * @return mixed The return value of $init_fn, or null on failure.
     */
    public static function initComponent($name, $init_fn) {
        $start = microtime(true);
        $result = null;
        $error = null;

        try {
            $result = $init_fn();
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        $elapsed_ms = round((microtime(true) - $start) * 1000, 2);

        self::$startup_results[] = array(
            'name'    => $name,
            'success' => $error === null,
            'error'   => $error,
            'time_ms' => $elapsed_ms,
        );

        return $result;
    }

    /**
     * Get all component startup results.
     *
     * @return array List of startup result records.
     */
    public static function getStartupResults() {
        return self::$startup_results;
    }

    /**
     * Get failed component startups only.
     *
     * @return array List of startup records where success === false.
     */
    public static function getFailedStartups() {
        return array_filter(self::$startup_results, function ($r) {
            return !$r['success'];
        });
    }

    /**
     * Check if all components started successfully.
     *
     * @return bool True if no failures.
     */
    public static function allStartupsSucceeded() {
        return empty(self::getFailedStartups());
    }

    /**
     * Get total startup time in milliseconds.
     *
     * @return float Total milliseconds.
     */
    public static function getTotalStartupTime() {
        $total = 0;
        foreach (self::$startup_results as $r) {
            $total += $r['time_ms'];
        }
        return round($total, 2);
    }

    /**
     * Log a summary of all startup results to the provided logger.
     *
     * @param Riseup_File_Logger $logger Logger instance.
     * @return void
     */
    public static function logStartupSummary($logger) {
        $total = count(self::$startup_results);
        $failed = count(self::getFailedStartups());
        $time = self::getTotalStartupTime();

        if ($failed > 0) {
            $logger->warn('[INIT] Startup complete with failures', array(
                'total'      => $total,
                'failed'     => $failed,
                'time_ms'    => $time,
                'failures'   => array_map(function ($r) {
                    return $r['name'] . ': ' . $r['error'];
                }, self::getFailedStartups()),
            ));
        } else {
            $logger->info('[INIT] All components started successfully', array(
                'total'   => $total,
                'time_ms' => $time,
            ));
        }
    }

    /**
     * Reset all tracked state (primarily for testing).
     *
     * @return void
     */
    public static function reset() {
        self::$ensured_dirs = array();
        self::$startup_results = array();
        self::$pdo_unavailable_warned = false;
    }
}
