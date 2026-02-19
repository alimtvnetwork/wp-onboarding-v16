<?php
/**
 * ExporterPublicApiTrait — public API methods for snapshot ZIP export.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\EndpointType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\SnapshotErrorType;
use RiseupAsia\Enums\SnapshotExportStatusType;
use RiseupAsia\Enums\TableType;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\BooleanHelpers;

trait ExporterPublicApiTrait {

    /**
     * Get an existing valid ZIP or build a new one for the given full snapshot.
     *
     * @param int $fullSnapshotId The full snapshot's ID.
     * @return array {success: bool, export?: array, error?: string}
     */
    public function getOrBuildZip(int $fullSnapshotId): array {
        $this->log(LogLevelType::Info->value, 'getOrBuildZip called', array('snapshot_id' => $fullSnapshotId));

        $snapshot = $this->getFullSnapshot($fullSnapshotId);
        $isSnapshotMissing = ($snapshot === null || $snapshot === false);
        if ($isSnapshotMissing) {
            return array('success' => false, 'error' => 'Full snapshot not found', 'code' => SnapshotErrorType::NotFound->value);
        }

        $existing = $this->getValidExport($fullSnapshotId);
        if ($existing && file_exists($existing['zip_path'])) {
            $this->log(LogLevelType::Info->value, 'Returning cached ZIP export', array('export_id' => $existing['id'], 'filename' => $existing['zip_filename']));

            return array('success' => true, 'cached' => true, 'export' => $existing);
        }

        if ($existing) {
            $this->deleteExportRecord($existing['id']);
        }

        return $this->buildZip($snapshot);
    }

    /**
     * Invalidate (expire) the cached ZIP for a full snapshot.
     */
    public function invalidateZip(int $fullSnapshotId): bool {
        $this->log(LogLevelType::Info->value, 'Invalidating ZIP export', array('snapshot_id' => $fullSnapshotId));

        $pdo = $this->db->get_pdo();
        $isPdoMissing = ($pdo === null);
        if ($isPdoMissing) {
            return false;
        }

        $export = $this->getValidExport($fullSnapshotId);
        $isExportMissing = ($export === null || $export === false);
        if ($isExportMissing) {
            $this->log(LogLevelType::Debug->value, 'No valid export to invalidate');
            return false;
        }

        if (file_exists($export['zip_path'])) {
            @unlink($export['zip_path']);
            $this->log(LogLevelType::Info->value, 'Deleted cached ZIP file', array('path' => basename($export['zip_path'])));
        }

        $stmt = $pdo->prepare('UPDATE ' . TableType::SnapshotExports->value . ' SET status = ?, expires_at = datetime(\'now\') WHERE id = ?');
        $stmt->execute(array(SnapshotExportStatusType::Expired->value, $export['id']));

        $this->log(LogLevelType::Info->value, 'Export marked as expired', array('export_id' => $export['id']));

        return true;
    }

    /**
     * Remove all export records and files for a full snapshot.
     */
    public function removeExports(int $fullSnapshotId): void {
        $pdo = $this->db->get_pdo();
        $isPdoMissing = ($pdo === null);
        if ($isPdoMissing) {
            return;
        }

        $stmt = $pdo->prepare('SELECT id, zip_path FROM ' . TableType::SnapshotExports->value . ' WHERE snapshot_id = ?');
        $stmt->execute(array($fullSnapshotId));
        $exports = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($exports as $export) {
            $hasZipPath = (BooleanHelpers::hasValue($export['zip_path']) && file_exists($export['zip_path']));
            if ($hasZipPath) {
                @unlink($export['zip_path']);
                $this->log(LogLevelType::Debug->value, 'Deleted export ZIP', array('path' => basename($export['zip_path'])));
            }
        }

        $stmt = $pdo->prepare('DELETE FROM ' . TableType::SnapshotExports->value . ' WHERE snapshot_id = ?');
        $stmt->execute(array($fullSnapshotId));

        $this->log(LogLevelType::Info->value, 'Removed all exports for snapshot', array('snapshot_id' => $fullSnapshotId, 'count' => count($exports)));
    }

    /**
     * Generate a time-limited download URL for an export.
     */
    public function getDownloadUrl(int $exportId): ?string {
        $export = $this->getExportById($exportId);
        $exportStatus = SnapshotExportStatusType::tryFrom($export['status'] ?? '');
        $isExportInvalid = ($export === null || $export === false || $exportStatus === null || $exportStatus->isOtherThan(SnapshotExportStatusType::Valid));
        if ($isExportInvalid) {
            return null;
        }

        $nonce = wp_create_nonce('riseup_snapshot_download_' . $exportId);

        return rest_url(PluginConfigType::apiFullNamespace() . '/' . EndpointType::SnapshotDownloadFile->value . '?token=' . $nonce . '&id=' . $exportId);
    }

    /**
     * Validate a download token and return the export record.
     */
    public function validateDownloadToken(int $exportId, string $token): ?array {
        $valid = wp_verify_nonce($token, 'riseup_snapshot_download_' . $exportId);
        $isTokenInvalid = ($valid === false);
        if ($isTokenInvalid) {
            $this->log(LogLevelType::Warn->value, 'Invalid download token', array('export_id' => $exportId));
            return null;
        }

        $export = $this->getExportById($exportId);
        $isExportMissing = ($export === null || $export === false);
        if ($isExportMissing) {
            $this->log(LogLevelType::Warn->value, 'Export not found for download', array('export_id' => $exportId));
            return null;
        }

        $exportStatus = SnapshotExportStatusType::tryFrom($export['status'] ?? '');
        $isExportNotValid = ($exportStatus === null || $exportStatus->isOtherThan(SnapshotExportStatusType::Valid));
        if ($isExportNotValid) {
            $this->log(LogLevelType::Warn->value, 'Export is not valid', array('export_id' => $exportId, 'status' => $export['status']));
            return null;
        }

        if (PathHelper::isFileMissing($export['zip_path'])) {
            $this->log(LogLevelType::Warn->value, 'Export ZIP file missing', array('path' => $export['zip_path']));
            return null;
        }

        return $export;
    }

    /**
     * Get export status for a full snapshot.
     */
    public function getExportStatus(int $fullSnapshotId): ?array {
        $pdo = $this->db->get_pdo();
        $isPdoMissing = ($pdo === null);
        if ($isPdoMissing) {
            return null;
        }

        $stmt = $pdo->prepare('SELECT * FROM ' . TableType::SnapshotExports->value . ' WHERE snapshot_id = ? ORDER BY created_at DESC LIMIT 1');
        $stmt->execute(array($fullSnapshotId));

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}