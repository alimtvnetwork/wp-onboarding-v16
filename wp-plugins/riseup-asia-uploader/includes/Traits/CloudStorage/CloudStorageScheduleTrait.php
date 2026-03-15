<?php
/**
 * CloudStorageScheduleTrait — WP-Cron registration and handlers for cloud storage backups.
 *
 * Registers two cron events: full backup (weekly default) and incremental backup (daily default).
 * Fires on the riseup_cloud_full_backup and riseup_cloud_incremental_backup hooks.
 *
 * @package RiseupAsia\Traits\CloudStorage
 * @since   2.16.0
 */

namespace RiseupAsia\Traits\CloudStorage;

if (!defined('ABSPATH')) {
    exit;
}

use Throwable;

use RiseupAsia\CloudStorage\BackupFolderResolver;
use RiseupAsia\CloudStorage\ZipSplitter;
use RiseupAsia\Enums\BackupScheduleType;
use RiseupAsia\Enums\BackupStrategyType;
use RiseupAsia\Enums\CloudStorageBackupStatusType;
use RiseupAsia\Enums\CloudStorageBackupType;
use RiseupAsia\Enums\CloudStorageProviderType;
use RiseupAsia\Enums\HookType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\TableType;
use RiseupAsia\Helpers\BooleanHelpers;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Snapshot\SnapshotOrchestrator;

trait CloudStorageScheduleTrait {

    /** Register WP-Cron schedules for cloud backup frequencies. */
    public function registerCloudBackupCronSchedules(array $schedules): array
    {
        $biweeklyKey = BackupScheduleType::Biweekly->recurrence();

        if (BooleanHelpers::isKeyMissing($schedules, $biweeklyKey)) {
            $schedules[$biweeklyKey] = array(
                'interval' => BackupScheduleType::Biweekly->intervalSeconds(),
                'display'  => 'Every Two Weeks',
            );
        }

        $monthlyKey = BackupScheduleType::Monthly->recurrence();

        if (BooleanHelpers::isKeyMissing($schedules, $monthlyKey)) {
            $schedules[$monthlyKey] = array(
                'interval' => BackupScheduleType::Monthly->intervalSeconds(),
                'display'  => 'Once Monthly',
            );
        }

        return $schedules;
    }

    /** Initialize cloud backup cron hooks. Called from plugin init. */
    public function initCloudBackupSchedule(): void
    {
        add_filter(HookType::CronSchedules->value, array($this, 'registerCloudBackupCronSchedules'));
        add_action(HookType::CronCloudFullBackup->value, array($this, 'handleScheduledFullBackup'));
        add_action(HookType::CronCloudIncrementalBackup->value, array($this, 'handleScheduledIncrementalBackup'));

        $this->syncCloudBackupSchedule();
    }

