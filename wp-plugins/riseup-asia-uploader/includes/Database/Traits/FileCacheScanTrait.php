<?php
/**
 * FileCacheScanTrait — directory scanning, manifest building, and reconciliation.
 *
 * @package RiseupAsia\Database\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Database\Traits;

if (!defined('ABSPATH')) {
    exit;
}

trait FileCacheScanTrait {

    public function getManifest(
        string $pluginSlug,
        string $pluginDir,
        RiseupUploadIgnore $ignore,
    ): array {
        $this->logger->debug('FileCache: Building manifest', array('slug' => $pluginSlug));

        if (!$this->db->isReady()) {
            $this->logger->warn('FileCache: Database not ready, falling back to full scan');

            return $this->fullScan($pluginDir, $ignore);
        }

        $cachedEntries = $this->loadCachedEntries($pluginSlug);
        $diskFiles = array();
        $this->scanDirectory($pluginDir, $pluginDir, $ignore, $diskFiles);

        $result = $this->reconcileManifest($pluginSlug, $pluginDir, $diskFiles, $cachedEntries);

        $this->logger->info('FileCache: Manifest built', array(
            'slug'     => $pluginSlug,
            'total'    => count($result['files']),
            'cached'   => $result['cached'],
            'computed' => $result['computed'],
            'removed'  => $result['removed'],
        ));

        return $result;
    }

    private function reconcileManifest(
        string $pluginSlug,
        string $pluginDir,
        array $diskFiles,
        array $cachedEntries,
    ): array {
        $files = array();
        $cachedCount = 0;
        $computedCount = 0;
        $activePaths = array();

        foreach ($diskFiles as $fileInfo) {
            $entry = $this->resolveFileEntry($pluginSlug, $pluginDir, $fileInfo, $cachedEntries);
            $files[] = $entry['file'];
            $activePaths[$fileInfo['path']] = true;
            $entry['cached'] ? $cachedCount++ : $computedCount++;
        }

        $removedCount = $this->pruneStaleEntries($pluginSlug, $cachedEntries, $activePaths);

        return array('files' => $files, 'cached' => $cachedCount, 'computed' => $computedCount, 'removed' => $removedCount);
    }

    private function resolveFileEntry(
        string $pluginSlug,
        string $pluginDir,
        array $fileInfo,
        array $cachedEntries,
    ): array {
        $path = $fileInfo['path'];
        $mtimeStr = gmdate('c', $fileInfo['mtime']);

        if (isset($cachedEntries[$path])) {
            $cached = $cachedEntries[$path];
            if ($cached['modified_at'] === $mtimeStr && (int) $cached['file_size'] === $fileInfo['size']) {
                return array(
                    'file'   => array('path' => $path, 'hash' => $cached['md5_hash'], 'modifiedAt' => $mtimeStr, 'size' => (int) $cached['file_size']),
                    'cached' => true,
                );
            }
        }

        $fullPath = $pluginDir . '/' . str_replace('/', DIRECTORY_SEPARATOR, $path);
        $hash = @md5_file($fullPath) ?: '';

        $this->upsertCacheEntry($pluginSlug, $path, $hash, $mtimeStr, $fileInfo['size']);

        return array(
            'file'   => array('path' => $path, 'hash' => $hash, 'modifiedAt' => $mtimeStr, 'size' => $fileInfo['size']),
            'cached' => false,
        );
    }

    private function pruneStaleEntries(
        string $pluginSlug,
        array $cachedEntries,
        array $activePaths,
    ): int {
        $removed = 0;
        foreach ($cachedEntries as $path => $entry) {
            if (!isset($activePaths[$path])) {
                $this->deleteCacheEntry($pluginSlug, $path);
                $removed++;
            }
        }

        return $removed;
    }

    private function scanDirectory(
        string $baseDir,
        string $dir,
        RiseupUploadIgnore $ignore,
        array &$files,
    ): void {
        $items = @scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $dir . '/' . $item;
            $relPath = ltrim(str_replace($baseDir, '', $fullPath), '/\\');

            if ($ignore->shouldIgnore($relPath)) {
                continue;
            }

            if (is_dir($fullPath)) {
                $this->scanDirectory($baseDir, $fullPath, $ignore, $files);

                continue;
            }

            $files[] = $this->buildFileInfo($relPath, $fullPath);
        }
    }

    private function buildFileInfo(string $relPath, string $fullPath): array {
        return array(
            'path'  => str_replace('\\', '/', $relPath),
            'mtime' => @filemtime($fullPath) ?: 0,
            'size'  => @filesize($fullPath) ?: 0,
        );
    }

    private function fullScan(string $pluginDir, RiseupUploadIgnore $ignore): array {
        $diskFiles = array();
        $this->scanDirectory($pluginDir, $pluginDir, $ignore, $diskFiles);

        $files = array();
        foreach ($diskFiles as $fileInfo) {
            $fullPath = $pluginDir . '/' . str_replace('/', DIRECTORY_SEPARATOR, $fileInfo['path']);
            $hash = @md5_file($fullPath);

            $files[] = array(
                'path'       => $fileInfo['path'],
                'hash'       => $hash ?: '',
                'modifiedAt' => $fileInfo['mtime'] ? gmdate('c', $fileInfo['mtime']) : null,
                'size'       => $fileInfo['size'],
            );
        }

        return array(
            'files'    => $files,
            'cached'   => 0,
            'computed' => count($files),
            'removed'  => 0,
        );
    }
}
