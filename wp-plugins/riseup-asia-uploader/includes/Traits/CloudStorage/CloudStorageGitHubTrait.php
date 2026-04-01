<?php
/**
 * CloudStorageGitHubTrait — GitHub API operations for cloud storage.
 *
 * Supports PAT authentication, Contents API (files ≤100 MB), and
 * Git Data API (blobs/trees/commits for files >100 MB).
 *
 * @package RiseupAsia\Traits\CloudStorage
 * @since   2.15.0
 */

namespace RiseupAsia\Traits\CloudStorage;

if (!defined('ABSPATH')) {
    exit;
}

use RuntimeException;
use Throwable;

use RiseupAsia\Enums\CloudStorageProviderType;
use RiseupAsia\Enums\HttpConfigType;
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Helpers\PathHelper;

trait CloudStorageGitHubTrait {

    private const GITHUB_API            = 'https://api.github.com';
    private const GITHUB_API_VERSION    = '2022-11-28';
    private const GITHUB_LARGE_FILE_MAX = 104857600; // 100 MB

    /** Test connection by verifying the authenticated user. */
    private function githubTestConnection(array $account, string $token): array
    {
        $body = $this->githubApiRequest('GET', '/user', $token);
        $login = $body['login'] ?? '';

        return [
            ResponseKeyType::Success->value          => true,
            ResponseKeyType::ConnectionStatus->value => 'Connected',
            ResponseKeyType::Username->value         => $login,
            ResponseKeyType::Message->value          => sprintf('Successfully authenticated as %s', $login),
        ];
    }

    /** Ensure the target repository exists; create if missing. */
    private function githubEnsureRepo(array $account, string $token): array
    {
        $owner = $account['RepoOwner'] ?? '';
        $repo  = $account['RepoName'] ?? 'wp-backups';

        $path       = sprintf('/repos/%s/%s', urlencode($owner), urlencode($repo));
        $statusCode = $this->githubApiStatusCode('GET', $path, $token);

        $repoExists = ($statusCode === HttpStatusType::Ok->value);

        if ($repoExists) {
            return ['exists' => true, 'created' => false];
        }

        $isOrg  = $this->githubIsOrganization($owner, $token);
        $apiUrl = $isOrg ? sprintf('/orgs/%s/repos', urlencode($owner)) : '/user/repos';

        $this->githubApiRequest('POST', $apiUrl, $token, [
            'name'        => $repo,
            'description' => 'WordPress site backups managed by Riseup Asia Uploader',
            'private'     => true,
            'auto_init'   => true,
        ]);

        return ['exists' => true, 'created' => true];
    }

    /** Upload a file via the Contents API (≤100 MB). */
    private function githubUploadFile(array $account, string $token, string $localPath, string $remotePath): array
    {
        $owner = $account['RepoOwner'] ?? '';
        $repo  = $account['RepoName'] ?? 'wp-backups';

        $this->githubEnsureRepo($account, $token);

        $fileSize = filesize($localPath);
        $isLarge  = ($fileSize > self::GITHUB_LARGE_FILE_MAX);

        if ($isLarge) {
            return $this->githubUploadLargeFile($account, $token, $localPath, $remotePath);
        }

        $contentsPath = sprintf('/repos/%s/%s/contents/%s', urlencode($owner), urlencode($repo), $remotePath);
        $existingSha  = $this->githubGetFileSha($contentsPath, $token);

        $content = file_get_contents($localPath);

        $putBody = [
            'message' => sprintf('Backup: %s', basename($remotePath)),
            'content' => base64_encode($content),
            'branch'  => 'main',
        ];

        $isUpdate = !empty($existingSha);

        if ($isUpdate) {
            $putBody['sha'] = $existingSha;
        }

        $body = $this->githubApiRequest('PUT', $contentsPath, $token, $putBody);

        return [
            ResponseKeyType::RemotePath->value => $body['content']['path'] ?? $remotePath,
            ResponseKeyType::RemoteUrl->value  => $body['content']['html_url'] ?? '',
            ResponseKeyType::Bytes->value      => $fileSize,
        ];
    }

