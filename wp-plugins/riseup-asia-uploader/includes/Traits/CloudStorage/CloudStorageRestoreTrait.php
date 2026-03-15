<?php
/**
 * CloudStorageRestoreTrait — Git-first restore with API fallback.
 *
 * Restores backups by cloning the specific branch via `git clone --depth 1`,
 * falling back to provider REST APIs when the git binary is unavailable.
 *
 * @package RiseupAsia\Traits\CloudStorage
 * @since   2.16.0
 */

namespace RiseupAsia\Traits\CloudStorage;

if (!defined('ABSPATH')) {
    exit;
}

use RuntimeException;
use Throwable;
use WP_REST_Request;
use WP_REST_Response;

use RiseupAsia\Enums\CloudStorageBackupType;
use RiseupAsia\Enums\CloudStorageProviderType;
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Helpers\PathHelper;

trait CloudStorageRestoreTrait {

    /** POST /cloud-storage/restore */
    public function handleCloudStorageRestore(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $params   = $request->get_json_params();
            $backupId = (int) ($params[ResponseKeyType::BackupId->value] ?? 0);
            $backup   = $this->getBackupHistoryById($backupId);

            $isNotFound = ($backup === false);

            if ($isNotFound) {
                return new WP_REST_Response(array(
                    ResponseKeyType::Success->value => false,
                    ResponseKeyType::Error->value   => 'Backup not found',
                ), HttpStatusType::NotFound->value);
            }

            $account = $this->getCloudStorageAccountById((int) $backup['AccountId']);

            $isAccountMissing = ($account === false);

            if ($isAccountMissing) {
                return new WP_REST_Response(array(
                    ResponseKeyType::Success->value => false,
                    ResponseKeyType::Error->value   => 'Cloud storage account not found',
                ), HttpStatusType::NotFound->value);
            }

            $tempDir = sys_get_temp_dir() . '/riseup-restore-' . wp_generate_uuid4();

            $this->downloadBackupToTemp($account, $backup, $tempDir);

            $zipPath      = $tempDir . '/' . $backup['RemotePath'];
            $isZipMissing = !file_exists($zipPath);

            if ($isZipMissing) {
                $this->cleanupTempDir($tempDir);

                return new WP_REST_Response(array(
                    ResponseKeyType::Success->value => false,
                    ResponseKeyType::Error->value   => 'Backup ZIP not found after download',
                ), HttpStatusType::InternalServerError->value);
            }

            // For incremental restore: also fetch and restore the base full backup first
            $isIncremental = ($backup['BackupType'] === CloudStorageBackupType::Incremental->value);

            if ($isIncremental) {
                $this->restoreIncrementalWithBase($account, $backup, $zipPath);
            } else {
                $this->restoreFromZip($zipPath);
            }

            $this->cleanupTempDir($tempDir);

            return new WP_REST_Response(array(
                ResponseKeyType::Success->value => true,
                ResponseKeyType::Message->value => 'Backup restored successfully',
            ), HttpStatusType::Ok->value);

        } catch (Throwable $e) {
            $this->fileLogger->logException($e, '[CLOUD-RESTORE] Restore failed');

            return new WP_REST_Response(array(
                ResponseKeyType::Success->value => false,
                ResponseKeyType::Error->value   => $e->getMessage(),
            ), HttpStatusType::InternalServerError->value);
        }
    }

    /**
     * Download a backup to a temp directory using git clone or API fallback.
     *
     * @param array  $account Account row.
     * @param array  $backup  Backup history row.
     * @param string $tempDir Temp directory path.
     */
    private function downloadBackupToTemp(array $account, array $backup, string $tempDir): void
    {
        $branchName   = $backup['BranchName'] ?? 'main';
        $isGitPresent = $this->isShellCommandAvailable('git');

        if ($isGitPresent) {
            $this->gitCloneShallow($account, $branchName, $tempDir);
        } else {
            $this->downloadViaApi($account, $backup['RemotePath'], $branchName, $tempDir);
        }
    }

    /** Restore an incremental backup by first restoring its base full backup. */
    private function restoreIncrementalWithBase(array $account, array $backup, string $incrZipPath): void
    {
        $baseFullId = $backup['BaseFullBackupId'] ?? null;
        $hasNoBase  = ($baseFullId === null);

        if ($hasNoBase) {
            throw new RuntimeException('Incremental backup has no base full backup reference');
        }

        $fullBackup = $this->getBackupHistoryById((int) $baseFullId);
        $isFullMissing = ($fullBackup === false);

        if ($isFullMissing) {
            throw new RuntimeException('Base full backup record not found');
        }

        // Download and restore the full backup first
        $fullTempDir = sys_get_temp_dir() . '/riseup-restore-full-' . wp_generate_uuid4();

        try {
            $this->downloadBackupToTemp($account, $fullBackup, $fullTempDir);

            $fullZipPath      = $fullTempDir . '/' . $fullBackup['RemotePath'];
            $isFullZipMissing = !file_exists($fullZipPath);

            if ($isFullZipMissing) {
                throw new RuntimeException('Base full backup ZIP not found after download');
            }

            // Restore full first, then apply incremental on top
            $this->restoreFromZip($fullZipPath);
            $this->restoreFromZip($incrZipPath, true); // true = incremental merge

        } finally {
            $this->cleanupTempDir($fullTempDir);
        }
    }

    /**
     * Shallow clone a specific branch from the remote repository.
     *
     * @param array  $account Account row with Provider, RepoOwner, RepoName, AccessToken.
     * @param string $branch  Branch name to clone.
     * @param string $destDir Destination directory.
     */
    private function gitCloneShallow(array $account, string $branch, string $destDir): void
    {
        $provider = CloudStorageProviderType::from($account['Provider']);
        $token    = $this->decryptToken($account['AccessToken']);

        $repoUrl = match(true) {
            $provider->isGitHub() => sprintf(
                'https://%s@github.com/%s/%s.git',
                $token,
                $account['RepoOwner'] ?? '',
                $account['RepoName'] ?? '',
            ),
            $provider->isGitLab() => sprintf(
                'https://oauth2:%s@%s/%s/%s.git',
                $token,
                rtrim($account['BaseUrl'] ?: 'gitlab.com', '/'),
                $account['RepoOwner'] ?? '',
                $account['RepoName'] ?? '',
            ),
            default => throw new RuntimeException('Git clone not supported for ' . $provider->label()),
        };

        $command = sprintf(
            'git clone --depth 1 --branch %s --single-branch %s %s 2>&1',
            escapeshellarg($branch),
            escapeshellarg($repoUrl),
            escapeshellarg($destDir),
        );

        $output   = array();
        $exitCode = 0;
        exec($command, $output, $exitCode);

        $isCloneFailed = ($exitCode !== 0);

        if ($isCloneFailed) {
            throw new RuntimeException(
                sprintf('Git clone failed (exit %d): %s', $exitCode, implode("\n", $output)),
            );
        }

        $this->fileLogger->info('[CLOUD-RESTORE] Git clone successful', array(
            'branch' => $branch,
            'dest'   => $destDir,
        ));
    }

    /**
     * Download a backup file via provider REST API (fallback when git is unavailable).
     *
     * @param array  $account    Account row.
     * @param string $remotePath Path within the repository.
     * @param string $branch     Branch name.
     * @param string $tempDir    Local temp directory to write the file into.
     */
    private function downloadViaApi(
        array $account,
        string $remotePath,
        string $branch,
        string $tempDir
    ): void {
        $provider = CloudStorageProviderType::from($account['Provider']);
        $token    = $this->decryptToken($account['AccessToken']);

        $content = match(true) {
            $provider->isGitHub() => $this->githubDownloadFile($account, $token, $remotePath, $branch),
            $provider->isGitLab() => $this->gitlabDownloadFile($account, $token, $remotePath, $branch),
            default               => throw new RuntimeException('API download not supported for ' . $provider->label()),
        };

        $localPath = $tempDir . '/' . $remotePath;
        $localDir  = dirname($localPath);

        $isDirMissing = !is_dir($localDir);

        if ($isDirMissing) {
            mkdir($localDir, 0755, true);
        }

        file_put_contents($localPath, $content);

        $this->fileLogger->info('[CLOUD-RESTORE] API download successful', array(
            'remotePath' => $remotePath,
            'branch'     => $branch,
        ));
    }

    /**
     * Download file content from GitHub Contents API.
     *
     * @param array  $account    Account row.
     * @param string $token      Decrypted access token.
     * @param string $remotePath File path in the repo.
     * @param string $branch     Branch name.
     * @return string Raw file content.
     */
    private function githubDownloadFile(
        array $account,
        string $token,
        string $remotePath,
        string $branch
    ): string {
        $owner = $account['RepoOwner'] ?? '';
        $repo  = $account['RepoName'] ?? '';
        $path  = sprintf(
            '/repos/%s/%s/contents/%s?ref=%s',
            urlencode($owner),
            urlencode($repo),
            $remotePath,
            urlencode($branch),
        );

        $response  = $this->githubApiRequest('GET', $path, $token);
        $isBase64  = (($response['encoding'] ?? '') === 'base64');
        $content   = $response['content'] ?? '';

        if ($isBase64) {
            return base64_decode($content);
        }

        return $content;
    }

    /**
     * Download file content from GitLab Files API.
     *
     * @param array  $account    Account row.
     * @param string $token      Decrypted access token.
     * @param string $remotePath File path in the repo.
     * @param string $branch     Branch name.
     * @return string Raw file content.
     */
    private function gitlabDownloadFile(
        array $account,
        string $token,
        string $remotePath,
        string $branch
    ): string {
        $projectId  = $this->gitlabProjectId($account);
        $encodedPath = urlencode($remotePath);
        $path = sprintf(
            '/projects/%s/repository/files/%s/raw?ref=%s',
            urlencode($projectId),
            $encodedPath,
            urlencode($branch),
        );

        return $this->gitlabApiRequestRaw('GET', $path, $token, $account);
    }

    /**
     * Check if a shell command is available.
     *
     * @param string $command Command name to check.
     * @return bool Whether the command is available.
     */
    private function isShellCommandAvailable(string $command): bool
    {
        $output   = array();
        $exitCode = 0;
        exec(sprintf('which %s 2>/dev/null', escapeshellarg($command)), $output, $exitCode);

        return ($exitCode === 0);
    }

    /** Remove a temp directory and all its contents. */
    private function cleanupTempDir(string $dir): void
    {
        $isDirPresent = is_dir($dir);

        if (!$isDirPresent) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
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

        rmdir($dir);
    }

    // ── Stub methods (implemented by snapshot restore system) ────

    /**
     * Restore site data from a ZIP file.
     *
     * @param string $zipPath      Absolute path to the ZIP.
     * @param bool   $isIncremental Whether to merge incrementally (true) or replace (false).
     */
    abstract private function restoreFromZip(string $zipPath, bool $isIncremental = false): void;
}
