<?php
/**
 * PluginBackupHandlerTrait — Create, restore, list, and delete plugin backups.
 *
 * Backups are stored as zip archives under wp-content/uploads/riseup-asia-uploader/backups/{slug}/.
 * Retention is enforced per plugin (default: 5 backups).
 *
 * @package RiseupAsia\Traits\Plugin
 * @since   1.64.0
 */

namespace RiseupAsia\Traits\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;
use Throwable;
use ZipArchive;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

use RiseupAsia\Enums\ActionType;
use RiseupAsia\Enums\BackupConfigType;
use RiseupAsia\Enums\BackupStatusType;
use RiseupAsia\Enums\BackupType;
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\StatusType;
use RiseupAsia\Helpers\DateHelper;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\ResultHelper;

trait PluginBackupHandlerTrait
{
    /**
     * POST /plugins/backup — Create a zip backup of a plugin.
     *
     * JSON body: { "slug": "plugin-slug", "type": "manual|pre_update|pre_publish|scheduled" }
     */
    public function handlePluginBackup(WP_REST_Request $request): WP_REST_Response
    {
        return $this->safeExecute(function () use ($request) {
            $body = $request->get_json_params();
            $slug = isset($body['slug']) ? sanitize_text_field($body['slug']) : '';

            if (empty($slug)) {
                return $this->errorResponse(
                    'Plugin slug is required',
                    HttpStatusType::BadRequest->value,
                );
            }

            $backupType = BackupType::tryFrom($body['type'] ?? '') ?? BackupType::Manual;

            $pluginDir = PathHelper::join(WP_PLUGIN_DIR, $slug);

            if (!is_dir($pluginDir)) {
                return $this->errorResponse(
                    'Plugin not found: ' . $slug,
                    HttpStatusType::NotFound->value,
                );
            }

            // Get current plugin version
            $version = $this->getPluginVersionFromDir($pluginDir, $slug);

            // Create backup directory
            $backupDir = PathHelper::join(PathHelper::getBackupsDir(), $slug);

            if (!is_dir($backupDir)) {
                wp_mkdir_p($backupDir);
            }

            // Generate filename with timestamp
            $timestamp = DateHelper::nowIso();
            $safeTimestamp = str_replace(array(':', 'T'), array('-', '_'), $timestamp);
            $filename = $slug . '_v' . $version . '_' . $safeTimestamp . '.zip';
            $zipPath = PathHelper::join($backupDir, $filename);

            // Create the zip
            $zip = new ZipArchive();
            $openResult = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

            if ($openResult !== true) {
                return $this->errorResponse(
                    'Failed to create backup archive',
                    HttpStatusType::ServerError->value,
                );
            }

            $this->addDirectoryToBackupZip($zip, $pluginDir, $slug);
            $zip->close();

            $fileSize = filesize($zipPath);

            // Build metadata
            $meta = array(
                'filename'   => $filename,
                'slug'       => $slug,
                'version'    => $version,
                'type'       => $backupType->value,
                'status'     => BackupStatusType::Complete->value,
                'file_size'  => $fileSize,
                'created_at' => $timestamp,
                'path'       => $zipPath,
            );

            // Write metadata JSON alongside zip
            $metaPath = $zipPath . '.json';
            file_put_contents($metaPath, wp_json_encode($meta, JSON_PRETTY_PRINT));

            // Enforce retention limit
            $this->enforceBackupRetention($backupDir);

            $this->fileLogger->info('Plugin backup created', array(
                'slug'     => $slug,
                'version'  => $version,
                'type'     => $backupType->value,
                'size'     => $fileSize,
                'filename' => $filename,
            ));

            return new WP_REST_Response(ResultHelper::ok(array(
                ResponseKeyType::Success->value  => true,
                ResponseKeyType::Message->value  => 'Backup created successfully',
                ResponseKeyType::Filename->value => $filename,
                ResponseKeyType::Slug->value     => $slug,
                ResponseKeyType::Version->value  => $version,
                ResponseKeyType::Size->value     => $fileSize,
                ResponseKeyType::Type->value     => $backupType->value,
            )), HttpStatusType::Ok->value);
        }, 'plugin_backup');
    }

