<?php
/**
 * Cleaner Deletion Trait
 *
 * Snapshot deletion with cascade support for incremental children.
 *
 * @package RiseupAsiaUploader
 * @since   1.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\TableType;

trait CleanerDeletionTrait {

    /**
     * Delete a single snapshot (file + database record).
     * If the snapshot is a full snapshot with an incremental/ subdirectory,
     * cascade-delete all incremental children (files + DB records).
     *
     * @param array $snapshot Snapshot record.
     * @return array { success, bytes_freed }.
     */
    private function deleteSnapshot($snapshot) {
        $bytes_freed = 0;
        $filepath = $snapshot['filepath'];
        $is_directory = is_dir($filepath);

        if ($is_directory) {
            $bytes_freed += $this->cascadeDeleteIncrementalDir($filepath, $snapshot['id']);
            $dir_size = $this->getDirectorySize($filepath);
            $this->deleteDirectoryRecursive($filepath);
            $bytes_freed += $dir_size;
        } else {
            $bytes_freed += $this->deleteSingleFileSnapshot($filepath);
            if ($bytes_freed === -1) {
                return array('success' => false, 'bytes_freed' => 0);
            }
        }

        $this->deleteSnapshotRecords($snapshot['id']);
        $this->removeExportCache($snapshot['id']);

        $this->log(LogLevelType::Debug->value, 'Deleted snapshot', array(
            'id' => $snapshot['id'],
            'filename' => $snapshot['filename'] ?? '',
            'bytes_freed' => RiseupPathUtils::formatBytes($bytes_freed),
        ));

        return array('success' => true, 'bytes_freed' => $bytes_freed);
    }

    /**
     * Cascade-delete incremental subdirectory and its DB records.
     *
     * @param string $filepath  Parent full snapshot directory path.
     * @param int    $parent_id Parent snapshot ID.
     * @return int Bytes freed.
     */
    private function cascadeDeleteIncrementalDir($filepath, $parent_id) {
        $incremental_dir = $filepath . '/incremental';
        if (RiseupBooleanHelpers::isDirMissing($incremental_dir)) {
            return 0;
        }

        $inc_size = $this->getDirectorySize($incremental_dir);
        $this->deleteDirectoryRecursive($incremental_dir);
        $this->cascadeDeleteIncrementalRecords($filepath);

        $this->log(LogLevelType::Info->value, 'Cascade-deleted incremental children', array(
            'parent_id'   => $parent_id,
            'parent_dir'  => basename($filepath),
            'bytes_freed' => RiseupPathUtils::formatBytes($inc_size),
        ));

        return $inc_size;
    }

    /**
     * Delete a single-file snapshot (.sqlite) and its ZIP.
     *
     * @param string $filepath Path to .sqlite file.
     * @return int Bytes freed, or -1 on failure.
     */
    private function deleteSingleFileSnapshot($filepath) {
        $bytes_freed = 0;

        if (RiseupPathUtils::fileExists($filepath)) {
            $bytes_freed = filesize($filepath);
            if (!RiseupPathUtils::deleteFile($filepath)) {
                $this->log(LogLevelType::Warn->value, 'Failed to delete snapshot file', array('filepath' => $filepath));
                return -1;
...
        $zip_path = $this->getZipPath($filepath);
        if (RiseupPathUtils::fileExists($zip_path)) {
            $bytes_freed += filesize($zip_path);
            RiseupPathUtils::deleteFile($zip_path);
        }

        return $bytes_freed;
    }

    /**
     * Delete snapshot DB record and progress records.
     *
     * @param int $snapshot_id Snapshot ID.
     */
    private function deleteSnapshotRecords($snapshot_id) {
        $this->db->delete(TableType::Snapshots->value, array('id' => $snapshot_id));
        $this->db->execute(
            'DELETE FROM ' . TableType::SnapshotProgress->value . ' WHERE snapshot_id = ?',
            array($snapshot_id)
        );
    }

    /**
     * Remove cached ZIP exports for a snapshot.
     *
     * @param int $snapshot_id Snapshot ID.
     */
    private function removeExportCache($snapshot_id) {
        try {
            require_once dirname(__FILE__) . '/../SnapshotExporter.php';
            $exporter = RiseupSnapshotExporter::getInstance($this->logger, $this->db);
            if ($exporter) {
                $exporter->removeExports((int) $snapshot_id);
            }
        } catch (Throwable $e) {
            $this->log(LogLevelType::Warn->value, 'Failed to remove cached ZIP exports during delete', array(
                'snapshot_id' => $snapshot_id,
                'error'       => $e->getMessage(),
            ));
        }
    }

    /**
     * Cascade-delete all incremental snapshot DB records whose filepath
     * is a child of the given parent directory.
     *
     * @param string $parent_dir Parent full snapshot directory path.
     */
    private function cascadeDeleteIncrementalRecords($parent_dir) {
        try {
            $incrementals = $this->db->query_all(
                'SELECT id FROM ' . TableType::Snapshots->value .
                " WHERE scope = 'incremental' AND filepath LIKE ?",
                array($parent_dir . '/incremental/%')
            ) ?: array();

            foreach ($incrementals as $inc) {
                $this->deleteSnapshotRecords($inc['id']);
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
