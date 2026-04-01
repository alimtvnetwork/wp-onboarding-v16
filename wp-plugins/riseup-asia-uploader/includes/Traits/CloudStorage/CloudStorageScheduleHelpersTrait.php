<?php
/**
 * CloudStorageScheduleHelpersTrait — Snapshot, splitting, upload, rotation, and cron helpers.
 *
 * @package RiseupAsia\Traits\CloudStorage
 * @since   2.17.0
 */

namespace RiseupAsia\Traits\CloudStorage;

if (!defined('ABSPATH')) {
    exit;
}

use RuntimeException;
use Throwable;

use RiseupAsia\CloudStorage\BackupFolderResolver;
use RiseupAsia\CloudStorage\ZipSplitter;
use RiseupAsia\Enums\BackupScheduleType;
use RiseupAsia\Enums\CloudStorageBackupStatusType;
use RiseupAsia\Enums\CloudStorageBackupType;
use RiseupAsia\Enums\CloudStorageProviderType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\TableType;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Snapshot\SnapshotOrchestrator;

trait CloudStorageScheduleHelpersTrait {

    // ── Snapshot + Split helpers ─────────────────────────────────

    /**
     * Create a full backup ZIP via the existing SnapshotOrchestrator.
     *
     * @return string Absolute path to the created ZIP file.
     * @throws RuntimeException If snapshot creation fails.
     */
    private function createFullBackupZip(): string
    {
        $orchestrator = SnapshotOrchestrator::getInstance();

        $result = $orchestrator->executeFullBackup([
            ResponseKeyType::Async->value => false,
        ]);

        $isSuccess = !empty($result[ResponseKeyType::Success->value]);

        if (!$isSuccess) {
            throw new RuntimeException(
                'Full snapshot creation failed: ' . ($result[ResponseKeyType::Error->value] ?? 'Unknown error'),
            );
        }

        $zipPath = $result[ResponseKeyType::ZipPath->value] ?? '';
        $isZipMissing = empty($zipPath) || PathHelper::isFileMissing($zipPath);

        if ($isZipMissing) {
            throw new RuntimeException('Full snapshot ZIP not found after creation');
        }

        return $zipPath;
    }

    /**
     * Create an incremental backup ZIP with delta data.
     *
     * @return array{ZipPath: string, TablesChanged: string, TotalNewRows: int}
     * @throws RuntimeException If incremental backup fails.
     */
    private function createIncrementalBackupZip(): array
    {
        $orchestrator = SnapshotOrchestrator::getInstance();

        $result = $orchestrator->executeIncrementalBackup([
            ResponseKeyType::Async->value => false,
        ]);

        $isSuccess = !empty($result[ResponseKeyType::Success->value]);

        if (!$isSuccess) {
            throw new RuntimeException(
                'Incremental backup failed: ' . ($result[ResponseKeyType::Error->value] ?? 'Unknown error'),
            );
        }

        $zipPath = $result[ResponseKeyType::ZipPath->value] ?? '';
        $isZipMissing = empty($zipPath) || PathHelper::isFileMissing($zipPath);

        if ($isZipMissing) {
            throw new RuntimeException('Incremental backup ZIP not found after creation');
        }

        return [
            ResponseKeyType::ZipPath->value        => $zipPath,
            ResponseKeyType::TablesChanged->value   => $result[ResponseKeyType::TablesChanged->value] ?? '',
            ResponseKeyType::TotalNewRows->value    => $result[ResponseKeyType::TotalNewRows->value] ?? 0,
        ];
    }

    /**
     * Split a backup ZIP into ≤ 3 MB chunks with a manifest.
     *
     * @param string                 $zipPath   Source ZIP path.
     * @param CloudStorageBackupType $type      Full or Incremental.
     * @param int                    $sequence  Sequence number.
     * @param string                 $label     Label for the manifest.
     * @return array{tempDir: string, chunks: array, manifestPath: string, chunkCount: int, totalSize: int}
     * @throws RuntimeException If splitting fails.
     */
    private function splitBackupZip(
        string $zipPath,
        CloudStorageBackupType $type,
        int $sequence,
        string $label,
    ): array {
        $tempDir = PathHelper::getTempDir('cloud-backup-split-' . uniqid());
        $splitter = new ZipSplitter();
        $result = $splitter->split($zipPath, $tempDir, $type, $sequence, $label);

        $isSuccess = !empty($result[ResponseKeyType::Success->value]);

        if (!$isSuccess) {
            throw new RuntimeException(
                'ZIP splitting failed: ' . ($result[ResponseKeyType::Error->value] ?? 'Unknown error'),
            );
        }

        return [
            'tempDir'      => $tempDir,
            'chunks'       => $result['chunks'],
            'manifestPath' => $result['manifestPath'],
            'chunkCount'   => $result['chunkCount'],
            'totalSize'    => $result['totalSize'],
        ];
    }

