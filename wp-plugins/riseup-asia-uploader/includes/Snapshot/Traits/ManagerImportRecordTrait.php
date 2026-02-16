<?php
/**
 * ManagerImportRecordTrait — Snapshot import record creation.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   2.0.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\SnapshotProviderType;
use RiseupAsia\Enums\SnapshotStatusType;
use RiseupAsia\Enums\TableType;

trait ManagerImportRecordTrait {

    private function getNextImportSequence(): int {
        $result = $this->db->querySingle('SELECT MAX(sequence) as max_seq FROM ' . TableType::Snapshots->value);
        return ($result && isset($result['max_seq'])) ? (int)$result['max_seq'] + 1 : 1;
    }

    private function createImportedSnapshotRecord(array $manifest, int $sequence, string $filename, string $filepath): int|false {
        $data = $this->buildImportRecord($manifest['snapshot'], $manifest, $sequence, $filename, $filepath);
        $result = $this->db->insert(TableType::Snapshots->value, $data);
        return $result ? $this->db->lastInsertId() : false;
    }

    private function buildImportRecord(array $snapshotData, array $manifest, int $sequence, string $filename, string $filepath): array {
        return array(
            'sequence' => $sequence, 'filename' => $filename, 'filepath' => $filepath,
            'provider' => SnapshotProviderType::Native->value, 'scope' => $snapshotData['scope'],
            'tables_json' => json_encode($snapshotData['tables']),
            'total_rows' => $snapshotData['total_rows'] ?? 0, 'file_size' => filesize($filepath),
            'trigger_source' => 'import', 'status' => SnapshotStatusType::Complete->value,
            'created_at' => date('c'), 'completed_at' => date('c'),
            'import_source' => json_encode(array(
                'original_id' => $snapshotData['id'] ?? null,
                'original_created_at' => $snapshotData['created_at'] ?? null,
                'source_site' => $manifest['source']['site_url'] ?? null,
            )),
        );
    }
}
