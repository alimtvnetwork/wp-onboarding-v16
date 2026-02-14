<?php
/**
 * ExporterBuildCollectTrait — File collection and incremental query for ZIP exports.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\TableType;

trait ExporterBuildCollectTrait {

    /**
     * Collect all .sqlite and .db files from a snapshot directory.
     *
     * @param string $dir Snapshot directory.
     * @return array Map of absolute path => relative path.
     */
    private function collectSnapshotFiles($dir) {
        $files = array();
        if (RiseupBooleanHelpers::is_dir_missing($dir)) {
            return $files;
        }

        $iterator = new DirectoryIterator($dir);
        foreach ($iterator as $file) {
            if ($file->isDot() || $file->isDir()) {
                continue;
            }
            $ext = strtolower($file->getExtension());
            if (in_array($ext, array('sqlite', 'db'), true)) {
                $files[$file->getPathname()] = $file->getFilename();
            }
        }
        return $files;
    }

    /**
     * Collect all .sqlite files from incremental subdirectories.
     *
     * @param string $incrementalDir The incremental/ directory path.
     * @return array Map of absolute path => relative path.
     */
    private function collectIncrementalFiles($incrementalDir) {
        $files = array();
        if (RiseupBooleanHelpers::is_dir_missing($incrementalDir)) {
            return $files;
        }

        $subdirs = new DirectoryIterator($incrementalDir);
        foreach ($subdirs as $subdir) {
            if ($subdir->isDot() || !$subdir->isDir()) {
                continue;
            }
            $subdirName = $subdir->getFilename();
            $innerIterator = new DirectoryIterator($subdir->getPathname());
            foreach ($innerIterator as $file) {
                if ($file->isDot() || $file->isDir()) {
                    continue;
                }
                $ext = strtolower($file->getExtension());
                if (in_array($ext, array('sqlite', 'db'), true)) {
                    $files[$file->getPathname()] = $subdirName . '/' . $file->getFilename();
                }
            }
        }
        return $files;
    }

    /**
     * Get all incremental snapshots belonging to a full snapshot.
     *
     * @param int    $parentId   Parent full snapshot ID.
     * @param string $parentName Parent snapshot filename.
     * @return array List of incremental snapshot records.
     */
    private function getIncrementalSnapshots($parentId, $parentName) {
        $pdo = $this->db->get_pdo();
        if (!$pdo) {
            return array();
        }

        $stmt = $pdo->prepare(
            'SELECT id, filename, filepath, scope, status, created_at FROM ' . TableType::Snapshots->value .
            ' WHERE scope = \'incremental\' AND filepath LIKE ? AND status = ? ORDER BY created_at ASC'
        );
        $parentDir = '%/' . $parentName . '/incremental/%';
        $stmt->execute(array($parentDir, SNAPSHOT_STATUS_COMPLETE));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
