<?php
/**
 * CloudStorageRestoreTrait — Folder-based restore with ZipReassembler.
 *
 * Downloads manifest + chunks from the remote folder, reassembles via
 * ZipReassembler (SHA-256 verification), then extracts the restored ZIP.
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
use WP_REST_Request;
use WP_REST_Response;
use ZipArchive;

use RiseupAsia\CloudStorage\ZipReassembler;
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
                return new WP_REST_Response([
                    ResponseKeyType::Success->value => false,
                    ResponseKeyType::Error->value   => 'Backup not found',
                ], HttpStatusType::NotFound->value);
            }

            $account = $this->getCloudStorageAccountById((int) $backup['AccountId']);

            $isAccountMissing = ($account === false);

            if ($isAccountMissing) {
                return new WP_REST_Response([
                    ResponseKeyType::Success->value => false,
                    ResponseKeyType::Error->value   => 'Cloud storage account not found',
                ], HttpStatusType::NotFound->value);
            }

            $tempDir = sys_get_temp_dir() . '/riseup-restore-' . wp_generate_uuid4();
            mkdir($tempDir, 0755, true);

            // For incremental restore: restore full base first, then apply incremental
            $isIncremental = ($backup['BackupType'] === CloudStorageBackupType::Incremental->value);

            if ($isIncremental) {
                $this->restoreIncrementalWithBase($account, $backup, $tempDir);
            } else {
                $zipPath = $this->downloadAndReassemble($account, $backup, $tempDir);
                $this->extractRestoredZip($zipPath);
            }

            $this->cleanupTempDir($tempDir);

            return new WP_REST_Response([
                ResponseKeyType::Success->value => true,
                ResponseKeyType::Message->value => 'Backup restored successfully',
            ], HttpStatusType::Ok->value);

        } catch (Throwable $e) {
            $this->fileLogger->logException($e, '[CLOUD-RESTORE] Restore failed');

            return new WP_REST_Response([
                ResponseKeyType::Success->value => false,
                ResponseKeyType::Error->value   => $e->getMessage(),
            ], HttpStatusType::InternalServerError->value);
        }
    }

    /**
     * Download chunks from a remote folder and reassemble into a ZIP.
     *
     * Steps:
     *   1. List files in the remote folder (manifest.json + chunks)
     *   2. Download each file to a local temp directory
     *   3. Run ZipReassembler to verify SHA-256 checksums and concatenate
     *
     * @param array  $account Account row.
     * @param array  $backup  Backup history row (must have FolderPath).
     * @param string $tempDir Base temp directory.
     * @return string Absolute path to the reassembled ZIP.
     */
    private function downloadAndReassemble(array $account, array $backup, string $tempDir): string
    {
        $folderPath = $backup['FolderPath'] ?? '';
        $isFolderEmpty = empty($folderPath);

        if ($isFolderEmpty) {
            throw new RuntimeException('Backup has no FolderPath — cannot restore');
        }

        $chunksDir = $tempDir . '/chunks';
        mkdir($chunksDir, 0755, true);

        $this->downloadFolderContents($account, $folderPath, $chunksDir);

        $outputPath = $tempDir . '/restored.zip';
        $reassembler = new ZipReassembler();
        $result = $reassembler->reassemble($chunksDir, $outputPath);

        $isFailed = !$result[ResponseKeyType::Success->value];

        if ($isFailed) {
            throw new RuntimeException(
                'ZipReassembler failed: ' . ($result[ResponseKeyType::Error->value] ?? 'unknown error')
            );
        }

        $this->fileLogger->info('[CLOUD-RESTORE] Reassembled ZIP', [
            'folderPath' => $folderPath,
            'totalSize'  => $result['totalSize'] ?? 0,
            'chunkCount' => $result['chunkCount'] ?? 0,
        ]);

        return $outputPath;
    }

    /**
     * Download all files from a remote folder to a local directory.
     *
     * @param array  $account   Account row.
     * @param string $folderPath Remote folder path (e.g., "full-backup/001 - 15 Mar 2026 - W11").
     * @param string $localDir  Local directory to write files into.
     */
    private function downloadFolderContents(array $account, string $folderPath, string $localDir): void
    {
        $provider = CloudStorageProviderType::from($account['Provider']);
        $token    = $this->decryptToken($account['AccessToken']);
        $branch   = $account['DefaultBranch'] ?? 'main';

        $files = match(true) {
            $provider->isGitHub() => $this->githubListFiles($account, $token, $folderPath),
            $provider->isGitLab() => $this->gitlabListFiles($account, $token, $folderPath),
            default               => throw new RuntimeException('Folder download not supported for ' . $provider->label()),
        };

        $isEmptyFolder = empty($files);

        if ($isEmptyFolder) {
            throw new RuntimeException('Remote folder is empty or not found: ' . $folderPath);
        }

        foreach ($files as $file) {
            $fileName   = $file['name'] ?? basename($file['path'] ?? '');
            $remotePath = $file['path'] ?? ($folderPath . '/' . $fileName);

            $content = match(true) {
                $provider->isGitHub() => $this->githubDownloadFile($account, $token, $remotePath, $branch),
                $provider->isGitLab() => $this->gitlabDownloadFile($account, $token, $remotePath, $branch),
                default               => throw new RuntimeException('File download not supported for ' . $provider->label()),
            };

            $localPath = $localDir . '/' . $fileName;
            file_put_contents($localPath, $content);
        }

        $this->fileLogger->info('[CLOUD-RESTORE] Downloaded folder contents', [
            'folderPath' => $folderPath,
            'fileCount'  => count($files),
        ]);
    }

    /** Restore an incremental backup by first restoring its base full backup. */
    private function restoreIncrementalWithBase(array $account, array $backup, string $tempDir): void
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

        // Download and reassemble both ZIPs
        $fullTempDir = $tempDir . '/full';
        $incrTempDir = $tempDir . '/incr';
        mkdir($fullTempDir, 0755, true);
        mkdir($incrTempDir, 0755, true);

        $fullZipPath = $this->downloadAndReassemble($account, $fullBackup, $fullTempDir);
        $incrZipPath = $this->downloadAndReassemble($account, $backup, $incrTempDir);

        // Restore full first, then apply incremental on top
        $this->extractRestoredZip($fullZipPath);
        $this->extractRestoredZip($incrZipPath, true);
    }

    /**
     * Extract a reassembled ZIP to the WordPress plugins directory.
     *
     * @param string $zipPath      Absolute path to the ZIP file.
     * @param bool   $isIncremental Whether to merge incrementally (true) or replace (false).
     */
    private function extractRestoredZip(string $zipPath, bool $isIncremental = false): void
    {
        $isZipMissing = PathHelper::isFileMissing($zipPath);

        if ($isZipMissing) {
            throw new RuntimeException('Restored ZIP not found: ' . $zipPath);
        }

        $zip = new ZipArchive();
        $openResult = $zip->open($zipPath);

        $isOpenFailed = ($openResult !== true);

        if ($isOpenFailed) {
            throw new RuntimeException('Failed to open restored ZIP: ' . $zipPath);
        }

        $extractDir = WP_PLUGIN_DIR;
        $isExtracted = $zip->extractTo($extractDir);
        $zip->close();

        if ($isExtracted === false) {
            throw new RuntimeException('Failed to extract restored ZIP contents');
        }

        $label = $isIncremental ? 'incremental' : 'full';

        $this->fileLogger->info('[CLOUD-RESTORE] Extracted ' . $label . ' ZIP', [
            'zipPath'    => $zipPath,
            'extractDir' => $extractDir,
        ]);
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
        $output   = [];
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
}
