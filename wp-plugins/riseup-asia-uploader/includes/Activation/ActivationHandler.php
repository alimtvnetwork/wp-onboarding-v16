<?php
/**
 * Riseup Asia Uploader - Activation Handler
 *
 * Handles plugin activation logic: directory creation, log initialization,
 * and security file placement.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Activation hook: ensure log directories and files exist on first activation.
 */
function riseup_asia_activate() {
    try {
        riseup_activate_load_dependencies();
        $dirs = riseup_activate_resolve_dirs();

        if ($dirs === null) {
            return;
        }

        riseup_activate_ensure_dirs($dirs['base'], $dirs['logs']);
        riseup_activate_write_log_files($dirs['logs']);
        riseup_activate_ensure_security($dirs['base']);
    } catch (\Throwable $e) {
        error_log('[Riseup Asia] Activation hook failed: ' . $e->getMessage());
    }
}

/**
 * Load required dependencies for activation context.
 */
function riseup_activate_load_dependencies() {
    $plugin_dir = dirname(__DIR__, 2);

    $constants_file = $plugin_dir . '/includes/constants.php';
    if (file_exists($constants_file)) {
        require_once $constants_file;
    }

    $helpers_file = $plugin_dir . '/includes/Helpers/BooleanHelpers.php';
    if (file_exists($helpers_file)) {
        require_once $helpers_file;
    }
}

/**
 * Resolve base and logs directories from WP uploads.
 *
 * @return array{base: string, logs: string}|null Null if upload dir has error.
 */
function riseup_activate_resolve_dirs() {
    $upload_dir = wp_upload_dir();
    $hasError = isset($upload_dir['error']) && $upload_dir['error'];

    if ($hasError) {
        return null;
    }

    return array(
        'base' => $upload_dir['basedir'] . '/' . UPLOADS_SUBDIR,
        'logs' => $upload_dir['basedir'] . '/' . UPLOADS_SUBDIR . '/' . LOGS_SUBDIR,
    );
}

/**
 * Create base and logs directories if missing.
 *
 * @param string $base_dir Base directory path.
 * @param string $logs_dir Logs directory path.
 */
function riseup_activate_ensure_dirs($base_dir, $logs_dir) {
    if (RiseupBooleanHelpers::is_dir_missing($base_dir)) {
        wp_mkdir_p($base_dir);
    }
    if (RiseupBooleanHelpers::is_dir_missing($logs_dir)) {
        wp_mkdir_p($logs_dir);
    }
}

/**
 * Write initial log file entries on activation.
 *
 * @param string $logs_dir Logs directory path.
 */
function riseup_activate_write_log_files($logs_dir) {
    $timestamp = gmdate('Y-m-d\TH:i:s') . 'Z';
    $version = defined('PLUGIN_VERSION') ? PLUGIN_VERSION : 'unknown';

    $log_file = $logs_dir . '/' . LOG_FILENAME;
    @file_put_contents($log_file, sprintf(
        "[%s] [INFO] Plugin activated (activation hook) (riseup-asia-uploader.php:0) {\"version\":\"%s\",\"php\":\"%s\",\"wp\":\"%s\"}\n",
        $timestamp, $version, phpversion(), get_bloginfo('version')
    ), FILE_APPEND | LOCK_EX);

    $error_file = $logs_dir . '/' . ERROR_LOG_FILENAME;
    @file_put_contents($error_file, sprintf(
        "[%s] [INFO] Plugin activated — error log initialized (v%s)\n",
        $timestamp, $version
    ), FILE_APPEND | LOCK_EX);

    $stacktrace_file = $logs_dir . '/' . STACKTRACE_FILENAME;
    if (RiseupBooleanHelpers::is_file_missing($stacktrace_file)) {
        @file_put_contents($stacktrace_file, sprintf(
            "# Riseup Asia Uploader - Stack Trace Log (initialized %s)\n\n",
            $timestamp
        ));
    }
}

/**
 * Ensure security files (.htaccess, index.php) exist in the base directory.
 *
 * @param string $base_dir Base directory path.
 */
function riseup_activate_ensure_security($base_dir) {
    if (class_exists('RiseupInitHelpers')) {
        RiseupInitHelpers::addSecurityFiles($base_dir);
        return;
    }

    $htaccess = $base_dir . '/.htaccess';
    if (RiseupBooleanHelpers::is_file_missing($htaccess)) {
        @file_put_contents($htaccess, "# Riseup Asia Uploader - Security\nOrder Deny,Allow\nDeny from all\n");
    }

    $index = $base_dir . '/index.php';
    if (RiseupBooleanHelpers::is_file_missing($index)) {
        @file_put_contents($index, "<?php\n// Silence is golden.\n");
    }
}