    /** Upload a large file via the Git Data API (blob → tree → commit). */
    private function githubUploadLargeFile(array $account, string $token, string $localPath, string $remotePath): array
    {
        $owner = $account['RepoOwner'] ?? '';
        $repo  = $account['RepoName'] ?? 'wp-backups';
        $base  = sprintf('/repos/%s/%s', urlencode($owner), urlencode($repo));

        $refBody       = $this->githubApiRequest('GET', "{$base}/git/refs/heads/main", $token);
        $lastCommitSha = $refBody['object']['sha'] ?? '';

        $commitBody  = $this->githubApiRequest('GET', "{$base}/git/commits/{$lastCommitSha}", $token);
        $baseTreeSha = $commitBody['tree']['sha'] ?? '';

        $content  = file_get_contents($localPath);
        $blobBody = $this->githubApiRequest('POST', "{$base}/git/blobs", $token, [
            'content'  => base64_encode($content),
            'encoding' => 'base64',
        ]);
        $blobSha = $blobBody['sha'] ?? '';

        $treeBody = $this->githubApiRequest('POST', "{$base}/git/trees", $token, [
            'base_tree' => $baseTreeSha,
            'tree'      => [[
                'path' => $remotePath,
                'mode' => '100644',
                'type' => 'blob',
                'sha'  => $blobSha,
            ]],
        ]);
        $newTreeSha = $treeBody['sha'] ?? '';

        $newCommitBody = $this->githubApiRequest('POST', "{$base}/git/commits", $token, [
            'message' => sprintf('Backup: %s', basename($remotePath)),
            'tree'    => $newTreeSha,
            'parents' => [$lastCommitSha],
        ]);
        $newCommitSha = $newCommitBody['sha'] ?? '';

        $this->githubApiRequest('PATCH', "{$base}/git/refs/heads/main", $token, [
            'sha' => $newCommitSha,
        ]);

        return [
            ResponseKeyType::RemotePath->value => $remotePath,
            ResponseKeyType::RemoteUrl->value  => sprintf('https://github.com/%s/%s/blob/main/%s', $owner, $repo, $remotePath),
            ResponseKeyType::Bytes->value      => filesize($localPath),
        ];
    }

    /** List files in a repository directory. */
    private function githubListFiles(array $account, string $token, string $dir): array
    {
        $body = $this->githubListContents($account, $token, $dir);
        $files = [];

        foreach ($body as $item) {
            $isFile = (($item['type'] ?? '') === 'file');

            if ($isFile) {
                $files[] = [
                    'Name'      => $item['name'] ?? '',
                    'Path'      => $item['path'] ?? '',
                    'Size'      => $item['size'] ?? 0,
                    'Sha'       => $item['sha'] ?? '',
                    'RemoteUrl' => $item['html_url'] ?? '',
                ];
            }
        }

        return $files;
    }

    /**
     * List subdirectory names in a repository directory.
     *
     * @param array  $account Account row.
     * @param string $token   Decrypted access token.
     * @param string $dir     Directory path.
     * @return array<string> Directory names (not full paths).
     */
    private function githubListDirectories(array $account, string $token, string $dir): array
    {
        $body = $this->githubListContents($account, $token, $dir);
        $dirs = [];

        foreach ($body as $item) {
            $isDir = (($item['type'] ?? '') === 'dir');

            if ($isDir) {
                $dirs[] = $item['name'] ?? '';
            }
        }

        return $dirs;
    }

    /**
     * Fetch raw Contents API response for a directory.
     *
     * @param array  $account Account row.
     * @param string $token   Decrypted access token.
     * @param string $dir     Directory path.
     * @return array Raw API response items.
     */
    private function githubListContents(array $account, string $token, string $dir): array
    {
        $owner = $account['RepoOwner'] ?? '';
        $repo  = $account['RepoName'] ?? 'wp-backups';

        $path = sprintf('/repos/%s/%s/contents/%s', urlencode($owner), urlencode($repo), $dir);

        $statusCode = $this->githubApiStatusCode('GET', $path, $token);
        $isNotFound = ($statusCode === HttpStatusType::NotFound->value);

        if ($isNotFound) {
            return [];
        }

        return $this->githubApiRequest('GET', $path, $token);
    }