    /** Sync cron events with current cloud storage settings. */
    public function syncCloudBackupSchedule(): void
    {
        $this->clearCloudBackupCron(HookType::CronCloudFullBackup->value);
        $this->clearCloudBackupCron(HookType::CronCloudIncrementalBackup->value);

        $settings = $this->getActiveCloudBackupSettings();

        $hasNoSettings = ($settings === false);

        if ($hasNoSettings) {
            return;
        }

        $isAutoBackupDisabled = empty($settings['AutoBackupEnabled']);

        if ($isAutoBackupDisabled) {
            return;
        }

        $strategy = BackupStrategyType::tryFrom($settings['BackupType'] ?? '') ?? BackupStrategyType::FullOnly;

        // ── Schedule full backup ──────────────────────────────────
        $fullSchedule = BackupScheduleType::tryFrom($settings['FullBackupSchedule'] ?? '') ?? BackupScheduleType::Weekly;
        $isFullAutomatic = $fullSchedule->isAutomatic();

        if ($isFullAutomatic) {
            $fullTime      = $settings['FullBackupTimeUtc'] ?? '02:00';
            $fullDay       = (int) ($settings['FullBackupDayOfWeek'] ?? 0);
            $nextFullRun   = $this->calculateNextCloudBackupTimestamp($fullSchedule, $fullTime, $fullDay);

            wp_schedule_event(
                $nextFullRun,
                $fullSchedule->recurrence(),
                HookType::CronCloudFullBackup->value,
            );

            $this->fileLogger->info('[CLOUD-SCHEDULE] Full backup scheduled', array(
                'frequency' => $fullSchedule->value,
                'nextRun'   => gmdate('c', $nextFullRun),
            ));
        }

        // ── Schedule incremental backup (if strategy includes it) ─
        $isIncrementalEnabled = $strategy->isFullAndIncremental();

        if ($isIncrementalEnabled) {
            $incrSchedule = BackupScheduleType::tryFrom($settings['IncrementalBackupSchedule'] ?? '') ?? BackupScheduleType::Daily;
            $isIncrAutomatic = $incrSchedule->isAutomatic();

            if ($isIncrAutomatic) {
                $incrTime    = $settings['IncrementalBackupTimeUtc'] ?? '02:00';
                $nextIncrRun = $this->calculateNextCloudBackupTimestamp($incrSchedule, $incrTime, 0);

                wp_schedule_event(
                    $nextIncrRun,
                    $incrSchedule->recurrence(),
                    HookType::CronCloudIncrementalBackup->value,
                );

                $this->fileLogger->info('[CLOUD-SCHEDULE] Incremental backup scheduled', array(
                    'frequency' => $incrSchedule->value,
                    'nextRun'   => gmdate('c', $nextIncrRun),
                ));
            }
        }
    }

    /** Handle scheduled full backup cron event. */
    public function handleScheduledFullBackup(): void
    {
        $this->fileLogger->info('[CLOUD-BACKUP] Starting scheduled full backup');

        try {
            $accounts = $this->getEnabledCloudStorageAccounts();

            foreach ($accounts as $account) {
                $this->executeFullBackupForAccount($account);
            }

            $this->fileLogger->info('[CLOUD-BACKUP] Scheduled full backup complete');

        } catch (Throwable $e) {
            $this->fileLogger->logException($e, '[CLOUD-BACKUP] Scheduled full backup failed');
        }
    }

    /** Handle scheduled incremental backup cron event. */
    public function handleScheduledIncrementalBackup(): void
    {
        $this->fileLogger->info('[CLOUD-BACKUP] Starting scheduled incremental backup');

        try {
            $accounts = $this->getEnabledCloudStorageAccounts();

            foreach ($accounts as $account) {
                $this->executeIncrementalBackupForAccount($account);
            }

            $this->fileLogger->info('[CLOUD-BACKUP] Scheduled incremental backup complete');

        } catch (Throwable $e) {
            $this->fileLogger->logException($e, '[CLOUD-BACKUP] Scheduled incremental backup failed');
        }
    }

    /**
     * Handle a manual backup triggered by the user.
     *
     * @param string $label User-provided label for the backup folder.
     */
    public function handleManualBackup(string $label): void
    {
        $this->fileLogger->info('[CLOUD-BACKUP] Starting manual backup', array('label' => $label));

        try {
            $accounts = $this->getEnabledCloudStorageAccounts();

            foreach ($accounts as $account) {
                $this->executeFullBackupForAccount($account, $label);
            }

            $this->fileLogger->info('[CLOUD-BACKUP] Manual backup complete', array('label' => $label));

        } catch (Throwable $e) {
            $this->fileLogger->logException($e, '[CLOUD-BACKUP] Manual backup failed');
        }
    }

