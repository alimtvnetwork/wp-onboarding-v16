<?php
/**
 * Riseup Asia Uploader - File Hash Cache
 *
 * SQLite-backed file hash caching for efficient sync comparisons.
 * Caches MD5 hashes and modification times so repeated requests
 * skip expensive md5_file() calls for unchanged files.
 *
 * @package RiseupAsiaUploader
 * @since   1.10.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class RiseupFileCache
 *
 * Manages cached file hashes in the plugin's SQLite database.
 * Compares filemtime() against cached values to skip unchanged files.
 */
class RiseupFileCache {

    /**
     * Singleton instance.
     *
     * @var RiseupFileCache|null
     */
    private static $instance = null;

    /**
     * File logger instance.
     *
     * @var RiseupFileLogger
     */
    private $logger;

    /**
     * Database instance.
     *
     * @var RiseupDatabase
     */
    private $db;

    /**
     * Get singleton instance.
     *
     * @param RiseupFileLogger $logger File logger.
     * @param RiseupDatabase    $db     Database instance.
     * @return RiseupFileCache
     */
    public static function getInstance($logger, $db) {
        if (self::$instance === null) {
            self::$instance = new self($logger, $db);
        }
        return self::$instance;
    }

    /**
     * Constructor.
     *
     * @param RiseupFileLogger $logger File logger.
     * @param RiseupDatabase    $db     Database instance.
     */
    private function __construct($logger, $db) {
        $this->logger = $logger;
        $this->db = $db;
    }