    /** Delete a file from the repository. */
    private function githubDeleteFile(array $account, string $token, string $remotePath): bool
    {
        $owner = $account['RepoOwner'] ?? '';
        $repo  = $account['RepoName'] ?? 'wp-backups';

        $contentsPath = sprintf('/repos/%s/%s/contents/%s', urlencode($owner), urlencode($repo), $remotePath);
        $sha          = $this->githubGetFileSha($contentsPath, $token);

        $isMissing = empty($sha);

        if ($isMissing) {
            return false;
        }

        $this->githubApiRequest('DELETE', $contentsPath, $token, [
            'message' => sprintf('Remove old backup: %s', basename($remotePath)),
            'sha'     => $sha,
            'branch'  => 'main',
        ]);

        return true;
    }

    /**
     * Delete a folder and all its contents from the repository in a single commit.
     *
     * Uses the Git Data API (tree → commit → ref update) to remove all files
     * under the given path atomically, avoiding per-file API calls.
     *
     * @param array  $account Account row.
     * @param string $token   Decrypted access token.
     * @param string $path    Remote folder path (e.g., "full-backup/001 - 15 Mar 2026 - W11").
     */
    private function githubDeleteFolder(array $account, string $token, string $path): void
    {
        $owner = $account['RepoOwner'] ?? '';
        $repo  = $account['RepoName'] ?? 'wp-backups';
        $base  = sprintf('/repos/%s/%s', urlencode($owner), urlencode($repo));

        // 1. List all files in the folder recursively
        $filePaths = $this->githubListFolderFilesRecursive($account, $token, $path);
        $hasNoFiles = empty($filePaths);

        if ($hasNoFiles) {
            return;
        }

        // 2. Get current HEAD commit and its tree
        $refBody       = $this->githubApiRequest('GET', "{$base}/git/refs/heads/main", $token);
        $lastCommitSha = $refBody['object']['sha'] ?? '';

        $commitBody  = $this->githubApiRequest('GET', "{$base}/git/commits/{$lastCommitSha}", $token);
        $baseTreeSha = $commitBody['tree']['sha'] ?? '';

        // 3. Build tree entries that delete each file (sha = null removes it)
        $treeEntries = [];

        foreach ($filePaths as $filePath) {
            $treeEntries[] = [
                'path' => $filePath,
                'mode' => '100644',
                'type' => 'blob',
                'sha'  => null,
            ];
        }

        $treeBody = $this->githubApiRequest('POST', "{$base}/git/trees", $token, [
            'base_tree' => $baseTreeSha,
            'tree'      => $treeEntries,
        ]);
        $newTreeSha = $treeBody['sha'] ?? '';

        // 4. Create commit and update ref
        $newCommitBody = $this->githubApiRequest('POST', "{$base}/git/commits", $token, [
            'message' => sprintf('cleanup: remove folder %s', $path),
            'tree'    => $newTreeSha,
            'parents' => [$lastCommitSha],
        ]);
        $newCommitSha = $newCommitBody['sha'] ?? '';

        $this->githubApiRequest('PATCH', "{$base}/git/refs/heads/main", $token, [
            'sha' => $newCommitSha,
        ]);

        $this->fileLogger->info('[CLOUD-GITHUB] Deleted folder', [
            'path'      => $path,
            'fileCount' => count($filePaths),
        ]);
    }

