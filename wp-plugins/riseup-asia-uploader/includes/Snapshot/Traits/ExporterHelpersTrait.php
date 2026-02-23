<?php
/**
 * ExporterHelpersTrait — helper methods for snapshot exporter.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use PDO;
use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\SnapshotExportStatusType;
use RiseupAsia\Enums\SnapshotModeType;
use RiseupAsia\Enums\SnapshotStatusType;
use RiseupAsia\Enums\TableType;
use RiseupAsia\Helpers\BooleanHelpers;

trait ExporterHelpersTrait {
    /** Get a full snapshot record by ID (validates it's not incremental). */
    private function getFullSnapshot(int $snapshotId): ?array {
        $pdo = $this->db->getPdo();
        $isPdoMissing = ($pdo === null);

        if ($isPdoMissing) {
            return null;
        }

        $stmt = $pdo->prepare('SELECT * FROM ' . TableType::Snapshots->value . ' WHERE Id = ?');
        $stmt->execute(array($snapshotId));
        $snapshot = $stmt->fetch(PDO::FETCH_ASSOC);

        $isSnapshotMissing = ($snapshot === null || $snapshot === false);

        if ($isSnapshotMissing) {
            return null;
        }

        if ($snapshot['Scope'] === SnapshotModeType::Incremental->value) {
            $this->log(LogLevelType::Warn->value, 'Cannot export incremental snapshot directly', array('id' => $snapshotId));

            return null;
        }

        $snapshotStatus = SnapshotStatusType::tryFrom($snapshot['Status'] ?? '');
        $isSnapshotIncomplete = ($snapshotStatus === null || $snapshotStatus->isOtherThan(SnapshotStatusType::Complete));

        if ($isSnapshotIncomplete) {
            $this->log(LogLevelType::Warn->value, 'Snapshot not complete', array(
                'id'     => $snapshotId,
                'status' => $snapshot['Status'],
            ));

            return null;
        }

        return $snapshot;
    }

    /** Get a valid (non-expired) export record for a snapshot. */
    private function getValidExport(int $snapshotId): ?array {
        $pdo = $this->db->getPdo();
        $isPdoMissing = ($pdo === null);

        if ($isPdoMissing) {
            return null;
        }

        $stmt = $pdo->prepare('SELECT * FROM ' . TableType::SnapshotExports->value . ' WHERE SnapshotId = ? AND Status = ?');
        $stmt->execute(array($snapshotId, SnapshotExportStatusType::Valid->value));

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Get an export record by ID. */
    private function getExportById(int $exportId): ?array {
        $pdo = $this->db->getPdo();
        $isPdoMissing = ($pdo === null);

        if ($isPdoMissing) {
            return null;
        }

        $stmt = $pdo->prepare('SELECT * FROM ' . TableType::SnapshotExports->value . ' WHERE Id = ?');
        $stmt->execute(array($exportId));

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Delete an export record. */
    private function deleteExportRecord(int $exportId): void {
        $pdo = $this->db->getPdo();
        $isPdoMissing = ($pdo === null);

        if ($isPdoMissing) {
            return;
        }

        $stmt = $pdo->prepare('DELETE FROM ' . TableType::SnapshotExports->value . ' WHERE Id = ?');
        $stmt->execute(array($exportId));
    }

    /** Log helper. */
    private function log(
        string $level,
        string $message,
        array $context = array(),
    ): void {
        $context['class'] = 'RiseupSnapshotExporter';

        switch ($level) {
            case LogLevelType::Error->value:
                $this->logger->error('[SnapshotExporter] ' . $message, $context);
                break;
            case LogLevelType::Warn->value:
                $this->logger->warn('[SnapshotExporter] ' . $message, $context);
                break;
            case LogLevelType::Debug->value:
                $this->logger->debug('[SnapshotExporter] ' . $message, $context);
                break;
            default:
                $this->logger->info('[SnapshotExporter] ' . $message, $context);
                break;
        }
    }

    /** Reset singleton (for testing). */
    public static function reset(): void {
        self::$instance = null;
    }
}
