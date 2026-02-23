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

use PDO;
use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\EndpointType;
use RiseupAsia\Enums\NonceType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\SnapshotErrorType;
use RiseupAsia\Enums\SnapshotExportStatusType;
use RiseupAsia\Enums\TableType;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\BooleanHelpers;
use RiseupAsia\Helpers\ResultHelper;

trait ExporterPublicApiTrait {
    /**
     * Get an existing valid ZIP or build a new one for the given full snapshot.
     *
     * @param int $fullSnapshotId The full snapshot's ID.
     * @return array {success: bool, export?: array, error?: string}
     */
    public function getOrBuildZip(int $fullSnapshotId): array {
        $this->log(LogLevelType::Info->value, 'getOrBuildZip called', array(ResponseKeyType::SnapshotId->value => $fullSnapshotId));

        $snapshot = $this->getFullSnapshot($fullSnapshotId);
        $isSnapshotMissing = ($snapshot === null || $snapshot === false);

        if ($isSnapshotMissing) {
            return ResultHelper::errorWithCode(
                'Full snapshot not found',
                SnapshotErrorType::NotFound->value,
            );
        }

        $existing = $this->getValidExport($fullSnapshotId);
        if ($existing && file_exists($existing['ZipPath'])) {
            $this->log(LogLevelType::Info->value, 'Returning cached ZIP export', array(
                'exportId' => $existing['Id'],
                'filename' => $existing['ZipFilename'],
            ));

            return ResultHelper::ok(array(
                ResponseKeyType::Cached->value => true,
                ResponseKeyType::Export->value => $existing,
            ));
        }

        if ($existing) {
            $this->deleteExportRecord($existing['Id']);
        }

        return $this->buildZip($snapshot);
    }

    /**
     * Invalidate (expire) the cached ZIP for a full snapshot.
     */
    public function invalidateZip(int $fullSnapshotId): bool {
        $this->log(LogLevelType::Info->value, 'Invalidating ZIP export', array(ResponseKeyType::SnapshotId->value => $fullSnapshotId));

        $pdo = $this->db->getPdo();
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

        if (file_exists($export['ZipPath'])) {
            @unlink($export['ZipPath']);
            $this->log(LogLevelType::Info->value, 'Deleted cached ZIP file', array('path' => basename($export['ZipPath'])));
        }

        $stmt = $pdo->prepare('UPDATE ' . TableType::SnapshotExports->value . ' SET Status = ?, ExpiresAt = datetime(\'now\') WHERE Id = ?');
        $stmt->execute(array(SnapshotExportStatusType::Expired->value, $export['Id']));

        $this->log(LogLevelType::Info->value, 'Export marked as expired', array('exportId' => $export['Id']));

        return true;
    }

    /**
     * Remove all export records and files for a full snapshot.
     */
    public function removeExports(int $fullSnapshotId): void {
        $pdo = $this->db->getPdo();
        $isPdoMissing = ($pdo === null);

        if ($isPdoMissing) {
            return;
        }

        $stmt = $pdo->prepare('SELECT Id, ZipPath FROM ' . TableType::SnapshotExports->value . ' WHERE SnapshotId = ?');
        $stmt->execute(array($fullSnapshotId));
        $exports = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($exports as $export) {
            $hasZipPath = (BooleanHelpers::hasValue($export['ZipPath']) && file_exists($export['ZipPath']));

            if ($hasZipPath) {
                @unlink($export['ZipPath']);
                $this->log(LogLevelType::Debug->value, 'Deleted export ZIP', array('path' => basename($export['ZipPath'])));
            }
        }

        $stmt = $pdo->prepare('DELETE FROM ' . TableType::SnapshotExports->value . ' WHERE SnapshotId = ?');
        $stmt->execute(array($fullSnapshotId));

        $this->log(LogLevelType::Info->value, 'Removed all exports for snapshot', array(ResponseKeyType::SnapshotId->value => $fullSnapshotId, ResponseKeyType::Count->value => count($exports)));
    }

    /**
     * Generate a time-limited download URL for an export.
     */
    public function getDownloadUrl(int $exportId): ?string {
        $export = $this->getExportById($exportId);
        $exportStatus = SnapshotExportStatusType::tryFrom($export['Status'] ?? '');
        $isExportInvalid = ($export === null || $export === false || $exportStatus === null || $exportStatus->isOtherThan(SnapshotExportStatusType::Valid));

        if ($isExportInvalid) {
            return null;
        }

        $nonce = wp_create_nonce(NonceType::SnapshotDownload->withSuffix($exportId));

        return rest_url(PluginConfigType::apiFullNamespace() . '/' . EndpointType::SnapshotDownloadFile->value . '?token=' . $nonce . '&id=' . $exportId);
    }

    /**
     * Validate a download token and return the export record.
     */
    public function validateDownloadToken(int $exportId, string $token): ?array {
        $valid = wp_verify_nonce($token, NonceType::SnapshotDownload->withSuffix($exportId));
        $isTokenInvalid = ($valid === false);

        if ($isTokenInvalid) {
            $this->log(LogLevelType::Warn->value, 'Invalid download token', array('exportId' => $exportId));
            return null;
        }

        $export = $this->getExportById($exportId);
        $isExportMissing = ($export === null || $export === false);

        if ($isExportMissing) {
            $this->log(LogLevelType::Warn->value, 'Export not found for download', array('exportId' => $exportId));
            return null;
        }

        $exportStatus = SnapshotExportStatusType::tryFrom($export['Status'] ?? '');
        $isExportNotValid = ($exportStatus === null || $exportStatus->isOtherThan(SnapshotExportStatusType::Valid));

        if ($isExportNotValid) {
            $this->log(LogLevelType::Warn->value, 'Export is not valid', array('exportId' => $exportId, 'status' => $export['Status']));
            return null;
        }

        if (PathHelper::isFileMissing($export['ZipPath'])) {
            $this->log(LogLevelType::Warn->value, 'Export ZIP file missing', array('path' => $export['ZipPath']));
            return null;
        }

        return $export;
    }

    /**
     * Get export status for a full snapshot.
     */
    public function getExportStatus(int $fullSnapshotId): ?array {
        $pdo = $this->db->getPdo();
        $isPdoMissing = ($pdo === null);

        if ($isPdoMissing) {
            return null;
        }

        $stmt = $pdo->prepare('SELECT * FROM ' . TableType::SnapshotExports->value . ' WHERE SnapshotId = ? ORDER BY CreatedAt DESC LIMIT 1');
        $stmt->execute(array($fullSnapshotId));

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
