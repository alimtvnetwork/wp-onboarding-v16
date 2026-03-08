<?php
/**
 * Plugin Manager Filesystem Trait — Plugin file discovery and directory operations.
 *
 * @package PluginsOnboard
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Trait OnboardPluginManagerFilesystemTrait
 *
 * Handles finding plugin main files and recursive directory deletion.
 */
trait OnboardPluginManagerFilesystemTrait {

    /**
     * Find plugin main file.
     *
     * @param string $slug Plugin slug.
     * @return string|null
     */
    private function find_plugin_file($slug) {
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
     * Delete plugin directory.
     *
     * @param string $slug Plugin slug.
     */
    private function delete_plugin_directory($slug) {
        $dir = WP_PLUGIN_DIR . '/' . $slug;

        if (is_dir($dir)) {
            $this->recursive_delete($dir);
        }
    }

    /**
     * Recursively delete directory.
     *
     * @param string $dir Directory path.
     */
    private function recursive_delete($dir) {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), array('.', '..'));

        foreach ($files as $file) {
            $path = $dir . '/' . $file;

            if (is_dir($path)) {
                $this->recursive_delete($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
