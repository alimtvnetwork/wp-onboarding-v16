<?php
/**
 * PathHelperDirTrait — directory guards, creation, security files, and validation.
 *
 * @package RiseupAsia\Helpers\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Helpers\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;

trait PathHelperDirTrait {

    // ── Directory Guards (moved from BooleanDomainTrait) ──

    public static function isDirExists(string $dirPath): bool { return !empty($dirPath) && is_dir($dirPath); }
    public static function isDirMissing(string $dirPath): bool { return empty($dirPath) || !is_dir($dirPath); }
    public static function isDirWritable(string $dirPath): bool { return !empty($dirPath) && is_dir($dirPath) && is_writable($dirPath); }
    public static function isDirReadonly(string $dirPath): bool { return empty($dirPath) || !is_dir($dirPath) || !is_writable($dirPath); }
    public static function isNotDirectory(string $path): bool { return !is_dir($path); }

    // ── Path Safety ──

    public static function isSafePath(string $path, string $basePath): bool {
        $realBase = realpath($basePath);
        if ($realBase === false) {
            self::safeLog(LogLevelType::Warn->value, '[PATH] Base path does not exist', array('base' => $basePath));
            return false;
        }

        $realPath = self::resolvePathOrParent($path);
        if ($realPath === null) { return false; }

        $realBase = str_replace('\\', '/', $realBase);
        $realPath = str_replace('\\', '/', $realPath);

        return self::checkTraversal($path, $realPath, $realBase);
    }

    private static function resolvePathOrParent(string $path): ?string {
        $realPath = realpath($path);
        if ($realPath !== false) { return $realPath; }

        $parent = dirname($path);
        $realParent = realpath($parent);
        if ($realParent === false) {
            self::safeLog(LogLevelType::Warn->value, '[PATH] Neither path nor parent exists', array('path' => $path, 'parent' => $parent));
            return null;
        }

        return self::join($realParent, basename($path));
    }

    private static function checkTraversal(
        string $path,
        string $realPath,
        string $realBase,
    ): bool {
        $isSafe = strpos($realPath, $realBase) === 0;
        if (!$isSafe) {
            self::safeLog(LogLevelType::Error->value, '[PATH] Path traversal attempt detected', array('path' => $path, 'resolved' => $realPath, 'base' => $realBase));
        }

        return $isSafe;
    }

    public static function isPathMissing(string $path, string $basePath): bool {
        return !self::isSafePath($path, $basePath);
    }

    // ── Directory Creation ──

    public static function ensureDir(string $path, bool $secure = false): bool {
        $path = self::join($path);
        if (empty($path)) {
            self::safeLog(LogLevelType::Error->value, '[PATH] Empty path provided to ensureDir');
            return false;
        }

        if (is_dir($path)) { return self::handleExistingDir($path, $secure); }
        return self::createNewDir($path, $secure);
    }

    private static function handleExistingDir(string $path, bool $secure): bool {
        self::safeLog(LogLevelType::Debug->value, '[PATH] Directory already exists', array('path' => $path));
        if ($secure) { self::addSecurityFiles($path); }
        return true;
    }

    private static function createNewDir(string $path, bool $secure): bool {
        self::safeLog(LogLevelType::Info->value, '[PATH] Creating directory', array('path' => $path, 'secure' => $secure));
        if (!wp_mkdir_p($path)) {
            self::logDirCreationFailure($path);
            return false;
        }
        self::safeLog(LogLevelType::Info->value, '[PATH] Directory created successfully', array('path' => $path));
        if ($secure) { self::addSecurityFiles($path); }
        return true;
    }

    private static function logDirCreationFailure(string $path): void {
        $error = error_get_last();
        self::safeLog(LogLevelType::Error->value, '[PATH] Directory creation failed', array(
            'path' => $path, 'error' => $error ? $error['message'] : 'Unknown error',
            'parent_exists' => is_dir(dirname($path)), 'parent_writable' => is_writable(dirname($path)),
        ));
    }

    public static function addSecurityFiles(string $path): bool {
        $success = true;

        $htaccessPath = self::join($path, '.htaccess');
        if (self::isFileMissing($htaccessPath)) {
            $content = "# Riseup Asia Uploader - Security\nOrder Deny,Allow\nDeny from all\n";
            if (@file_put_contents($htaccessPath, $content) === false) {
                self::safeLog(LogLevelType::Warn->value, '[PATH] Failed to create .htaccess', array('path' => $htaccessPath));
                $success = false;
            }
        }

        $indexPath = self::join($path, 'index.php');
        if (self::isFileMissing($indexPath)) {
            if (@file_put_contents($indexPath, "<?php\n// Silence is golden.\n") === false) {
                self::safeLog(LogLevelType::Warn->value, '[PATH] Failed to create index.php', array('path' => $indexPath));
                $success = false;
            }
        }

        return $success;
    }

    public static function ensurePath(bool $secure, string ...$segments) {
        $path = self::join(...$segments);
        if (empty($path)) {
            self::safeLog(LogLevelType::Error->value, '[PATH] Empty path from segments', array('segments' => $segments));
            return false;
        }
        if (!self::ensureDir($path, $secure)) { return false; }
        return $path;
    }
}
