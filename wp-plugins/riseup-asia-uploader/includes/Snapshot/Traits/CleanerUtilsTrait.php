<?php
/**
 * Cleaner Utils Trait
 *
 * Settings, filesystem helpers, audit trail, and logging.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.9.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\ActionType;
use RiseupAsia\Enums\OptionNameType;
use RiseupAsia\Enums\StatusType;
use RiseupAsia\Enums\RetentionType;
use RiseupAsia\Enums\SnapshotConfigType;
use RiseupAsia\Enums\TriggerSourceType;
use RiseupAsia\Helpers\PathUtils;
use RiseupAsia\Helpers\BooleanHelpers;

trait CleanerUtilsTrait {

    private function loadSettings(array $overrides): array {
        $defaults = array(
            'retention_type'  => RetentionType::Days->value,
            'retention_days'  => SnapshotConfigType::RetentionDaysDefault->value,
            'retention_count' => SnapshotConfigType::RetentionCountDefault->value,
        );

        $saved = get_option(
            OptionNameType::SnapshotSettings->value,
            array()
        );
        if (is_array($saved)) {
            $defaults = array_merge($defaults, $saved);
        }

        return array_merge($defaults, array_filter($overrides, function($v) { return $v !== null; }));
    }

    private function getZipPath(string $sqlitePath): string {
        return preg_replace('/\.sqlite$/', '.zip', $sqlitePath);
    }

    private function deleteDirectoryRecursive(string $dir): void {
        if (BooleanHelpers::isDirMissing($dir)) return;

        $items = array_diff(scandir($dir), array('.', '..'));
        foreach ($items as $item) {
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->deleteDirectoryRecursive($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    private function getDirectorySize(string $dir): int {
        $size = 0;
        if (BooleanHelpers::isDirMissing($dir)) return 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        return $size;
    }

    private function logCleanupAudit(array $results): void {
        try {
            $this->db->logTransaction(
                ActionType::SnapshotCleanup->value,
                json_encode(array(
                    'retention_deleted' => $results['retention']['deleted'],
                    'retention_skipped' => $results['retention']['skipped_master'],
                    'orphans_removed'   => $results['orphans']['removed'],
                    'stuck_cleaned'     => $results['stuck']['cleaned'],
                    'space_freed'       => PathUtils::formatBytes($results['space_freed_bytes']),
                    'errors'            => count($results['errors']),
                    'duration'          => $results['duration'],
                )),
                empty($results['errors']) ? StatusType::Success->value : StatusType::Failed->value,
                TriggerSourceType::Api->value
            );
        } catch (Throwable $e) {
            $this->log(LogLevelType::Error->value, 'Failed to log cleanup action', array('error' => $e->getMessage()));
        }
    }

    private function log(string $level, string $message, array $context = array()): void {
        $prefix = '[SNAPSHOT] [CLEANER]';
        $fullMessage = $prefix . ' ' . $message;

        if (!empty($context)) {
            $fullMessage .= ' ' . json_encode($context);
        }

        if (!$this->logger) {
            return;
        }

        switch ($level) {
            case LogLevelType::Debug->value: $this->logger->debug($fullMessage); break;
            case LogLevelType::Info->value:  $this->logger->info($fullMessage);  break;
            case LogLevelType::Warn->value:  $this->logger->warn($fullMessage);  break;
            case LogLevelType::Error->value: $this->logger->error($fullMessage);  break;
            default:      $this->logger->info($fullMessage);
        }
    }
}
