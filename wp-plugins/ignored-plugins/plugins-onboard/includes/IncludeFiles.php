<?php
/**
 * Plugins Onboard - Include Files Utility
 *
 * Centralized file loading with enum-like constants, error tracking,
 * and stack trace logging. Replaces raw require_once / include_once
 * calls with a structured, debuggable approach.
 *
 * Usage:
 *   OnboardIncludeFiles::load(OnboardIncludeFiles::DATABASE);
 *   OnboardIncludeFiles::load(OnboardIncludeFiles::OAUTH, true); // include instead of require
 *
 * @package PluginsOnboard
 * @since   1.0.9
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class OnboardIncludeFiles
 *
 * Provides enum-like constants for all includable files and a single
 * load() method that handles require/include with full error tracking
 * and stack trace capture on failure.
 */
class OnboardIncludeFiles {

    // ─── File Enum Constants ────────────────────────────────────────
    // Each constant maps to the relative path from the plugin root.

    /** Core infrastructure */
    const CONSTANTS       = 'includes/Constants.php';
    const LOGGER          = 'includes/Logger.php';
    const PATHS           = 'includes/Paths.php';
    const BOOLEAN_HELPERS = 'includes/BooleanHelpers.php';
    const INIT_HELPERS    = 'includes/InitHelpers.php';
    const CONFIG          = 'includes/Config.php';

    /** Data layer */
    const DATABASE         = 'includes/Database.php';
    const TOKEN_ENCRYPTION = 'includes/TokenEncryption.php';

    /** Security */
    const RATE_LIMITER   = 'includes/RateLimiter.php';
    const AUDIT_LOGGER   = 'includes/AuditLogger.php';
    const OAUTH          = 'includes/OAuth.php';
    const MUTATION_TOKEN = 'includes/MutationToken.php';
    const IP_WHITELIST   = 'includes/IpWhitelist.php';
    const SECURITY_UTILS = 'includes/SecurityUtils.php';

    /** Feature modules */
    const SNAPSHOT         = 'includes/Snapshot.php';
    const BACKUP_MANAGER   = 'includes/BackupManager.php';
    const PLUGIN_MANAGER   = 'includes/PluginManager.php';
    const UPLOAD_VALIDATOR = 'includes/UploadValidator.php';
    const DEBUG_MAINTENANCE = 'includes/DebugMaintenance.php';
    const CLEANUP          = 'includes/Cleanup.php';

    /** API & Admin */
    const API      = 'api/Api.php';
    const API_PERMISSIONS = 'api/Permissions.php';
    const ADMIN_UI = 'admin/AdminUi.php';

    // ─── Tracking ───────────────────────────────────────────────────

    /**
     * Load results for diagnostics.
     * Each entry: ['file' => string, 'success' => bool, 'error' => string|null, 'mode' => string]
     *
     * @var array
     */
    private static $results = array();

    // ─── Public API ─────────────────────────────────────────────────

    /**
     * Load a file by its enum constant.
     *
     * @param string $fileConstant One of the class constants (e.g., OnboardIncludeFiles::DATABASE).
     * @param bool   $isInclude    If true, use include_once instead of require_once.
     *                             Default false (require_once).
     * @return bool True if file loaded successfully, false on failure.
     */
    public static function load($fileConstant, $isInclude = false) {
        $filepath = ONBOARD_PLUGIN_DIR . $fileConstant;
        $mode     = $isInclude ? 'include' : 'require';

        // Check file existence first
        if (OnboardBooleanHelpers::isFileMissing($filepath)) {
            $trace = self::captureStackTrace();
            $errorMsg = "File not found: {$fileConstant} (resolved: {$filepath})";

            self::$results[] = array(
                'file'    => $fileConstant,
                'success' => false,
                'error'   => $errorMsg,
                'mode'    => $mode,
            );

            // Log error with stack trace
            OnboardLogger::error($errorMsg, null, array(
                'stackTrace' => $trace,
                'mode'       => $mode,
                'constant'   => $fileConstant,
            ));

            error_log("Plugins Onboard [{$mode}]: {$errorMsg}\nStack trace:\n{$trace}");

            return false;
        }

        try {
            if ($isInclude) {
                include_once $filepath;
            } else {
                require_once $filepath;
            }

            self::$results[] = array(
                'file'    => $fileConstant,
                'success' => true,
                'error'   => null,
                'mode'    => $mode,
            );

            OnboardLogger::debug("✓ Loaded [{$mode}]: {$fileConstant}");
            return true;

        } catch (Throwable $e) {
            $trace = $e->getTraceAsString();
            $errorMsg = "Failed to load {$fileConstant}: {$e->getMessage()}";

            self::$results[] = array(
                'file'    => $fileConstant,
                'success' => false,
                'error'   => $errorMsg,
                'mode'    => $mode,
            );

            OnboardLogger::error($errorMsg, $e, array(
                'mode'     => $mode,
                'constant' => $fileConstant,
            ));

            error_log("Plugins Onboard [{$mode}]: {$errorMsg}\nStack trace:\n{$trace}");

            return false;
        }
    }

    /**
     * Load multiple files from an array of enum constants.
     *
     * @param array $constants Array of class constants.
     * @param bool  $isInclude If true, use include_once for all files.
     * @return int Number of files that failed to load.
     */
    public static function loadMany($constants, $isInclude = false) {
        $failures = 0;
        foreach ($constants as $constant) {
            if (OnboardBooleanHelpers::isFalsy(self::load($constant, $isInclude))) {
                $failures++;
            }
        }
        return $failures;
    }

    // ─── Diagnostics ────────────────────────────────────────────────

    /**
     * Get all load results.
     *
     * @return array
     */
    public static function getResults() {
        return self::$results;
    }

    /**
     * Get only failed load results.
     *
     * @return array
     */
    public static function getFailures() {
        return array_filter(self::$results, function ($r) {
            return OnboardBooleanHelpers::isFalsy($r['success']);
        });
    }

    /**
     * Check if all files loaded successfully.
     *
     * @return bool
     */
    public static function allLoaded() {
        return empty(self::getFailures());
    }

    /**
     * Log a summary of load results.
     *
     * @return void
     */
    public static function logSummary() {
        $total  = count(self::$results);
        $failed = count(self::getFailures());

        if ($failed > 0) {
            $failureDetails = array_map(function ($r) {
                return "[{$r['mode']}] {$r['file']}: {$r['error']}";
            }, self::getFailures());

            OnboardLogger::error("Dependency loading: {$failed}/{$total} failed", null, array(
                'failures' => $failureDetails,
            ));
        } else {
            OnboardLogger::debug("All dependencies loaded: {$total}/{$total} successful");
        }
    }

    /**
     * Reset tracked state (for testing).
     *
     * @return void
     */
    public static function reset() {
        self::$results = array();
    }

    // ─── Internal ───────────────────────────────────────────────────

    /**
     * Capture a stack trace from the current call site.
     *
     * @return string Formatted stack trace string.
     */
    private static function captureStackTrace() {
        $exception = new \Exception();
        $trace = $exception->getTraceAsString();

        // Remove the first frame (this method itself)
        $lines = explode("\n", $trace);
        array_shift($lines);

        return implode("\n", $lines);
    }
}