    /**
     * POST /plugins/backup-restore — Restore a plugin from a backup zip.
     *
     * JSON body: { "slug": "plugin-slug", "filename": "backup-file.zip" }
     */
    public function handlePluginBackupRestore(WP_REST_Request $request): WP_REST_Response
    {
        return $this->safeExecute(function () use ($request) {
            $body = $request->get_json_params();
            $slug = isset($body['slug']) ? sanitize_text_field($body['slug']) : '';
            $filename = isset($body['filename']) ? sanitize_file_name($body['filename']) : '';

            if (empty($slug) || empty($filename)) {
                return $this->errorResponse(
                    'Plugin slug and backup filename are required',
                    HttpStatusType::BadRequest->value,
                );
            }

            $backupDir = PathHelper::join(PathHelper::getBackupsDir(), $slug);
            $zipPath = PathHelper::join($backupDir, $filename);

            if (!file_exists($zipPath)) {
                return $this->errorResponse(
                    'Backup file not found: ' . $filename,
                    HttpStatusType::NotFound->value,
                );
            }

            $pluginDir = PathHelper::join(WP_PLUGIN_DIR, $slug);

            // Check if plugin was active before restore
            $wasActive = is_plugin_active($slug . '/' . $slug . '.php');

            // Delete current plugin directory
            if (is_dir($pluginDir)) {
                $this->deleteDirectoryRecursive($pluginDir);
            }

            // Extract backup
            $zip = new ZipArchive();
            $openResult = $zip->open($zipPath);

            if ($openResult !== true) {
                return $this->errorResponse(
                    'Failed to open backup archive',
                    HttpStatusType::ServerError->value,
                );
            }

            $extractResult = $zip->extractTo(WP_PLUGIN_DIR);
            $zip->close();

            if (!$extractResult) {
                return $this->errorResponse(
                    'Failed to extract backup archive',
                    HttpStatusType::ServerError->value,
                );
            }

            // Re-activate if it was active before
            if ($wasActive && file_exists($pluginDir . '/' . $slug . '.php')) {
                activate_plugin($slug . '/' . $slug . '.php');
            }

            // Update metadata status
            $metaPath = $zipPath . '.json';

            if (file_exists($metaPath)) {
                $rawMeta = @file_get_contents($metaPath);

                if ($rawMeta !== false) {
                    $meta = json_decode($rawMeta, true);

                    if (is_array($meta)) {
                        $meta['status'] = BackupStatusType::Restored->value;
                        $meta['restored_at'] = DateHelper::nowIso();
                        $isWriteFailed = (file_put_contents($metaPath, wp_json_encode($meta, JSON_PRETTY_PRINT)) === false);

                        if ($isWriteFailed) {
                            $this->fileLogger->warn('Failed to update backup metadata after restore', array('path' => $metaPath));
                        }
                    }
                } else {
                    $this->fileLogger->warn('Failed to read backup metadata', array('path' => $metaPath));
                }
            }

            $this->fileLogger->info('Plugin restored from backup', array(
                'slug'     => $slug,
                'filename' => $filename,
                'reactivated' => $wasActive,
            ));

            return new WP_REST_Response(ResultHelper::ok(array(
                ResponseKeyType::Success->value => true,
                ResponseKeyType::Message->value => 'Plugin restored successfully from backup',
                ResponseKeyType::Slug->value    => $slug,
                ResponseKeyType::Filename->value => $filename,
            )), HttpStatusType::Ok->value);
        }, 'plugin_backup_restore');
    }

    /**
     * GET /plugins/backup-list — List available backups for a plugin.
     *
     * Query param: ?slug=plugin-slug (optional — lists all if omitted)
     */
    public function handlePluginBackupList(WP_REST_Request $request): WP_REST_Response
    {
        return $this->safeExecute(function () use ($request) {
            $slug = sanitize_text_field($request->get_param('slug') ?? '');
            $backupsBaseDir = PathHelper::getBackupsDir();
            $result = array();

            if (!empty($slug)) {
                $pluginBackups = $this->listBackupsForPlugin($backupsBaseDir, $slug);
                $result = $pluginBackups;
            } else {
                // List all plugins with backups
                if (is_dir($backupsBaseDir)) {
                    $dirs = scandir($backupsBaseDir);

                    foreach ($dirs as $dir) {
                        if ($dir === '.' || $dir === '..') {
                            continue;
                        }

                        $dirPath = PathHelper::join($backupsBaseDir, $dir);

                        if (!is_dir($dirPath)) {
                            continue;
                        }

                        $pluginBackups = $this->listBackupsForPlugin($backupsBaseDir, $dir);
                        $result = array_merge($result, $pluginBackups);
                    }
                }
            }

            // Sort by created_at descending
            usort($result, function ($a, $b) {
                return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
            });

            return new WP_REST_Response(ResultHelper::ok(array(
                'backups' => $result,
                ResponseKeyType::Total->value => count($result),
            )), HttpStatusType::Ok->value);
        }, 'plugin_backup_list');
    }

