<?php
/**
 * FileSystemTrait — file system utilities (temp dir, plugin file detection, ZIP helpers).
 *
 * Extracted from riseup-asia-uploader.php (lines 4047–4304).
 *
 * @package RiseupAsiaUploader
 */

trait FileSystemTrait {

    /**
     * Get temp directory path.
     *
     * @return string
     */
    private function get_temp_dir() {
        $temp_dir = RiseupPathUtils::getTempDir();
        RiseupPathUtils::ensureDir($temp_dir);
        return $temp_dir;
    }

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
     *
     * @param string $slug Plugin slug.
     * @return string|null Plugin file path or null.
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

    /**
     * Detect plugin slug from ZIP file.
     *
     * @param ZipArchive $zip ZIP archive.
     * @return string|null Plugin slug or null.
     */
    private function detect_plugin_slug_from_zip($zip) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('/^([^\/]+)\/[^\/]+\.php$/', $name, $matches)) {
                $content = $zip->getFromIndex($i);
                if ($content && strpos($content, 'Plugin Name:') !== false) {
                    return $matches[1];
                }
            }
        }
        return null;
    }

    /**
     * Delete a directory recursively.
     *
     * @param string $dir Directory path.
     * @return bool Success.
     */
    private function delete_directory($dir) {
        if (RiseupBooleanHelpers::is_dir_missing($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->delete_directory($path);
            } else {
                @unlink($path);
            }
        }

        return @rmdir($dir);
    }

    /**
     * Copy a directory recursively.
     *
     * @param string $src Source directory path.
     * @param string $dst Destination directory path.
     * @return bool Success.
     */
    private function copy_directory($src, $dst) {
        if (RiseupBooleanHelpers::is_dir_missing($src)) {
            return false;
        }

        if (RiseupBooleanHelpers::is_dir_missing($dst)) {
            wp_mkdir_p($dst);
        }

        $files = array_diff(scandir($src), array('.', '..'));
        foreach ($files as $file) {
            $src_path = $src . '/' . $file;
            $dst_path = $dst . '/' . $file;
            if (is_dir($src_path)) {
                $this->copy_directory($src_path, $dst_path);
            } else {
                copy($src_path, $dst_path);
            }
        }

        return true;
    }

    /**
     * Add directory to ZIP recursively.
     *
     * @param ZipArchive         $zip      ZIP archive.
     * @param string             $src_dir  Source directory.
     * @param string             $zip_dir  Directory name in ZIP.
     * @param RiseupUploadIgnore $ignore   Upload ignore parser.
     */
    private function add_dir_to_zip($zip, $src_dir, $zip_dir, $ignore) {
        $dir = opendir($src_dir);
        if (!$dir) {
            return;
        }

        $zip->addEmptyDir($zip_dir);

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $src_path = $src_dir . '/' . $file;
            $zip_path = $zip_dir . '/' . $file;

            $relative = str_replace($src_dir . '/', '', $src_path);
            if ($ignore->shouldIgnore($relative)) {
                continue;
            }

            if (is_dir($src_path)) {
                $this->add_dir_to_zip($zip, $src_path, $zip_path, $ignore);
            } else {
                $zip->addFile($src_path, $zip_path);
            }
        }

        closedir($dir);
    }
}
