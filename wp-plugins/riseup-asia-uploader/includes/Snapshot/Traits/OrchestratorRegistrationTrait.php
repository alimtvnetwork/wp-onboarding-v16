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
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\SnapshotProviderType;
use RiseupAsia\Enums\SnapshotStatusType;
use RiseupAsia\Enums\SnapshotTriggerType;
use RiseupAsia\Enums\TableType;
use RiseupAsia\Helpers\DateHelper;

trait OrchestratorRegistrationTrait {
    private function registerSnapshot(
        string $title,
        string $scope,
        array $workerResult,
        array $pluginStats,
        string $snapshotDir,
    ): int|false {
        $pdo = $this->db->getPdo();
        $isPdoMissing = ($pdo === null);

        if ($isPdoMissing) {

            return false;
        }

        try {
            $sequence = $this->getNextSnapshotSequence($pdo);
            $tables_json = $this->buildSnapshotTablesJson($workerResult, $pluginStats);
            $dir_size = $this->getDirectorySize($snapshotDir);

            return $this->insertSnapshotRecord(
                $pdo,
                $sequence,
                $snapshotDir,
                $scope,
                $tables_json,
                $workerResult,
                $dir_size,
            );
        } catch (Throwable $e) {
            $this->log(LogLevelType::Error->value, 'Failed to register snapshot', array(ResponseKeyType::Error->value => $e->getMessage()));

            return false;
        }
    }

    private function getNextSnapshotSequence(PDO $pdo): int {
        $row = $pdo->query("SELECT MAX(Sequence) as max_seq FROM " . TableType::Snapshots->value)->fetch(PDO::FETCH_ASSOC);

        return ($row && $row['max_seq']) ? (int)$row['max_seq'] + 1 : 1;
    }

    private function buildSnapshotTablesJson(array $workerResult, array $pluginStats): string {

        return json_encode(array(
            ResponseKeyType::Exported->value      => $workerResult[ResponseKeyType::Tables->value] ?? 0,
            ResponseKeyType::TotalRows->value     => $workerResult[ResponseKeyType::TotalRows->value] ?? 0,
            ResponseKeyType::Errors->value        => $workerResult[ResponseKeyType::Errors->value] ?? array(),
            ResponseKeyType::Plugins->value       => $pluginStats[ResponseKeyType::Count->value] ?? 0,
            ResponseKeyType::PluginDetails->value => $pluginStats[ResponseKeyType::Plugins->value] ?? array(),
        ));
    }

    private function insertSnapshotRecord(
        PDO $pdo,
        int $sequence,
        string $snapshotDir,
        string $scope,
        string $tablesJson,
        array $workerResult,
        int $dirSize,
    ): int {
        $now = DateHelper::nowIso();
        $stmt = $pdo->prepare("INSERT INTO " . TableType::Snapshots->value . "
            (sequence, filename, filepath, provider, scope, tables_json, total_rows,
             file_size, trigger_source, status, created_at, completed_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->execute(array(
            $sequence,
            basename($snapshotDir),
            $snapshotDir,
            SnapshotProviderType::Native->value,
            $scope,
            $tablesJson,
            $workerResult[ResponseKeyType::TotalRows->value] ?? 0,
            $dirSize,
            SnapshotTriggerType::Api->value,
            SnapshotStatusType::Complete->value,
            $now,
            $now,
        ));

        return (int)$pdo->lastInsertId();
    }
}
