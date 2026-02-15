<?php
/**
 * ExporterBuildCollectTrait — File collection and incremental query for ZIP exports.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\SnapshotStatusType;
use RiseupAsia\Enums\TableType;

trait ExporterBuildCollectTrait {

    private function collectSnapshotFiles(string $dir): array {
        $files = array();
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $path = $item->getPathname();
            $files[] = $path;
        }

        return $files;
    }

    private function collectIncrementalFiles(string $incrementalDir): array {
        $files = array();
        if (!is_dir($incrementalDir)) {
            return $files;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($incrementalDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $path = $item->getPathname();
            $files[] = $path;
        }

        return $files;
    }

    private function getIncrementalSnapshots(int $parentId, string $parentName): array {
        $pdo = $this->db->getPdo();
        if (!$pdo) {
            return array();
        }

        $stmt = $pdo->prepare(
            'SELECT id, filename, filepath, scope, status, created_at FROM ' . TableType::SnapshotExports->value .
            ' WHERE scope = \'incremental\' AND filepath LIKE ? AND status = ? ORDER BY created_at ASC'
        );
        $parentDir = '%/' . $parentName . '/incremental/%';
        $stmt->execute(array($parentDir, SnapshotStatusType::Complete->value));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
