<?php
/**
 * IncrementalRegistrationTrait — Snapshot registration and ZIP invalidation.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

use RiseupAsia\Enums\LogLevelType;

trait IncrementalRegistrationTrait {

    /** Register the incremental snapshot in the tracking table. */
    private function registerIncrementalSnapshot($title, $master_dir, $folder_name, $sequence, $tables_changed, $total_new_rows, $incremental_dir) {
        $pdo = $this->db->get_pdo();
        if (!$pdo) {
            return false;
        }

        try {
            $snap_sequence = $this->getNextTrackingSequence($pdo);
            $tables_json = $this->buildIncrementalMetaJson($master_dir, $folder_name, $sequence, $tables_changed, $total_new_rows);
            $dir_size = $this->calculateDirectorySize($incremental_dir);

            return $this->insertIncrementalRecord($pdo, $snap_sequence, $folder_name, $incremental_dir, $tables_json, $total_new_rows, $dir_size);
        } catch (Exception $e) {
            $this->log(LogLevelType::Error->value, 'Failed to register incremental snapshot', array('error' => $e->getMessage()));
            return false;
        }
    }

    /** Get the next tracking sequence. */
    private function getNextTrackingSequence(PDO $pdo): int {
        $row = $pdo->query("SELECT MAX(sequence) as max_seq FROM " . TABLE_SNAPSHOTS)->fetch(PDO::FETCH_ASSOC);
        return ($row && $row['max_seq']) ? (int)$row['max_seq'] + 1 : 1;
    }

    /** Build the incremental metadata JSON. */
    private function buildIncrementalMetaJson(string $masterDir, string $folderName, int $sequence, int $tablesChanged, int $totalNewRows): string {
        return json_encode(array(
            'type' => 'incremental', 'master' => basename($masterDir), 'sequence' => $sequence,
            'folder' => $folderName, 'tables_changed' => $tablesChanged, 'total_new_rows' => $totalNewRows,
        ));
    }

    /** Calculate total size of a directory. */
    private function calculateDirectorySize(string $dir): int {
        if (RiseupBooleanHelpers::is_dir_missing($dir)) {
            return 0;
        }

        $size = 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        return $size;
    }

    /** Insert an incremental snapshot record. */
    private function insertIncrementalRecord(PDO $pdo, int $sequence, string $filename, string $filepath, string $tablesJson, int $totalRows, int $dirSize): int {
        $now = gmdate('c');
        $stmt = $pdo->prepare("INSERT INTO " . TABLE_SNAPSHOTS . "
            (sequence, filename, filepath, provider, scope, tables_json, total_rows,
             file_size, trigger_source, status, created_at, completed_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->execute(array(
            $sequence, $filename, $filepath, SNAPSHOT_PROVIDER_NATIVE, 'incremental',
            $tablesJson, $totalRows, $dirSize, SNAPSHOT_TRIGGER_API, SNAPSHOT_STATUS_COMPLETE, $now, $now,
        ));

        return (int)$pdo->lastInsertId();
    }

    /** Invalidate any cached ZIP export for the parent full snapshot. */
    private function invalidateParentZipExport($master_dir) {
        try {
            $parent = $this->findParentSnapshot($master_dir);
            if (!$parent) {
                return;
            }

            $this->doInvalidateZip($parent, $master_dir);
        } catch (Exception $e) {
            $this->log(LogLevelType::Warn->value, 'Failed to invalidate parent ZIP export', array('error' => $e->getMessage()));
        }
    }

    /** Find the parent snapshot record by filepath. */
    private function findParentSnapshot(string $master_dir): ?array {
        $pdo = $this->db->get_pdo();
        if (!$pdo) {
            return null;
        }

        $stmt = $pdo->prepare('SELECT id FROM ' . TABLE_SNAPSHOTS . ' WHERE filepath = ? AND status = ? LIMIT 1');
        $stmt->execute(array($master_dir, SNAPSHOT_STATUS_COMPLETE));
        $parent = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$parent) {
            $this->log(LogLevelType::Debug->value, 'No parent snapshot found for ZIP invalidation', array('master_dir' => basename($master_dir)));
            return null;
        }

        return $parent;
    }

    /** Perform the actual ZIP invalidation for a parent snapshot. */
    private function doInvalidateZip(array $parent, string $master_dir) {
        require_once dirname(__FILE__) . '/../SnapshotExporter.php';
        $exporter = RiseupSnapshotExporter::getInstance($this->logger, $this->db);
        if (!$exporter) {
            return;
        }

        $invalidated = $exporter->invalidateZip((int) $parent['id']);
        $this->log(LogLevelType::Info->value, 'Parent ZIP export invalidated after incremental backup', array(
            'parent_id' => $parent['id'], 'invalidated' => $invalidated,
        ));
    }
}
