<?php
/**
 * ExporterBuildCollectTrait — File collection and incremental query for ZIP exports.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.57.0
 */

declare(strict_types=1);

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RiseupAsia\Enums\SnapshotStatusType;
use RiseupAsia\Enums\TableType;
use RiseupAsia\Helpers\PathHelper;

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

        if (PathHelper::isDirMissing($incrementalDir)) {
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
        $isPdoMissing = ($pdo === null);

        if ($isPdoMissing) {
            return array();
        }

        $stmt = $pdo->prepare(
            'SELECT Id, Filename, Filepath, Scope, Status, CreatedAt FROM ' . TableType::SnapshotExports->value .
            ' WHERE Scope = \'incremental\' AND Filepath LIKE ? AND Status = ? ORDER BY CreatedAt ASC'
        );
        $parentDir = '%/' . $parentName . '/incremental/%';
        $stmt->execute(array($parentDir, SnapshotStatusType::Complete->value));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
