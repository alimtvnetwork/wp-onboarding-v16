<?php
/**
 * ExporterHelpersTrait — helper methods for snapshot exporter.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\SnapshotExportStatusType;
use RiseupAsia\Enums\SnapshotModeType;
use RiseupAsia\Enums\SnapshotStatusType;
use RiseupAsia\Enums\TableType;

trait ExporterHelpersTrait {

    /**
     * Get a full snapshot record by ID (validates it's not incremental).
     *
     * @param int $snapshotId Snapshot ID.
     * @return array|null Snapshot record or null.
     */
    private function getFullSnapshot($snapshotId) {
        $pdo = $this->db->getPdo();
        if (!$pdo) {
            return null;
        }

        $stmt = $pdo->prepare('SELECT * FROM ' . TableType::Snapshots->value . ' WHERE id = ?');
        $stmt->execute(array($snapshotId));
        $snapshot = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$snapshot) {
            return null;
        }

        if ($snapshot['scope'] === SnapshotModeType::Incremental->value) {
            $this->log(LogLevelType::Warn->value, 'Cannot export incremental snapshot directly', array('id' => $snapshotId));
            return null;
        }

        if ($snapshot['status'] !== SnapshotStatusType::Complete->value) {
            $this->log(LogLevelType::Warn->value, 'Snapshot not complete', array('id' => $snapshotId, 'status' => $snapshot['status']));
            return null;
        }

        return $snapshot;
    }

    /**
     * Get a valid (non-expired) export record for a snapshot.
     *
     * @param int $snapshotId Full snapshot ID.
     * @return array|null Export record or null.
     */
    private function getValidExport($snapshotId) {
        $pdo = $this->db->getPdo();
        if (!$pdo) {
            return null;
        }

        $stmt = $pdo->prepare('SELECT * FROM ' . TableType::SnapshotExports->value . ' WHERE snapshot_id = ? AND status = ?');
        $stmt->execute(array($snapshotId, SnapshotExportStatusType::Valid->value));
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get an export record by ID.
     *
     * @param int $exportId Export ID.
     * @return array|null
     */
    private function getExportById($exportId) {
        $pdo = $this->db->getPdo();
        if (!$pdo) {
            return null;
        }

        $stmt = $pdo->prepare('SELECT * FROM ' . TableType::SnapshotExports->value . ' WHERE id = ?');
        $stmt->execute(array($exportId));
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Delete an export record.
     *
     * @param int $exportId Export ID.
     */
    private function deleteExportRecord($exportId) {
        $pdo = $this->db->getPdo();
        if (!$pdo) {
            return;
        }

        $stmt = $pdo->prepare('DELETE FROM ' . TableType::SnapshotExports->value . ' WHERE id = ?');
        $stmt->execute(array($exportId));
    }

    /**
     * Log helper.
     *
     * @param string $level   Log level.
     * @param string $message Log message.
     * @param array  $context Context data.
     */
    private function log($level, $message, $context = array()) {
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

    /**
     * Reset singleton (for testing).
     */
    public static function reset() {
        self::$instance = null;
    }
}
