<?php
/**
 * OrchestratorRegistrationTrait — Snapshot record registration.
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
use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\SnapshotProviderType;
use RiseupAsia\Enums\SnapshotStatusType;
use RiseupAsia\Enums\SnapshotTriggerType;
use RiseupAsia\Enums\TableType;

trait OrchestratorRegistrationTrait {

    private function registerSnapshot(string $title, string $scope, array $workerResult, array $pluginStats, string $snapshotDir): int|false {
        $pdo = $this->db->getPdo();
        if (!$pdo) {
            return false;
        }

        try {
            $sequence = $this->getNextSnapshotSequence($pdo);
            $tables_json = $this->buildSnapshotTablesJson($workerResult, $pluginStats);
            $dir_size = $this->getDirectorySize($snapshotDir);

            return $this->insertSnapshotRecord($pdo, $sequence, $snapshotDir, $scope, $tables_json, $workerResult, $dir_size);
        } catch (Throwable $e) {
            $this->log(LogLevelType::Error->value, 'Failed to register snapshot', array('error' => $e->getMessage()));
            return false;
        }
    }

    private function getNextSnapshotSequence(PDO $pdo): int {
        $row = $pdo->query("SELECT MAX(sequence) as max_seq FROM " . TableType::Snapshots->value)->fetch(PDO::FETCH_ASSOC);
        return ($row && $row['max_seq']) ? (int)$row['max_seq'] + 1 : 1;
    }

    private function buildSnapshotTablesJson(array $workerResult, array $pluginStats): string {
        return json_encode(array(
            'exported' => $workerResult['tables'] ?? 0, 'total_rows' => $workerResult['total_rows'] ?? 0,
            'errors' => $workerResult['errors'] ?? array(), 'plugins' => $pluginStats['count'] ?? 0,
            'plugin_details' => $pluginStats['plugins'] ?? array(),
        ));
    }

    private function insertSnapshotRecord(PDO $pdo, int $sequence, string $snapshotDir, string $scope, string $tablesJson, array $workerResult, int $dirSize): int {
        $now = gmdate('c');
        $stmt = $pdo->prepare("INSERT INTO " . TableType::Snapshots->value . "
            (sequence, filename, filepath, provider, scope, tables_json, total_rows,
             file_size, trigger_source, status, created_at, completed_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->execute(array(
            $sequence, basename($snapshotDir), $snapshotDir, SnapshotProviderType::Native->value, $scope,
            $tablesJson, $workerResult['total_rows'] ?? 0, $dirSize,
            SnapshotTriggerType::Api->value, SnapshotStatusType::Complete->value, $now, $now,
        ));

        return (int)$pdo->lastInsertId();
    }
}
