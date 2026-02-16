<?php
/**
 * CleanerDeletionTrait — Snapshot deletion with cascade support.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.9.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\TableType;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Snapshot\SnapshotExporter;

trait CleanerDeletionTrait {

    private function deleteSnapshot(array $snapshot): array {
        $bytes_freed = 0;
        $filepath = $snapshot['filepath'];
        $is_directory = is_dir($filepath);

        if ($is_directory) {
            $bytes_freed += $this->cascadeDeleteIncrementalDir($filepath, (int) $snapshot['id']);
            $dir_size = $this->getDirectorySize($filepath);
            $this->deleteDirectoryRecursive($filepath);
            $bytes_freed += $dir_size;
        } else {
            $bytes_freed += $this->deleteSingleFileSnapshot($filepath);
            if ($bytes_freed === -1) {
                return array('success' => false, 'bytes_freed' => 0);
            }
        }

        $this->deleteSnapshotRecords((int) $snapshot['id']);
        $this->removeExportCache((int) $snapshot['id']);

        $this->log(LogLevelType::Debug->value, 'Deleted snapshot', array(
            'id' => $snapshot['id'],
            'filename' => $snapshot['filename'] ?? '',
            'bytes_freed' => PathHelper::formatBytes($bytes_freed),
        ));

        return array('success' => true, 'bytes_freed' => $bytes_freed);
    }

    private function cascadeDeleteIncrementalDir(string $filepath, int $parentId): int {
        $incremental_dir = $filepath . '/incremental';
        if (PathHelper::isDirMissing($incremental_dir)) {
            return 0;
        }

        $inc_size = $this->getDirectorySize($incremental_dir);
        $this->deleteDirectoryRecursive($incremental_dir);
        $this->cascadeDeleteIncrementalRecords($filepath);

        $this->log(LogLevelType::Info->value, 'Cascade-deleted incremental children', array(
            'parent_id'   => $parentId,
            'parent_dir'  => basename($filepath),
            'bytes_freed' => PathHelper::formatBytes($inc_size),
        ));

        return $inc_size;
    }

    private function deleteSingleFileSnapshot(string $filepath): int {
        $bytes_freed = 0;

        if (PathHelper::fileExists($filepath)) {
            $bytes_freed = filesize($filepath);
            if (!PathHelper::deleteFile($filepath)) {
                $this->log(LogLevelType::Warn->value, 'Failed to delete snapshot file', array('filepath' => $filepath));
                return -1;
            }
        }

        $zip_path = $this->getZipPath($filepath);
        if (PathHelper::fileExists($zip_path)) {
            $bytes_freed += filesize($zip_path);
            PathHelper::deleteFile($zip_path);
        }

        return $bytes_freed;
    }

    private function deleteSnapshotRecords(int $snapshotId): void {
        $this->db->delete(TableType::Snapshots->value, array('id' => $snapshotId));
        $this->db->execute(
            'DELETE FROM ' . TableType::SnapshotProgress->value . ' WHERE snapshot_id = ?',
            array($snapshotId)
        );
    }

    private function removeExportCache(int $snapshotId): void {
        try {
            $exporter = SnapshotExporter::getInstance($this->logger, $this->db);
            if ($exporter) {
                $exporter->removeExports($snapshotId);
            }
        } catch (Throwable $e) {
            $this->log(LogLevelType::Warn->value, 'Failed to remove cached ZIP exports during delete', array(
                'snapshot_id' => $snapshotId,
                'error'       => $e->getMessage(),
            ));
        }
    }

    private function cascadeDeleteIncrementalRecords(string $parentDir): void {
        try {
            $incrementals = $this->db->query_all(
                'SELECT id FROM ' . TableType::Snapshots->value .
                " WHERE scope = '" . \RiseupAsia\Enums\SnapshotModeType::Incremental->value . "' AND filepath LIKE ?",
                array($parentDir . '/incremental/%')
            ) ?: array();

            foreach ($incrementals as $inc) {
                $this->deleteSnapshotRecords((int) $inc['id']);
            }

            $this->log(LogLevelType::Debug->value, 'Deleted incremental DB records', array(
                'count' => count($incrementals),
            ));
        } catch (Throwable $e) {
            $this->log(LogLevelType::Error->value, 'Failed to cascade-delete incremental records', array(
                'error' => $e->getMessage(),
            ));
        }
    }
}