    /** Execute a full backup for a single account. */
    private function executeFullBackupForAccount(array $account, ?string $label = null): void
    {
        $accountId = (int) $account['Id'];
        $provider  = CloudStorageProviderType::from($account['Provider']);
        $token     = $provider->isGoogleDrive() ? '' : $this->decryptToken($account['AccessToken']);
        $timestamp = time();

        $resolver = new BackupFolderResolver();
        $existingFolders = $this->listRemoteFolders($account, $token, BackupFolderResolver::FULL_ROOT);
        $sequence = $resolver->resolveNextFullSequence($existingFolders);

        $folderName = $resolver->buildFullFolderName($sequence, $timestamp, $label);
        $folderPath = $resolver->buildFullPath($sequence, $timestamp, $label);
        $commitMessage = $resolver->buildCommitMessage(
            CloudStorageBackupType::Full,
            $sequence,
            null,
            $timestamp,
            $label,
        );

        $historyId = $this->insertBackupHistory(array(
            'AccountId'  => $accountId,
            'BackupType' => CloudStorageBackupType::Full->value,
            'FileName'   => $folderName,
            'RemotePath' => $folderPath,
            'BranchName' => 'main',
            'FolderPath' => $folderPath,
            'Status'     => CloudStorageBackupStatusType::Pending->value,
        ));

        try {
            $this->updateBackupHistoryStatus($historyId, CloudStorageBackupStatusType::Uploading);

            $startTime = microtime(true);

            // ── 1. Create snapshot ZIP via SnapshotOrchestrator ──
            $zipPath = $this->createFullBackupZip();

            // ── 2. Split into ≤ 3 MB chunks ────────────────────
            $splitResult = $this->splitBackupZip(
                $zipPath,
                CloudStorageBackupType::Full,
                $sequence,
                $folderName,
            );

            // ── 3. Upload chunks + manifest to remote folder ───
            $this->uploadSplitChunks($account, $token, $splitResult, $folderPath, $commitMessage);

            $duration = round(microtime(true) - $startTime, 2);

            $this->updateBackupHistoryStatus($historyId, CloudStorageBackupStatusType::Success, array(
                'FolderPath'    => $folderPath,
                'ChunkCount'    => $splitResult['chunkCount'],
                'TotalSize'     => $splitResult['totalSize'],
                'FileSizeBytes' => $splitResult['totalSize'],
                'Duration'      => $duration,
            ));

            // ── 4. Rotation ────────────────────────────────────
            $this->applyFullBackupRotation($account, $token);

            $this->fileLogger->info('[CLOUD-BACKUP] Full backup uploaded', array(
                'accountId'  => $accountId,
                'folder'     => $folderPath,
                'chunks'     => $splitResult['chunkCount'],
                'totalSize'  => $splitResult['totalSize'],
                'duration'   => $duration,
            ));

            // ── 5. Cleanup temp files ──────────────────────────
            $this->cleanupTempDir($splitResult['tempDir']);

        } catch (Throwable $e) {
            $this->updateBackupHistoryStatus($historyId, CloudStorageBackupStatusType::Failed, array(
                'ErrorMessage' => $e->getMessage(),
            ));

            $this->fileLogger->logException($e, '[CLOUD-BACKUP] Full backup failed for account ' . $accountId);
        }
    }

