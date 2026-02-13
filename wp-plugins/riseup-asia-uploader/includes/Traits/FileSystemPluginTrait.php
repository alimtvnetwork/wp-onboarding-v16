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
    private function find_plugin_file($slug) {
        try {
            if (RiseupBooleanHelpers::is_func_missing('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'find_plugin_file: Failed to load plugin.php');
            return null;
        }

        try {
            if (function_exists('wp_clean_plugins_cache')) {
                wp_clean_plugins_cache(true);
            } else {
                wp_cache_delete('plugins', 'plugins');
            }
        } catch (Throwable $e) {
            $this->file_logger->warn('find_plugin_file: Failed to clear plugin cache', array(
                'error' => $e->getMessage(),
            ));
        }

        try {
            $all_plugins = get_plugins();
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'find_plugin_file: get_plugins() threw an exception');
            return null;
        }

        if (empty($all_plugins)) {
            $this->file_logger->warn('find_plugin_file: get_plugins() returned empty — trying filesystem fallback', array(
                'requested_slug' => $slug,
            ));
            return $this->find_plugin_file_from_filesystem($slug);
        }

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

        $this->file_logger->warn('Plugin slug not found via get_plugins(), trying filesystem fallback', array(
            'requested_slug'  => $slug,
            'available_slugs' => $available_slugs,
            'total_plugins'   => count($all_plugins),
        ));

        return $this->find_plugin_file_from_filesystem($slug);
    }

    /**
     * Filesystem fallback to locate a plugin file.
     */
    private function find_plugin_file_from_filesystem($slug) {
        try {
            $plugin_dir = WP_PLUGIN_DIR . '/' . $slug;

            if (is_dir($plugin_dir)) {
                $main_file = $plugin_dir . '/' . $slug . '.php';
                if (file_exists($main_file)) {
                    $this->file_logger->info('find_plugin_file_from_filesystem: Found directory plugin', array(
                        'plugin_file' => $slug . '/' . $slug . '.php',
                    ));
                    return $slug . '/' . $slug . '.php';
                }

                $php_files = glob($plugin_dir . '/*.php');
                if ($php_files) {
                    foreach ($php_files as $file) {
                        $header = @file_get_contents($file, false, null, 0, 8192);
                        if ($header !== false && stripos($header, 'Plugin Name:') !== false) {
                            $relative = $slug . '/' . basename($file);
                            $this->file_logger->info('find_plugin_file_from_filesystem: Found plugin via header scan', array(
                                'plugin_file' => $relative,
                            ));
                            return $relative;
                        }
                    }
                }
            }

            $single_file = WP_PLUGIN_DIR . '/' . $slug . '.php';
            if (file_exists($single_file)) {
                $this->file_logger->info('find_plugin_file_from_filesystem: Found single-file plugin', array(
                    'plugin_file' => $slug . '.php',
                ));
                return $slug . '.php';
            }

            $this->file_logger->warn('find_plugin_file_from_filesystem: Plugin not found on filesystem', array(
                'requested_slug' => $slug,
                'checked_dir'    => $plugin_dir,
                'checked_file'   => $single_file,
            ));
        } catch (Throwable $e) {
            $this->file_logger->log_exception($e, 'find_plugin_file_from_filesystem: Filesystem scan failed');
        }

        return null;
    }
}
