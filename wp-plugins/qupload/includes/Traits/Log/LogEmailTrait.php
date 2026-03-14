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
        $this->fileLogger->info('Logs email endpoint called', ['endpoint' => 'logs/email']);
        $machineName = $request->get_header('X-Riseup-Source-Machine');
        $machineError = $this->validateMachineHeader($machineName);

        if ($machineError !== null) {
            return $machineError;
        }

        $isRateLimited = $this->isEmailRateLimited();

        if ($isRateLimited) {
            return $this->buildLogErrorResponse('Rate limit exceeded (max ' . self::EMAIL_MAX_PER_HOUR . '/hour)', 'rate_limited', HttpStatusType::TooManyRequests);
        }

        return $this->processEmailRequest($request, $machineName);
    }

    /** Parse the request body, collect log files, and delegate to send. */
    private function processEmailRequest(WP_REST_Request $request, string $machineName): WP_REST_Response {
        $body = $request->get_json_params();
        $recipient = $this->resolveEmailRecipient($body);
        $includeArchives = (bool) ($body['include_archives'] ?? false);
        $logTypes = $body['log_types'] ?? self::LOG_FILE_NAMES;
        $logsDir = PathHelper::getLogsDir();
        $collected = $this->collectLogFiles($logsDir, $logTypes, $includeArchives);

        return $this->validateAndSendLogEmail($machineName, $recipient, $collected);
    }

    /** Validate collected files and send the email. */
    private function validateAndSendLogEmail(string $machineName, string $recipient, array $collected): WP_REST_Response {
        $hasNoFiles = empty($collected['attachments']);

        if ($hasNoFiles) {
            return $this->buildLogErrorResponse('No log files found', 'no_logs_found', HttpStatusType::NotFound);
        }

        $isTooLarge = ($collected['total_size'] > self::EMAIL_MAX_ATTACHMENT_BYTES);

        if ($isTooLarge) {
            $this->cleanupTempFiles($collected['temp_files']);

            return $this->buildLogErrorResponse('Total attachment size exceeds 10 MB. Try with include_archives: false.', 'size_exceeded', HttpStatusType::BadRequest);
        }

        return $this->sendLogEmail($machineName, $recipient, $collected);
    }

    /** Build and send the email, then return the appropriate response. */
    private function sendLogEmail(string $machineName, string $recipient, array $collected): WP_REST_Response {
        $clientIp = $this->resolveClientIp();
        $subject = $this->buildEmailSubject();
        $emailBody = $this->buildEmailBody($collected['file_names'], $collected['total_size'], $machineName, $clientIp);
        $headers = array('Content-Type: text/plain; charset=UTF-8');

        $wasSent = wp_mail($recipient, $subject, $emailBody, $headers, $collected['attachments']);
        $this->cleanupTempFiles($collected['temp_files']);
        $isSendFailed = ($wasSent === false);

        if ($isSendFailed) {
            return $this->buildLogErrorResponse('wp_mail_failed', 'wp_mail_failed', HttpStatusType::ServerError, 'Failed to send email. Ensure WordPress has email sending configured (e.g., GoSMTP, WP Mail SMTP).');
        }

        $this->incrementEmailCount();
        $this->fileLogger->info('Log files emailed', array('recipient' => $recipient, 'files' => $collected['file_names'], 'machine' => $machineName, 'ip' => $clientIp));

        return $this->buildEmailSuccessResponse($recipient, $collected, $machineName, $clientIp);
    }

    /** Build the success response after sending log email. */
    private function buildEmailSuccessResponse(string $recipient, array $collected, string $machineName, string $clientIp): WP_REST_Response {
        return new WP_REST_Response(
            array(
                ResponseKeyType::Success->value => true,
                'sent_to'                       => $recipient,
                'files_attached'                => $collected['file_names'],
                'total_size_bytes'              => $collected['total_size'],
                'requested_by'                  => array(
                    'machine' => $machineName,
                    'ip'      => $clientIp,
                ),
            ),
            HttpStatusType::Ok->value,
        );
    }

    // ── Log File Collection ──────────────────────────────────────────

    /** Collect log files and optionally archived rotations. */
    private function collectLogFiles(string $logsDir, array $logTypes, bool $includeArchives): array {
        $activeResult = $this->collectActiveLogFiles($logsDir, $logTypes);
        $attachments = $activeResult['attachments'];
        $fileNames = $activeResult['file_names'];
        $totalSize = $activeResult['total_size'];
        $tempFiles = array();

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

    /** Collect active (non-archived) log files. */
    private function collectActiveLogFiles(string $logsDir, array $logTypes): array {
        $attachments = array();
        $fileNames = array();
        $totalSize = 0;

        foreach ($logTypes as $logType) {
            $filePath = $logsDir . '/' . $logType;
            $size = $this->getValidFileSize($filePath);
            $isValidFile = ($size > 0);

            if ($isValidFile) {
                $attachments[] = $filePath;
                $fileNames[] = $logType;
                $totalSize += $size;
            }
        }

        return array('attachments' => $attachments, 'file_names' => $fileNames, 'total_size' => $totalSize);
    }

    /** Return file size in bytes if valid, or 0 if missing/empty. */
    private function getValidFileSize(string $filePath): int {
        $isFileExists = file_exists($filePath);

        if ($isFileExists === false) {
            return 0;
        }

        $size = @filesize($filePath);
        $isValidSize = ($size !== false && $size > 0);

        return $isValidSize ? $size : 0;
    }

    /** Collect archived log files from rotation folders. */
    private function collectArchivedFiles(string $archiveDir, array $logTypes): array {
        $result = array('attachments' => array(), 'file_names' => array(), 'total_size' => 0, 'temp_files' => array());
        $isArchiveMissing = !is_dir($archiveDir);

        if ($isArchiveMissing) {
            return $result;
        }

        $folders = $this->getSortedArchiveFolders($archiveDir);

        foreach ($folders as $folder) {
            $folderPath = $archiveDir . '/' . $folder;
            $this->collectFolderFiles($folderPath, $folder, $logTypes, $result);
        }

        return $result;
    }

    /** Collect log files from a single archive folder. */
    private function collectFolderFiles(string $folderPath, string $folder, array $logTypes, array &$result): void {
        foreach ($logTypes as $logType) {
            $sourceFile = $folderPath . '/' . $logType;
            $copyResult = $this->copyArchivedLogFile($sourceFile, $logType, $folder);

            if ($copyResult === null) {
                continue;
            }

            $result['attachments'][] = $copyResult['path'];
            $result['file_names'][] = $copyResult['name'];
            $result['total_size'] += $copyResult['size'];
            $result['temp_files'][] = $copyResult['path'];
        }
    }

    /** Copy an archived log file to a temp location with a renamed name. */
    private function copyArchivedLogFile(string $sourceFile, string $logType, string $folder): ?array {
        $size = $this->getValidFileSize($sourceFile);
        $isInvalidFile = ($size === 0);

        if ($isInvalidFile) {
            return null;
        }

        $baseName = pathinfo($logType, PATHINFO_FILENAME);
        $extension = pathinfo($logType, PATHINFO_EXTENSION);
        $renamedName = $baseName . '_' . $folder . '.' . $extension;
        $tempPath = $this->buildArchiveTempPath($renamedName);

        $isCopied = @copy($sourceFile, $tempPath);

        if ($isCopied === false) {
            return null;
        }

        return array('path' => $tempPath, 'name' => $renamedName, 'size' => $size);
    }

    /** Build the temp file path for an archived log copy. */
    private function buildArchiveTempPath(string $fileName): string {
        $tempDir = PathHelper::getTempDir() . '/log-email';
        PathHelper::ensureDirectory($tempDir);

        return $tempDir . '/' . $fileName;
    }

    /** Get sorted archive folder names (natural sort). */
    private function getSortedArchiveFolders(string $archiveDir): array {
        $entries = @scandir($archiveDir);
        $isReadFailed = ($entries === false);

        if ($isReadFailed) {
            return array();
        }

        $folders = $this->filterDirectoryEntries($archiveDir, $entries);
        natsort($folders);

        return array_values($folders);
    }

    /** Filter directory entries to only include subdirectories. */
    private function filterDirectoryEntries(string $parentDir, array $entries): array {
        $folders = array();

        foreach ($entries as $entry) {
            $isDotEntry = ($entry === '.' || $entry === '..');

            if ($isDotEntry) {
                continue;
            }

            $isDirectory = is_dir($parentDir . '/' . $entry);

            if ($isDirectory) {
                $folders[] = $entry;
            }
        }

        return $folders;
    }

    // ── Email Composition ────────────────────────────────────────────

    /** Build the email subject line. */
    private function buildEmailSubject(): string {
        $siteName = get_bloginfo('name');
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');

        return '[' . PluginConfigType::ShortName->value . '] Log Files — ' . $siteName . ' — ' . $timestamp;
    }

    /** Build the email body text. */
    private function buildEmailBody(array $fileNames, int $totalSize, string $machineName, string $clientIp): string {
        $lines = $this->buildEmailHeaderLines($machineName, $clientIp);

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

    /** Build the header lines for the email body. */
    private function buildEmailHeaderLines(string $machineName, string $clientIp): array {
        return array(
            PluginConfigType::Name->value . ' — Log File Export',
            str_repeat('=', 50),
            '',
            'Site URL:        ' . get_site_url(),
            'Plugin Version:  ' . PluginConfigType::Version->value,
            'Requested By:    ' . $machineName . ' (' . $clientIp . ')',
            'Timestamp:       ' . gmdate('Y-m-d\TH:i:s\Z'),
            '',
            'Attached Files:',
        );
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