    /** Execute an incremental backup for a single account. */
    private function executeIncrementalBackupForAccount(array $account): void
    {
        $accountId = (int) $account['Id'];
        $provider  = CloudStorageProviderType::from($account['Provider']);
        $token     = $provider->isGoogleDrive() ? '' : $this->decryptToken($account['AccessToken']);

        // Find the latest full backup for this account
        $latestFull = $this->getLatestFullBackup($accountId);
        $hasNoFullBackup = ($latestFull === false);

        if ($hasNoFullBackup) {
            $this->fileLogger->info('[CLOUD-BACKUP] No full backup found, running full backup instead', array(
                'accountId' => $accountId,
            ));

            $this->executeFullBackupForAccount($account);

            return;
        }

        $resolver = new BackupFolderResolver();
        $parentFolderName = basename($latestFull['FolderPath'] ?? $latestFull['RemotePath'] ?? '');
        $existingIncrSubs = $this->listRemoteFolders(
            $account,
            $token,
            $resolver->getIncrementalRootForFull($parentFolderName),
        );
        $incrSequence = $resolver->resolveNextIncrementalSequence($existingIncrSubs);

        $incrFolderPath = $resolver->buildIncrementalPath($parentFolderName, $incrSequence);
        $timestamp = time();
        $commitMessage = $resolver->buildCommitMessage(
            CloudStorageBackupType::Incremental,
            (int) ($latestFull['Id'] ?? 0),
            $incrSequence,
            $timestamp,
        );

        $historyId = $this->insertBackupHistory(array(
            'AccountId'        => $accountId,
            'BackupType'       => CloudStorageBackupType::Incremental->value,
            'FileName'         => str_pad((string) $incrSequence, 3, '0', STR_PAD_LEFT),
            'RemotePath'       => $incrFolderPath,
            'BranchName'       => 'main',
            'FolderPath'       => $incrFolderPath,
            'BaseFullBackupId' => (int) $latestFull['Id'],
            'Status'           => CloudStorageBackupStatusType::Pending->value,
        ));

        try {
            $this->updateBackupHistoryStatus($historyId, CloudStorageBackupStatusType::Uploading);

            $startTime = microtime(true);

            // ── 1. Create incremental ZIP ──────────────────────
            $lastBackupTimestamp = $latestFull['CreatedAt'] ?? gmdate('Y-m-d H:i:s');
            $incrResult = $this->createIncrementalBackupZip($lastBackupTimestamp);

            $zipPath = $incrResult[ResponseKeyType::ZipPath->value];

            // ── 2. Split into ≤ 3 MB chunks ────────────────────
            $splitResult = $this->splitBackupZip(
                $zipPath,
                CloudStorageBackupType::Incremental,
                $incrSequence,
                str_pad((string) $incrSequence, 3, '0', STR_PAD_LEFT),
            );

            // ── 3. Upload chunks + manifest ────────────────────
            $this->uploadSplitChunks($account, $token, $splitResult, $incrFolderPath, $commitMessage);

            $duration = round(microtime(true) - $startTime, 2);

            $this->updateBackupHistoryStatus($historyId, CloudStorageBackupStatusType::Success, array(
                'FolderPath'    => $incrFolderPath,
                'ChunkCount'    => $splitResult['chunkCount'],
                'TotalSize'     => $splitResult['totalSize'],
                'FileSizeBytes' => $splitResult['totalSize'],
                'TablesChanged' => $incrResult[ResponseKeyType::TablesChanged->value] ?? '',
                'RowsChanged'   => $incrResult[ResponseKeyType::TotalNewRows->value] ?? 0,
                'Duration'      => $duration,
            ));

            $this->fileLogger->info('[CLOUD-BACKUP] Incremental backup uploaded', array(
                'accountId'     => $accountId,
                'folder'        => $incrFolderPath,
                'chunks'        => $splitResult['chunkCount'],
                'tablesChanged' => $incrResult[ResponseKeyType::TablesChanged->value] ?? '',
                'duration'      => $duration,
            ));

            // ── 4. Cleanup temp files ──────────────────────────
            $this->cleanupTempDir($splitResult['tempDir']);

        } catch (Throwable $e) {
            $this->updateBackupHistoryStatus($historyId, CloudStorageBackupStatusType::Failed, array(
                'ErrorMessage' => $e->getMessage(),
            ));

            $this->fileLogger->logException($e, '[CLOUD-BACKUP] Incremental backup failed for account ' . $accountId);
        }
    }

    // ── Snapshot + Split helpers ─────────────────────────────────

    /**
     * Create a full backup ZIP via the existing SnapshotOrchestrator.
     *
     * @return string Absolute path to the created ZIP file.
     * @throws \RuntimeException If snapshot creation fails.
     */
    private function createFullBackupZip(): string
    {
        $orchestrator = SnapshotOrchestrator::getInstance();

        $result = $orchestrator->executeFullBackup(array(
            ResponseKeyType::Async->value => false,
        ));

        $isSuccess = !empty($result[ResponseKeyType::Success->value]);

        if (!$isSuccess) {
            throw new \RuntimeException(
                'Full snapshot creation failed: ' . ($result[ResponseKeyType::Error->value] ?? 'Unknown error'),
            );
        }

        $zipPath = $result[ResponseKeyType::ZipPath->value] ?? '';
        $isZipMissing = empty($zipPath) || PathHelper::isFileMissing($zipPath);

        if ($isZipMissing) {
            throw new \RuntimeException('Full snapshot ZIP not found after creation');
        }

        return $zipPath;
    }

