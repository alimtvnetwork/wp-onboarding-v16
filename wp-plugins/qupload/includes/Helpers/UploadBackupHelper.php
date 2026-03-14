<?php
/**
 * UploadBackupHelper — Creates and restores plugin directory backups during uploads.
 *
 * Used to roll back to the previous version when extraction or activation fails.
 *
 * @package QUpload\Helpers
 * @since   2.13.0
 */

namespace QUpload\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

use QUpload\Logging\FileLogger;

class UploadBackupHelper
{
    private FileLogger $fileLogger;

    public function __construct(FileLogger $fileLogger)
    {
        $this->fileLogger = $fileLogger;
    }

    /**
     * Create a backup of the existing plugin directory before replacement.
     *
     * @return string|false Backup directory path on success, false on failure.
     */
    public function createBackup(string $slug): string|false
    {
        $sourceDir = WP_PLUGIN_DIR . '/' . $slug;
        $isDirMissing = !is_dir($sourceDir);

        if ($isDirMissing) {
            $this->fileLogger->warn('No existing plugin directory to backup', ['slug' => $slug]);

            return false;
        }

        $backupDir = $this->resolveBackupPath($slug);
        $isBackupDirReady = PathHelper::ensureDirectory(dirname($backupDir));

        if ($isBackupDirReady === false) {
            $this->fileLogger->error('Failed to create backup parent directory', ['slug' => $slug]);

            return false;
        }

        $isCopied = $this->copyDirectoryRecursive($sourceDir, $backupDir);

        if ($isCopied === false) {
            $this->fileLogger->error('Failed to copy plugin directory for backup', ['slug' => $slug, 'backupDir' => $backupDir]);

            return false;
        }

        $this->fileLogger->info('Plugin backup created', ['slug' => $slug, 'backupDir' => $backupDir]);

        return $backupDir;
    }

    /**
     * Restore a plugin from backup after a failed upload.
     *
     * @return bool True if rollback succeeded.
     */
    public function rollback(string $backupDir, string $slug): bool
    {
        $targetDir = WP_PLUGIN_DIR . '/' . $slug;

        // Remove the broken/partial new version
        if (is_dir($targetDir)) {
            $this->deleteDirectoryRecursive($targetDir);
        }

        $isRestored = @rename($backupDir, $targetDir);

        if ($isRestored === false) {
            // Fallback: copy then delete
            $isRestored = $this->copyDirectoryRecursive($backupDir, $targetDir);
            $this->deleteDirectoryRecursive($backupDir);
        }

        if ($isRestored) {
            $this->fileLogger->info('Plugin rollback succeeded', ['slug' => $slug]);
        } else {
            $this->fileLogger->error('Plugin rollback failed — plugin may be in broken state', ['slug' => $slug]);
        }

        return $isRestored;
    }

    /**
     * Re-activate the rolled-back plugin.
     */
    public function reactivateAfterRollback(string $slug): void
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        wp_cache_delete('plugins', 'plugins');
        $allPlugins = get_plugins();

        foreach ($allPlugins as $file => $data) {
            if (dirname($file) === $slug) {
                if (function_exists('opcache_reset')) {
                    opcache_reset();
                }

                activate_plugin($file);
                $this->fileLogger->info('Rolled-back plugin re-activated', ['slug' => $slug, 'file' => $file]);

                return;
            }
        }

        $this->fileLogger->warn('Could not find plugin file to re-activate after rollback', ['slug' => $slug]);
    }

    /** Remove backup directory after successful upload. */
    public function cleanup(string $backupDir): void
    {
        if (is_dir($backupDir)) {
            $this->deleteDirectoryRecursive($backupDir);
            $this->fileLogger->info('Backup cleaned up', ['backupDir' => $backupDir]);
        }
    }

    private function resolveBackupPath(string $slug): string
    {
        return PathHelper::getTempDir() . '/backup_' . $slug . '_' . time();
    }

    private function copyDirectoryRecursive(string $source, string $dest): bool
    {
        $isReady = PathHelper::ensureDirectory($dest);

        if ($isReady === false) {
            return false;
        }

        $entries = scandir($source);

        if ($entries === false) {
            return false;
        }

        foreach ($entries as $entry) {
            $isSkippable = $entry === '.' || $entry === '..';

            if ($isSkippable) {
                continue;
            }

            $srcPath = $source . '/' . $entry;
            $dstPath = $dest . '/' . $entry;

            if (is_dir($srcPath)) {
                $isSubCopied = $this->copyDirectoryRecursive($srcPath, $dstPath);

                if ($isSubCopied === false) {
                    return false;
                }
            } else {
                $isCopied = @copy($srcPath, $dstPath);

                if ($isCopied === false) {
                    return false;
                }
            }
        }

        return true;
    }

    private function deleteDirectoryRecursive(string $dir): bool
    {
        if (!is_dir($dir)) {
            return true;
        }

        $entries = scandir($dir);

        if ($entries === false) {
            return false;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;
            $isDeleted = is_dir($path) ? $this->deleteDirectoryRecursive($path) : @unlink($path);

            if ($isDeleted === false) {
                return false;
            }
        }

        return @rmdir($dir);
    }
}
