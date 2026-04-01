<?php
/**
 * CloudStorageScheduleTrait — WP-Cron registration and backup execution.
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

    use CloudStorageScheduleHelpersTrait;

    /** Register WP-Cron schedules for cloud backup frequencies. */
    public function registerCloudBackupCronSchedules(array $schedules): array
    {
        $biweeklyKey = BackupScheduleType::Biweekly->recurrence();

        if (BooleanHelpers::isKeyMissing($schedules, $biweeklyKey)) {
            $schedules[$biweeklyKey] = [
                'interval' => BackupScheduleType::Biweekly->intervalSeconds(),
                'display'  => 'Every Two Weeks',
            ];
        }

        $monthlyKey = BackupScheduleType::Monthly->recurrence();

        if (BooleanHelpers::isKeyMissing($schedules, $monthlyKey)) {
            $schedules[$monthlyKey] = [
                'interval' => BackupScheduleType::Monthly->intervalSeconds(),
                'display'  => 'Once Monthly',
            ];
        }

        return $schedules;
    }

    /** Initialize cloud backup cron hooks. Called from plugin init. */
    public function initCloudBackupSchedule(): void
    {
        add_filter(HookType::CronSchedules->value, [$this, 'registerCloudBackupCronSchedules']);
        add_action(HookType::CronCloudFullBackup->value, [$this, 'handleScheduledFullBackup']);
        add_action(HookType::CronCloudIncrementalBackup->value, [$this, 'handleScheduledIncrementalBackup']);

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

            $this->fileLogger->info('[CLOUD-SCHEDULE] Full backup scheduled', [
                'frequency' => $fullSchedule->value,
                'nextRun'   => gmdate('c', $nextFullRun),
            ]);
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

                $this->fileLogger->info('[CLOUD-SCHEDULE] Incremental backup scheduled', [
                    'frequency' => $incrSchedule->value,
                    'nextRun'   => gmdate('c', $nextIncrRun),
                ]);
            }
        }
    }

    /** Handle scheduled full backup cron event. */
    public function handleScheduledFullBackup(): void
    {
        $this->fileLogger->info('[CLOUD-BACKUP] Starting scheduled full backup');

        $accounts = $this->getEnabledCloudStorageAccounts();

        foreach ($accounts as $account) {
            try {
                $this->executeFullBackupForAccount($account);
            } catch (Throwable $e) {
                $this->fileLogger->logException($e, '[CLOUD-BACKUP] Scheduled full backup failed for account ' . ($account['Id'] ?? '?'));
            }
        }

        $this->fileLogger->info('[CLOUD-BACKUP] Scheduled full backup complete');
    }

    /** Handle scheduled incremental backup cron event. */
    public function handleScheduledIncrementalBackup(): void
    {
        $this->fileLogger->info('[CLOUD-BACKUP] Starting scheduled incremental backup');

        $accounts = $this->getEnabledCloudStorageAccounts();

        foreach ($accounts as $account) {
            try {
                $this->executeIncrementalBackupForAccount($account);
            } catch (Throwable $e) {
                $this->fileLogger->logException($e, '[CLOUD-BACKUP] Scheduled incremental backup failed for account ' . ($account['Id'] ?? '?'));
            }
        }

        $this->fileLogger->info('[CLOUD-BACKUP] Scheduled incremental backup complete');
    }

    /**
     * Handle a manual backup triggered by the user.
     *
     * @param string $label User-provided label for the backup folder.
     */
    public function handleManualBackup(string $label): void
    {
        $this->fileLogger->info('[CLOUD-BACKUP] Starting manual backup', ['label' => $label]);

        $accounts = $this->getEnabledCloudStorageAccounts();

        foreach ($accounts as $account) {
            try {
                $this->executeFullBackupForAccount($account, $label);
            } catch (Throwable $e) {
                $this->fileLogger->logException($e, '[CLOUD-BACKUP] Manual backup failed for account ' . ($account['Id'] ?? '?'));
            }
        }

        $this->fileLogger->info('[CLOUD-BACKUP] Manual backup complete', ['label' => $label]);
    }

    /** Execute a full backup for a single account. */
    private function executeFullBackupForAccount(array $account, ?string $label = null): void
    {
        $accountId = (int) $account['Id'];
        $provider  = CloudStorageProviderType::from($account['Provider']);
        $token     = $provider->isGoogleDrive() ? '' : $this->decryptToken($account['AccessToken']);
        $timestamp = time();

        $resolver = new BackupFolderResolver();
        $existingFolders = $this->listRemoteDirectories($account, $token, BackupFolderResolver::FULL_ROOT);
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

        $historyId = $this->insertBackupHistory([
            'AccountId'  => $accountId,
            'BackupType' => CloudStorageBackupType::Full->value,
            'FileName'   => $folderName,
            'RemotePath' => $folderPath,
            'BranchName' => 'main',
            'FolderPath' => $folderPath,
            'Status'     => CloudStorageBackupStatusType::Pending->value,
        ]);

        $zipPath     = null;
        $splitResult = null;

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

            $this->updateBackupHistoryStatus($historyId, CloudStorageBackupStatusType::Success, [
                'FolderPath'    => $folderPath,
                'ChunkCount'    => $splitResult['chunkCount'],
                'TotalSize'     => $splitResult['totalSize'],
                'FileSizeBytes' => $splitResult['totalSize'],
                'Duration'      => $duration,
            ]);

            // ── 4. Rotation ────────────────────────────────────
            $this->applyFullBackupRotation($account, $token);

            $this->fileLogger->info('[CLOUD-BACKUP] Full backup uploaded', [
                'accountId'  => $accountId,
                'folder'     => $folderPath,
                'chunks'     => $splitResult['chunkCount'],
                'totalSize'  => $splitResult['totalSize'],
                'duration'   => $duration,
            ]);

        } catch (Throwable $e) {
            $this->updateBackupHistoryStatus($historyId, CloudStorageBackupStatusType::Failed, [
                'ErrorMessage' => $e->getMessage(),
            ]);

            $this->fileLogger->logException($e, '[CLOUD-BACKUP] Full backup failed for account ' . $accountId);

        } finally {
            $this->cleanupBackupTempFiles($zipPath, $splitResult);
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
            $this->fileLogger->info('[CLOUD-BACKUP] No full backup found, running full backup instead', [
                'accountId' => $accountId,
            ]);

            $this->executeFullBackupForAccount($account);

            return;
        }

        $resolver = new BackupFolderResolver();
        $parentFolderName = basename($latestFull['FolderPath'] ?? $latestFull['RemotePath'] ?? '');
        $existingIncrSubs = $this->listRemoteDirectories(
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

        $historyId = $this->insertBackupHistory([
            'AccountId'        => $accountId,
            'BackupType'       => CloudStorageBackupType::Incremental->value,
            'FileName'         => str_pad((string) $incrSequence, 3, '0', STR_PAD_LEFT),
            'RemotePath'       => $incrFolderPath,
            'BranchName'       => 'main',
            'FolderPath'       => $incrFolderPath,
            'BaseFullBackupId' => (int) $latestFull['Id'],
            'Status'           => CloudStorageBackupStatusType::Pending->value,
        ]);

        $zipPath     = null;
        $splitResult = null;

        try {
            $this->updateBackupHistoryStatus($historyId, CloudStorageBackupStatusType::Uploading);

            $startTime = microtime(true);

            // ── 1. Create incremental ZIP ──────────────────────
            $incrResult = $this->createIncrementalBackupZip();

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

            $this->updateBackupHistoryStatus($historyId, CloudStorageBackupStatusType::Success, [
                'FolderPath'    => $incrFolderPath,
                'ChunkCount'    => $splitResult['chunkCount'],
                'TotalSize'     => $splitResult['totalSize'],
                'FileSizeBytes' => $splitResult['totalSize'],
                'TablesChanged' => $incrResult[ResponseKeyType::TablesChanged->value] ?? '',
                'RowsChanged'   => $incrResult[ResponseKeyType::TotalNewRows->value] ?? 0,
                'Duration'      => $duration,
            ]);

            $this->fileLogger->info('[CLOUD-BACKUP] Incremental backup uploaded', [
                'accountId'     => $accountId,
                'folder'        => $incrFolderPath,
                'chunks'        => $splitResult['chunkCount'],
                'tablesChanged' => $incrResult[ResponseKeyType::TablesChanged->value] ?? '',
                'duration'      => $duration,
            ]);

        } catch (Throwable $e) {
            $this->updateBackupHistoryStatus($historyId, CloudStorageBackupStatusType::Failed, [
                'ErrorMessage' => $e->getMessage(),
            ]);

            $this->fileLogger->logException($e, '[CLOUD-BACKUP] Incremental backup failed for account ' . $accountId);

        } finally {
            $this->cleanupBackupTempFiles($zipPath, $splitResult);
        }
    }
}
