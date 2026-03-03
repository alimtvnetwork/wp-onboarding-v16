<?php
/**
 * Path Manager class.
 *
 * Centralized path resolution for the plugin.
 * Uses meaningful constants to identify each directory's purpose.
 *
 * @package PluginsOnboard
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class OnboardPaths
 *
 * Provides centralized path management with lazy loading.
 */
class OnboardPaths {

    /**
     * Directory type constants - clearly describe purpose.
     */
    const DIR_PLUGIN_DATA       = 'plugin_data';        // SQLite databases and settings
    const DIR_PLUGIN_SNAPSHOTS  = 'plugin_snapshots';   // Plugin backup snapshots
    const DIR_TEMP_UPLOADS      = 'temp_uploads';       // Temporary upload files
    const DIR_SECURITY_LOGS     = 'security_logs';      // Security and audit logs
    const DIR_DATABASE          = 'database';           // Database files directory

    /**
     * File type constants - specific database files.
     */
    const FILE_MAIN_DATABASE    = 'main_database';      // plugin-manager.sqlite
    const FILE_AUDIT_DATABASE   = 'audit_database';     // audit.sqlite

    /**
     * Required directories - static list, no need to recreate.
     */
    const REQUIRED_DIRECTORIES = array(
        self::DIR_DATABASE,
        self::DIR_PLUGIN_SNAPSHOTS,
        self::DIR_TEMP_UPLOADS,
        self::DIR_SECURITY_LOGS,
    );

    /**
     * Cached paths.
     *
     * @var array
     */
    private static $path_cache = array();

    /**
     * Get path by type constant.
     *
     * @param string $path_type One of the class constants.
     * @return string Full path.
     */
    public static function get($path_type) {
        if (isset(self::$path_cache[$path_type])) {
            return self::$path_cache[$path_type];
        }

        $resolved_path = self::resolve_path($path_type);
        self::$path_cache[$path_type] = $resolved_path;

        return $resolved_path;
    }

    /**
     * Resolve path based on type constant.
     *
     * @param string $path_type Path type constant.
     * @return string Resolved path.
     */
    private static function resolve_path($path_type) {
        $base_path = self::get_base_path();

        switch ($path_type) {
            case self::DIR_DATABASE:
                return $base_path . 'db/';

            case self::DIR_PLUGIN_DATA:
                return $base_path . 'db/';

            case self::DIR_PLUGIN_SNAPSHOTS:
                return $base_path . 'snapshots/';

            case self::DIR_TEMP_UPLOADS:
                return $base_path . 'temp/';

            case self::DIR_SECURITY_LOGS:
                return $base_path . 'logs/';

            case self::FILE_MAIN_DATABASE:
                return $base_path . 'db/plugin-manager.sqlite';

            case self::FILE_AUDIT_DATABASE:
                return $base_path . 'db/audit.sqlite';

            default:
                return '';
        }
    }

    /**
     * Get base path for plugin storage.
     *
     * SINGLE SOURCE OF TRUTH: Change the base path here to relocate ALL plugin data.
     *
     * @return string Base directory path.
     * @throws Exception If WP_CONTENT_DIR is not defined.
     */
    private static function get_base_path() {
        $cache_key = '_base_path';

        if (isset(self::$path_cache[$cache_key])) {
            return self::$path_cache[$cache_key];
        }

        // Verify WordPress constants are available.
        if (!defined('WP_CONTENT_DIR')) {
            throw new Exception('WP_CONTENT_DIR constant is not defined. WordPress may not be loaded properly.');
        }

        // CONFIGURATION: Change this path to relocate ALL plugin storage.
        // Default: wp-content/uploads/plugins-onboard/
        $base_path = WP_CONTENT_DIR . '/uploads/plugins-onboard/';

        self::$path_cache[$cache_key] = $base_path;

        return $base_path;
    }

    /**
     * Clear the path cache (useful for testing).
     */
    public static function clear_cache() {
        self::$path_cache = array();
    }

    /**
     * Ensure a directory exists and is created if not.
     *
     * @param string $dir_type Directory type constant (DIR_* only).
     * @return bool True if directory exists or was created.
     * @throws Exception If directory path is empty or cannot be created.
     */
    public static function ensure_directory_exists($dir_type) {
        $path = self::get($dir_type);

        if (empty($path)) {
            throw new Exception("Failed to resolve path for directory type: {$dir_type}");
        }

        if (is_dir($path)) {
            return true;
        }

        // Attempt to create directory.
        $is_created = false;
        if (function_exists('wp_mkdir_p')) {
            $is_created = wp_mkdir_p($path);
        } else {
            $is_created = @mkdir($path, 0755, true);
        }

        $isCreationFailed = empty($is_created);
        if ($isCreationFailed) {
            throw new Exception("Failed to create directory: {$path}. Check parent directory permissions.");
        }

        return true;
    }

    /**
     * Check if a directory path is writable.
     *
     * @param string $dir_type Directory type constant (DIR_* only).
     * @return bool True if writable.
     */
    public static function is_directory_writable($dir_type) {
        $path = self::get($dir_type);
        $hasPath    = !empty($path);
        $isWritable = $hasPath && is_writable($path);

        return $isWritable;
    }

    /**
     * Check if a file exists.
     *
     * @param string $file_type File type constant (FILE_* only).
     * @return bool True if file exists.
     */
    public static function file_exists($file_type) {
        $path = self::get($file_type);
        $hasPath    = !empty($path);
        $fileExists = $hasPath && file_exists($path);

        return $fileExists;
    }

    /**
     * Get file size if exists.
     *
     * @param string $file_type File type constant (FILE_* only).
     * @return int File size in bytes, 0 if not exists.
     */
    public static function get_file_size($file_type) {
        $path = self::get($file_type);
        $hasPath    = !empty($path);
        $fileExists = $hasPath && file_exists($path);

        if ($fileExists) {
            return filesize($path);
        }

        return 0;
    }

    /**
     * Create all required plugin directories.
     *
     * @return bool True if all directories created successfully.
     * @throws Exception If any directory cannot be created.
     */
    public static function create_all_directories() {
        $errors = array();

        foreach (self::REQUIRED_DIRECTORIES as $dir_type) {
            try {
                self::ensure_directory_exists($dir_type);
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
                OnboardErrorLog::log($e, 'Plugins Onboard: Directory creation failed:');
            }
        }

        if (!empty($errors)) {
            throw new Exception('Failed to create required directories: ' . implode('; ', $errors));
        }

        return true;
    }
}
