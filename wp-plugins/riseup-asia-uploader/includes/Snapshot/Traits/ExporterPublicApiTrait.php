<?php
/**
 * ExporterPublicApiTrait — public API methods for snapshot ZIP export.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\EndpointType;
use RiseupAsia\Enums\SnapshotExportStatusType;
use RiseupAsia\Enums\TableType;

trait ExporterPublicApiTrait {

    /**
     * Get an existing valid ZIP or build a new one for the given full snapshot.
     *
     * @param int $fullSnapshotId The full snapshot's ID.
     * @return array {success: bool, export?: array, error?: string}
     */
    public function getOrBuildZip($fullSnapshotId) {
        $this->log(LogLevelType::Info->value, 'getOrBuildZip called', array('snapshot_id' => $fullSnapshotId));

        $snapshot = $this->getFullSnapshot($fullSnapshotId);
        if (!$snapshot) {
            return array('success' => false, 'error' => 'Full snapshot not found', 'code' => ERR_SNAPSHOT_NOT_FOUND);
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
     *
     * @param int $fullSnapshotId The full snapshot's ID.
     * @return bool True if an export was invalidated.
     */
    public function invalidateZip($fullSnapshotId) {
        $this->log(LogLevelType::Info->value, 'Invalidating ZIP export', array('snapshot_id' => $fullSnapshotId));

        $pdo = $this->db->get_pdo();
        if (!$pdo) {
            return false;
        }

        $export = $this->getValidExport($fullSnapshotId);
        if (!$export) {
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
     *
     * @param int $fullSnapshotId The full snapshot's ID.
     */
    public function removeExports($fullSnapshotId) {
        $pdo = $this->db->get_pdo();
        if (!$pdo) {
            return;
        }

        $stmt = $pdo->prepare('SELECT id, zip_path FROM ' . TableType::SnapshotExports->value . ' WHERE snapshot_id = ?');
        $stmt->execute(array($fullSnapshotId));
        $exports = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($exports as $export) {
            if (!empty($export['zip_path']) && file_exists($export['zip_path'])) {
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
     *
     * @param int $exportId The export record ID.
     * @return string|null Download URL or null.
     */
    public function getDownloadUrl($exportId) {
        $export = $this->getExportById($exportId);
        if (!$export || $export['status'] !== SnapshotExportStatusType::Valid->value) {
            return null;
        }

        $nonce = wp_create_nonce('riseup_snapshot_download_' . $exportId);
        return rest_url(API_FULL_NAMESPACE . '/' . EndpointType::SnapshotDownloadFile->value . '?token=' . $nonce . '&id=' . $exportId);
    }

    /**
     * Validate a download token and return the export record.
     *
     * @param int    $exportId The export ID.
     * @param string $token    The nonce token.
     * @return array|null The export record, or null if invalid.
     */
    public function validateDownloadToken($exportId, $token) {
        $valid = wp_verify_nonce($token, 'riseup_snapshot_download_' . $exportId);
        if (!$valid) {
            $this->log(LogLevelType::Warn->value, 'Invalid download token', array('export_id' => $exportId));
            return null;
        }

        $export = $this->getExportById($exportId);
        if (!$export) {
            $this->log(LogLevelType::Warn->value, 'Export not found for download', array('export_id' => $exportId));
            return null;
        }

        if ($export['status'] !== SnapshotExportStatusType::Valid->value) {
            $this->log(LogLevelType::Warn->value, 'Export is not valid', array('export_id' => $exportId, 'status' => $export['status']));
            return null;
        }

        if (RiseupBooleanHelpers::is_file_missing($export['zip_path'])) {
            $this->log(LogLevelType::Warn->value, 'Export ZIP file missing', array('path' => $export['zip_path']));
            return null;
        }

        return $export;
    }

    /**
     * Get export status for a full snapshot.
     *
     * @param int $fullSnapshotId The full snapshot's ID.
     * @return array|null Export metadata or null.
     */
    public function getExportStatus($fullSnapshotId) {
        $pdo = $this->db->get_pdo();
        if (!$pdo) {
            return null;
        }

        $stmt = $pdo->prepare('SELECT * FROM ' . TableType::SnapshotExports->value . ' WHERE snapshot_id = ? ORDER BY created_at DESC LIMIT 1');
        $stmt->execute(array($fullSnapshotId));
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
