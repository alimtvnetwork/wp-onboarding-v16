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
        $filepath = $snapshot['Filepath'];
        $isDirectory = is_dir($filepath);

        $bytesFreed = $isDirectory
            ? $this->deleteDirectorySnapshot($filepath, (int) $snapshot['Id'])
            : $this->deleteSingleFileSnapshot($filepath);

        if ($bytesFreed === -1) {
            return ResultHelper::failed(array(ResponseKeyType::BytesFreed->value => 0));
        }

        $this->deleteSnapshotRecords((int) $snapshot['Id']);
        $this->removeExportCache((int) $snapshot['Id']);
        $this->logSnapshotDeleted($snapshot, $bytesFreed);

        return ResultHelper::ok(array(ResponseKeyType::BytesFreed->value => $bytesFreed));
    }

    private function deleteDirectorySnapshot(string $filepath, int $snapshotId): int {
        $bytesFreed = $this->cascadeDeleteIncrementalDir($filepath, $snapshotId);
        $dirSize = $this->getDirectorySize($filepath);
        $this->deleteDirectoryRecursive($filepath);

        return $bytesFreed + $dirSize;
    }

    private function logSnapshotDeleted(array $snapshot, int $bytesFreed): void {
        $this->log(LogLevelType::Debug->value, 'Deleted snapshot', array(
            'id'                             => $snapshot['Id'],
            ResponseKeyType::Filename->value => $snapshot['Filename'] ?? '',
            'bytesFreed'                     => PathHelper::formatBytes($bytesFreed),
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
        $bytesFreed = $this->deleteMainFile($filepath);

        if ($bytesFreed === -1) {
            return -1;
        }

        $bytesFreed += $this->deleteAssociatedZip($filepath);

        return $bytesFreed;
    }

    private function deleteMainFile(string $filepath): int {
        if (PathHelper::isFileMissing($filepath)) {
            return 0;
        }

        $size = filesize($filepath);
        $isDeleteFailed = (PathHelper::deleteFile($filepath) === false);

        if ($isDeleteFailed) {
            $this->log(LogLevelType::Warn->value, 'Failed to delete snapshot file', array('filepath' => $filepath));

            return -1;
        }

        return $size;
    }

    private function deleteAssociatedZip(string $filepath): int {
        $zipPath = $this->getZipPath($filepath);

        if (PathHelper::isFileMissing($zipPath)) {
            return 0;
        }

        $size = filesize($zipPath);
        PathHelper::deleteFile($zipPath);

        return $size;
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
            $this->deleteCascadedRecords($parentDir);
        } catch (Throwable $e) {
            $this->log(LogLevelType::Error->value, 'Failed to cascade-delete incremental records', array(
                ResponseKeyType::Error->value => $e->getMessage(),
            ));
        }
    }

    private function deleteCascadedRecords(string $parentDir): void {
        $incrementals = $this->db->queryAll(
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
    }
}