    /**
     * Get cached file manifest for a plugin, refreshing stale entries.
     *
     * Scans the plugin directory and compares each file's filemtime()
     * against the cached modified_at. If unchanged, returns cached hash.
     * If changed or new, recalculates md5_file() and updates cache.
     * Removes cache entries for deleted files.
     *
     * @param string               $pluginSlug Plugin slug.
     * @param string               $pluginDir  Absolute path to plugin directory.
     * @param RiseupUploadIgnore   $ignore     Upload ignore patterns.
     * @return array{files: array, cached: int, computed: int, removed: int}
     */
    public function getManifest($pluginSlug, $pluginDir, $ignore) {
        $this->logger->debug('FileCache: Building manifest', array('slug' => $pluginSlug));

        if (!$this->db->is_ready()) {
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

    /**
     * Reconcile disk files against cached entries to build the manifest.
     *
     * @param string $pluginSlug    Plugin slug.
     * @param string $pluginDir     Plugin directory path.
     * @param array  $diskFiles     Files found on disk.
     * @param array  $cachedEntries Existing cache entries.
     * @return array{files: array, cached: int, computed: int, removed: int}
     */
    private function reconcileManifest(string $pluginSlug, string $pluginDir, array $diskFiles, array $cachedEntries): array {
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

    /**
     * Resolve a single file entry: use cache if valid, otherwise compute hash.
     *
     * @param string $pluginSlug    Plugin slug.
     * @param string $pluginDir     Plugin directory path.
     * @param array  $fileInfo      Disk file info (path, mtime, size).
     * @param array  $cachedEntries Existing cache entries.
     * @return array{file: array, cached: bool}
     */
    private function resolveFileEntry(string $pluginSlug, string $pluginDir, array $fileInfo, array $cachedEntries): array {
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

    /**
     * Remove cache entries for files that no longer exist on disk.
     *
     * @param string $pluginSlug    Plugin slug.
     * @param array  $cachedEntries All cached entries.
     * @param array  $activePaths   Paths that still exist on disk.
     * @return int Number of entries removed.
     */
    private function pruneStaleEntries(string $pluginSlug, array $cachedEntries, array $activePaths): int {
        $removed = 0;
        foreach ($cachedEntries as $path => $entry) {
            if (!isset($activePaths[$path])) {
                $this->deleteCacheEntry($pluginSlug, $path);
                $removed++;
            }
        }
        return $removed;
    }

    /**
     * Invalidate all cache entries for a plugin.
     *
     * @param string $pluginSlug Plugin slug.
     * @return int Number of entries removed.
     */
    public function invalidate($pluginSlug) {
        if (!$this->db->is_ready()) {
            return 0;
        }

        try {
            $deleted = RiseupORM::for_table(TABLE_FILE_CACHE)
                ->where('plugin_slug', $pluginSlug)
                ->delete();

            $this->logger->info('FileCache: Cache invalidated', array(
                'slug'    => $pluginSlug,
                'deleted' => $deleted,
            ));

            return $deleted;
        } catch (Exception $e) {
            $this->logger->error('FileCache: Failed to invalidate', array(
                'slug'  => $pluginSlug,
                'error' => $e->getMessage(),
            ));
            return 0;
        }
    }

    /**
     * Load all cached entries for a plugin, indexed by relative path.
     *
     * @param string $pluginSlug Plugin slug.
     * @return array<string, array> Path => cache entry.
     */
    private function loadCachedEntries($pluginSlug) {
        try {
            $rows = RiseupORM::for_table(TABLE_FILE_CACHE)
                ->where('plugin_slug', $pluginSlug)
                ->find_many();

            $entries = array();
            foreach ($rows as $row) {
                $entries[$row['relative_path']] = $row;
            }
            return $entries;
        } catch (Exception $e) {
            $this->logger->error('FileCache: Failed to load cache', array(
                'slug'  => $pluginSlug,
                'error' => $e->getMessage(),
            ));
            return array();
        }
    }

    /**
     * Insert or update a cache entry.
     *
     * @param string $pluginSlug Plugin slug.
     * @param string $path       Relative file path.
     * @param string $hash       MD5 hash.
     * @param string $modifiedAt ISO 8601 UTC timestamp.
     * @param int    $size       File size in bytes.
     */
    private function upsertCacheEntry($pluginSlug, $path, $hash, $modifiedAt, $size) {
        try {
            $pdo = $this->db->get_pdo();
            if (!$pdo) {
                return;
            }

            $now = gmdate('Y-m-d\TH:i:s\Z');

            // Use INSERT OR REPLACE for upsert
            $stmt = $pdo->prepare(
                "INSERT OR REPLACE INTO " . TABLE_FILE_CACHE .
                " (plugin_slug, relative_path, md5_hash, modified_at, file_size, cached_at)" .
                " VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute(array($pluginSlug, $path, $hash, $modifiedAt, $size, $now));
        } catch (Exception $e) {
            $this->logger->error('FileCache: Failed to upsert', array(
                'path'  => $path,
                'error' => $e->getMessage(),
            ));
        }
    }

    /**
     * Delete a cache entry for a specific file.
     *
     * @param string $pluginSlug Plugin slug.
     * @param string $path       Relative file path.
     */
    private function deleteCacheEntry($pluginSlug, $path) {
        try {
            RiseupORM::for_table(TABLE_FILE_CACHE)
                ->where('plugin_slug', $pluginSlug)
                ->where('relative_path', $path)
                ->delete();
        } catch (Exception $e) {
            $this->logger->error('FileCache: Failed to delete entry', array(
                'path'  => $path,
                'error' => $e->getMessage(),
            ));
        }
    }

    /**
     * Scan a directory recursively and collect raw file info (no hashing).
     *
     * @param string               $baseDir Base directory for relative paths.
     * @param string               $dir     Current directory to scan.
     * @param RiseupUploadIgnore   $ignore  Ignore patterns.
     * @param array                $files   Reference to files array.
     */
    private function scanDirectory($baseDir, $dir, $ignore, &$files) {
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
            } else {
                $mtime = @filemtime($fullPath);
                $size = @filesize($fullPath);

                $files[] = array(
                    'path'  => str_replace('\\', '/', $relPath),
                    'mtime' => $mtime ?: 0,
                    'size'  => $size ?: 0,
                );
            }
        }
    }

    /**
     * Full scan fallback when database is not available.
     *
     * @param string               $pluginDir Plugin directory.
     * @param RiseupUploadIgnore   $ignore    Ignore patterns.
     * @return array{files: array, cached: int, computed: int, removed: int}
     */
    private function fullScan($pluginDir, $ignore) {
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
