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
    const CONSTANTS       = 'includes/constants.php';
    const LOGGER          = 'includes/class-logger.php';
    const PATHS           = 'includes/class-paths.php';
    const BOOLEAN_HELPERS = 'includes/class-boolean-helpers.php';
    const INIT_HELPERS    = 'includes/class-init-helpers.php';
    const CONFIG          = 'includes/class-config.php';

    /** Data layer */
    const DATABASE         = 'includes/class-database.php';
    const TOKEN_ENCRYPTION = 'includes/class-token-encryption.php';

    /** Security */
    const RATE_LIMITER   = 'includes/class-rate-limiter.php';
    const AUDIT_LOGGER   = 'includes/class-audit-logger.php';
    const OAUTH          = 'includes/class-oauth.php';
    const MUTATION_TOKEN = 'includes/class-mutation-token.php';
    const IP_WHITELIST   = 'includes/class-ip-whitelist.php';
    const SECURITY_UTILS = 'includes/security-utils.php';

    /** Feature modules */
    const SNAPSHOT         = 'includes/class-snapshot.php';
    const BACKUP_MANAGER   = 'includes/class-backup-manager.php';
    const PLUGIN_MANAGER   = 'includes/class-plugin-manager.php';
    const UPLOAD_VALIDATOR = 'includes/class-upload-validator.php';
    const DEBUG_MAINTENANCE = 'includes/class-debug-maintenance.php';
    const CLEANUP          = 'includes/class-cleanup.php';

    /** API & Admin */
    const API      = 'api/class-api.php';
    const API_PERMISSIONS = 'api/class-permissions.php';
    const ADMIN_UI = 'admin/class-admin-ui.php';

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
