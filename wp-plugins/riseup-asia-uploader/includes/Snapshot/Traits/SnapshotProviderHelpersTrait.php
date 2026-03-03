<?php
/**
 * SnapshotProviderHelpersTrait — Shared helpers for snapshot providers.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.9.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use Throwable;
use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\TableType;
use RiseupAsia\Helpers\DateHelper;
use RiseupAsia\Helpers\PathHelper;

trait SnapshotProviderHelpersTrait {
    protected function log(
        string $level,
        string $message,
        array $context = array(),
    ): void {
        $prefix = '[SNAPSHOT] [' . strtoupper($this->providerId) . ']';
        $fullMessage = $prefix . ' ' . $message;

        $hasContext = !empty($context);

        if ($hasContext) {
            $fullMessage .= ' ' . json_encode($context);
        }

        if ($this->logger) {
            $this->dispatchLog($level, $fullMessage);
        }
    }

    private function dispatchLog(string $level, string $message): void {
        $method = strtolower($level);
        if (method_exists($this->logger, $method)) {
            $this->logger->$method($message);

            return;
        }
        $this->logger->info($message);
    }

    protected function logError(Throwable $e, string $message, array $context = array()): void {
        $context[ResponseKeyType::Error->value] = $e->getMessage();
        $context['trace'] = $e->getTraceAsString();
        $this->log(LogLevelType::Error->value, $message, $context);
    }

    protected function logWarn(Throwable $e, string $message, array $context = array()): void {
        $context[ResponseKeyType::Error->value] = $e->getMessage();
        $context['trace'] = $e->getTraceAsString();
        $this->log(LogLevelType::Warn->value, $message, $context);
    }

    protected function getSnapshotsDir(): string {
        return PathHelper::getSnapshotsDir();
    }

    protected function ensureSnapshotsDir(): bool {
        $dir = PathHelper::makePath(true, PathHelper::getSnapshotsDir());

        if ($dir === false) {
            $this->log(LogLevelType::Error->value, 'Failed to ensure snapshots directory');

            return false;
        }

        $this->log(LogLevelType::Debug->value, 'Snapshots directory ensured', array('path' => $dir));

        return true;
    }

    protected function generateSnapshotFilename(int $sequence): string {
        $sequencePadded = str_pad($sequence, 3, '0', STR_PAD_LEFT);

        return sprintf('%s_%s', $sequencePadded, DateHelper::nowFilenameDatetime());
    }

    protected function getNextSequence(): int {
        $result = $this->db->querySingle('SELECT MAX(Sequence) as max_seq FROM ' . TableType::Snapshots->value);

        return ($result && isset($result['max_seq'])) ? (int)$result['max_seq'] + 1 : 1;
    }

    protected function formatBytes(int $bytes, int $decimals = 1): string {
        return PathHelper::formatBytes($bytes, $decimals);
    }
}
