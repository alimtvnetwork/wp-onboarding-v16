<?php
/**
 * Cleaner Helper Trait
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

use Throwable;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\ActionType;
use RiseupAsia\Enums\OptionNameType;
use RiseupAsia\Enums\SettingsKeyType;
use RiseupAsia\Enums\StatusType;
use RiseupAsia\Enums\RetentionType;
use RiseupAsia\Enums\SnapshotConfigType;
use RiseupAsia\Enums\TriggerSourceType;
use RiseupAsia\Helpers\PathHelper;


trait CleanerHelperTrait {
    private function loadSettings(array $overrides): array {
        $defaults = array(
            SettingsKeyType::RetentionType->value  => RetentionType::Days->value,
            SettingsKeyType::RetentionDays->value  => SnapshotConfigType::RetentionDaysDefault->value,
            SettingsKeyType::RetentionCount->value => SnapshotConfigType::RetentionCountDefault->value,
        );

        $saved = get_option(
            OptionNameType::SnapshotSettings->value,
            array()
        );

        if (is_array($saved)) {
            $saved = SettingsKeyType::migrateArray($saved);
            $defaults = array_merge($defaults, $saved);
        }

        $definedOverrides = array_filter($overrides, function($v) {
            $isDefined = ($v !== null);

            return $isDefined;
        });

        return array_merge($defaults, $definedOverrides);
    }

    private function getZipPath(string $sqlitePath): string {
        return preg_replace('/\.sqlite$/', '.zip', $sqlitePath);
    }

    private function deleteDirectoryRecursive(string $dir): void {
        if (PathHelper::isDirMissing($dir)) {
            return;
        }

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

        if (PathHelper::isDirMissing($dir)) {
            return 0;
        }

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
                    'retentionDeleted'               => $results[ResponseKeyType::Retention->value][ResponseKeyType::Deleted->value],
                    'retentionSkipped'               => $results[ResponseKeyType::Retention->value][ResponseKeyType::SkippedMaster->value],
                    'orphansRemoved'                 => $results[ResponseKeyType::Orphans->value][ResponseKeyType::Removed->value],
                    'stuckCleaned'                   => $results[ResponseKeyType::Stuck->value][ResponseKeyType::Cleaned->value],
                    'spaceFreed'                     => PathHelper::formatBytes($results[ResponseKeyType::SpaceFreedBytes->value]),
                    ResponseKeyType::Errors->value   => count($results[ResponseKeyType::Errors->value]),
                    ResponseKeyType::Duration->value => $results[ResponseKeyType::Duration->value],
                )),
                empty($results[ResponseKeyType::Errors->value]) ? StatusType::Success->value : StatusType::Failed->value,
                TriggerSourceType::Api->value,
            );
        } catch (Throwable $e) {
            $this->log(LogLevelType::Error->value, 'Failed to log cleanup action', array('error' => $e->getMessage()));
        }
    }

    private function log(
        string $level,
        string $message,
        array $context = array(),
    ): void {
        $prefix = '[SNAPSHOT] [CLEANER]';
        $fullMessage = $prefix . ' ' . $message;
        $hasContext = !empty($context);

        if ($hasContext) {
            $fullMessage .= ' ' . json_encode($context);
        }

        $isLoggerMissing = ($this->logger === null);

        if ($isLoggerMissing) {
            return;
        }

        switch ($level) {
            case LogLevelType::Debug->value: $this->logger->debug($fullMessage); break;
            case LogLevelType::Info->value:  $this->logger->info($fullMessage);  break;
            case LogLevelType::Warn->value:  $this->logger->warn($fullMessage);  break;
            case LogLevelType::Error->value: $this->logger->error($fullMessage); break;
            default:      $this->logger->info($fullMessage);
        }
    }
}