    /**
     * Recursively list all file paths under a directory in the repository.
     *
     * @param array  $account Account row.
     * @param string $token   Decrypted access token.
     * @param string $dir     Directory path.
     * @return array<string> Flat list of file paths.
     */
    private function githubListFolderFilesRecursive(array $account, string $token, string $dir): array
    {
        $owner = $account['RepoOwner'] ?? '';
        $repo  = $account['RepoName'] ?? 'wp-backups';

        $contentsPath = sprintf('/repos/%s/%s/contents/%s', urlencode($owner), urlencode($repo), $dir);
        $statusCode   = $this->githubApiStatusCode('GET', $contentsPath, $token);
        $isNotFound   = ($statusCode === HttpStatusType::NotFound->value);

        if ($isNotFound) {
            return [];
        }

        $body  = $this->githubApiRequest('GET', $contentsPath, $token);
        $paths = [];

        foreach ($body as $item) {
            $type = $item['type'] ?? '';
            $itemPath = $item['path'] ?? '';

            $isFile = ($type === 'file');

            if ($isFile) {
                $paths[] = $itemPath;
            }

            $isDir = ($type === 'dir');

            if ($isDir) {
                $subPaths = $this->githubListFolderFilesRecursive($account, $token, $itemPath);
                $paths = array_merge($paths, $subPaths);
            }
        }

        return $paths;
    }

    // ── GitHub Private Helpers ──────────────────────────────────────

    /** Build authenticated HTTP options for GitHub. */
    private function githubBuildOptions(string $method, string $token, ?array $body = null): array
    {
        $options = HttpConfigType::authenticatedOptions($method, 'Bearer ' . $token);
        $options['headers']['Accept']               = 'application/vnd.github+json';
        $options['headers']['User-Agent']            = PluginConfigType::Slug->value;
        $options['headers']['X-GitHub-Api-Version']  = self::GITHUB_API_VERSION;

        $hasBody = ($body !== null);

        if ($hasBody) {
            $options['body'] = wp_json_encode($body);
        }

        return $options;
    }

    /** Make a GitHub API request and return decoded body. */
    private function githubApiRequest(string $method, string $path, string $token, ?array $body = null): array
    {
        $url     = self::GITHUB_API . $path;
        $options = $this->githubBuildOptions($method, $token, $body);

        $response = wp_remote_request($url, $options);
        $isWpError = is_wp_error($response);

        if ($isWpError) {
            throw new RuntimeException('GitHub API request failed: ' . $response->get_error_message());
        }

        $statusCode  = wp_remote_retrieve_response_code($response);
        $decoded     = json_decode(wp_remote_retrieve_body($response), true) ?? [];
        $isRateLimit = ($statusCode === 403);

        if ($isRateLimit) {
            $resetAt = wp_remote_retrieve_header($response, 'X-RateLimit-Reset');

            throw new RuntimeException(
                sprintf('GitHub API rate limited. Resets at %s', date('Y-m-d H:i:s', (int) $resetAt)),
            );
        }

        $isClientError = ($statusCode >= 400);

        if ($isClientError) {
            throw new RuntimeException(
                sprintf('GitHub API error [%d]: %s', $statusCode, $decoded['message'] ?? 'Unknown error'),
            );
        }

        return $decoded;
    }

    /** Get the HTTP status code for a GitHub API request. */
    private function githubApiStatusCode(string $method, string $path, string $token): int
    {
        $url      = self::GITHUB_API . $path;
        $options  = $this->githubBuildOptions($method, $token);
        $response = wp_remote_request($url, $options);

        $isWpError = is_wp_error($response);

        if ($isWpError) {
            return 0;
        }

        return (int) wp_remote_retrieve_response_code($response);
    }

    /** Get the SHA of an existing file (for update/delete). */
    private function githubGetFileSha(string $contentsPath, string $token): string
    {
        $statusCode = $this->githubApiStatusCode('GET', $contentsPath, $token);
        $fileExists = ($statusCode === HttpStatusType::Ok->value);

        if (!$fileExists) {
            return '';
        }

        $body = $this->githubApiRequest('GET', $contentsPath, $token);

        return $body['sha'] ?? '';
    }

    /** Determine if the owner is an organization (for repo creation). */
    private function githubIsOrganization(string $owner, string $token): bool
    {
        $userBody    = $this->githubApiRequest('GET', '/user', $token);
        $currentUser = $userBody['login'] ?? '';
        $isDifferent = ($owner !== $currentUser);

        return $isDifferent;
    }
}
