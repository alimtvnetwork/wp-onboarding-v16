<?php
/**
 * Plugin Manager Filesystem Trait — Delegates to OnboardFilesystemUtils.
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
 * Thin wrappers around OnboardFilesystemUtils for backward compatibility.
 */
trait OnboardPluginManagerFilesystemTrait {

    /**
     * Find plugin main file.
     *
     * @param string $slug Plugin slug.
     * @return string|null
     */
    private function find_plugin_file($slug) {
        return OnboardFilesystemUtils::find_plugin_file($slug);
    }

    /**
     * Delete plugin directory.
     *
     * @param string $slug Plugin slug.
     */
    private function delete_plugin_directory($slug) {
        OnboardFilesystemUtils::delete_directory(WP_PLUGIN_DIR . '/' . $slug);
    }
}
