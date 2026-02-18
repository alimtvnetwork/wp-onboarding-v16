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
use RiseupAsia\Enums\TableType;
use RiseupAsia\Enums\ActionType;
use RiseupAsia\Enums\StatusType;

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
            'tables_restored'      => $masterResult['tables_restored'],
            'total_rows'           => $totalRows,
            'incrementals_applied' => $incResult['applied'],
            'errors'               => count($errors),
            'backup_id'            => $backupId,
            'duration'             => round($duration, 2) . 's',
        ));

        return array(
            'success'              => true,
            'tables_restored'      => $masterResult['tables_restored'],
            'total_rows'           => $totalRows,
            'incrementals_applied' => $incResult['applied'],
            'backup_id'            => $backupId,
            'errors'               => $errors,
            'duration'             => $duration,
            'meta'                 => $meta,
        );
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
            $this->log(LogLevelType::Warn->value, 'Failed to log audit for restore', array('error' => $e->getMessage()));
        }
    }

    private function buildAuditDetails(
        string $snapshotDir,
        int $tablesRestored,
        int $totalRows,
        float $duration,
    ): string {

        return json_encode(array(
            'directory' => basename($snapshotDir), 'tables_restored' => $tablesRestored,
            'total_rows' => $totalRows, 'duration' => round($duration, 2), 'type' => 'per_table',
        ));
    }

    private function insertAuditRecord(PDO $pdo, string $details): void {
        $stmt = $pdo->prepare(
            "INSERT INTO " . TableType::Transactions->value .
            " (plugin, action, status, details, source, created_at) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute(array(
            PluginConfigType::Slug->value, ActionType::SnapshotRestore->value, StatusType::Success->value,
            $details, gethostname() ?: php_uname('n'), gmdate('Y-m-d H:i:s'),
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
