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

use Throwable;
use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\SnapshotModeType;
use RiseupAsia\Enums\TableType;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\ResultHelper;
use RiseupAsia\Snapshot\SnapshotExporter;

trait CleanerDeletionTrait {
    private function deleteSnapshot(array $snapshot): array {
        $bytesFreed = 0;
        $filepath = $snapshot['Filepath'];
        $isDirectory = is_dir($filepath);

        if ($isDirectory) {
            $bytesFreed += $this->cascadeDeleteIncrementalDir($filepath, (int) $snapshot['Id']);
            $dirSize = $this->getDirectorySize($filepath);
            $this->deleteDirectoryRecursive($filepath);
            $bytesFreed += $dirSize;
        } else {
            $bytesFreed += $this->deleteSingleFileSnapshot($filepath);

            if ($bytesFreed === -1) {
                return ResultHelper::failed(array(
                    'bytesFreed' => 0,
                ));
            }
        }

        $this->deleteSnapshotRecords((int) $snapshot['Id']);
        $this->removeExportCache((int) $snapshot['Id']);

        $this->log(LogLevelType::Debug->value, 'Deleted snapshot', array(
            'id'                             => $snapshot['Id'],
            ResponseKeyType::Filename->value => $snapshot['Filename'] ?? '',
            'bytesFreed'                     => PathHelper::formatBytes($bytesFreed),
        ));

        return ResultHelper::ok(array(
            'bytesFreed' => $bytesFreed,
        ));
    }

    private function cascadeDeleteIncrementalDir(string $filepath, int $parentId): int {
        $incrementalDir = $filepath . '/incremental';

        if (PathHelper::isDirMissing($incrementalDir)) {
            return 0;
        }

        $incSize = $this->getDirectorySize($incrementalDir);
        $this->deleteDirectoryRecursive($incrementalDir);
        $this->cascadeDeleteIncrementalRecords($filepath);

        $this->log(LogLevelType::Info->value, 'Cascade-deleted incremental children', array(
            'parentId'   => $parentId,
            'parentDir'  => basename($filepath),
            'bytesFreed' => PathHelper::formatBytes($incSize),
        ));

        return $incSize;
    }

    private function deleteSingleFileSnapshot(string $filepath): int {
        $bytesFreed = 0;

        if (PathHelper::fileExists($filepath)) {
            $bytesFreed = filesize($filepath);
            $isDeleteFailed = (PathHelper::deleteFile($filepath) === false);

            if ($isDeleteFailed) {
                $this->log(LogLevelType::Warn->value, 'Failed to delete snapshot file', array('filepath' => $filepath));

                return -1;
            }
        }

        $zipPath = $this->getZipPath($filepath);

        if (PathHelper::fileExists($zipPath)) {
            $bytesFreed += filesize($zipPath);
            PathHelper::deleteFile($zipPath);
        }

        return $bytesFreed;
    }

    private function deleteSnapshotRecords(int $snapshotId): void {
        $this->db->delete(TableType::Snapshots->value, array('Id' => $snapshotId));
        $this->db->execute(
            'DELETE FROM ' . TableType::SnapshotProgress->value . ' WHERE SnapshotId = ?',
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
                ResponseKeyType::SnapshotId->value => $snapshotId,
                ResponseKeyType::Error->value      => $e->getMessage(),
            ));
        }
    }

    private function cascadeDeleteIncrementalRecords(string $parentDir): void {
        try {
            $incrementals = $this->db->query_all(
                'SELECT Id FROM ' . TableType::Snapshots->value .
                " WHERE Scope = '" . SnapshotModeType::Incremental->value . "' AND Filepath LIKE ?",
                array($parentDir . '/incremental/%')
            ) ?: array();

            foreach ($incrementals as $inc) {
                $this->deleteSnapshotRecords((int) $inc['Id']);
            }

            $this->log(LogLevelType::Debug->value, 'Deleted incremental DB records', array(
                ResponseKeyType::Count->value => count($incrementals),
            ));
        } catch (Throwable $e) {
            $this->log(LogLevelType::Error->value, 'Failed to cascade-delete incremental records', array(
                ResponseKeyType::Error->value => $e->getMessage(),
            ));
        }
    }
}
