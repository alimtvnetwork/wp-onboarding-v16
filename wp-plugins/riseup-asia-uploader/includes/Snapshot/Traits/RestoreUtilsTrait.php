<?php
/**
 * Restore Utils Trait
 *
 * Result building, audit logging, and log helper.
 *
 * @package RiseupAsiaUploader
 * @since   1.15.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait RestoreUtilsTrait {

    /**
     * Build the final restore result.
     *
     * @param array    $masterResult Master restore result.
     * @param array    $incResult    Incremental result.
     * @param int|null $backupId     Pre-restore backup ID.
     * @param array    $errors       All errors.
     * @param float    $duration     Duration.
     * @param array    $meta         Snapshot metadata.
     * @param int      $totalRows    Total rows restored.
     * @return array Final result.
     */
    private function buildRestoreResult(array $masterResult, array $incResult, ?int $backupId, array $errors, float $duration, array $meta, int $totalRows): array {
        $this->log('INFO', 'Per-table restore complete', array(
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

    /**
     * Log an audit trail entry for the restore operation.
     *
     * @param string $snapshot_dir    Snapshot directory.
     * @param int    $tables_restored Number of tables restored.
     * @param int    $total_rows      Total rows restored.
     * @param float  $duration        Duration in seconds.
     */
    private function logAuditRestore($snapshot_dir, $tables_restored, $total_rows, $duration) {
        $pdo = $this->db->get_pdo();
        if (!$pdo) {
            return;
        }

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO " . TABLE_TRANSACTIONS .
                " (plugin, action, status, details, source, created_at) VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute(array(
                PLUGIN_SLUG,
                ACTION_SNAPSHOT_RESTORE,
                STATUS_SUCCESS,
                json_encode(array(
                    'directory'       => basename($snapshot_dir),
                    'tables_restored' => $tables_restored,
                    'total_rows'      => $total_rows,
                    'duration'        => round($duration, 2),
                    'type'            => 'per_table',
                )),
                gethostname() ?: php_uname('n'),
                gmdate('Y-m-d H:i:s'),
            ));
        } catch (\Throwable $e) {
            $this->log('WARN', 'Failed to log audit for restore', array('error' => $e->getMessage()));
        }
    }

    /**
     * Log a message.
     *
     * @param string $level   Log level.
     * @param string $message Message.
     * @param array  $context Context data.
     */
    private function log($level, $message, $context = array()) {
        if (!$this->logger) {
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
