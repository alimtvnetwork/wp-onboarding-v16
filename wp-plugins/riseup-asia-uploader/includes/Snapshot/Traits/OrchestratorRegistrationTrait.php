<?php
/**
 * OrchestratorRegistrationTrait — Snapshot record registration.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

trait OrchestratorRegistrationTrait {

    /**
     * Register the completed snapshot in the snapshots table.
     */
    private function registerSnapshot($title, $scope, $worker_result, $plugin_stats, $snapshot_dir) {
        $pdo = $this->db->get_pdo();
        if (!$pdo) {
            return false;
        }

        try {
            $sequence = $this->getNextSnapshotSequence($pdo);
            $tables_json = $this->buildSnapshotTablesJson($worker_result, $plugin_stats);
            $dir_size = $this->getDirectorySize($snapshot_dir);

            return $this->insertSnapshotRecord($pdo, $sequence, $snapshot_dir, $scope, $tables_json, $worker_result, $dir_size);
        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to register snapshot', array('error' => $e->getMessage()));
            return false;
        }
    }

    /** Get next snapshot sequence. */
    private function getNextSnapshotSequence(PDO $pdo): int {
        $row = $pdo->query("SELECT MAX(sequence) as max_seq FROM " . TABLE_SNAPSHOTS)->fetch(PDO::FETCH_ASSOC);
        return ($row && $row['max_seq']) ? (int)$row['max_seq'] + 1 : 1;
    }

    /** Build tables JSON metadata. */
    private function buildSnapshotTablesJson(array $workerResult, array $pluginStats): string {
        return json_encode(array(
            'exported' => $workerResult['tables'] ?? 0, 'total_rows' => $workerResult['total_rows'] ?? 0,
            'errors' => $workerResult['errors'] ?? array(), 'plugins' => $pluginStats['count'] ?? 0,
            'plugin_details' => $pluginStats['plugins'] ?? array(),
        ));
    }

    /** Insert a snapshot record. */
    private function insertSnapshotRecord(PDO $pdo, int $sequence, string $snapshotDir, string $scope, string $tablesJson, array $workerResult, int $dirSize): int {
        $now = gmdate('c');
        $stmt = $pdo->prepare("INSERT INTO " . TABLE_SNAPSHOTS . "
            (sequence, filename, filepath, provider, scope, tables_json, total_rows,
             file_size, trigger_source, status, created_at, completed_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->execute(array(
            $sequence, basename($snapshotDir), $snapshotDir, SNAPSHOT_PROVIDER_NATIVE, $scope,
            $tablesJson, $workerResult['total_rows'] ?? 0, $dirSize,
            SNAPSHOT_TRIGGER_API, SNAPSHOT_STATUS_COMPLETE, $now, $now,
        ));

        return (int)$pdo->lastInsertId();
    }
}
