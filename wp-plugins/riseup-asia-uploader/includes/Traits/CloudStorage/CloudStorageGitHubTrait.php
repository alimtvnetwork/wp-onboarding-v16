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

        return array(
            ResponseKeyType::Success->value          => true,
            ResponseKeyType::ConnectionStatus->value => 'Connected',
            ResponseKeyType::Username->value         => $login,
            ResponseKeyType::Message->value          => sprintf('Successfully authenticated as %s', $login),
        );
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
            return array('exists' => true, 'created' => false);
        }

        $isOrg  = $this->githubIsOrganization($owner, $token);
        $apiUrl = $isOrg ? sprintf('/orgs/%s/repos', urlencode($owner)) : '/user/repos';

        $this->githubApiRequest('POST', $apiUrl, $token, array(
            'name'        => $repo,
            'description' => 'WordPress site backups managed by Riseup Asia Uploader',
            'private'     => true,
            'auto_init'   => true,
        ));

        return array('exists' => true, 'created' => true);
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

        $putBody = array(
            'message' => sprintf('Backup: %s', basename($remotePath)),
            'content' => base64_encode($content),
            'branch'  => 'main',
        );

        $isUpdate = !empty($existingSha);

        if ($isUpdate) {
            $putBody['sha'] = $existingSha;
        }

        $body = $this->githubApiRequest('PUT', $contentsPath, $token, $putBody);

        return array(
            ResponseKeyType::RemotePath->value => $body['content']['path'] ?? $remotePath,
            ResponseKeyType::RemoteUrl->value  => $body['content']['html_url'] ?? '',
            ResponseKeyType::Bytes->value      => $fileSize,
        );
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
        $blobBody = $this->githubApiRequest('POST', "{$base}/git/blobs", $token, array(
            'content'  => base64_encode($content),
            'encoding' => 'base64',
        ));
        $blobSha = $blobBody['sha'] ?? '';

        $treeBody = $this->githubApiRequest('POST', "{$base}/git/trees", $token, array(
            'base_tree' => $baseTreeSha,
            'tree'      => array(array(
                'path' => $remotePath,
                'mode' => '100644',
                'type' => 'blob',
                'sha'  => $blobSha,
            )),
        ));
        $newTreeSha = $treeBody['sha'] ?? '';

        $newCommitBody = $this->githubApiRequest('POST', "{$base}/git/commits", $token, array(
            'message' => sprintf('Backup: %s', basename($remotePath)),
            'tree'    => $newTreeSha,
            'parents' => array($lastCommitSha),
        ));
        $newCommitSha = $newCommitBody['sha'] ?? '';

        $this->githubApiRequest('PATCH', "{$base}/git/refs/heads/main", $token, array(
            'sha' => $newCommitSha,
        ));

        return array(
            ResponseKeyType::RemotePath->value => $remotePath,
            ResponseKeyType::RemoteUrl->value  => sprintf('https://github.com/%s/%s/blob/main/%s', $owner, $repo, $remotePath),
            ResponseKeyType::Bytes->value      => filesize($localPath),
        );
    }

    /** List files in a repository directory. */
    private function githubListFiles(array $account, string $token, string $dir): array
    {
        $owner = $account['RepoOwner'] ?? '';
        $repo  = $account['RepoName'] ?? 'wp-backups';

        $path = sprintf('/repos/%s/%s/contents/%s', urlencode($owner), urlencode($repo), $dir);

        $statusCode = $this->githubApiStatusCode('GET', $path, $token);
        $isNotFound = ($statusCode === HttpStatusType::NotFound->value);

        if ($isNotFound) {
            return array();
        }

        $body  = $this->githubApiRequest('GET', $path, $token);
        $files = array();

        foreach ($body as $item) {
            $isFile = (($item['type'] ?? '') === 'file');

            if ($isFile) {
                $files[] = array(
                    'Name'      => $item['name'] ?? '',
                    'Path'      => $item['path'] ?? '',
                    'Size'      => $item['size'] ?? 0,
                    'Sha'       => $item['sha'] ?? '',
                    'RemoteUrl' => $item['html_url'] ?? '',
                );
            }
        }

        return $files;
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

        $this->githubApiRequest('DELETE', $contentsPath, $token, array(
            'message' => sprintf('Remove old backup: %s', basename($remotePath)),
            'sha'     => $sha,
            'branch'  => 'main',
        ));

        return true;
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
        $decoded     = json_decode(wp_remote_retrieve_body($response), true) ?? array();
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
