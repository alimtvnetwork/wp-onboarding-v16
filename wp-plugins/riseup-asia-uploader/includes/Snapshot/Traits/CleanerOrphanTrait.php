<?php
/**
 * Cleaner Orphan Trait
 *
 * Orphan file cleanup and stuck/failed snapshot handling.
 *
 * @package RiseupAsiaUploader
 * @since   1.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\TableType;

trait CleanerOrphanTrait {

    /**
     * Cleanup orphan files (files without database records).
     *
     * @param bool $dry_run Simulate only.
     * @return array { removed, files, bytes_freed }.
     */
    private function cleanupOrphanFiles($dry_run = false) {
        $result = array('removed' => 0, 'files' => array(), 'bytes_freed' => 0);

        $snapshots_dir = RiseupPathUtils::getSnapshotsDir();
        if (!RiseupPathUtils::dirExists($snapshots_dir)) {
            return $result;
        }

        $db_files = $this->db->query_all(
            'SELECT filepath, filename FROM ' . TableType::Snapshots->value
        ) ?: array();

        $db_filepaths = array_column($db_files, 'filepath');
        $db_filenames = array();
        foreach ($db_files as $f) {
            if (!empty($f['filename'])) {
                $db_filenames[basename($f['filename'])] = true;
            }
        }

        $this->cleanupOrphanSqliteFiles($snapshots_dir, $db_filepaths, $db_filenames, $dry_run, $result);
        $this->cleanupOrphanDirectories($snapshots_dir, $db_filepaths, $db_filenames, $dry_run, $result);

        return $result;
    }

    /**
     * Remove orphan .sqlite files from snapshots directory.
     *
     * @param string $snapshots_dir Snapshots directory path.
     * @param array  $db_filepaths  Known filepaths from DB.
     * @param array  $db_filenames  Known filenames from DB (basename => true).
     * @param bool   $dry_run       Simulate only.
     * @param array  &$result       Result array (modified by reference).
     */
    private function cleanupOrphanSqliteFiles($snapshots_dir, $db_filepaths, $db_filenames, $dry_run, &$result) {
        $files = glob(RiseupPathUtils::join($snapshots_dir, '*.sqlite'));
        if (empty($files)) {
            return;
        }

        foreach ($files as $file) {
            if (in_array($file, $db_filepaths) || isset($db_filenames[basename($file)])) {
                continue;
            }

            $result['files'][] = basename($file);
            $bytes = filesize($file);

            if (!$dry_run) {
                if (RiseupPathUtils::deleteFile($file)) {
                    $result['removed']++;
                    $result['bytes_freed'] += $bytes;
...
                    $zip_path = $this->getZipPath($file);
                    if (RiseupPathUtils::fileExists($zip_path)) {
                        $result['bytes_freed'] += filesize($zip_path);
                        RiseupPathUtils::deleteFile($zip_path);
                    }
                }
            } else {
                $result['removed']++;
                $result['bytes_freed'] += $bytes;
            }
        }
    }

    /**
     * Remove orphan directories from snapshots directory.
     *
     * @param string $snapshots_dir Snapshots directory path.
     * @param array  $db_filepaths  Known filepaths from DB.
     * @param array  $db_filenames  Known filenames from DB (basename => true).
     * @param bool   $dry_run       Simulate only.
     * @param array  &$result       Result array (modified by reference).
     */
    private function cleanupOrphanDirectories($snapshots_dir, $db_filepaths, $db_filenames, $dry_run, &$result) {
        $entries = scandir($snapshots_dir);

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === '.htaccess' || $entry === 'index.php') {
                continue;
            }

            $full_path = $snapshots_dir . DIRECTORY_SEPARATOR . $entry;
            if (RiseupBooleanHelpers::is_dir_missing($full_path)) {
                continue;
            }

            if (!isset($db_filenames[$entry]) && RiseupBooleanHelpers::is_not_in_list($full_path, $db_filepaths)) {
                $result['files'][] = $entry;
                if (!$dry_run) {
                    $dir_size = $this->getDirectorySize($full_path);
                    $this->deleteDirectoryRecursive($full_path);
                    $result['removed']++;
                    $result['bytes_freed'] += $dir_size;
                    $this->log(LogLevelType::Info->value, 'Orphan snapshot directory removed', array('dir' => $entry));
                } else {
                    $result['removed']++;
                }
            }
        }
    }

    /**
     * Cleanup stuck/failed snapshots older than the configured threshold.
     *
     * @param bool $dry_run Simulate only.
     * @return array { cleaned, ids }.
     */
    private function cleanupStuckSnapshots($dry_run = false) {
        $result = array('cleaned' => 0, 'ids' => array());

        $stuck_hours = defined('SNAPSHOT_STUCK_HOURS') ? SNAPSHOT_STUCK_HOURS : 24;
        $cutoff = date('c', strtotime("-{$stuck_hours} hours"));

        $stuck = $this->db->query_all(
            'SELECT id, filepath, filename, status FROM ' . TableType::Snapshots->value .
            ' WHERE status IN (?, ?, ?) AND created_at < ?',
            array(
                SNAPSHOT_STATUS_PENDING,
                SNAPSHOT_STATUS_RUNNING,
                SNAPSHOT_STATUS_FAILED,
                $cutoff
            )
        ) ?: array();

        foreach ($stuck as $snapshot) {
            $result['ids'][] = (int) $snapshot['id'];

            if (!$dry_run) {
                $this->db->execute(
                    'UPDATE ' . TableType::Snapshots->value . ' SET status = ?, error = ? WHERE id = ?',
                    array(
                        SNAPSHOT_STATUS_FAILED,
                        "Auto-cleaned: stuck for >{$stuck_hours} hours",
                        $snapshot['id']
                    )
                );

                $this->log(LogLevelType::Warn->value, 'Stuck snapshot marked as failed', array(
                    'id'     => $snapshot['id'],
                    'status' => $snapshot['status'],
                ));
            }

            $result['cleaned']++;
        }

        return $result;
    }
}
