<?php
/**
 * LogStatusTrait — Returns log file metadata for remote monitoring.
 *
 * @package QUpload\Traits\Log
 * @since   2.12.0
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

trait LogStatusTrait
{
    /** Handle GET /logs/status — return log file sizes, line counts, archive info. */
    public function handleLogsStatus(WP_REST_Request $request): WP_REST_Response {
        $logsDir = PathHelper::getLogsDir();

        $logStatus = $this->buildFileStatus($logsDir . '/log.txt');
        $errorStatus = $this->buildFileStatus($logsDir . '/error.txt');
        $stacktraceStatus = $this->buildFileStatus($logsDir . '/stacktrace.txt');

        $archiveCount = $this->countArchiveFolders($logsDir . '/archive');

        return new WP_REST_Response(
            array(
                ResponseKeyType::Success->value => true,
                'logs' => array(
                    'log_file'        => $logStatus,
                    'error_file'      => $errorStatus,
                    'stacktrace_file' => $stacktraceStatus,
                    'archive_count'   => $archiveCount,
                ),
            ),
            HttpStatusType::Ok->value,
        );
    }

    /** Build metadata array for a single log file. */
    private function buildFileStatus(string $filePath): array {
        $isFileExists = file_exists($filePath);

        if ($isFileExists === false) {
            return array(
                'exists'        => false,
                'size_bytes'    => 0,
                'last_modified' => null,
                'line_count'    => 0,
            );
        }

        $size = @filesize($filePath);
        $mtime = @filemtime($filePath);
        $lineCount = $this->countFileLines($filePath);

        return array(
            'exists'        => true,
            'size_bytes'    => ($size !== false) ? $size : 0,
            'last_modified' => ($mtime !== false) ? gmdate('Y-m-d\TH:i:s\Z', $mtime) : null,
            'line_count'    => $lineCount,
        );
    }

    /** Count lines in a file without loading entire contents. */
    private function countFileLines(string $filePath): int {
        $handle = @fopen($filePath, 'r');
        $isOpenFailed = ($handle === false);

        if ($isOpenFailed) {
            return 0;
        }

        $count = 0;

        while (fgets($handle) !== false) {
            $count++;
        }

        fclose($handle);

        return $count;
    }

    /** Count subdirectories in the archive folder. */
    private function countArchiveFolders(string $archiveDir): int {
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
}