    /**
     * Create an incremental backup ZIP with delta data since the given timestamp.
     *
     * @param string $lastBackupTimestamp ISO timestamp of the last full backup.
     * @return array{ZipPath: string, TablesChanged: string, TotalNewRows: int}
     * @throws \RuntimeException If incremental backup fails.
     */
    private function createIncrementalBackupZip(string $lastBackupTimestamp): array
    {
        $orchestrator = SnapshotOrchestrator::getInstance();

        $result = $orchestrator->executeIncrementalBackup(array(
            ResponseKeyType::Async->value => false,
        ));

        $isSuccess = !empty($result[ResponseKeyType::Success->value]);

        if (!$isSuccess) {
            throw new \RuntimeException(
                'Incremental backup failed: ' . ($result[ResponseKeyType::Error->value] ?? 'Unknown error'),
            );
        }

        $zipPath = $result[ResponseKeyType::ZipPath->value] ?? '';
        $isZipMissing = empty($zipPath) || PathHelper::isFileMissing($zipPath);

        if ($isZipMissing) {
            throw new \RuntimeException('Incremental backup ZIP not found after creation');
        }

        return array(
            ResponseKeyType::ZipPath->value        => $zipPath,
            ResponseKeyType::TablesChanged->value   => $result[ResponseKeyType::TablesChanged->value] ?? '',
            ResponseKeyType::TotalNewRows->value    => $result[ResponseKeyType::TotalNewRows->value] ?? 0,
        );
    }

    /**
     * Split a backup ZIP into ≤ 3 MB chunks with a manifest.
     *
     * @param string                 $zipPath   Source ZIP path.
     * @param CloudStorageBackupType $type      Full or Incremental.
     * @param int                    $sequence  Sequence number.
     * @param string                 $label     Label for the manifest.
     * @return array{tempDir: string, chunks: array, manifestPath: string, chunkCount: int, totalSize: int}
     * @throws \RuntimeException If splitting fails.
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
            throw new \RuntimeException(
                'ZIP splitting failed: ' . ($result[ResponseKeyType::Error->value] ?? 'Unknown error'),
            );
        }

        return array(
            'tempDir'      => $tempDir,
            'chunks'       => $result['chunks'],
            'manifestPath' => $result['manifestPath'],
            'chunkCount'   => $result['chunkCount'],
            'totalSize'    => $result['totalSize'],
        );
    }

    /**
     * Upload split chunks + manifest to the remote folder path.
     *
     * @param array  $account       Account row.
     * @param string $token         Decrypted token.
     * @param array  $splitResult   Output from splitBackupZip().
     * @param string $folderPath    Remote folder path (e.g., "full-backup/001 - 15 Mar 2026 - W11").
     * @param string $commitMessage Git commit message.
     */
    private function uploadSplitChunks(
        array $account,
        string $token,
        array $splitResult,
        string $folderPath,
        string $commitMessage,
    ): void {
        $provider = CloudStorageProviderType::from($account['Provider']);
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

        $this->fileLogger->info('[CLOUD-UPLOAD] All chunks uploaded', array(
            'folder' => $folderPath,
            'chunks' => count($splitResult['chunks']),
        ));
    }

