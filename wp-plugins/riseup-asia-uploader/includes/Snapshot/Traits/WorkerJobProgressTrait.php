<?php
/**
 * WorkerJobProgressTrait — Snapshot job progress queries.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   2.0.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use PDO;
use Throwable;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\TableType;

trait WorkerJobProgressTrait {

    public function getJobProgress(int $jobId): ?array {
        $pdo = $this->db->getPdo();
        $isPdoMissing = ($pdo === null);

        if ($isPdoMissing) {
            return null;
        }

        $job = $this->getJob($pdo, $jobId);
        $isJobMissing = ($job === null || $job === false);

        if ($isJobMissing) {
            return null;
        }

        $all_tables = json_decode($job['TablesJson'], true);
        $total_tables = count($all_tables);
        $pool_size = (int) $job['PoolSize'];
        $total_batches = (int) ceil($total_tables / $pool_size);

        $table_progress = $this->loadTableProgress($pdo);

        return array(
            'job_id' => (int) $job['Id'], 'status' => $job['Status'],
            'total_tables' => $total_tables, 'tables_exported' => (int) $job['TablesExported'],
            ResponseKeyType::TotalRows->value => (int) $job['TotalRows'], 'pool_size' => $pool_size,
            'total_batches' => $total_batches, 'current_batch' => (int) $job['CurrentBatch'],
            ResponseKeyType::Errors->value => json_decode($job['ErrorsJson'] ?? '[]', true),
            'created_at' => $job['CreatedAt'], 'updated_at' => $job['UpdatedAt'],
            'completed_at' => $job['CompletedAt'], 'table_progress' => $table_progress,
            'percent' => $total_tables > 0 ? round(((int) $job['TablesExported'] / $total_tables) * 100, 1) : 0,
        );
    }

    private function loadTableProgress(PDO $pdo): array {
        try {
            $stmt = $pdo->prepare("SELECT TableName, Status, RowsTotal, RowsExported, ErrorMessage
                FROM " . TableType::SnapshotProgress->value . " WHERE SnapshotId = 0");
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {

            return array();
        }
    }
}
