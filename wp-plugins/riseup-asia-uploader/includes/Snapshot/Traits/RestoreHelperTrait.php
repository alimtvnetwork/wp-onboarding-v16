<?php
/**
 * Restore Helper Trait
 *
 * Result building, audit logging, and log helper.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.15.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use PDO;
use Throwable;
use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\SnapshotWorkerModeType;
use RiseupAsia\Enums\TableType;
use RiseupAsia\Enums\ActionType;
use RiseupAsia\Enums\StatusType;
use RiseupAsia\Helpers\DateHelper;
use RiseupAsia\Helpers\ResultHelper;

trait RestoreHelperTrait {

    private function buildRestoreResult(
        array $masterResult,
        array $incResult,
        ?int $backupId,
        array $errors,
        float $duration,
        array $meta,
        int $totalRows,
    ): array {
        $this->log(LogLevelType::Info->value, 'Per-table restore complete', array(
            ResponseKeyType::TablesRestored->value => $masterResult[ResponseKeyType::TablesRestored->value],
            ResponseKeyType::TotalRows->value      => $totalRows,
            'incrementals_applied'                  => $incResult['applied'],
            ResponseKeyType::Errors->value          => count($errors),
            ResponseKeyType::BackupId->value        => $backupId,
            ResponseKeyType::Duration->value        => round($duration, 2) . 's',
        ));

        return ResultHelper::ok(array(
            ResponseKeyType::TablesRestored->value => $masterResult[ResponseKeyType::TablesRestored->value],
            ResponseKeyType::TotalRows->value      => $totalRows,
            'incrementals_applied'                 => $incResult['applied'],
            ResponseKeyType::BackupId->value       => $backupId,
            ResponseKeyType::Errors->value         => $errors,
            ResponseKeyType::Duration->value       => $duration,
            'meta'                                 => $meta,
        ));
    }

    private function logAuditRestore(
        string $snapshotDir,
        int $tablesRestored,
        int $totalRows,
        float $duration,
    ): void {
        $pdo = $this->db->getPdo();
        $isPdoMissing = ($pdo === null);
        if ($isPdoMissing) {

            return;
        }

        try {
            $details = $this->buildAuditDetails($snapshotDir, $tablesRestored, $totalRows, $duration);
            $this->insertAuditRecord($pdo, $details);
        } catch (Throwable $e) {
            $this->log(LogLevelType::Warn->value, 'Failed to log audit for restore', array(ResponseKeyType::Error->value => $e->getMessage()));
        }
    }

    private function buildAuditDetails(
        string $snapshotDir,
        int $tablesRestored,
        int $totalRows,
        float $duration,
    ): string {

        return json_encode(array(
            ResponseKeyType::Directory->value      => basename($snapshotDir),
            ResponseKeyType::TablesRestored->value  => $tablesRestored,
            ResponseKeyType::TotalRows->value       => $totalRows,
            ResponseKeyType::Duration->value        => round($duration, 2),
            'type'                                  => SnapshotWorkerModeType::PerTable->value,
        ));
    }

    private function insertAuditRecord(PDO $pdo, string $details): void {
        $stmt = $pdo->prepare(
            "INSERT INTO " . TableType::Transactions->value .
            " (PluginSlug, Action, Status, Details, SourceMachine, CreatedAt) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute(array(
            PluginConfigType::Slug->value, ActionType::SnapshotRestore->value, StatusType::Success->value,
            $details, gethostname() ?: php_uname('n'), DateHelper::nowUtc(),
        ));
    }

    private function log(
        string $level,
        string $message,
        array $context = array(),
    ): void {
        $isLoggerMissing = ($this->logger === null);
        if ($isLoggerMissing) {

            return;
        }

        $prefix = '[RestoreEngine] ';
        $method = strtolower($level);

        if (method_exists($this->logger, $method)) {
            $this->logger->$method($prefix . $message, $context);
        } else {
            $this->logger->info($prefix . $message, $context);
        }
    }
}
