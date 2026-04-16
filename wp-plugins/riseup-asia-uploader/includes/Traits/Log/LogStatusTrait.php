<?php
/**
 * LogStatusTrait — Returns log file and database metadata for remote monitoring.
 *
 * @package RiseupAsia\Traits\Log
 * @since   1.60.0
 */

namespace RiseupAsia\Traits\Log;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;

use RiseupAsia\Database\Database;
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\TableType;
use RiseupAsia\Helpers\InitHelpers;

trait LogStatusTrait
{
    /** Handle GET /logs/status — return log file sizes, line counts, archive info, DB counts. */
    public function handleLogsStatus(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() use ($request) {
            $logsDir = $this->fileLogger->getLogsDir();

            $logStatus = $this->buildFileStatus($logsDir . '/log.txt');
            $errorStatus = $this->buildFileStatus($logsDir . '/error.txt');
            $stacktraceStatus = $this->buildFileStatus($logsDir . '/stacktrace.txt');

            $archiveCount = $this->countArchiveFolders($logsDir . '/archive');
            $dbCounts = $this->getDatabaseCounts();

            return new WP_REST_Response(
                [
                    ResponseKeyType::Success->value => true,
                    ResponseKeyType::Logs->value => [
                        ResponseKeyType::LogFile->value        => $logStatus,
                        ResponseKeyType::ErrorFile->value      => $errorStatus,
                        ResponseKeyType::StacktraceFile->value => $stacktraceStatus,
                        ResponseKeyType::ArchiveCount->value   => $archiveCount,
                    ],
                    ResponseKeyType::Database->value => $dbCounts,
                ],
                HttpStatusType::Ok->value,
            );
        }, 'logs-status');
    }

    /** Build metadata array for a single log file. */
    private function buildFileStatus(string $filePath): array {
        $isFileExists = file_exists($filePath);

        if ($isFileExists === false) {
            return [
                ResponseKeyType::Exists->value       => false,
                ResponseKeyType::SizeBytes->value    => 0,
                ResponseKeyType::LastModified->value => null,
                ResponseKeyType::LineCount->value    => 0,
            ];
        }

        $size = @filesize($filePath);
        $mtime = @filemtime($filePath);
        $lineCount = $this->countFileLines($filePath);

        return [
            ResponseKeyType::Exists->value       => true,
            ResponseKeyType::SizeBytes->value    => ($size !== false) ? $size : 0,
            ResponseKeyType::LastModified->value => ($mtime !== false) ? gmdate('Y-m-d\TH:i:s\Z', $mtime) : null,
            ResponseKeyType::LineCount->value    => $lineCount,
        ];
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

    /** Get transaction and error session counts from the database. */
    private function getDatabaseCounts(): array {
        $txKey = ResponseKeyType::TransactionCount->value;
        $esKey = ResponseKeyType::ErrorSessionCount->value;

        $result = [
            $txKey => 0,
            $esKey => 0,
        ];

        try {
            $db = Database::getInstance();
            $pdo = $db->getPdo();
            $isPdoMissing = ($pdo === null);

            if ($isPdoMissing) {
                return $result;
            }

            $txStmt = $pdo->query('SELECT COUNT(*) FROM ' . TableType::Transactions->value);
            $isTransactionCountReady = ($txStmt !== false);

            if ($isTransactionCountReady) {
                $result[$txKey] = (int) $txStmt->fetchColumn();
            }

            $esStmt = $pdo->query('SELECT COUNT(*) FROM ' . TableType::ErrorSessions->value);
            $isErrorSessionCountReady = ($esStmt !== false);

            if ($isErrorSessionCountReady) {
                $result[$esKey] = (int) $esStmt->fetchColumn();
            }
        } catch (\Throwable $e) {
            $this->fileLogger->logException($e, 'Failed to get database counts for log status');
        }

        return $result;
    }
}
