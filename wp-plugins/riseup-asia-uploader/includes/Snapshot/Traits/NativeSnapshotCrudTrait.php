<?php
/**
 * NativeSnapshotCrudTrait — Snapshot delete, export, import, list, and get operations.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\TableType;

trait NativeSnapshotCrudTrait {

    public function deleteSnapshot(int $snapshotId): array {
        $snapshot = $this->getSnapshot($snapshotId);
        if (!$snapshot) {
            return array('success' => false, 'error' => 'Snapshot not found');
        }

        $filepath = $snapshot['filepath'];
        if (RiseupPathUtils::fileExists($filepath)) {
            if (!RiseupPathUtils::deleteFile($filepath)) {
                $this->log(LogLevelType::Error->value, 'Failed to delete snapshot file', array('filepath' => $filepath));
                return array('success' => false, 'error' => 'Failed to delete snapshot file');
            }
        }

        $zip_path = str_replace('.sqlite', '.zip', $filepath);
        if (RiseupPathUtils::fileExists($zip_path)) {
            RiseupPathUtils::deleteFile($zip_path);
        }

        $this->db->delete(TableType::Snapshots->value, array('id' => $snapshotId));
        $this->log(LogLevelType::Info->value, 'Snapshot deleted', array('snapshot_id' => $snapshotId, 'filename' => $snapshot['filename']));
        return array('success' => true);
    }

    public function exportSnapshot(int $snapshotId): array {
        $snapshot = $this->getSnapshot($snapshotId);
        if (!$snapshot) {
            return array('success' => false, 'error' => 'Snapshot not found');
        }

        $filepath = $snapshot['filepath'];
        if (!RiseupPathUtils::fileExists($filepath)) {
            return array('success' => false, 'error' => 'Snapshot file not found');
        }

        return $this->createExportZip($snapshotId, $filepath, $snapshot);
    }

    private function createExportZip(int $snapshotId, string $filepath, array $snapshot): array {
        $zip_path = str_replace('.sqlite', '.zip', $filepath);

        $zip = new ZipArchive();
        if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return array('success' => false, 'error' => 'Failed to create ZIP file');
        }

        $zip->addFile($filepath, basename($filepath));
        $zip->addFromString('manifest.json', json_encode($this->buildExportManifest($snapshotId, $snapshot), JSON_PRETTY_PRINT));
        $zip->close();

        return array('success' => true, 'filepath' => $zip_path, 'filename' => basename($zip_path), 'size' => filesize($zip_path));
    }

    private function buildExportManifest(int $snapshotId, array $snapshot): array {
        return array(
            'version' => PLUGIN_VERSION, 'created_at' => date('c'), 'snapshot_id' => $snapshotId,
            'filename' => $snapshot['filename'], 'scope' => $snapshot['scope'],
            'tables' => json_decode($snapshot['tables_json'], true),
            'total_rows' => $snapshot['total_rows'], 'file_size' => $snapshot['file_size'],
        );
    }

    public function importSnapshot(string $filepath): array {
        $manager = RiseupSnapshotManager::getInstance($this->logger, $this->db);
        return $manager->importSnapshot($filepath);
    }

    public function restoreSnapshot(int $snapshotId, array $options): array {
        $manager = RiseupSnapshotManager::getInstance($this->logger, $this->db);
        return $manager->restoreSnapshot($snapshotId, $options);
    }

    public function getSnapshot(int $snapshotId): ?array {
        return $this->db->querySingle('SELECT * FROM ' . TableType::Snapshots->value . ' WHERE id = ?', array($snapshotId));
    }

    public function listSnapshots(int $limit = 50, int $offset = 0): array {
        $snapshots = $this->db->queryAll(
            'SELECT * FROM ' . TableType::Snapshots->value . ' WHERE provider = ? ORDER BY created_at DESC LIMIT ? OFFSET ?',
            array($this->provider_id, $limit, $offset)
        );
        $total = $this->db->querySingle('SELECT COUNT(*) as count FROM ' . TableType::Snapshots->value . ' WHERE provider = ?', array($this->provider_id));
        return array('snapshots' => $snapshots ?: array(), 'total' => $total ? (int)$total['count'] : 0);
    }

    public function getAvailableTables(): array {
        $tables = array();
        $all_tables = $this->wpdb->get_results("SHOW TABLE STATUS", ARRAY_A);
        foreach ($all_tables as $table_info) {
            $tables[] = array(
                'name' => $table_info['Name'], 'rows' => (int)$table_info['Rows'],
                'size' => (int)$table_info['Data_length'] + (int)$table_info['Index_length'],
                'is_core' => strpos($table_info['Name'], $this->wpdb->prefix) === 0,
            );
        }
        return $tables;
    }
}
