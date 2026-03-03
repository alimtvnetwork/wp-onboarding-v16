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

        $allTables = json_decode($job['TablesJson'], true);
        $totalTables = count($allTables);
        $poolSize = (int) $job['PoolSize'];
        $totalBatches = (int) ceil($totalTables / $poolSize);

        $tableProgress = $this->loadTableProgress($pdo);

        return array(
            ResponseKeyType::JobId->value          => (int) $job['Id'],
            ResponseKeyType::Status->value         => $job['Status'],
            ResponseKeyType::TotalTables->value    => $totalTables,
            ResponseKeyType::TablesExported->value => (int) $job['TablesExported'],
            ResponseKeyType::TotalRows->value      => (int) $job['TotalRows'],
            ResponseKeyType::PoolSize->value       => $poolSize,
            ResponseKeyType::TotalBatches->value   => $totalBatches,
            ResponseKeyType::CurrentBatch->value   => (int) $job['CurrentBatch'],
            ResponseKeyType::Errors->value         => json_decode($job['ErrorsJson'] ?? '[]', true),
            ResponseKeyType::CreatedAt->value      => $job['CreatedAt'],
            ResponseKeyType::UpdatedAt->value      => $job['UpdatedAt'],
            ResponseKeyType::CompletedAt->value    => $job['CompletedAt'],
            ResponseKeyType::TableProgress->value  => $tableProgress,
            ResponseKeyType::Percent->value        => $totalTables > 0 ? round(((int) $job['TablesExported'] / $totalTables) * 100, 1) : 0,
        );
    }

    private function loadTableProgress(PDO $pdo): array {
        try {
            $stmt = $pdo->prepare("SELECT TableName, Status, RowsTotal, RowsExported, ErrorMessage
                FROM " . TableType::SnapshotProgress->value . " WHERE SnapshotId = 0");
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('WorkerJobProgressTrait::loadTableProgress() failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return array();
        }
    }
}
