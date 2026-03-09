<?php
/**
 * FileCacheStoreTrait — cache CRUD operations (load, upsert, delete, invalidate).
 *
 * @package RiseupAsia\Database\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Database\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use Throwable;

use RiseupAsia\Database\Orm;
use RiseupAsia\Enums\TableType;
use RiseupAsia\Helpers\DateHelper;

trait FileCacheStoreTrait {

    public function invalidate(string $pluginSlug): int {
        $isDbUnavailable = ($this->db->isReady() === false);

        if ($isDbUnavailable) {
            return 0;
        }

        try {
            $deleted = Orm::forTable(TableType::FileCache->value)
                ->where('PluginSlug', $pluginSlug)
                ->delete();

            $this->logger->info('FileCache: Cache invalidated', array(
                'slug'    => $pluginSlug,
                'deleted' => $deleted,
            ));

            return $deleted;
        } catch (Throwable $e) {
            $this->logger->error('FileCache: Failed to invalidate', array(
                'slug'  => $pluginSlug,
                'error' => $e->getMessage(),
            ));

            return 0;
        }
    }

    private function loadCachedEntries(string $pluginSlug): array {
        try {
            $rows = Orm::forTable(TableType::FileCache->value)
                ->where('PluginSlug', $pluginSlug)
                ->findMany();

            $entries = array();
            foreach ($rows as $row) {
                $entries[$row['RelativePath']] = $row;
            }

            return $entries;
        } catch (Throwable $e) {
            $this->logger->error('FileCache: Failed to load cache', array(
                'slug'  => $pluginSlug,
                'error' => $e->getMessage(),
            ));

            return array();
        }
    }

    private function upsertCacheEntry(
        string $pluginSlug,
        string $path,
        string $hash,
        string $modifiedAt,
        int $size,
    ): void {
        try {
            $pdo = $this->db->getPdo();
            $isPdoMissing = ($pdo === null);

            if ($isPdoMissing) {
                return;
            }

            $now = DateHelper::nowUtc();

            $stmt = $pdo->prepare(
                "INSERT OR REPLACE INTO " . TableType::FileCache->value .
                " (PluginSlug, RelativePath, Md5Hash, ModifiedAt, FileSize, CachedAt)" .
                " VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute(array(
                $pluginSlug,
                $path,
                $hash,
                $modifiedAt,
                $size,
                $now,
            ));
        } catch (Throwable $e) {
            $this->logger->error('FileCache: Failed to upsert', array(
                'path'  => $path,
                'error' => $e->getMessage(),
            ));
        }
    }

    private function deleteCacheEntry(string $pluginSlug, string $path): void {
        try {
            Orm::forTable(TableType::FileCache->value)
                ->where('PluginSlug', $pluginSlug)
                ->where('RelativePath', $path)
                ->delete();
        } catch (Throwable $e) {
            $this->logger->error('FileCache: Failed to delete entry', array(
                'path'  => $path,
                'error' => $e->getMessage(),
            ));
        }
    }
}
