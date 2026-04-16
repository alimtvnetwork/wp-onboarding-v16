<?php
/**
 * LogRotationStatusTrait — Returns log rotation configuration and state.
 *
 * @package RiseupAsia\Traits\Log
 * @since   1.61.0
 */

namespace RiseupAsia\Traits\Log;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;

use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\ResponseKeyType;

trait LogRotationStatusTrait
{
    /** Handle GET /logs/rotation-status — return rotation config and current archive state. */
    public function handleLogsRotationStatus(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() use ($request) {
            $config = $this->fileLogger->getRotationConfig();
            $logsDir = $this->fileLogger->getLogsDir();
            $archiveDir = $logsDir . '/archive';

            $archiveCount = $this->countRotationArchiveFolders($archiveDir);
            $oldestArchive = $this->getOldestArchiveTimestamp($archiveDir);
            $newestArchive = $this->getNewestArchiveTimestamp($archiveDir);

            return new WP_REST_Response(
                [
                    ResponseKeyType::Success->value => true,
                    ResponseKeyType::Rotation->value => [
                        ResponseKeyType::Config->value         => $config,
                        ResponseKeyType::ArchiveCount->value   => $archiveCount,
                        ResponseKeyType::OldestArchive->value  => $oldestArchive,
                        ResponseKeyType::NewestArchive->value  => $newestArchive,
                    ],
                ],
                HttpStatusType::Ok->value,
            );
        }, 'logs-rotation-status');
    }

    /** Count subdirectories in the archive folder. */
    private function countRotationArchiveFolders(string $archiveDir): int {
        $isArchiveMissing = !is_dir($archiveDir);

        if ($isArchiveMissing) {
            return 0;
        }

        $entries = @scandir($archiveDir);
        $isReadFailed = ($entries === false);

        if ($isReadFailed) {
            return 0;
        }

        $count = 0;

        foreach ($entries as $entry) {
            $isDotEntry = ($entry === '.' || $entry === '..');

            if ($isDotEntry) {
                continue;
            }

            $isDirectory = is_dir($archiveDir . '/' . $entry);

            if ($isDirectory) {
                $count++;
            }
        }

        return $count;
    }

    /** Get the oldest archive folder's modification timestamp. */
    private function getOldestArchiveTimestamp(string $archiveDir): ?string {
        return $this->getArchiveTimestamp($archiveDir, true);
    }

    /** Get the newest archive folder's modification timestamp. */
    private function getNewestArchiveTimestamp(string $archiveDir): ?string {
        return $this->getArchiveTimestamp($archiveDir, false);
    }

    /** Get archive folder timestamp (oldest or newest). */
    private function getArchiveTimestamp(string $archiveDir, bool $oldest): ?string {
        $isArchiveMissing = !is_dir($archiveDir);

        if ($isArchiveMissing) {
            return null;
        }

        $entries = @scandir($archiveDir);
        $isReadFailed = ($entries === false);

        if ($isReadFailed) {
            return null;
        }

        $folders = [];

        foreach ($entries as $entry) {
            $isDotEntry = ($entry === '.' || $entry === '..');

            if ($isDotEntry) {
                continue;
            }

            $fullPath = $archiveDir . '/' . $entry;
            $isDirectory = is_dir($fullPath);

            if ($isDirectory) {
                $folders[] = $entry;
            }
        }

        $hasFolders = count($folders) > 0;

        if ($hasFolders === false) {
            return null;
        }

        sort($folders, SORT_NUMERIC);
        $targetFolder = $oldest ? $folders[0] : $folders[count($folders) - 1];
        $targetPath = $archiveDir . '/' . $targetFolder;

        $mtime = @filemtime($targetPath);
        $hasModTime = ($mtime !== false);

        if ($hasModTime === false) {
            return null;
        }

        return gmdate('Y-m-d\TH:i:s\Z', $mtime);
    }
}
