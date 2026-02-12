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

        // Load existing cache entries for this plugin
        $cachedEntries = $this->loadCachedEntries($pluginSlug);

        // Scan filesystem
        $diskFiles = array();
        $this->scanDirectory($pluginDir, $pluginDir, $ignore, $diskFiles);

        $files = array();
        $cachedCount = 0;
        $computedCount = 0;
        $cachedPaths = array();

        foreach ($diskFiles as $fileInfo) {
            $path = $fileInfo['path'];
            $mtime = $fileInfo['mtime'];
            $size = $fileInfo['size'];
            $cachedPaths[$path] = true;

            // Check if we have a valid cache entry
            if (isset($cachedEntries[$path])) {
                $cached = $cachedEntries[$path];
                $cachedMtime = $cached['modified_at'];

                // Compare modification time (UTC ISO 8601)
                $mtimeStr = gmdate('c', $mtime);
                if ($cachedMtime === $mtimeStr && (int)$cached['file_size'] === $size) {
                    // File unchanged - use cached hash
                    $files[] = array(
                        'path'       => $path,
                        'hash'       => $cached['md5_hash'],
                        'modifiedAt' => $cachedMtime,
                        'size'       => (int)$cached['file_size'],
                    );
                    $cachedCount++;
                    continue;
                }
            }

            // File is new or changed - compute hash
            $fullPath = $pluginDir . '/' . str_replace('/', DIRECTORY_SEPARATOR, $path);
            $hash = @md5_file($fullPath);
            if ($hash === false) {
                $hash = '';
            }
            $mtimeStr = gmdate('c', $mtime);

            $files[] = array(
                'path'       => $path,
                'hash'       => $hash,
                'modifiedAt' => $mtimeStr,
                'size'       => $size,
            );

            // Update cache
            $this->upsertCacheEntry($pluginSlug, $path, $hash, $mtimeStr, $size);
            $computedCount++;
        }

        // Remove cache entries for deleted files
        $removedCount = 0;
        foreach ($cachedEntries as $cachedPath => $entry) {
            if (!isset($cachedPaths[$cachedPath])) {
                $this->deleteCacheEntry($pluginSlug, $cachedPath);
                $removedCount++;
            }
        }

        $this->logger->info('FileCache: Manifest built', array(
            'slug'     => $pluginSlug,
            'total'    => count($files),
            'cached'   => $cachedCount,
            'computed' => $computedCount,
            'removed'  => $removedCount,
        ));

        return array(
            'files'    => $files,
            'cached'   => $cachedCount,
            'computed' => $computedCount,
            'removed'  => $removedCount,
        );
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
            $deleted = RiseupORM::for_table(RISEUP_TABLE_FILE_CACHE)
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
            $rows = RiseupORM::for_table(RISEUP_TABLE_FILE_CACHE)
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
                "INSERT OR REPLACE INTO " . RISEUP_TABLE_FILE_CACHE .
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
            RiseupORM::for_table(RISEUP_TABLE_FILE_CACHE)
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

            if ($ignore->should_ignore($relPath)) {
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
