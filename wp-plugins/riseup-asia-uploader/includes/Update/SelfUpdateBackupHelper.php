<?php
/**
 * SelfUpdateBackupHelper — Pre-update backup, rollback, and cleanup for self-updates.
 *
 * Standalone helper class that creates a full backup of the plugin directory
 * before a self-update, restores it on failure, and cleans up on success.
 *
 * @package RiseupAsia\Update
 * @since   2.4.0
 */

namespace RiseupAsia\Update;

if (!defined('ABSPATH')) {
    exit;
}

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Helpers\DateHelper;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Logging\FileLogger;

class SelfUpdateBackupHelper
{
    private FileLogger $fileLogger;

    public function __construct(FileLogger $fileLogger)
    {
        $this->fileLogger = $fileLogger;
    }

    /**
     * Create a timestamped backup of the current plugin directory.
     *
     * Copies the entire plugin folder to wp-content/upgrade/{slug}-backup-{timestamp}.
     *
     * @return string|false Backup directory path on success, false on failure.
     */
    public function createBackup(): string|false
    {
        $pluginDir = PathHelper::getPluginDir();

        if (PathHelper::isDirMissing($pluginDir)) {
            $this->fileLogger->error('Self-update backup failed: plugin directory not found', array(
                'dir' => $pluginDir,
            ));

            return false;
        }

        $upgradeDir = WP_CONTENT_DIR . '/upgrade';

        if (PathHelper::isDirMissing($upgradeDir)) {
            wp_mkdir_p($upgradeDir);
        }

        $backupName = PluginConfigType::Slug->value . '-selfupdate-backup-' . DateHelper::nowCompact();
        $backupDir  = $upgradeDir . '/' . $backupName;

        $this->fileLogger->info('Creating self-update backup', array(
            'source' => $pluginDir,
            'backup' => $backupDir,
        ));

        $copied = $this->recursiveCopy($pluginDir, $backupDir);

        if ($copied === false) {
            $this->fileLogger->error('Self-update backup copy failed', array(
                'source' => $pluginDir,
                'backup' => $backupDir,
            ));

            return false;
        }

        $this->fileLogger->info('Self-update backup created successfully', array('path' => $backupDir));

        return $backupDir;
    }

    /**
     * Restore the plugin from a backup directory after a failed self-update.
     *
     * Removes the current (broken) plugin directory and copies the backup back.
     *
     * @param string $backupDir Absolute path to the backup directory.
     *
     * @return bool True on success, false on failure.
     */
    public function rollback(string $backupDir): bool
    {
        if (PathHelper::isDirMissing($backupDir)) {
            $this->fileLogger->error('Self-update rollback failed: backup directory not found', array(
                'backupDir' => $backupDir,
            ));

            return false;
        }

        $pluginDir = PathHelper::getPluginDir();

        $this->fileLogger->warn('Rolling back self-update from backup', array(
            'backup' => $backupDir,
            'target' => $pluginDir,
        ));

        // Remove the failed new version
        $this->recursiveDelete($pluginDir);

        // Restore from backup
        $restored = $this->recursiveCopy($backupDir, $pluginDir);

        if ($restored === false) {
            $this->fileLogger->error('Self-update rollback restore failed — plugin may be in broken state', array(
                'backup' => $backupDir,
                'target' => $pluginDir,
            ));

            return false;
        }

        // Clean up the backup
        $this->recursiveDelete($backupDir);

        $this->fileLogger->info('Self-update rollback completed successfully');

        return true;
    }

    /**
     * Clean up the backup directory after a successful self-update.
     *
     * @param string $backupDir Absolute path to the backup directory.
     */
    public function cleanup(string $backupDir): void
    {
        if (PathHelper::isDirMissing($backupDir)) {
            return;
        }

        $this->recursiveDelete($backupDir);
        $this->fileLogger->info('Self-update backup cleaned up', array('path' => $backupDir));
    }

    /**
     * Get the version string from the backed-up plugin's main file.
     *
     * @param string $backupDir Absolute path to the backup directory.
     *
     * @return string Version string or empty string if not found.
     */
    public function getBackupVersion(string $backupDir): string
    {
        $mainFile = $backupDir . '/' . PluginConfigType::Slug->value . '.php';

        if (!file_exists($mainFile)) {
            return '';
        }

        $content = file_get_contents($mainFile, false, null, 0, 8192);

        if ($content !== false && preg_match('/Version:\s*([0-9]+\.[0-9]+\.[0-9]+)/', $content, $matches)) {
            return $matches[1];
        }

        return '';
    }

    private function recursiveCopy(string $source, string $destination): bool
    {
        $isMkdirFailed = !wp_mkdir_p($destination);

        if ($isMkdirFailed) {
            return false;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $targetPath = $destination . '/' . $iterator->getSubPathname();

            if ($item->isDir()) {
                $isDirCreateFailed = !wp_mkdir_p($targetPath);

                if ($isDirCreateFailed) {
                    return false;
                }
            } else {
                $isCopyFailed = !copy($item->getPathname(), $targetPath);

                if ($isCopyFailed) {
                    return false;
                }
            }
        }

        return true;
    }

    private function recursiveDelete(string $dir): void
    {
        if (PathHelper::isDirMissing($dir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($dir);
    }
}