    /** Upload split chunks + manifest to the remote folder path. */
    private function uploadSplitChunks(
        array $account,
        string $token,
        array $splitResult,
        string $folderPath,
        string $commitMessage,
    ): void {
        $branch = $account['DefaultBranch'] ?? 'main';

        // Upload manifest.json first
        $manifestRemotePath = $folderPath . '/manifest.json';

        $this->dispatchCloudUpload(
            $account,
            $token,
            $splitResult['manifestPath'],
            $manifestRemotePath,
            $branch,
        );

        // Upload each chunk
        foreach ($splitResult['chunks'] as $chunk) {
            $chunkRemotePath = $folderPath . '/' . $chunk['file'];

            $chunkLocalPath = dirname($splitResult['manifestPath'])
                . DIRECTORY_SEPARATOR . $chunk['file'];

            $this->dispatchCloudUpload(
                $account,
                $token,
                $chunkLocalPath,
                $chunkRemotePath,
                $branch,
            );
        }

        $this->fileLogger->info('[CLOUD-UPLOAD] All chunks uploaded', [
            'folder' => $folderPath,
            'chunks' => count($splitResult['chunks']),
        ]);
    }

    /** Clean up source ZIP and split temp directory after backup (success or failure). */
    private function cleanupBackupTempFiles(?string $zipPath, ?array $splitResult): void
    {
        $hasZipPath = !empty($zipPath);

        if ($hasZipPath) {
            $isZipExists = file_exists($zipPath);

            if ($isZipExists) {
                unlink($zipPath);
            }
        }

        $hasSplitDir = !empty($splitResult['tempDir']);

        if ($hasSplitDir) {
            $this->cleanupTempDirRecursive($splitResult['tempDir']);
        }
    }

    // ── Remote folder operations ────────────────────────────────

    /** List remote directory names at a given path (for sequence resolution). */
    private function listRemoteDirectories(array $account, string $token, string $path): array
    {
        try {
            $provider = CloudStorageProviderType::from($account['Provider']);

            return match(true) {
                $provider->isGitHub()      => $this->githubListDirectories($account, $token, $path),
                $provider->isGitLab()      => $this->gitlabListDirectories($account, $token, $path),
                $provider->isGoogleDrive() => $this->googleDriveListDirectories($account, $token, $path),
                default                    => [],
            };

        } catch (Throwable $e) {
            $this->fileLogger->logException($e, '[CLOUD-BACKUP] Failed to list remote directories at ' . $path);

            return [];
        }
    }

    /** Delete a remote folder and all its contents. */
    private function deleteRemoteFolder(array $account, string $token, string $path): void
    {
        try {
            $provider = CloudStorageProviderType::from($account['Provider']);

            match(true) {
                $provider->isGitHub()      => $this->githubDeleteFolder($account, $token, $path),
                $provider->isGitLab()      => $this->gitlabDeleteFolder($account, $token, $path),
                $provider->isGoogleDrive() => $this->googleDriveDeleteFolder($account, $token, $path),
                default                    => null,
            };

        } catch (Throwable $e) {
            $this->fileLogger->logException($e, '[CLOUD-ROTATION] Failed to delete remote folder: ' . $path);
        }
    }

    /** Dispatch upload to the appropriate provider. */
    private function dispatchCloudUpload(
        array $account,
        string $token,
        string $localPath,
        string $remotePath,
        string $branch
    ): array {
        $provider = CloudStorageProviderType::from($account['Provider']);

        return match(true) {
            $provider->isGitHub()      => $this->githubUploadFile($account, $token, $localPath, $remotePath),
            $provider->isGitLab()      => $this->gitlabUploadFile($account, $token, $localPath, $remotePath),
            $provider->isGoogleDrive() => $this->googleDriveUploadFile($account, $token, $localPath, $remotePath),
            default                    => throw new RuntimeException('Provider not supported: ' . $provider->value),
        };
    }

    // ── Rotation ────────────────────────────────────────────────

    /**
     * Apply full backup rotation — delete oldest backups beyond retention count.
     * Uses folder-based pruning: deletes remote folder + associated incremental folders.
     */
    private function applyFullBackupRotation(array $account, string $token): void
    {
        $accountId      = (int) $account['Id'];
        $retentionCount = $this->getRetentionCountForAccount($account);
        $resolver       = new BackupFolderResolver();

        $table = TableType::CloudStorageBackupHistory->value;

        $fullBackups = $this->db->queryAll(
            "SELECT * FROM {$table} WHERE AccountId = :accountId AND BackupType = :type AND Status = :status ORDER BY CreatedAt DESC",
            [
                'accountId' => $accountId,
                'type'      => CloudStorageBackupType::Full->value,
                'status'    => CloudStorageBackupStatusType::Success->value,
            ],
        );

        $totalFullBackups = count($fullBackups);
        $hasExcess        = ($totalFullBackups > $retentionCount);

        if (!$hasExcess) {
            return;
        }

        $expiredBackups = array_slice($fullBackups, $retentionCount);

        foreach ($expiredBackups as $expired) {
            $this->rotateExpiredBackup($account, $token, $expired, $resolver, $table);
        }
    }