    /**
     * List remote folder names at a given path (for sequence resolution).
     *
     * @param array  $account Account row.
     * @param string $token   Decrypted token.
     * @param string $path    Remote directory path.
     * @return array<string> Folder names.
     */
    private function listRemoteFolders(array $account, string $token, string $path): array
    {
        try {
            $provider = CloudStorageProviderType::from($account['Provider']);

            $result = match(true) {
                $provider->isGitHub()      => $this->githubListFiles($account, $token, $path),
                $provider->isGitLab()      => $this->gitlabListFiles($account, $token, $path),
                $provider->isGoogleDrive() => $this->googleDriveListFiles($account, $token, $path),
                default                    => array(),
            };

            $folders = array();

            foreach ($result as $item) {
                $isDirectory = ($item['type'] ?? '') === 'dir';

                if ($isDirectory) {
                    $folders[] = $item['name'] ?? basename($item['path'] ?? '');
                }
            }

            return $folders;

        } catch (Throwable $e) {
            $this->fileLogger->logException($e, '[CLOUD-BACKUP] Failed to list remote folders at ' . $path);

            return array();
        }
    }

    /**
     * Clean up a temporary directory and its contents.
     *
     * @param string $dirPath Absolute path to the temp directory.
     */
    private function cleanupTempDir(string $dirPath): void
    {
        $isDirExists = is_dir($dirPath);

        if (!$isDirExists) {
            return;
        }

        $files = glob($dirPath . DIRECTORY_SEPARATOR . '*');

        foreach ($files as $file) {
            $isFile = is_file($file);

            if ($isFile) {
                unlink($file);
            }
        }

        rmdir($dirPath);
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

    /** Get the backup prefix for an account from its provider settings. */
    private function getBackupPrefixForAccount(array $account): string
    {
        $table    = TableType::CloudStorageSettings->value;
        $provider = $account['Provider'] ?? '';

        $settings = $this->db->queryOne(
            "SELECT BackupPrefix FROM {$table} WHERE Provider = :provider",
            array('provider' => $provider),
        );

        return $settings['BackupPrefix'] ?? 'wp-backup';
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
            array(
                'accountId' => $accountId,
                'type'      => CloudStorageBackupType::Full->value,
                'status'    => CloudStorageBackupStatusType::Success->value,
            ),
        );

        $totalFullBackups = count($fullBackups);
        $hasExcess        = ($totalFullBackups > $retentionCount);

        if (!$hasExcess) {
            return;
        }

        $expiredBackups = array_slice($fullBackups, $retentionCount);

        foreach ($expiredBackups as $expired) {
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
                array('baseId' => $expiredId),
            );

            // Delete the full backup record itself
            $this->db->execute(
                "DELETE FROM {$table} WHERE Id = :id",
                array('id' => $expiredId),
            );

            $parsed = $resolver->parseFullFolderName($folderName);
            $seq = $parsed['sequence'] ?? $expiredId;
            $cleanupMessage = $resolver->buildCleanupCommitMessage($seq);

            $this->fileLogger->info('[CLOUD-ROTATION] Rotated full backup', array(
                'backupId' => $expiredId,
                'folder'   => $folderPath,
                'message'  => $cleanupMessage,
            ));
        }
    }

    /** Get retention count for an account's provider. */
    private function getRetentionCountForAccount(array $account): int
    {
        $table    = TableType::CloudStorageSettings->value;
        $provider = $account['Provider'] ?? '';

        $settings = $this->db->queryOne(
            "SELECT RetentionCount FROM {$table} WHERE Provider = :provider",
            array('provider' => $provider),
        );

        return (int) ($settings['RetentionCount'] ?? 10);
    }

    /**
     * Delete a remote folder and all its contents.
     *
     * @param array  $account Account row.
     * @param string $token   Decrypted token.
     * @param string $path    Remote folder path.
     */
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

    /**
     * Dispatch upload to the appropriate provider.
     *
     * @param array  $account    Account row.
     * @param string $token      Decrypted token.
     * @param string $localPath  Local file path.
     * @param string $remotePath Remote file path.
     * @param string $branch     Target branch name.
     * @return array{RemoteUrl: string, CommitSha: string}
     */
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
            default                    => throw new \RuntimeException('Provider not supported: ' . $provider->value),
        };
    }
}
