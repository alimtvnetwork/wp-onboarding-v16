<?php
/**
 * NativeSnapshotCrudTrait — Snapshot delete, export, import, list, and get operations.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use ZipArchive;
use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\ResponseMessageType;
use RiseupAsia\Enums\TableType;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Snapshot\SnapshotManager;

trait NativeSnapshotCrudTrait {

    public function deleteSnapshot(int $snapshotId): array {
        $snapshot = $this->getSnapshot($snapshotId);
        $isSnapshotMissing = ($snapshot === null);
        if ($isSnapshotMissing) {

            return array(ResponseKeyType::Success->value => false, ResponseKeyType::Error->value => ResponseMessageType::SnapshotNotFound->value);
        }

        $filepath = $snapshot['filepath'];
        if (PathHelper::fileExists($filepath)) {
            $isDeleteFailed = (PathHelper::deleteFile($filepath) === false);
            if ($isDeleteFailed) {
                $this->log(LogLevelType::Error->value, 'Failed to delete snapshot file', array('filepath' => $filepath));

                return array(ResponseKeyType::Success->value => false, ResponseKeyType::Error->value => 'Failed to delete snapshot file');
            }
        }

        $zip_path = str_replace('.sqlite', '.zip', $filepath);
        if (PathHelper::fileExists($zip_path)) {
            PathHelper::deleteFile($zip_path);
        }

        $this->db->delete(TableType::Snapshots->value, array('id' => $snapshotId));
        $this->log(LogLevelType::Info->value, 'Snapshot deleted', array(ResponseKeyType::SnapshotId->value => $snapshotId, ResponseKeyType::Filename->value => $snapshot['filename']));

        return array(ResponseKeyType::Success->value => true);
    }

    public function exportSnapshot(int $snapshotId): array {
        $snapshot = $this->getSnapshot($snapshotId);
        $isSnapshotMissing = ($snapshot === null);
        if ($isSnapshotMissing) {

            return array(ResponseKeyType::Success->value => false, ResponseKeyType::Error->value => ResponseMessageType::SnapshotNotFound->value);
        }

        $filepath = $snapshot['filepath'];
        if (PathHelper::isFileMissing($filepath)) {

            return array(ResponseKeyType::Success->value => false, ResponseKeyType::Error->value => ResponseMessageType::SnapshotFileMissing->value);
        }

        return $this->createExportZip($snapshotId, $filepath, $snapshot);
    }

    private function createExportZip(
        int $snapshotId,
        string $filepath,
        array $snapshot,
    ): array {
        $zip_path = str_replace('.sqlite', '.zip', $filepath);

        $zip = new ZipArchive();
        if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {

            return array(ResponseKeyType::Success->value => false, ResponseKeyType::Error->value => ResponseMessageType::ZipCreateFailed->value);
        }

        $zip->addFile($filepath, basename($filepath));
        $zip->addFromString('manifest.json', json_encode($this->buildExportManifest($snapshotId, $snapshot), JSON_PRETTY_PRINT));
        $zip->close();

        return array(ResponseKeyType::Success->value => true, 'filepath' => $zip_path, ResponseKeyType::Filename->value => basename($zip_path), ResponseKeyType::Size->value => filesize($zip_path));
    }

    private function buildExportManifest(int $snapshotId, array $snapshot): array {

        return array(
            'version' => PluginConfigType::Version->value, 'created_at' => date('c'), ResponseKeyType::SnapshotId->value => $snapshotId,
            ResponseKeyType::Filename->value => $snapshot['filename'], ResponseKeyType::Scope->value => $snapshot['scope'],
            ResponseKeyType::Tables->value => json_decode($snapshot['tables_json'], true),
            ResponseKeyType::TotalRows->value => $snapshot['total_rows'], ResponseKeyType::FileSize->value => $snapshot['file_size'],
        );
    }

    public function importSnapshot(string $filepath): array {
        $manager = SnapshotManager::getInstance($this->logger, $this->db);

        return $manager->importSnapshot($filepath);
    }

    public function restoreSnapshot(int $snapshotId, array $options): array {
        $manager = SnapshotManager::getInstance($this->logger, $this->db);

        return $manager->restoreSnapshot($snapshotId, $options);
    }

    public function getSnapshot(int $snapshotId): ?array {

        return $this->db->querySingle('SELECT * FROM ' . TableType::Snapshots->value . ' WHERE id = ?', array($snapshotId));
    }

    public function listSnapshots(int $limit = 50, int $offset = 0): array { // PaginationConfigType::DefaultLimit
        $snapshots = $this->db->queryAll(
            'SELECT * FROM ' . TableType::Snapshots->value . ' WHERE provider = ? ORDER BY created_at DESC LIMIT ? OFFSET ?',
            array($this->provider_id, $limit, $offset)
        );
        $total = $this->db->querySingle('SELECT COUNT(*) as count FROM ' . TableType::Snapshots->value . ' WHERE provider = ?', array($this->provider_id));

        return array(ResponseKeyType::Snapshots->value => $snapshots ?: array(), ResponseKeyType::Total->value => $total ? (int)$total[ResponseKeyType::Count->value] : 0);
    }

    public function getAvailableTables(): array {
        $tables = array();
        $all_tables = $this->wpdb->get_results("SHOW TABLE STATUS", ARRAY_A);
        foreach ($all_tables as $table_info) {
            $tables[] = array(
                'name' => $table_info['Name'], ResponseKeyType::Rows->value => (int)$table_info['Rows'],
                ResponseKeyType::Size->value => (int)$table_info['Data_length'] + (int)$table_info['Index_length'],
                'is_core' => strpos($table_info['Name'], $this->wpdb->prefix) === 0,
            );
        }

        return $tables;
    }
}
