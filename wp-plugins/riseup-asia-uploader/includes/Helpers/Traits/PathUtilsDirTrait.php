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
     * @param string $basePath  Allowed base path.
     */
    public static function isSafePath(string $path, string $basePath): bool {
        $realBase = realpath($basePath);
        if ($realBase === false) {
            self::safeLog(LogLevelType::Warn->value, '[PATH] Base path does not exist', array('base' => $basePath));

            return false;
        }

        $realPath = self::resolvePathOrParent($path);
        if ($realPath === null) {
            return false;
        }

        $realBase = str_replace('\\', '/', $realBase);
        $realPath = str_replace('\\', '/', $realPath);

        return self::checkTraversal($path, $realPath, $realBase);
    }

    /** Resolve a path via realpath, falling back to parent resolution. */
    private static function resolvePathOrParent(string $path): ?string {
        $realPath = realpath($path);
        if ($realPath !== false) {
            return $realPath;
        }

        $parent = dirname($path);
        $realParent = realpath($parent);
        if ($realParent === false) {
            self::safeLog(LogLevelType::Warn->value, '[PATH] Neither path nor parent exists', array('path' => $path, 'parent' => $parent));

            return null;
        }

        return self::join($realParent, basename($path));
    }

    /** Check for path traversal and log if detected. */
    private static function checkTraversal(string $path, string $realPath, string $realBase): bool {
        $isSafe = strpos($realPath, $realBase) === 0;
        if (!$isSafe) {
            self::safeLog(LogLevelType::Error->value, '[PATH] Path traversal attempt detected', array('path' => $path, 'resolved' => $realPath, 'base' => $realBase));
        }

        return $isSafe;
    }

    /**
     * Check if ensuring a directory fails (semantic inverse of ensureDir).
     *
     * @param string $path   Directory path.
     * @param bool   $secure Add security files.
     * @return bool True if directory is MISSING (creation failed).
     */
    public static function isDirMissing(string $path, bool $secure = false): bool {
        return !self::ensureDir($path, $secure);
    }

    /**
     * Ensure a directory exists, creating it if necessary.
     *
     * @param string $path   Directory path.
     * @param bool   $secure Add .htaccess and index.php for security.
     * @return bool True if directory exists or was created successfully.
     */
    public static function ensureDir(string $path, bool $secure = false): bool {
        $path = self::join($path);
        if (empty($path)) {
            self::safeLog(LogLevelType::Error->value, '[PATH] Empty path provided to ensureDir');

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
            self::addSecurityFiles($path);
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
            self::addSecurityFiles($path);
        }

        return true;
    }

    /** Log detailed directory creation failure diagnostics. */
    private static function logDirCreationFailure(string $path): void {
        $error = error_get_last();
        self::safeLog(LogLevelType::Error->value, '[PATH] Directory creation failed', array(
            'path' => $path, 'error' => $error ? $error['message'] : 'Unknown error',
            'parent_exists' => is_dir(dirname($path)), 'parent_writable' => is_writable(dirname($path)),
        ));
    }

    /**
     * Add security files (.htaccess and index.php) to a directory.
     */
    public static function addSecurityFiles(string $path): bool {
        $success = true;

        $htaccessPath = self::join($path, '.htaccess');
        if (RiseupBooleanHelpers::is_file_missing($htaccessPath)) {
            $content = "# Riseup Asia Uploader - Security\nOrder Deny,Allow\nDeny from all\n";
            if (@file_put_contents($htaccessPath, $content) === false) {
                self::safeLog(LogLevelType::Warn->value, '[PATH] Failed to create .htaccess', array('path' => $htaccessPath));
                $success = false;
            }
        }

        $indexPath = self::join($path, 'index.php');
        if (RiseupBooleanHelpers::is_file_missing($indexPath)) {
            if (@file_put_contents($indexPath, "<?php\n// Silence is golden.\n") === false) {
                self::safeLog(LogLevelType::Warn->value, '[PATH] Failed to create index.php', array('path' => $indexPath));
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
    public static function ensurePath(bool $secure, string ...$segments) {
        $path = self::join(...$segments);
        if (empty($path)) {
            self::safeLog(LogLevelType::Error->value, '[PATH] Empty path from segments', array('segments' => $segments));
            return false;
        }
        if (self::isDirMissing($path, $secure)) {
            return false;
        }
        return $path;
    }
}
