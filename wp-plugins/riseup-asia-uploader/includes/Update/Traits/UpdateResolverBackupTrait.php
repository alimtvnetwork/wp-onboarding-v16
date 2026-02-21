<?php
/**
 * UpdateResolverBackupTrait — Pre-update plugin backup and rollback.
 *
 * @package RiseupAsia\Update\Traits
 * @since   2.2.0
 */

namespace RiseupAsia\Update\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use WP_Error;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\WpErrorCodeType;
use RiseupAsia\Helpers\DateHelper;

trait UpdateResolverBackupTrait {

    /**
     * Create a backup of the current plugin directory before applying an update.
     *
     * Copies the entire plugin folder to a timestamped backup directory
     * within wp-content/upgrade/. Returns the backup path on success.
     *
     * @return string|WP_Error Backup directory path on success, WP_Error on failure.
     */
    public function createPreUpdateBackup(): string|WP_Error {
        $pluginDir = WP_PLUGIN_DIR . '/' . PluginConfigType::Slug->value;
        $isPluginDirMissing = !is_dir($pluginDir);
        if ($isPluginDirMissing) {
            $this->fileLogger->error('Plugin directory not found for backup', array('dir' => $pluginDir));

            return new WP_Error(
                WpErrorCodeType::FileNotFound->value,
                'Plugin directory not found — cannot create backup',
            );
        }

        $upgradeDir = WP_CONTENT_DIR . '/upgrade';
        $isUpgradeDirMissing = !is_dir($upgradeDir);
        if ($isUpgradeDirMissing) {
            wp_mkdir_p($upgradeDir);
        }

        $backupName = PluginConfigType::Slug->value . '-backup-' . DateHelper::nowCompact();
        $backupDir = $upgradeDir . '/' . $backupName;

        $this->fileLogger->info('Creating pre-update backup', array('source' => $pluginDir, 'backup' => $backupDir));

        $copied = $this->recursiveCopy($pluginDir, $backupDir);
        if ($copied === false) {
            $this->fileLogger->error('Backup copy failed');

            return new WP_Error(
                WpErrorCodeType::BackupFailed->value,
                'Failed to create pre-update backup of plugin directory',
            );
        }

        $this->fileLogger->info('Pre-update backup created', array('path' => $backupDir));

        return $backupDir;
    }

    /**
     * Restore the plugin from a backup directory, replacing the current version.
     *
     * @param string $backupDir Absolute path to the backup directory.
     *
     * @return true|WP_Error True on success, WP_Error on failure.
     */
    public function rollbackFromBackup(string $backupDir): true|WP_Error {
        $isBackupMissing = !is_dir($backupDir);
        if ($isBackupMissing) {
            $this->fileLogger->error('Backup directory not found for rollback', array('dir' => $backupDir));

            return new WP_Error(
                WpErrorCodeType::FileNotFound->value,
                'Backup directory not found — cannot rollback',
            );
        }

        $pluginDir = WP_PLUGIN_DIR . '/' . PluginConfigType::Slug->value;

        $this->fileLogger->warn('Rolling back plugin from backup', array('backup' => $backupDir, 'target' => $pluginDir));

        // Remove the failed update
        $this->recursiveDelete($pluginDir);

        // Restore from backup
        $restored = $this->recursiveCopy($backupDir, $pluginDir);
        if ($restored === false) {
            $this->fileLogger->error('Rollback restore failed — plugin may be in broken state');

            return new WP_Error(
                WpErrorCodeType::RollbackFailed->value,
                'Rollback failed — manual intervention required',
            );
        }

        // Clean up the backup directory
        $this->recursiveDelete($backupDir);

        $this->fileLogger->info('Rollback completed successfully');

        return true;
    }

    /**
     * Clean up a backup directory after a successful update.
     */
    public function cleanupBackup(string $backupDir): void {
        $isBackupExists = is_dir($backupDir);
        if ($isBackupExists) {
            $this->recursiveDelete($backupDir);
            $this->fileLogger->info('Pre-update backup cleaned up', array('path' => $backupDir));
        }
    }

    private function recursiveCopy(
        string $source,
        string $destination,
    ): bool {
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

    private function recursiveDelete(string $dir): void {
        $isDirMissing = !is_dir($dir);
        if ($isDirMissing) {
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
