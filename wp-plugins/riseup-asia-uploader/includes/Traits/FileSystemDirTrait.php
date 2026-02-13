<?php
/**
 * FileSystemDirTrait — directory operations, ZIP helpers, temp dir.
 *
 * @package RiseupAsia\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait FileSystemDirTrait {

    /**
     * Get temp directory path.
     */
    private function get_temp_dir() {
        $temp_dir = RiseupPathUtils::getTempDir();
        RiseupPathUtils::ensureDir($temp_dir);
        return $temp_dir;
    }

    /**
     * Detect plugin slug from ZIP file.
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
