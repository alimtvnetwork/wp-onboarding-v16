<?php
/**
 * WorkerJobProgressTrait — Snapshot job progress queries.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

use RiseupAsia\Enums\TableType;

trait WorkerJobProgressTrait {

    /**
     * Get the current progress of a snapshot job.
     *
     * @param int $job_id Job ID.
     * @return array|null Progress info or null.
     */
    public function getJobProgress($job_id) {
        $pdo = $this->db->getPdo();
        if (!$pdo) return null;

        $job = $this->getJob($pdo, $job_id);
        if (!$job) return null;

        $all_tables = json_decode($job['tables_json'], true);
        $total_tables = count($all_tables);
        $pool_size = (int) $job['pool_size'];
        $total_batches = (int) ceil($total_tables / $pool_size);

        $table_progress = $this->loadTableProgress($pdo);

        return array(
            'job_id' => (int) $job['id'], 'status' => $job['status'],
            'total_tables' => $total_tables, 'tables_exported' => (int) $job['tables_exported'],
            'total_rows' => (int) $job['total_rows'], 'pool_size' => $pool_size,
            'total_batches' => $total_batches, 'current_batch' => (int) $job['current_batch'],
            'errors' => json_decode($job['errors_json'] ?? '[]', true),
            'created_at' => $job['created_at'], 'updated_at' => $job['updated_at'],
            'completed_at' => $job['completed_at'], 'table_progress' => $table_progress,
            'percent' => $total_tables > 0 ? round(((int) $job['tables_exported'] / $total_tables) * 100, 1) : 0,
        );
    }

    /**
     * Load table-level progress records.
     *
     * @param PDO $pdo Database connection.
     * @return array Table progress records.
     */
    private function loadTableProgress(PDO $pdo): array {
        try {
            $stmt = $pdo->prepare("SELECT table_name, status, rows_total, rows_exported, error_message
                FROM " . TableType::SnapshotProgress->value . " WHERE snapshot_id = 0");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return array();
        }
    }
}
