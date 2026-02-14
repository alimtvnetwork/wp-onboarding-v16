<?php
/**
 * FileCacheStoreTrait — cache CRUD operations (load, upsert, delete, invalidate).
 *
 * @package RiseupAsia\Database\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\TableType;

trait FileCacheStoreTrait {

    public function invalidate($pluginSlug) {
        if (!$this->db->is_ready()) {
            return 0;
        }

        try {
            $deleted = RiseupORM::for_table(TableType::FileCache->value)
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

    private function loadCachedEntries($pluginSlug) {
        try {
            $rows = RiseupORM::for_table(TableType::FileCache->value)
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

    private function upsertCacheEntry($pluginSlug, $path, $hash, $modifiedAt, $size) {
        try {
            $pdo = $this->db->get_pdo();
            if (!$pdo) {
                return;
            }

            $now = gmdate('Y-m-d\TH:i:s\Z');

            $stmt = $pdo->prepare(
                "INSERT OR REPLACE INTO " . TableType::FileCache->value .
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

    private function deleteCacheEntry($pluginSlug, $path) {
        try {
            RiseupORM::for_table(TableType::FileCache->value)
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
}