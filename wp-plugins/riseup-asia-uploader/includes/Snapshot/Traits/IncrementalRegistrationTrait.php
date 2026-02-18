<?php
/**
 * IncrementalRegistrationTrait — Snapshot registration and ZIP invalidation.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   2.0.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use PDO;
use Throwable;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\SnapshotProviderType;
use RiseupAsia\Snapshot\SnapshotExporter;
use RiseupAsia\Enums\SnapshotStatusType;
use RiseupAsia\Enums\SnapshotTriggerType;
use RiseupAsia\Enums\TableType;
use RiseupAsia\Helpers\PathHelper;

trait IncrementalRegistrationTrait {

    private function registerIncrementalSnapshot(
        string $title,
        string $masterDir,
        string $folderName,
        int $sequence,
        int $tablesChanged,
        int $totalNewRows,
        string $incrementalDir,
    ): int|false {
        $pdo = $this->db->getPdo();
        $isPdoMissing = ($pdo === null);
        if ($isPdoMissing) {
            return false;
        }

        try {
            $snap_sequence = $this->getNextTrackingSequence($pdo);
            $tables_json = $this->buildIncrementalMetaJson($masterDir, $folderName, $sequence, $tablesChanged, $totalNewRows);
            $dir_size = $this->calculateDirectorySize($incrementalDir);

            return $this->insertIncrementalRecord($pdo, $snap_sequence, $folderName, $incrementalDir, $tables_json, $totalNewRows, $dir_size);
        } catch (Throwable $e) {
            $this->log(LogLevelType::Error->value, 'Failed to register incremental snapshot', array('error' => $e->getMessage()));

            return false;
        }
    }

    private function getNextTrackingSequence(PDO $pdo): int {
        $row = $pdo->query("SELECT MAX(sequence) as max_seq FROM " . TableType::Snapshots->value)->fetch(PDO::FETCH_ASSOC);

        return ($row && $row['max_seq']) ? (int)$row['max_seq'] + 1 : 1;
    }

    private function buildIncrementalMetaJson(
        string $masterDir,
        string $folderName,
        int $sequence,
        int $tablesChanged,
        int $totalNewRows,
    ): string {

        return json_encode(array(
            'type' => 'incremental', 'master' => basename($masterDir), 'sequence' => $sequence,
            'folder' => $folderName, 'tables_changed' => $tablesChanged, 'total_new_rows' => $totalNewRows,
        ));
    }

    private function calculateDirectorySize(string $dir): int {
        if (PathHelper::isDirMissing($dir)) {
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

    private function insertIncrementalRecord(
        PDO $pdo,
        int $sequence,
        string $filename,
        string $filepath,
        string $tablesJson,
        int $totalRows,
        int $dirSize,
    ): int {
        $now = gmdate('c');
        $stmt = $pdo->prepare("INSERT INTO " . TableType::Snapshots->value . "
            (sequence, filename, filepath, provider, scope, tables_json, total_rows,
             file_size, trigger_source, status, created_at, completed_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->execute(array(
            $sequence, $filename, $filepath, SnapshotProviderType::Native->value, 'incremental',
            $tablesJson, $totalRows, $dirSize, SnapshotTriggerType::Api->value, SnapshotStatusType::Complete->value, $now, $now,
        ));

        return (int)$pdo->lastInsertId();
    }

    private function invalidateParentZipExport(string $masterDir): void {
        try {
            $parent = $this->findParentSnapshot($masterDir);
            $isParentMissing = ($parent === null);
            if ($isParentMissing) {
                return;
            }

            $this->doInvalidateZip($parent, $masterDir);
        } catch (Throwable $e) {
            $this->log(LogLevelType::Warn->value, 'Failed to invalidate parent ZIP export', array('error' => $e->getMessage()));
        }
    }

    private function findParentSnapshot(string $masterDir): ?array {
        $pdo = $this->db->getPdo();
        $isPdoMissing = ($pdo === null);
        if ($isPdoMissing) {
            return null;
        }

        $stmt = $pdo->prepare('SELECT id FROM ' . TableType::Snapshots->value . ' WHERE filepath = ? AND status = ? LIMIT 1');
        $stmt->execute(array($masterDir, SnapshotStatusType::Complete->value));
        $parent = $stmt->fetch(PDO::FETCH_ASSOC);

        $isParentMissing = ($parent === false || $parent === null);
        if ($isParentMissing) {
            $this->log(LogLevelType::Debug->value, 'No parent snapshot found for ZIP invalidation', array('master_dir' => basename($masterDir)));

            return null;
        }

        return $parent;
    }

    private function doInvalidateZip(array $parent, string $masterDir): void {
        $exporter = SnapshotExporter::getInstance($this->logger, $this->db);
        $isExporterMissing = ($exporter === null);
        if ($isExporterMissing) {
            return;
        }

        $invalidated = $exporter->invalidateZip((int) $parent['id']);
        $this->log(LogLevelType::Info->value, 'Parent ZIP export invalidated after incremental backup', array(
            'parent_id' => $parent['id'], 'invalidated' => $invalidated,
        ));
    }
}
