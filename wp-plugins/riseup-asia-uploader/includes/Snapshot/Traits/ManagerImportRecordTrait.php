<?php
/**
 * ManagerImportRecordTrait — Snapshot import record creation.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\TableType;

trait ManagerImportRecordTrait {

    /**
     * Get the next sequence number.
     *
     * @return int Next sequence.
     */
    private function getNextImportSequence() {
        $result = $this->db->query_single('SELECT MAX(sequence) as max_seq FROM ' . TableType::Snapshots->value);
        return ($result && isset($result['max_seq'])) ? (int)$result['max_seq'] + 1 : 1;
    }

    /**
     * Create a database record for an imported snapshot.
     *
     * @param array  $manifest Original manifest.
     * @param int    $sequence New sequence number.
     * @param string $filename New filename.
     * @param string $filepath Full path.
     * @return int|false Snapshot ID or false.
     */
    private function createImportedSnapshotRecord($manifest, $sequence, $filename, $filepath) {
        $data = $this->buildImportRecord($manifest['snapshot'], $manifest, $sequence, $filename, $filepath);
        $result = $this->db->insert(TableType::Snapshots->value, $data);
        return $result ? $this->db->lastInsertId() : false;
    }

    /** Build the database record for an imported snapshot. */
    private function buildImportRecord(array $snapshot_data, array $manifest, int $sequence, string $filename, string $filepath): array {
        return array(
            'sequence' => $sequence, 'filename' => $filename, 'filepath' => $filepath,
            'provider' => SNAPSHOT_PROVIDER_NATIVE, 'scope' => $snapshot_data['scope'],
            'tables_json' => json_encode($snapshot_data['tables']),
            'total_rows' => $snapshot_data['total_rows'] ?? 0, 'file_size' => filesize($filepath),
            'trigger_source' => 'import', 'status' => SNAPSHOT_STATUS_COMPLETE,
            'created_at' => date('c'), 'completed_at' => date('c'),
            'import_source' => json_encode(array(
                'original_id' => $snapshot_data['id'] ?? null,
                'original_created_at' => $snapshot_data['created_at'] ?? null,
                'source_site' => $manifest['source']['site_url'] ?? null,
            )),
        );
    }
}
