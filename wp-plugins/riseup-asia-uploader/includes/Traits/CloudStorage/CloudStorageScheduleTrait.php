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

use RiseupAsia\Enums\BackupScheduleType;
use RiseupAsia\Enums\BackupStrategyType;
use RiseupAsia\Enums\CloudStorageBackupStatusType;
use RiseupAsia\Enums\CloudStorageBackupType;
use RiseupAsia\Enums\CloudStorageProviderType;
use RiseupAsia\Enums\HookType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\TableType;
use RiseupAsia\Helpers\BooleanHelpers;

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

    /** Execute a full backup for a single account. */
    private function executeFullBackupForAccount(array $account): void
    {
        $accountId  = (int) $account['Id'];
        $provider   = CloudStorageProviderType::from($account['Provider']);
        $token      = $provider->isGoogleDrive() ? '' : $this->decryptToken($account['AccessToken']);
        $prefix     = $this->getBackupPrefixForAccount($account);
        $timestamp  = gmdate('Y-m-d-His');
        $fileName   = sprintf('%s-full-%s.zip', $prefix, $timestamp);
        $branchName = $account['DefaultBranch'] ?? 'main';
        $remotePath = sprintf('backups/%s', $fileName);

        $historyId = $this->insertBackupHistory(array(
            'AccountId'  => $accountId,
            'BackupType' => CloudStorageBackupType::Full->value,
            'FileName'   => $fileName,
            'RemotePath' => $remotePath,
            'BranchName' => $branchName,
            'Status'     => CloudStorageBackupStatusType::Pending->value,
        ));

        try {
            $this->updateBackupHistoryStatus($historyId, CloudStorageBackupStatusType::Uploading);

            $startTime = microtime(true);

            // Create snapshot ZIP using existing backup system
            $zipPath = $this->createFullBackupZip($prefix, $timestamp);

            $fileSizeBytes = filesize($zipPath);

            // Upload to provider
            $uploadResult = $this->dispatchCloudUpload($account, $token, $zipPath, $remotePath, $branchName);

            $duration = round(microtime(true) - $startTime, 2);

            $this->updateBackupHistoryStatus($historyId, CloudStorageBackupStatusType::Success, array(
                'RemoteUrl'     => $uploadResult['RemoteUrl'] ?? '',
                'CommitSha'     => $uploadResult['CommitSha'] ?? '',
                'FileSizeBytes' => $fileSizeBytes,
                'Duration'      => $duration,
            ));

            // Apply rotation
            $this->applyFullBackupRotation($account, $token);

            $this->fileLogger->info('[CLOUD-BACKUP] Full backup uploaded', array(
                'accountId' => $accountId,
                'file'      => $fileName,
                'duration'  => $duration,
            ));

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

        $prefix     = $this->getBackupPrefixForAccount($account);
        $timestamp  = gmdate('Y-m-d-His');
        $isoWeek    = gmdate('Y-\WW');
        $fileName   = sprintf('%s-incr-%s.zip', $prefix, $timestamp);
        $branchName = sprintf('incremental/%s', $isoWeek);
        $remotePath = sprintf('backups/%s', $fileName);

        $historyId = $this->insertBackupHistory(array(
            'AccountId'        => $accountId,
            'BackupType'       => CloudStorageBackupType::Incremental->value,
            'FileName'         => $fileName,
            'RemotePath'       => $remotePath,
            'BranchName'       => $branchName,
            'BaseFullBackupId' => (int) $latestFull['Id'],
            'Status'           => CloudStorageBackupStatusType::Pending->value,
        ));

        try {
            $this->updateBackupHistoryStatus($historyId, CloudStorageBackupStatusType::Uploading);

            // Ensure the incremental branch exists
            $isBranchMissing = !$this->branchExists($account, $token, $branchName);

            if ($isBranchMissing) {
                $fullCommitSha = $latestFull['CommitSha'] ?? '';
                $this->createBranch($account, $token, $branchName, $fullCommitSha);
            }

            $startTime = microtime(true);

            // Create incremental ZIP based on timestamp detection
            $lastBackupTimestamp = $latestFull['CreatedAt'] ?? gmdate('Y-m-d H:i:s');
            $incrementalResult  = $this->createIncrementalBackupZip($prefix, $timestamp, $lastBackupTimestamp);

            $zipPath       = $incrementalResult['ZipPath'];
            $fileSizeBytes = filesize($zipPath);

            // Upload to provider on the incremental branch
            $uploadResult = $this->dispatchCloudUpload($account, $token, $zipPath, $remotePath, $branchName);

            $duration = round(microtime(true) - $startTime, 2);

            $this->updateBackupHistoryStatus($historyId, CloudStorageBackupStatusType::Success, array(
                'RemoteUrl'     => $uploadResult['RemoteUrl'] ?? '',
                'CommitSha'     => $uploadResult['CommitSha'] ?? '',
                'FileSizeBytes' => $fileSizeBytes,
                'TablesChanged' => $incrementalResult['TablesChanged'] ?? '',
                'RowsChanged'   => $incrementalResult['RowsChanged'] ?? 0,
                'Duration'      => $duration,
            ));

            $this->fileLogger->info('[CLOUD-BACKUP] Incremental backup uploaded', array(
                'accountId' => $accountId,
                'file'      => $fileName,
                'branch'    => $branchName,
                'duration'  => $duration,
            ));

        } catch (Throwable $e) {
            $this->updateBackupHistoryStatus($historyId, CloudStorageBackupStatusType::Failed, array(
                'ErrorMessage' => $e->getMessage(),
            ));

            $this->fileLogger->logException($e, '[CLOUD-BACKUP] Incremental backup failed for account ' . $accountId);
        }
    }

    // ── Private helpers ──────────────────────────────────────────

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
     * Also prunes associated incremental branches.
     */
    private function applyFullBackupRotation(array $account, string $token): void
    {
        $accountId      = (int) $account['Id'];
        $retentionCount = $this->getRetentionCountForAccount($account);

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
            $expiredId = (int) $expired['Id'];

            // Find and delete associated incremental branches
            $incrementals = $this->db->queryAll(
                "SELECT DISTINCT BranchName FROM {$table} WHERE BaseFullBackupId = :baseId",
                array('baseId' => $expiredId),
            );

            foreach ($incrementals as $incr) {
                $branchName = $incr['BranchName'] ?? '';
                $isBranchPresent = !empty($branchName);

                if ($isBranchPresent) {
                    $this->deleteBranch($account, $token, $branchName);
                    $this->fileLogger->info('[CLOUD-ROTATION] Deleted incremental branch', array('branch' => $branchName));
                }
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

            $this->fileLogger->info('[CLOUD-ROTATION] Rotated full backup', array(
                'backupId' => $expiredId,
                'file'     => $expired['FileName'] ?? '',
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

    // ── Stub methods (implemented by backup system) ──────────────

    /**
     * Create a full backup ZIP. Delegates to the existing snapshot system.
     *
     * @param string $prefix    Backup file prefix.
     * @param string $timestamp Formatted timestamp for filename.
     * @return string Absolute path to the created ZIP.
     */
    abstract private function createFullBackupZip(string $prefix, string $timestamp): string;

    /**
     * Create an incremental backup ZIP with delta data since the given timestamp.
     *
     * @param string $prefix             Backup file prefix.
     * @param string $timestamp          Formatted timestamp for filename.
     * @param string $lastBackupTimestamp ISO timestamp of the last backup.
     * @return array{ZipPath: string, TablesChanged: string, RowsChanged: int}
     */
    abstract private function createIncrementalBackupZip(
        string $prefix,
        string $timestamp,
        string $lastBackupTimestamp
    ): array;

    /**
     * Dispatch upload to the appropriate provider.
     *
     * @param array  $account    Account row.
     * @param string $token      Decrypted token.
     * @param string $localPath  Local ZIP path.
     * @param string $remotePath Remote file path.
     * @param string $branch     Target branch name.
     * @return array{RemoteUrl: string, CommitSha: string}
     */
    abstract private function dispatchCloudUpload(
        array $account,
        string $token,
        string $localPath,
        string $remotePath,
        string $branch
    ): array;
}
