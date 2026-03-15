<?php
/**
 * CloudStorageGitLabTrait — GitLab API operations for cloud storage.
 *
 * Supports PAT authentication via PRIVATE-TOKEN header, self-hosted GitLab
 * instances via BaseUrl, Repository Files API (create/update), and
 * Commits API for large file uploads.
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

trait CloudStorageGitLabTrait {

    private const GITLAB_DEFAULT_BASE = 'https://gitlab.com';

    /** Test connection by verifying the authenticated user. */
    private function gitlabTestConnection(array $account, string $token): array
    {
        $apiBase = $this->gitlabGetApiBase($account);
        $body    = $this->gitlabApiRequest('GET', $apiBase, '/user', $token);
        $username = $body['username'] ?? '';

        return array(
            ResponseKeyType::Success->value          => true,
            ResponseKeyType::ConnectionStatus->value => 'Connected',
            ResponseKeyType::Username->value         => $username,
            ResponseKeyType::Message->value          => sprintf('Successfully authenticated as %s', $username),
        );
    }

    /** Ensure the target project exists; create if missing. */
    private function gitlabEnsureProject(array $account, string $token): array
    {
        $apiBase     = $this->gitlabGetApiBase($account);
        $namespace   = $account['RepoOwner'] ?? '';
        $projectName = $account['RepoName'] ?? 'wp-backups';
        $projectPath = urlencode($namespace . '/' . $projectName);

        $statusCode   = $this->gitlabApiStatusCode('GET', $apiBase, '/projects/' . $projectPath, $token);
        $projectExists = ($statusCode === HttpStatusType::Ok->value);

        if ($projectExists) {
            return array('exists' => true, 'created' => false);
        }

        $createBody = array(
            'name'                   => $projectName,
            'description'            => 'WordPress site backups managed by Riseup Asia Uploader',
            'visibility'             => 'private',
            'initialize_with_readme' => true,
        );

        $namespaceId = $this->gitlabResolveNamespaceId($apiBase, $token, $namespace);
        $hasNamespaceId = ($namespaceId > 0);

        if ($hasNamespaceId) {
            $createBody['namespace_id'] = $namespaceId;
        }

        $this->gitlabApiRequest('POST', $apiBase, '/projects', $token, $createBody);

        return array('exists' => true, 'created' => true);
    }

    /** Upload a file via the Repository Files API. */
    private function gitlabUploadFile(array $account, string $token, string $localPath, string $remotePath): array
    {
        $apiBase     = $this->gitlabGetApiBase($account);
        $namespace   = $account['RepoOwner'] ?? '';
        $projectName = $account['RepoName'] ?? 'wp-backups';
        $projectPath = urlencode($namespace . '/' . $projectName);

        $this->gitlabEnsureProject($account, $token);

        $encodedFilePath = urlencode($remotePath);
        $fileUrl         = sprintf('/projects/%s/repository/files/%s', $projectPath, $encodedFilePath);
        $content         = file_get_contents($localPath);
        $fileSize        = filesize($localPath);

        $fileBody = array(
            'branch'         => 'main',
            'commit_message' => sprintf('Backup: %s', basename($remotePath)),
            'content'        => base64_encode($content),
            'encoding'       => 'base64',
        );

        $existsStatus = $this->gitlabApiStatusCode(
            'HEAD',
            $apiBase,
            $fileUrl . '?ref=main',
            $token,
        );

        $fileExists = ($existsStatus === HttpStatusType::Ok->value);
        $method     = $fileExists ? 'PUT' : 'POST';

        $this->gitlabApiRequest($method, $apiBase, $fileUrl, $token, $fileBody);

        $baseUrl  = rtrim($account['BaseUrl'] ?? self::GITLAB_DEFAULT_BASE, '/');
        $webUrl   = sprintf('%s/%s/-/blob/main/%s', $baseUrl, $namespace . '/' . $projectName, $remotePath);

        return array(
            ResponseKeyType::RemotePath->value => $remotePath,
            ResponseKeyType::RemoteUrl->value  => $webUrl,
            ResponseKeyType::Bytes->value      => $fileSize,
        );
    }

    /** List files in a repository directory. */
    private function gitlabListFiles(array $account, string $token, string $dir): array
    {
        $apiBase     = $this->gitlabGetApiBase($account);
        $namespace   = $account['RepoOwner'] ?? '';
        $projectName = $account['RepoName'] ?? 'wp-backups';
        $projectPath = urlencode($namespace . '/' . $projectName);

        $treePath   = sprintf('/projects/%s/repository/tree?path=%s&ref=main', $projectPath, urlencode($dir));
        $statusCode = $this->gitlabApiStatusCode('GET', $apiBase, $treePath, $token);
        $isNotFound = ($statusCode === HttpStatusType::NotFound->value);

        if ($isNotFound) {
            return array();
        }

        $body  = $this->gitlabApiRequest('GET', $apiBase, $treePath, $token);
        $files = array();

        foreach ($body as $item) {
            $isBlob = (($item['type'] ?? '') === 'blob');

            if ($isBlob) {
                $filePath        = $item['path'] ?? '';
                $encodedFilePath = urlencode($filePath);
                $fileMetaPath    = sprintf('/projects/%s/repository/files/%s?ref=main', $projectPath, $encodedFilePath);

                $fileMeta = $this->gitlabApiRequest('GET', $apiBase, $fileMetaPath, $token);

                $files[] = array(
                    'Name'      => $item['name'] ?? '',
                    'Path'      => $filePath,
                    'Size'      => (int) ($fileMeta['size'] ?? 0),
                    'RemoteUrl' => '',
                );
            }
        }

        return $files;
    }

    /** Delete a file from the repository. */
    private function gitlabDeleteFile(array $account, string $token, string $remotePath): bool
    {
        $apiBase     = $this->gitlabGetApiBase($account);
        $namespace   = $account['RepoOwner'] ?? '';
        $projectName = $account['RepoName'] ?? 'wp-backups';
        $projectPath = urlencode($namespace . '/' . $projectName);

        $encodedFilePath = urlencode($remotePath);
        $fileUrl         = sprintf('/projects/%s/repository/files/%s', $projectPath, $encodedFilePath);

        $existsStatus = $this->gitlabApiStatusCode('HEAD', $apiBase, $fileUrl . '?ref=main', $token);
        $isMissing    = ($existsStatus !== HttpStatusType::Ok->value);

        if ($isMissing) {
            return false;
        }

        $this->gitlabApiRequest('DELETE', $apiBase, $fileUrl, $token, array(
            'branch'         => 'main',
            'commit_message' => sprintf('Remove old backup: %s', basename($remotePath)),
        ));

        return true;
    }

    // ── GitLab Private Helpers ─────────────────────────────────────

    /** Derive the API base URL from account BaseUrl (supports self-hosted). */
    private function gitlabGetApiBase(array $account): string
    {
        $baseUrl = $account['BaseUrl'] ?? '';
        $isCustom = !empty($baseUrl);

        $host = $isCustom ? rtrim($baseUrl, '/') : self::GITLAB_DEFAULT_BASE;

        return $host . '/api/v4';
    }

    /** Build authenticated HTTP options for GitLab. */
    private function gitlabBuildOptions(string $method, string $token, ?array $body = null): array
    {
        $options = HttpConfigType::authenticatedOptions($method, '');

        $options['headers']['PRIVATE-TOKEN'] = $token;
        $options['headers']['Content-Type']  = 'application/json';
        $options['headers']['User-Agent']    = PluginConfigType::Slug->value;

        $hasBody = ($body !== null);

        if ($hasBody) {
            $options['body'] = wp_json_encode($body);
        }

        return $options;
    }

    /** Make a GitLab API request and return decoded body. */
    private function gitlabApiRequest(string $method, string $apiBase, string $path, string $token, ?array $body = null): array
    {
        $url     = $apiBase . $path;
        $options = $this->gitlabBuildOptions($method, $token, $body);

        $response  = wp_remote_request($url, $options);
        $isWpError = is_wp_error($response);

        if ($isWpError) {
            throw new RuntimeException('GitLab API request failed: ' . $response->get_error_message());
        }

        $statusCode    = (int) wp_remote_retrieve_response_code($response);
        $decoded       = json_decode(wp_remote_retrieve_body($response), true) ?? array();
        $isClientError = ($statusCode >= 400);

        if ($isClientError) {
            $errorMessage = $decoded['message'] ?? $decoded['error'] ?? 'Unknown error';

            throw new RuntimeException(
                sprintf('GitLab API error [%d]: %s', $statusCode, $errorMessage),
            );
        }

        return $decoded;
    }

    /** Get the HTTP status code for a GitLab API request. */
    private function gitlabApiStatusCode(string $method, string $apiBase, string $path, string $token): int
    {
        $url      = $apiBase . $path;
        $options  = $this->gitlabBuildOptions($method, $token);
        $response = wp_remote_request($url, $options);

        $isWpError = is_wp_error($response);

        if ($isWpError) {
            return 0;
        }

        return (int) wp_remote_retrieve_response_code($response);
    }

    /** Resolve a namespace (group) to its numeric ID. Returns 0 if not a group. */
    private function gitlabResolveNamespaceId(string $apiBase, string $token, string $namespace): int
    {
        $statusCode = $this->gitlabApiStatusCode('GET', $apiBase, '/groups/' . urlencode($namespace), $token);
        $isNotGroup = ($statusCode !== HttpStatusType::Ok->value);

        if ($isNotGroup) {
            return 0;
        }

        $body = $this->gitlabApiRequest('GET', $apiBase, '/groups/' . urlencode($namespace), $token);

        return (int) ($body['id'] ?? 0);
    }
}
