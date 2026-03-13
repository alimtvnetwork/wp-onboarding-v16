<?php
/**
 * Filesystem Utilities — Shared helpers for plugin file discovery and directory operations.
 *
 * @package PluginsOnboard
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class OnboardFilesystemUtils
 *
 * Static utility methods used by OnboardPluginManager and OnboardSnapshot.
 */
class OnboardFilesystemUtils {

    /**
     * Find plugin main file by slug.
     *
     * Checks common file names first, then falls back to scanning
     * for a PHP file with a valid Plugin Name header.
     *
     * @param string $slug Plugin slug.
     * @return string|null Relative plugin file path or null.
     */
    public static function find_plugin_file($slug) {
        $possible_files = array(
            $slug . '/' . $slug . '.php',
            $slug . '/plugin.php',
            $slug . '/index.php',
        );

        foreach ($possible_files as $file) {
            if (file_exists(WP_PLUGIN_DIR . '/' . $file)) {
                return $file;
            }
        }

        // Search for PHP file with plugin headers.
        $files = glob(WP_PLUGIN_DIR . '/' . $slug . '/*.php');

        foreach ($files as $file) {
            $data = get_file_data($file, array('Name' => 'Plugin Name'));

            if (!empty($data['Name'])) {
                return $slug . '/' . basename($file);
            }
        }

        return null;
    }

    /**
     * Recursively delete a directory and its contents.
     *
     * @param string $dir Absolute directory path.
     */
    public static function delete_directory($dir) {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), array('.', '..'));

        foreach ($files as $file) {
            $path = $dir . '/' . $file;

            if (is_dir($path)) {
                self::delete_directory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
