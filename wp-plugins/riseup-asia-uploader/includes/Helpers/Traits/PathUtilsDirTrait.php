<?php
/**
 * PathUtilsDirTrait — directory creation, security files, and validation.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;

trait PathUtilsDirTrait {

    /**
     * Check if a path is within allowed boundaries (prevents path traversal).
     *
     * @param string $path      Path to validate.
     * @param string $base_path Allowed base path.
     * @return bool True if path is safe.
     */
    public static function is_safe_path($path, $base_path) {
        $real_base = realpath($base_path);
        if ($real_base === false) {
            self::safeLog(LogLevelType::Warn->value, '[PATH] Base path does not exist', array('base' => $base_path));

            return false;
        }

        $real_path = self::resolvePathOrParent($path);
        if ($real_path === null) {
            return false;
        }

        $real_base = str_replace('\\', '/', $real_base);
        $real_path = str_replace('\\', '/', $real_path);

        return self::checkTraversal($path, $real_path, $real_base);
    }

    /** Resolve a path via realpath, falling back to parent resolution. */
    private static function resolvePathOrParent(string $path): ?string {
        $real_path = realpath($path);
        if ($real_path !== false) {
            return $real_path;
        }

        $parent = dirname($path);
        $real_parent = realpath($parent);
        if ($real_parent === false) {
            self::safeLog(LogLevelType::Warn->value, '[PATH] Neither path nor parent exists', array('path' => $path, 'parent' => $parent));

            return null;
        }

        return self::join($real_parent, basename($path));
    }

    /** Check for path traversal and log if detected. */
    private static function checkTraversal(string $path, string $real_path, string $real_base): bool {
        $is_safe = strpos($real_path, $real_base) === 0;
        if (!$is_safe) {
            self::safeLog(LogLevelType::Error->value, '[PATH] Path traversal attempt detected', array('path' => $path, 'resolved' => $real_path, 'base' => $real_base));
        }

        return $is_safe;
    }

    /**
     * Check if ensuring a directory fails (semantic inverse of ensure_dir).
     *
     * @param string $path   Directory path.
     * @param bool   $secure Add security files.
     * @return bool True if directory is MISSING (creation failed).
     */
    public static function is_dir_missing($path, $secure = false) {
        return !self::ensure_dir($path, $secure);
    }

    /**
     * Ensure a directory exists, creating it if necessary.
     *
     * @param string $path   Directory path.
     * @param bool   $secure Add .htaccess and index.php for security.
     * @return bool True if directory exists or was created successfully.
     */
    public static function ensure_dir($path, $secure = false) {
        $path = self::join($path);
        if (empty($path)) {
            self::safeLog(LogLevelType::Error->value, '[PATH] Empty path provided to ensure_dir');

            return false;
        }

        if (is_dir($path)) {
            return self::handleExistingDir($path, $secure);
        }

        return self::createNewDir($path, $secure);
    }

    /** Handle an already-existing directory (optionally secure it). */
    private static function handleExistingDir(string $path, bool $secure): bool {
        self::safeLog(LogLevelType::Debug->value, '[PATH] Directory already exists', array('path' => $path));
        if ($secure) {
            self::add_security_files($path);
        }

        return true;
    }

    /** Create a new directory and optionally add security files. */
    private static function createNewDir(string $path, bool $secure): bool {
        self::safeLog(LogLevelType::Info->value, '[PATH] Creating directory', array('path' => $path, 'secure' => $secure));

        if (!wp_mkdir_p($path)) {
            self::logDirCreationFailure($path);

            return false;
        }

        self::safeLog(LogLevelType::Info->value, '[PATH] Directory created successfully', array('path' => $path));
        if ($secure) {
            self::add_security_files($path);
        }

        return true;
    }

    /** Log detailed directory creation failure diagnostics. */
    private static function logDirCreationFailure(string $path) {
        $error = error_get_last();
        self::safeLog(LogLevelType::Error->value, '[PATH] Directory creation failed', array(
            'path' => $path, 'error' => $error ? $error['message'] : 'Unknown error',
            'parent_exists' => is_dir(dirname($path)), 'parent_writable' => is_writable(dirname($path)),
        ));
    }

    /**
     * Add security files (.htaccess and index.php) to a directory.
     *
     * @param string $path Directory path.
     * @return bool True if files were created successfully.
     */
    public static function add_security_files($path) {
        $success = true;

        $htaccess_path = self::join($path, '.htaccess');
        if (RiseupBooleanHelpers::is_file_missing($htaccess_path)) {
            $content = "# Riseup Asia Uploader - Security\nOrder Deny,Allow\nDeny from all\n";
            if (@file_put_contents($htaccess_path, $content) === false) {
                self::safeLog(LogLevelType::Warn->value, '[PATH] Failed to create .htaccess', array('path' => $htaccess_path));
                $success = false;
            }
        }

        $index_path = self::join($path, 'index.php');
        if (RiseupBooleanHelpers::is_file_missing($index_path)) {
            if (@file_put_contents($index_path, "<?php\n// Silence is golden.\n") === false) {
                self::safeLog(LogLevelType::Warn->value, '[PATH] Failed to create index.php', array('path' => $index_path));
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Join path segments and ensure the directory exists.
     *
     * @param bool   $secure     Add security files.
     * @param string ...$segments Path segments to join.
     * @return string|false Full path if successful, false on failure.
     */
    public static function ensure_path($secure, ...$segments) {
        $path = self::join(...$segments);
        if (empty($path)) {
            self::safeLog(LogLevelType::Error->value, '[PATH] Empty path from segments', array('segments' => $segments));
            return false;
        }
        if (self::is_dir_missing($path, $secure)) {
            return false;
        }
        return $path;
    }
}