    /**
     * POST /plugins/backup-delete — Delete a specific backup.
     *
     * JSON body: { "slug": "plugin-slug", "filename": "backup-file.zip" }
     */
    public function handlePluginBackupDelete(WP_REST_Request $request): WP_REST_Response
    {
        return $this->safeExecute(function () use ($request) {
            $body = $request->get_json_params();
            $slug = isset($body['slug']) ? sanitize_text_field($body['slug']) : '';
            $filename = isset($body['filename']) ? sanitize_file_name($body['filename']) : '';

            if (empty($slug) || empty($filename)) {
                return $this->errorResponse(
                    'Plugin slug and backup filename are required',
                    HttpStatusType::BadRequest->value,
                );
            }

            $backupDir = PathHelper::join(PathHelper::getBackupsDir(), $slug);
            $zipPath = PathHelper::join($backupDir, $filename);
            $metaPath = $zipPath . '.json';

            if (!file_exists($zipPath)) {
                return $this->errorResponse(
                    'Backup file not found: ' . $filename,
                    HttpStatusType::NotFound->value,
                );
            }

            $deleted = @unlink($zipPath);

            if (file_exists($metaPath)) {
                @unlink($metaPath);
            }

            if (!$deleted) {
                return $this->errorResponse(
                    'Failed to delete backup file',
                    HttpStatusType::ServerError->value,
                );
            }

            $this->fileLogger->info('Plugin backup deleted', array(
                'slug'     => $slug,
                'filename' => $filename,
            ));

            return new WP_REST_Response(ResultHelper::ok(array(
                ResponseKeyType::Success->value => true,
                ResponseKeyType::Message->value => 'Backup deleted successfully',
            )), HttpStatusType::Ok->value);
        }, 'plugin_backup_delete');
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Read plugin version from main plugin file header.
     */
    private function getPluginVersionFromDir(string $pluginDir, string $slug): string
    {
        $mainFile = PathHelper::join($pluginDir, $slug . '.php');

        if (!file_exists($mainFile)) {
            // Try finding any PHP file with plugin header
            $files = glob($pluginDir . '/*.php');

            foreach ($files as $file) {
                $data = get_plugin_data($file, false, false);

                if (!empty($data['Version'])) {
                    return $data['Version'];
                }
            }

            return 'unknown';
        }

        $data = get_plugin_data($mainFile, false, false);

        return !empty($data['Version']) ? $data['Version'] : 'unknown';
    }

    /**
     * Recursively add a directory to a ZipArchive.
     */
    private function addDirectoryToBackupZip(ZipArchive $zip, string $dir, string $prefix): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $relativePath = $prefix . '/' . $iterator->getSubPathname();

            if ($item->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                $zip->addFile($item->getPathname(), $relativePath);
            }
        }
    }

    /**
     * Delete a directory and all its contents.
     */
    private function deleteDirectoryRecursive(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        return @rmdir($dir);
    }

    /**
     * List backups for a specific plugin slug.
     *
     * @return list<array{filename: string, slug: string, version: string, type: string, status: string, file_size: int, created_at: string}>
     */
    private function listBackupsForPlugin(string $backupsBaseDir, string $slug): array
    {
        $dir = PathHelper::join($backupsBaseDir, $slug);
        $result = array();

        if (!is_dir($dir)) {
            return $result;
        }

        $files = glob($dir . '/*.zip');

        foreach ($files as $zipFile) {
            $metaFile = $zipFile . '.json';
            $filename = basename($zipFile);

            if (file_exists($metaFile)) {
                $rawMeta = @file_get_contents($metaFile);
                $meta = ($rawMeta !== false) ? json_decode($rawMeta, true) : null;

                if (is_array($meta)) {
                    $meta['filename'] = $filename;
                    $meta['file_size'] = filesize($zipFile);
                    $result[] = $meta;

                    continue;
                }
            }

            // Fallback: build metadata from filename
            $result[] = array(
                'filename'   => $filename,
                'slug'       => $slug,
                'version'    => 'unknown',
                'type'       => BackupType::Manual->value,
                'status'     => BackupStatusType::Complete->value,
                'file_size'  => filesize($zipFile),
                'created_at' => gmdate('Y-m-d\TH:i:s\Z', filemtime($zipFile)),
            );
        }

        return $result;
    }

    /**
     * Enforce backup retention: keep only the newest N backups per plugin directory.
     */
    private function enforceBackupRetention(string $backupDir): void
    {
        $maxBackups = BackupConfigType::RetentionPerPlugin->value;
        $files = glob($backupDir . '/*.zip');

        if (count($files) <= $maxBackups) {
            return;
        }

        // Sort by modification time descending (newest first)
        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        // Delete excess backups
        $toDelete = array_slice($files, $maxBackups);

        foreach ($toDelete as $file) {
            $this->fileLogger->info('Auto-deleting old backup', array(
                'file' => basename($file),
            ));
            @unlink($file);

            $metaFile = $file . '.json';

            if (file_exists($metaFile)) {
                @unlink($metaFile);
            }
        }
    }
}
