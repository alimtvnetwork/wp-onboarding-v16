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
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\BooleanHelpers;

trait OrchestratorHelpersTrait {

    private function buildPhaseError(string $phase, array $result): array {

        return array(ResponseKeyType::Success->value => false, ResponseKeyType::Error->value => 'Table export failed: ' . ($result[ResponseKeyType::Error->value] ?? 'Unknown error'), ResponseKeyType::Phase->value => $phase);
    }

    private function buildExceptionResult(Exception $e, string $phase): array {
        $this->log(LogLevelType::Error->value, ucfirst(str_replace('_', ' ', $phase)) . ' failed', array(ResponseKeyType::Error->value => $e->getMessage(), 'trace' => $e->getTraceAsString()));

        return array(ResponseKeyType::Success->value => false, ResponseKeyType::Error->value => $e->getMessage(), ResponseKeyType::Phase->value => $phase);
    }

    private function getDirectorySize(string $dir): int {
        $size = 0;
        if (PathHelper::isDirMissing($dir)) return 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile()) $size += $file->getSize();
        }

        return $size;
    }

    private function formatBytes(int $bytes): string {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';

        return round($bytes / 1073741824, 1) . ' GB';
    }

    private function log(
        string $level,
        string $message,
        array $context = array(),
    ): void {
        $full = '[SNAPSHOT] [ORCHESTRATOR] ' . $message;
        $hasContext = BooleanHelpers::hasValue($context);
        if ($hasContext) {
            $full .= ' ' . json_encode($context);
        }

        $isLoggerMissing = ($this->logger === null);
        if ($isLoggerMissing) {
            return;
        }

        switch ($level) {
            case LogLevelType::Warn->value:  $this->logger->warn($full); break;
            case LogLevelType::Error->value: $this->logger->error($full); break;
            default:      $this->logger->info($full);
        }
    }
}
