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
use RiseupAsia\Helpers\PathHelper;

trait OrchestratorHelpersTrait {

    private function buildPhaseError(string $phase, array $result): array {
        return array('success' => false, 'error' => 'Table export failed: ' . ($result['error'] ?? 'Unknown error'), 'phase' => $phase);
    }

    private function buildExceptionResult(Exception $e, string $phase): array {
        $this->log(LogLevelType::Error->value, ucfirst(str_replace('_', ' ', $phase)) . ' failed', array('error' => $e->getMessage(), 'trace' => $e->getTraceAsString()));
        return array('success' => false, 'error' => $e->getMessage(), 'phase' => $phase);
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

    private function log(string $level, string $message, array $context = array()): void {
        $full = '[SNAPSHOT] [ORCHESTRATOR] ' . $message;
        if (!empty($context)) $full .= ' ' . json_encode($context);
        if (!$this->logger) return;
        switch ($level) {
            case LogLevelType::Warn->value:  $this->logger->warn($full); break;
            case LogLevelType::Error->value: $this->logger->error($full); break;
            default:      $this->logger->info($full);
        }
    }
}
