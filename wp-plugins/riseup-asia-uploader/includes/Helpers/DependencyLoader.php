<?php
/**
 * Riseup Asia Uploader - Dependency Loader
 *
 * Structured file loading with error tracking. Wraps require_once
 * calls in try/catch blocks to capture load failures and provide
 * diagnostic summaries instead of fatal crashes.
 *
 * PHP class naming follows PascalCase convention without underscores.
 *
 * @package RiseupAsiaUploader
 * @since   1.21.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class RiseupDependencyLoader
 *
 * Loads PHP dependency files with structured error tracking.
 * Each file load is wrapped in a try/catch to prevent a single
 * missing or broken file from crashing the entire plugin.
 */
class RiseupDependencyLoader {

    /**
     * Results of each file load attempt.
     * Each entry: array('label' => string, 'file' => string, 'success' => bool, 'error' => string|null)
     *
     * @var array
     */
    private static array $results = array();

    /**
     * Load a single PHP file with error tracking.
     *
     * Uses require_once to load the file. If loading fails (parse error,
     * missing file, etc.), the error is captured and recorded.
     *
     * @param string $label  Human-readable label (e.g., 'FileLogger', 'Database').
     * @param string $path   Absolute path to the PHP file.
     * @return bool True if loaded successfully, false on failure.
     */
    public static function load(string $label, string $path): bool {
        if (RiseupBooleanHelpers::isFileMissing($path)) {
            self::recordResult($label, $path, false, 'File not found: ' . $path);

            return false;
        }

        try {
            require_once $path;
            self::recordResult($label, $path, true, null);

            return true;
        } catch (Throwable $e) {
            self::recordResult($label, $path, false, $e->getMessage());

            return false;
        }
    }

    /** Record a file load result. */
    private static function recordResult(string $label, string $path, bool $success, ?string $error) {
        self::$results[] = array(
            'label'   => $label,
            'file'    => basename($path),
            'success' => $success,
            'error'   => $error,
        );
    }

    /**
     * Load multiple files from a manifest.
     *
     * The manifest is an array of [label, path] pairs.
     * Files are loaded in order. A failure in one file does NOT
     * prevent subsequent files from loading.
     *
     * @param array $manifest Array of [label, absolute_path] pairs.
     * @return int Number of files that failed to load.
     */
    public static function loadManifest(array $manifest): int {
        $failures = 0;
        foreach ($manifest as $entry) {
            $label = $entry[0];
            $path  = $entry[1];
            if (!self::load($label, $path)) {
                $failures++;
            }
        }
        return $failures;
    }

    /**
     * Get all load results.
     *
     * @return array List of result records.
     */
    public static function getResults(): array {
        return self::$results;
    }

    /**
     * Get only failed load results.
     *
     * @return array List of failed result records.
     */
    public static function getFailures(): array {
        return array_filter(self::$results, function (array $r): bool {
            return !$r['success'];
        });
    }

    /**
     * Check if all files loaded successfully.
     *
     * @return bool True if no failures.
     */
    public static function allLoaded(): bool {
        return empty(self::getFailures());
    }

    /**
     * Log a summary of load results to the provided logger.
     *
     * @param RiseupFileLogger $logger Logger instance.
     * @return void
     */
    public static function logSummary(RiseupFileLogger $logger): void {
        $total  = count(self::$results);
        $failed = count(self::getFailures());

        if ($failed > 0) {
            $logger->warn('[DEPS] Dependency loading complete with failures', array(
                'total'    => $total,
                'failed'   => $failed,
                'failures' => array_map(function (array $r): string {
                    return $r['label'] . ' (' . $r['file'] . '): ' . $r['error'];
                }, self::getFailures()),
            ));
        } else {
            $logger->debug('[DEPS] All dependencies loaded', array(
                'total' => $total,
            ));
        }
    }

    /**
     * Reset tracked state (primarily for testing).
     *
     * @return void
     */
    public static function reset(): void {
        self::$results = array();
    }
}
