<?php
/**
 * LogEmailTrait — Emails log files as attachments via wp_mail().
 *
 * Collects active log files and optional archived rotations,
 * validates size cap, and sends via wp_mail() with plain-text body.
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
use QUpload\Enums\PluginConfigType;
use QUpload\Enums\ResponseKeyType;
use QUpload\Helpers\PathHelper;

trait LogEmailTrait
{
    private const EMAIL_MAX_PER_HOUR = 5;
    private const EMAIL_MAX_ATTACHMENT_BYTES = 10 * 1024 * 1024; // 10 MB
    private const LOG_FILE_NAMES = ['log.txt', 'error.txt', 'stacktrace.txt'];

    /** Handle POST /logs/email — collect log files and email them as attachments. */
    public function handleLogsEmail(WP_REST_Request $request): WP_REST_Response {
        $machineName = $request->get_header('X-Riseup-Source-Machine');
        $isMachineMissing = empty($machineName);

        if ($isMachineMissing) {
            return new WP_REST_Response(
                array(
                    ResponseKeyType::Success->value => false,
                    ResponseKeyType::Error->value   => 'X-Riseup-Source-Machine header is required',
                    'code'                          => 'machine_header_missing',
                ),
                HttpStatusType::BadRequest->value,
            );
        }

        $isMachineApproved = $this->isMachineApproved($machineName);

        if ($isMachineApproved === false) {
            return new WP_REST_Response(
                array(
                    ResponseKeyType::Success->value => false,
                    ResponseKeyType::Error->value   => 'Machine not in approved list',
                    'code'                          => 'machine_not_approved',
                ),
                HttpStatusType::Forbidden->value,
            );
        }

        $isRateLimited = $this->isEmailRateLimited();

        if ($isRateLimited) {
            return new WP_REST_Response(
                array(
                    ResponseKeyType::Success->value => false,
                    ResponseKeyType::Error->value   => 'Rate limit exceeded (max ' . self::EMAIL_MAX_PER_HOUR . '/hour)',
                    'code'                          => 'rate_limited',
                ),
                HttpStatusType::TooManyRequests->value,
            );
        }

        $body = $request->get_json_params();
        $recipient = $this->resolveEmailRecipient($body);
        $includeArchives = (bool) ($body['include_archives'] ?? false);
        $logTypes = $body['log_types'] ?? self::LOG_FILE_NAMES;

        $logsDir = PathHelper::getLogsDir();
        $collected = $this->collectLogFiles($logsDir, $logTypes, $includeArchives);
        $hasNoFiles = empty($collected['attachments']);

        if ($hasNoFiles) {
            return new WP_REST_Response(
                array(
                    ResponseKeyType::Success->value => false,
                    ResponseKeyType::Error->value   => 'No log files found',
                    'code'                          => 'no_logs_found',
                ),
                HttpStatusType::NotFound->value,
            );
        }

        $totalSize = $collected['total_size'];
        $isTooLarge = ($totalSize > self::EMAIL_MAX_ATTACHMENT_BYTES);

        if ($isTooLarge) {
            $this->cleanupTempFiles($collected['temp_files']);

            return new WP_REST_Response(
                array(
                    ResponseKeyType::Success->value => false,
                    ResponseKeyType::Error->value   => 'Total attachment size exceeds 10 MB. Try with include_archives: false.',
                    'code'                          => 'size_exceeded',
                ),
                HttpStatusType::BadRequest->value,
            );
        }

        $clientIp = $this->resolveClientIp();
        $subject = $this->buildEmailSubject();
        $emailBody = $this->buildEmailBody($collected['file_names'], $totalSize, $machineName, $clientIp);
        $headers = array('Content-Type: text/plain; charset=UTF-8');

        $wasSent = wp_mail($recipient, $subject, $emailBody, $headers, $collected['attachments']);

        $this->cleanupTempFiles($collected['temp_files']);

        $isSendFailed = ($wasSent === false);

        if ($isSendFailed) {
            return new WP_REST_Response(
                array(
                    ResponseKeyType::Success->value => false,
                    ResponseKeyType::Error->value   => 'wp_mail_failed',
                    'message'                       => 'Failed to send email. Ensure WordPress has email sending configured (e.g., GoSMTP, WP Mail SMTP).',
                ),
                HttpStatusType::ServerError->value,
            );
        }

        $this->incrementEmailCount();

        $this->fileLogger->info('Log files emailed', array(
            'recipient' => $recipient,
            'files'     => $collected['file_names'],
            'machine'   => $machineName,
            'ip'        => $clientIp,
        ));

        return new WP_REST_Response(
            array(
                ResponseKeyType::Success->value => true,
                'sent_to'                       => $recipient,
                'files_attached'                => $collected['file_names'],
                'total_size_bytes'              => $totalSize,
                'requested_by'                  => array(
                    'machine' => $machineName,
                    'ip'      => $clientIp,
                ),
            ),
            HttpStatusType::Ok->value,
        );
    }

    // ── Log File Collection ──────────────────────────────────────────

    /**
     * Collect log files and optionally archived rotations.
     *
     * @param string   $logsDir         Base logs directory.
     * @param string[] $logTypes        Which log file names to include.
     * @param bool     $includeArchives Whether to include archive folders.
     * @return array{attachments: string[], file_names: string[], total_size: int, temp_files: string[]}
     */
    private function collectLogFiles(string $logsDir, array $logTypes, bool $includeArchives): array {
        $attachments = array();
        $fileNames = array();
        $totalSize = 0;
        $tempFiles = array();

        // Collect active log files
        foreach ($logTypes as $logType) {
            $filePath = $logsDir . '/' . $logType;
            $isFileExists = file_exists($filePath);

            if ($isFileExists === false) {
                continue;
            }

            $size = @filesize($filePath);
            $isValidSize = ($size !== false && $size > 0);

            if ($isValidSize === false) {
                continue;
            }

            $attachments[] = $filePath;
            $fileNames[] = $logType;
            $totalSize += $size;
        }

        // Collect archived log files
        if ($includeArchives) {
            $archiveResult = $this->collectArchivedFiles($logsDir . '/archive', $logTypes);
            $attachments = array_merge($attachments, $archiveResult['attachments']);
            $fileNames = array_merge($fileNames, $archiveResult['file_names']);
            $totalSize += $archiveResult['total_size'];
            $tempFiles = $archiveResult['temp_files'];
        }

        return array(
            'attachments' => $attachments,
            'file_names'  => $fileNames,
            'total_size'  => $totalSize,
            'temp_files'  => $tempFiles,
        );
    }

    /**
     * Collect archived log files, copying with renamed names for clarity.
     *
     * @param string   $archiveDir Base archive directory.
     * @param string[] $logTypes   Which log file names to include.
     * @return array{attachments: string[], file_names: string[], total_size: int, temp_files: string[]}
     */
    private function collectArchivedFiles(string $archiveDir, array $logTypes): array {
        $attachments = array();
        $fileNames = array();
        $totalSize = 0;
        $tempFiles = array();

        $isArchiveMissing = !is_dir($archiveDir);

        if ($isArchiveMissing) {
            return array('attachments' => $attachments, 'file_names' => $fileNames, 'total_size' => $totalSize, 'temp_files' => $tempFiles);
        }

        $folders = $this->getSortedArchiveFolders($archiveDir);

        foreach ($folders as $folder) {
            $folderPath = $archiveDir . '/' . $folder;

            foreach ($logTypes as $logType) {
                $sourceFile = $folderPath . '/' . $logType;
                $isFileExists = file_exists($sourceFile);

                if ($isFileExists === false) {
                    continue;
                }

                $size = @filesize($sourceFile);
                $isValidSize = ($size !== false && $size > 0);

                if ($isValidSize === false) {
                    continue;
                }

                // Rename for clarity: log.txt → log_001.txt
                $baseName = pathinfo($logType, PATHINFO_FILENAME);
                $extension = pathinfo($logType, PATHINFO_EXTENSION);
                $renamedName = $baseName . '_' . $folder . '.' . $extension;

                $tempDir = PathHelper::getTempDir() . '/log-email';
                PathHelper::ensureDirectory($tempDir);
                $tempPath = $tempDir . '/' . $renamedName;

                $isCopied = @copy($sourceFile, $tempPath);

                if ($isCopied === false) {
                    continue;
                }

                $attachments[] = $tempPath;
                $fileNames[] = $renamedName;
                $totalSize += $size;
                $tempFiles[] = $tempPath;
            }
        }

        return array(
            'attachments' => $attachments,
            'file_names'  => $fileNames,
            'total_size'  => $totalSize,
            'temp_files'  => $tempFiles,
        );
    }

    /** Get sorted archive folder names (natural sort). */
    private function getSortedArchiveFolders(string $archiveDir): array {
        $entries = @scandir($archiveDir);
        $isReadFailed = ($entries === false);

        if ($isReadFailed) {
            return array();
        }

        $folders = array();

        foreach ($entries as $entry) {
            $isDotEntry = ($entry === '.' || $entry === '..');

            if ($isDotEntry) {
                continue;
            }

            $isDirectory = is_dir($archiveDir . '/' . $entry);

            if ($isDirectory) {
                $folders[] = $entry;
            }
        }

        natsort($folders);

        return array_values($folders);
    }

    // ── Email Composition ────────────────────────────────────────────

    /** Build the email subject line. */
    private function buildEmailSubject(): string {
        $siteName = get_bloginfo('name');
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');

        return '[' . PluginConfigType::ShortName->value . '] Log Files — ' . $siteName . ' — ' . $timestamp;
    }

    /**
     * Build the email body.
     *
     * @param string[] $fileNames   List of attached file names.
     * @param int      $totalSize   Total attachment size in bytes.
     * @param string   $machineName Requesting machine name.
     * @param string   $clientIp    Requesting IP address.
     */
    private function buildEmailBody(array $fileNames, int $totalSize, string $machineName, string $clientIp): string {
        $lines = array();
        $lines[] = PluginConfigType::Name->value . ' — Log File Export';
        $lines[] = str_repeat('=', 50);
        $lines[] = '';
        $lines[] = 'Site URL:        ' . get_site_url();
        $lines[] = 'Plugin Version:  ' . PluginConfigType::Version->value;
        $lines[] = 'Requested By:    ' . $machineName . ' (' . $clientIp . ')';
        $lines[] = 'Timestamp:       ' . gmdate('Y-m-d\TH:i:s\Z');
        $lines[] = '';
        $lines[] = 'Attached Files:';

        foreach ($fileNames as $fileName) {
            $lines[] = '  - ' . $fileName;
        }

        $lines[] = '';
        $lines[] = 'Total Size: ' . $this->formatBytes($totalSize);
        $lines[] = '';
        $lines[] = str_repeat('-', 50);
        $lines[] = 'This email was sent from the ' . PluginConfigType::Name->value . ' plugin.';

        return implode("\n", $lines);
    }

    /** Format bytes into human-readable string. */
    private function formatBytes(int $bytes): string {
        $isKilobytes = ($bytes >= 1024 && $bytes < 1048576);

        if ($isKilobytes) {
            return round($bytes / 1024, 1) . ' KB';
        }

        $isMegabytes = ($bytes >= 1048576);

        if ($isMegabytes) {
            return round($bytes / 1048576, 1) . ' MB';
        }

        return $bytes . ' B';
    }

    // ── Recipient Resolution ─────────────────────────────────────────

    /** Resolve the email recipient from request body or fall back to admin_email. */
    private function resolveEmailRecipient(array $body): string {
        $customRecipient = $body['recipient'] ?? '';
        $hasCustomRecipient = (is_string($customRecipient) && strlen(trim($customRecipient)) > 0);

        if ($hasCustomRecipient) {
            $sanitized = sanitize_email(trim($customRecipient));
            $isValidEmail = is_email($sanitized);

            if ($isValidEmail) {
                return $sanitized;
            }
        }

        return get_option('admin_email');
    }

    // ── Rate Limiting ────────────────────────────────────────────────

    /** Check if the email rate limit has been exceeded. */
    private function isEmailRateLimited(): bool {
        $rateKey = PluginConfigType::Slug->value . '_log_email_count';
        $count = (int) get_transient($rateKey);
        $isOverLimit = ($count >= self::EMAIL_MAX_PER_HOUR);

        return $isOverLimit;
    }

    /** Increment the email send count. */
    private function incrementEmailCount(): void {
        $rateKey = PluginConfigType::Slug->value . '_log_email_count';
        $count = (int) get_transient($rateKey);
        set_transient($rateKey, $count + 1, 3600);
    }

    // ── Cleanup ──────────────────────────────────────────────────────

    /** Remove temporary copies of archived log files. */
    private function cleanupTempFiles(array $tempFiles): void {
        foreach ($tempFiles as $tempFile) {
            $isFileExists = file_exists($tempFile);

            if ($isFileExists) {
                @unlink($tempFile);
            }
        }

        $tempDir = PathHelper::getTempDir() . '/log-email';
        $isDirExists = is_dir($tempDir);

        if ($isDirExists) {
            @rmdir($tempDir);
        }
    }
}