    /**
     * Rotate a single expired full backup: delete remote folders + DB records.
     *
     * @param array                $account  Account row.
     * @param string               $token    Decrypted token.
     * @param array                $expired  Expired backup history row.
     * @param BackupFolderResolver $resolver Folder resolver instance.
     * @param string               $table    History table name.
     */
    private function rotateExpiredBackup(
        array $account,
        string $token,
        array $expired,
        BackupFolderResolver $resolver,
        string $table,
    ): void {
        $expiredId  = (int) $expired['Id'];
        $folderPath = $expired['FolderPath'] ?? '';
        $folderName = basename($folderPath);

        // Delete remote full-backup folder
        $hasFolderPath = !empty($folderPath);

        if ($hasFolderPath) {
            $this->deleteRemoteFolder($account, $token, $folderPath);

            // Delete associated incremental folder
            $incrRoot = $resolver->getIncrementalRootForFull($folderName);
            $this->deleteRemoteFolder($account, $token, $incrRoot);
        }

        // Delete all history records linked to this full backup
        $this->db->execute(
            "DELETE FROM {$table} WHERE BaseFullBackupId = :baseId",
            ['baseId' => $expiredId],
        );

        // Delete the full backup record itself
        $this->db->execute(
            "DELETE FROM {$table} WHERE Id = :id",
            ['id' => $expiredId],
        );

        $parsed = $resolver->parseFullFolderName($folderName);
        $seq = $parsed['sequence'] ?? $expiredId;
        $cleanupMessage = $resolver->buildCleanupCommitMessage($seq);

        $this->fileLogger->info('[CLOUD-ROTATION] Rotated full backup', [
            'backupId' => $expiredId,
            'folder'   => $folderPath,
            'message'  => $cleanupMessage,
        ]);
    }

    // ── Private helpers ─────────────────────────────────────────

    /** Get all enabled cloud storage accounts with auto-backup on. */
    private function getEnabledCloudStorageAccounts(): array
    {
        $table = TableType::CloudStorageAccounts->value;

        return $this->db->queryAll(
            "SELECT * FROM {$table} WHERE IsActive = 1 ORDER BY Id ASC",
        );
    }

    /** Get the first active cloud backup settings row. */
    private function getActiveCloudBackupSettings(): array|false
    {
        $table = TableType::CloudStorageSettings->value;

        return $this->db->queryOne(
            "SELECT * FROM {$table} WHERE IsEnabled = 1 AND AutoBackupEnabled = 1 LIMIT 1",
        );
    }

    /** Get retention count for an account's provider. */
    private function getRetentionCountForAccount(array $account): int
    {
        $table    = TableType::CloudStorageSettings->value;
        $provider = $account['Provider'] ?? '';

        $settings = $this->db->queryOne(
            "SELECT RetentionCount FROM {$table} WHERE Provider = :provider",
            ['provider' => $provider],
        );

        return (int) ($settings['RetentionCount'] ?? 10);
    }

    /**
     * Calculate the next run timestamp for a cloud backup cron.
     *
     * @param BackupScheduleType $schedule  Frequency.
     * @param string             $timeUtc   HH:MM in UTC.
     * @param int                $dayOfWeek 0=Sunday, 6=Saturday (for weekly schedules).
     * @return int Unix timestamp of next run.
     */
    private function calculateNextCloudBackupTimestamp(
        BackupScheduleType $schedule,
        string $timeUtc,
        int $dayOfWeek
    ): int {
        $parts  = explode(':', $timeUtc);
        $hour   = (int) ($parts[0] ?? 2);
        $minute = (int) ($parts[1] ?? 0);

        $isWeeklyOrLonger = $schedule->isAnyOf(
            BackupScheduleType::Weekly,
            BackupScheduleType::Biweekly,
            BackupScheduleType::Monthly,
        );

        if ($isWeeklyOrLonger) {
            $currentDow = (int) gmdate('w');
            $daysUntil  = ($dayOfWeek - $currentDow + 7) % 7;
            $daysUntil  = ($daysUntil === 0) ? 7 : $daysUntil;

            return strtotime(sprintf(
                '+%d days %02d:%02d:00 UTC',
                $daysUntil,
                $hour,
                $minute,
            ));
        }

        // Hourly or daily: next occurrence of HH:MM UTC
        $todayRun = gmmktime($hour, $minute, 0);
        $isPast   = ($todayRun <= time());

        if ($isPast) {
            $interval = $schedule->intervalSeconds();

            return $todayRun + $interval;
        }

        return $todayRun;
    }

    /** Clear a specific cloud backup cron hook. */
    private function clearCloudBackupCron(string $hookName): void
    {
        $timestamp = wp_next_scheduled($hookName);
        $isScheduled = ($timestamp !== false);

        if ($isScheduled) {
            wp_unschedule_event($timestamp, $hookName);
        }
    }

    /**
     * Recursively remove a temp directory and all its contents.
     *
     * @param string $dirPath Absolute path to the temp directory.
     */
    private function cleanupTempDirRecursive(string $dirPath): void
    {
        $isDirExists = is_dir($dirPath);

        if (!$isDirExists) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dirPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            $isDirectory = $item->isDir();

            if ($isDirectory) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dirPath);
    }
}
