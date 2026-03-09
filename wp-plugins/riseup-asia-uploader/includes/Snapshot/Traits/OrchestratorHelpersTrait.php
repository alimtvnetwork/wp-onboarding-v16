<?php
/**
 * OrchestratorHelpersTrait — error building, directory sizing, formatting, and logging.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use Exception;
use Throwable;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Helpers\PathHelper;

trait OrchestratorHelpersTrait {
    private function buildPhaseError(string $phase, array $result): array {
        return array(
            ResponseKeyType::Success->value => false,
            ResponseKeyType::Error->value   => 'Table export failed: ' . ($result[ResponseKeyType::Error->value] ?? 'Unknown error'),
            ResponseKeyType::Phase->value   => $phase,
        );
    }

    private function buildExceptionResult(Exception $e, string $phase): array {
        $this->logError($e, ucfirst(str_replace('_', ' ', $phase)) . ' failed');

        return array(
            ResponseKeyType::Success->value => false,
            ResponseKeyType::Error->value   => $e->getMessage(),
            ResponseKeyType::Phase->value   => $phase,
        );
    }

    private function logError(Throwable $e, string $message, array $context = array()): void {
        $context[ResponseKeyType::Error->value] = $e->getMessage();
        $context['trace'] = $e->getTraceAsString();
        $this->log(LogLevelType::Error->value, $message, $context);
    }

    private function logWarn(Throwable $e, string $message, array $context = array()): void {
        $context[ResponseKeyType::Error->value] = $e->getMessage();
        $context['trace'] = $e->getTraceAsString();
        $this->log(LogLevelType::Warn->value, $message, $context);
    }

    private function getDirectorySize(string $dir): int {
        if (PathHelper::isDirMissing($dir)) {
            return 0;
        }

        $size = 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    private function formatBytes(int $bytes): string {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }

        if ($bytes < 1073741824) {
            return round($bytes / 1048576, 1) . ' MB';
        }

        return round($bytes / 1073741824, 1) . ' GB';
    }

    private function log(
        string $level,
        string $message,
        array $context = array(),
    ): void {
        $full = $this->formatOrchestratorLogMessage($message, $context);
        $isLoggerMissing = ($this->logger === null);

        if ($isLoggerMissing) {
            return;
        }

        $this->dispatchOrchestratorLog($level, $full);
    }

    private function formatOrchestratorLogMessage(string $message, array $context): string {
        $full = '[SNAPSHOT] [ORCHESTRATOR] ' . $message;
        $hasContext = !empty($context);

        if ($hasContext) {
            $full .= ' ' . json_encode($context);
        }

        return $full;
    }

    private function dispatchOrchestratorLog(string $level, string $message): void {
        switch ($level) {
            case LogLevelType::Warn->value:  $this->logger->warn($message); break;
            case LogLevelType::Error->value: $this->logger->error($message); break;
            default:                         $this->logger->info($message);
        }
    }
}
