<?php
/**
 * Cleaner Utils Trait
 *
 * Settings, filesystem helpers, audit trail, and logging.
 *
 * @package RiseupAsiaUploader
 * @since   1.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\ActionType;
use RiseupAsia\Enums\StatusType;
use RiseupAsia\Enums\RetentionType;
use RiseupAsia\Enums\TriggerSourceType;

trait CleanerUtilsTrait {

    /**
     * Load retention settings from WP options with overrides.
     *
     * @param array $overrides User-provided overrides.
     * @return array Resolved settings.
     */
    private function loadSettings($overrides) {
        $defaults = array(
            'retention_type'  => RetentionType::Days->value,
            'retention_days'  => defined('SNAPSHOT_RETENTION_DAYS_DEFAULT') ? SNAPSHOT_RETENTION_DAYS_DEFAULT : 30,
            'retention_count' => defined('SNAPSHOT_RETENTION_COUNT_DEFAULT') ? SNAPSHOT_RETENTION_COUNT_DEFAULT : 10,
        );

        $saved = get_option(
            defined('OPTION_SNAPSHOT_SETTINGS') ? OPTION_SNAPSHOT_SETTINGS : 'riseup_snapshot_settings',
            array()
        );
        if (is_array($saved)) {
            $defaults = array_merge($defaults, $saved);
        }

        return array_merge($defaults, array_filter($overrides, function($v) { return $v !== null; }));
    }

    /**
     * Get ZIP path from SQLite path.
     *
     * @param string $sqlite_path Path to .sqlite file.
     * @return string Path to .zip file.
     */
    private function getZipPath($sqlite_path) {
        return preg_replace('/\.sqlite$/', '.zip', $sqlite_path);
    }

    /**
     * Delete a directory recursively.
     *
     * @param string $dir Directory path.
     */
    private function deleteDirectoryRecursive($dir) {
        if (RiseupBooleanHelpers::is_dir_missing($dir)) return;

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

    /**
     * Get total size of a directory recursively.
     *
     * @param string $dir Directory path.
     * @return int Total size in bytes.
     */
    private function getDirectorySize($dir) {
        $size = 0;
        if (RiseupBooleanHelpers::is_dir_missing($dir)) return 0;

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

    /**
     * Log cleanup results to the audit trail.
     *
     * @param array $results Cleanup results from execute().
     */
    private function logCleanupAudit($results) {
        try {
            $this->db->logTransaction(
                ActionType::SnapshotCleanup->value,
                json_encode(array(
                    'retention_deleted' => $results['retention']['deleted'],
                    'retention_skipped' => $results['retention']['skipped_master'],
                    'orphans_removed'   => $results['orphans']['removed'],
                    'stuck_cleaned'     => $results['stuck']['cleaned'],
                    'space_freed'       => RiseupPathUtils::formatBytes($results['space_freed_bytes']),
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

    /**
     * Log a message with cleaner context prefix.
     *
     * @param string $level   Log level (e.g. LogLevelType::Info->value).
     * @param string $message Message.
     * @param array  $context Additional context.
     */
    private function log($level, $message, $context = array()) {
        $prefix = '[SNAPSHOT] [CLEANER]';
        $full_message = $prefix . ' ' . $message;

        if (!empty($context)) {
            $full_message .= ' ' . json_encode($context);
        }

        if (!$this->logger) {
            return;
        }

        switch ($level) {
            case LogLevelType::Debug->value: $this->logger->debug($full_message); break;
            case LogLevelType::Info->value:  $this->logger->info($full_message);  break;
            case LogLevelType::Warn->value:  $this->logger->warn($full_message);  break;
            case LogLevelType::Error->value: $this->logger->error($full_message);  break;
            default:      $this->logger->info($full_message);
        }
    }
}
