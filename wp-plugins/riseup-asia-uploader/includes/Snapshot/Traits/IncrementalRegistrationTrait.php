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
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\SnapshotModeType;
use RiseupAsia\Enums\SnapshotProviderType;
use RiseupAsia\Enums\SnapshotStatusType;
use RiseupAsia\Enums\SnapshotTriggerType;
use RiseupAsia\Enums\TableType;
use RiseupAsia\Helpers\DateHelper;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Snapshot\SnapshotExporter;

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
            return $this->createIncrementalRecord($pdo, $masterDir, $folderName, $sequence, $tablesChanged, $totalNewRows, $incrementalDir);
        } catch (Throwable $e) {
            $this->logError($e, 'Failed to register incremental snapshot');

            return false;
        }
    }

    private function createIncrementalRecord(
        PDO $pdo,
        string $masterDir,
        string $folderName,
        int $sequence,
        int $tablesChanged,
        int $totalNewRows,
        string $incrementalDir,
    ): int {
        $snapSequence = $this->getNextTrackingSequence($pdo);
        $tablesJson = $this->buildIncrementalMetaJson($masterDir, $folderName, $sequence, $tablesChanged, $totalNewRows);
        $dirSize = $this->calculateDirectorySize($incrementalDir);

        return $this->insertIncrementalRecord($pdo, $snapSequence, $folderName, $incrementalDir, $tablesJson, $totalNewRows, $dirSize);
    }

    private function getNextTrackingSequence(PDO $pdo): int {
        $row = $pdo->query("SELECT MAX(Sequence) as max_seq FROM " . TableType::Snapshots->value)->fetch(PDO::FETCH_ASSOC);

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
            ResponseKeyType::Type->value           => SnapshotModeType::Incremental->value,
            'master'                               => basename($masterDir),
            'sequence'                             => $sequence,
            ResponseKeyType::Folder->value         => $folderName,
            ResponseKeyType::TablesChanged->value  => $tablesChanged,
            ResponseKeyType::TotalNewRows->value   => $totalNewRows,
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
        $now = DateHelper::nowIso();

        $stmt = $pdo->prepare("INSERT INTO " . TableType::Snapshots->value . "
            (Sequence, Filename, Filepath, Provider, Scope, TablesJson, TotalRows,
             FileSize, TriggerSource, Status, CreatedAt, CompletedAt)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->execute($this->buildIncrementalInsertValues($sequence, $filename, $filepath, $tablesJson, $totalRows, $dirSize, $now));

        return (int)$pdo->lastInsertId();
    }

    private function buildIncrementalInsertValues(
        int $sequence,
        string $filename,
        string $filepath,
        string $tablesJson,
        int $totalRows,
        int $dirSize,
        string $now,
    ): array {
        return array(
            $sequence,
            $filename,
            $filepath,
            SnapshotProviderType::Native->value,
            SnapshotModeType::Incremental->value,
            $tablesJson,
            $totalRows,
            $dirSize,
            SnapshotTriggerType::Api->value,
            SnapshotStatusType::Complete->value,
            $now,
            $now,
        );
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
            $this->logWarn($e, 'Failed to invalidate parent ZIP export');
        }
    }

    private function findParentSnapshot(string $masterDir): ?array {
        $pdo = $this->db->getPdo();
        $isPdoMissing = ($pdo === null);

        if ($isPdoMissing) {
            return null;
        }

        $stmt = $pdo->prepare('SELECT Id FROM ' . TableType::Snapshots->value . ' WHERE Filepath = ? AND Status = ? LIMIT 1');
        $stmt->execute(array($masterDir, SnapshotStatusType::Complete->value));
        $parent = $stmt->fetch(PDO::FETCH_ASSOC);

        $isParentMissing = ($parent === false || $parent === null);

        if ($isParentMissing) {
            $this->log(LogLevelType::Debug->value, 'No parent snapshot found for ZIP invalidation', array('masterDir' => basename($masterDir)));

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

        $invalidated = $exporter->invalidateZip((int) $parent['Id']);
        $this->log(LogLevelType::Info->value, 'Parent ZIP export invalidated after incremental backup', array(
            'parentId'    => $parent['Id'],
            'invalidated' => $invalidated,
        ));
    }
}
