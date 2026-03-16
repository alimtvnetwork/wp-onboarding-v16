<?php
/**
 * LogRotationStatusTrait — Returns log rotation configuration and state.
 *
 * @package QUpload\Traits\Log
 * @since   2.18.0
 */

namespace QUpload\Traits\Log;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;

use QUpload\Enums\HttpStatusType;
use QUpload\Enums\ResponseKeyType;
use QUpload\Helpers\PathHelper;

trait LogRotationStatusTrait
{
    /** Handle GET /logs/rotation-status — return rotation config and current archive state. */
    public function handleLogsRotationStatus(WP_REST_Request $request): WP_REST_Response {
        $this->fileLogger->info('Logs rotation status endpoint called', ['endpoint' => 'logs/rotation-status']);

        $config = $this->fileLogger->getRotationConfig();
        $logsDir = PathHelper::getLogsDir();
        $archiveDir = $logsDir . '/archive';

        $archiveCount = $this->countRotationArchiveFolders($archiveDir);
        $oldestArchive = $this->getOldestArchiveTimestamp($archiveDir);
        $newestArchive = $this->getNewestArchiveTimestamp($archiveDir);

        return new WP_REST_Response(
            array(
                ResponseKeyType::Success->value => true,
                'rotation' => array(
                    'config'          => $config,
                    'archive_count'   => $archiveCount,
                    'oldest_archive'  => $oldestArchive,
                    'newest_archive'  => $newestArchive,
                ),
            ),
            HttpStatusType::Ok->value,
        );
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

        $folders = array();

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
