<?php
/**
 * SnapshotProviderHelpersTrait — Shared helpers for snapshot providers.
 *
 * Logging, directory management, filename generation, sequence numbering,
 * and byte formatting.
 *
 * @package RiseupAsiaUploader
 * @since   1.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\TableType;

trait SnapshotProviderHelpersTrait {

    /**
     * Log a message with snapshot context.
     */
    protected function log($level, $message, $context = array()) {
        $prefix = '[SNAPSHOT] [' . strtoupper($this->provider_id) . ']';
        $full_message = $prefix . ' ' . $message;

        if (!empty($context)) {
            $full_message .= ' ' . json_encode($context);
        }

        if ($this->logger) {
            $this->dispatchLog($level, $full_message);
        }
    }

    /**
     * Dispatch a log message to the appropriate logger method.
     */
    private function dispatchLog(string $level, string $message): void {
        $method = strtolower($level);
        if (method_exists($this->logger, $method)) {
            $this->logger->$method($message);
            return;
        }
        $this->logger->info($message);
    }

    /** @return string Full path to snapshots directory. */
    protected function getSnapshotsDir() {
        return RiseupPathUtils::getSnapshotsDir();
    }

    /** Ensure snapshots directory exists with proper security. */
    protected function ensureSnapshotsDir() {
        $dir = RiseupPathUtils::ensure_path(true, RiseupPathUtils::getSnapshotsDir());

        if ($dir === false) {
            $this->log(LogLevelType::Error->value, 'Failed to ensure snapshots directory');
            return false;
        }

        $this->log(LogLevelType::Debug->value, 'Snapshots directory ensured', array('path' => $dir));
        return true;
    }

    /** Generate a unique snapshot filename. */
    protected function generateSnapshotFilename($sequence) {
        $sequence_padded = str_pad($sequence, 3, '0', STR_PAD_LEFT);
        return sprintf('%s_%s', $sequence_padded, date('Y-m-d_His'));
    }

    /** Get the next sequence number for snapshots. */
    protected function getNextSequence() {
        $result = $this->db->query_single('SELECT MAX(sequence) as max_seq FROM ' . TableType::Snapshots->value);
        return ($result && isset($result['max_seq'])) ? (int)$result['max_seq'] + 1 : 1;
    }

    /** Format bytes to human-readable string. */
    protected function formatBytes($bytes, $decimals = 1) {
        return RiseupPathUtils::formatBytes($bytes, $decimals);
    }
}
