<?php
/**
 * FileSystemPluginTrait — plugin file detection and filesystem fallback.
 *
 * @package RiseupAsia\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait FileSystemPluginTrait {

    /**
     * Find plugin file by slug.
     *
     * @param string $slug Plugin slug.
     * @return string|null Plugin file or null.
     */
    private function findPluginFile($slug) {
        $this->ensurePluginFunctionsLoaded();
        $this->clearPluginCache();

        $all_plugins = $this->safeGetPlugins();
        if ($all_plugins === null) {
            return null;
        }

        if (empty($all_plugins)) {
            $this->fileLogger->warn('findPluginFile: get_plugins() returned empty — trying filesystem fallback', array(
                'requested_slug' => $slug,
            ));
            return $this->findPluginFileFromFilesystem($slug);
        }

        return $this->matchPluginBySlug($slug, $all_plugins);
    }

    /** Ensure wp-admin/includes/plugin.php is loaded. */
    private function ensurePluginFunctionsLoaded() {
        try {
            if (RiseupBooleanHelpers::is_func_missing('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
        } catch (\Throwable $e) {
            $this->fileLogger->log_exception($e, 'findPluginFile: Failed to load plugin.php');
        }
    }

    /** Clear the WordPress plugin cache. */
    private function clearPluginCache() {
        try {
            if (function_exists('wp_clean_plugins_cache')) {
                wp_clean_plugins_cache(true);
            } else {
                wp_cache_delete('plugins', 'plugins');
            }
        } catch (\Throwable $e) {
            $this->fileLogger->warn('findPluginFile: Failed to clear plugin cache', array(
                'error' => $e->getMessage(),
            ));
        }
    }

    /** Safely call get_plugins(), returning null on failure. */
    private function safeGetPlugins() {
        try {
            return get_plugins();
        } catch (\Throwable $e) {
            $this->fileLogger->log_exception($e, 'findPluginFile: get_plugins() threw an exception');
            return null;
        }
    }

    /** Match a slug against the plugins array, falling back to filesystem. */
    private function matchPluginBySlug(string $slug, array $all_plugins) {
        $available_slugs = array();
        foreach ($all_plugins as $plugin_file => $plugin_data) {
            $plugin_slug = dirname($plugin_file);
            if ($plugin_slug === '.') {
                $plugin_slug = basename($plugin_file, '.php');
            }
            if ($plugin_slug === $slug) {
                return $plugin_file;
            }
            $available_slugs[] = $plugin_slug;
        }

        $this->fileLogger->warn('Plugin slug not found via get_plugins(), trying filesystem fallback', array(
            'requested_slug'  => $slug,
            'available_slugs' => $available_slugs,
            'total_plugins'   => count($all_plugins),
        ));

        return $this->findPluginFileFromFilesystem($slug);
    }

    /**
     * Filesystem fallback to locate a plugin file.
     */
    private function findPluginFileFromFilesystem($slug) {
        try {
            $dir_result = $this->findDirPlugin($slug);
            if ($dir_result !== null) {
                return $dir_result;
            }

            return $this->findSingleFilePlugin($slug);
        } catch (\Throwable $e) {
            $this->fileLogger->log_exception($e, 'findPluginFileFromFilesystem: Filesystem scan failed');
        }

        return null;
    }

    /** Look for a plugin inside a directory. */
    private function findDirPlugin(string $slug) {
        $plugin_dir = WP_PLUGIN_DIR . '/' . $slug;

        if (RiseupBooleanHelpers::is_dir_missing($plugin_dir)) {
            return null;
        }

        $main_file = $plugin_dir . '/' . $slug . '.php';
        if (file_exists($main_file)) {
            $this->fileLogger->info('findDirPlugin: Found directory plugin', array(
                'plugin_file' => $slug . '/' . $slug . '.php',
            ));
            return $slug . '/' . $slug . '.php';
        }

        return $this->scanDirForPluginHeader($slug, $plugin_dir);
    }

    /** Scan PHP files in a directory for a Plugin Name header. */
    private function scanDirForPluginHeader(string $slug, string $plugin_dir) {
        $php_files = glob($plugin_dir . '/*.php');
        if (!$php_files) {
            return null;
        }

        foreach ($php_files as $file) {
            $header = @file_get_contents($file, false, null, 0, 8192);
            if ($header !== false && stripos($header, 'Plugin Name:') !== false) {
                $relative = $slug . '/' . basename($file);
                $this->fileLogger->info('scanDirForPluginHeader: Found plugin via header scan', array(
                    'plugin_file' => $relative,
                ));
                return $relative;
            }
        }

        return null;
    }

    /** Check for a single-file plugin (slug.php in plugins root). */
    private function findSingleFilePlugin(string $slug) {
        $single_file = WP_PLUGIN_DIR . '/' . $slug . '.php';
        if (file_exists($single_file)) {
            $this->fileLogger->info('findSingleFilePlugin: Found single-file plugin', array(
                'plugin_file' => $slug . '.php',
            ));
            return $slug . '.php';
        }

        $this->fileLogger->warn('findSingleFilePlugin: Plugin not found on filesystem', array(
            'requested_slug' => $slug,
        ));

        return null;
    }
}
