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
    private function getTempDir() {
        $temp_dir = RiseupPathUtils::getTempDir();
        RiseupPathUtils::ensureDir($temp_dir);
        return $temp_dir;
    }

    /**
     * Detect plugin slug from ZIP file.
     */
    private function detectPluginSlugFromZip($zip) {
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
    private function deleteDirectory($dir) {
        if (RiseupBooleanHelpers::isDirMissing($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }

        return @rmdir($dir);
    }

    /**
     * Copy a directory recursively.
     */
    private function copyDirectory($src, $dst) {
        if (RiseupBooleanHelpers::isDirMissing($src)) {
            return false;
        }

        if (RiseupBooleanHelpers::isDirMissing($dst)) {
            wp_mkdir_p($dst);
        }

        $files = array_diff(scandir($src), array('.', '..'));
        foreach ($files as $file) {
            $src_path = $src . '/' . $file;
            $dst_path = $dst . '/' . $file;
            if (is_dir($src_path)) {
                $this->copyDirectory($src_path, $dst_path);
            } else {
                copy($src_path, $dst_path);
            }
        }

        return true;
    }

    /**
     * Add directory to ZIP recursively.
     */
    private function addDirToZip($zip, $src_dir, $zip_dir, $ignore) {
        $dir = opendir($src_dir);
        if (!$dir) {
            return;
        }

        $zip->addEmptyDir($zip_dir);

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $this->processZipEntry($zip, $src_dir, $zip_dir, $file, $ignore);
        }

        closedir($dir);
    }

    /** Process a single directory entry for ZIP archival. */
    private function processZipEntry($zip, string $src_dir, string $zip_dir, string $file, $ignore) {
        $src_path = $src_dir . '/' . $file;
        $zip_path = $zip_dir . '/' . $file;

        $relative = str_replace($src_dir . '/', '', $src_path);
        if ($ignore->shouldIgnore($relative)) {
            return;
        }

        if (is_dir($src_path)) {
            $this->addDirToZip($zip, $src_path, $zip_path, $ignore);
        } else {
            $zip->addFile($src_path, $zip_path);
        }
    }
}
