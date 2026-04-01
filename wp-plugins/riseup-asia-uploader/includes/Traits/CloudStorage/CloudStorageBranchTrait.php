<?php
/**
 * CloudStorageBranchTrait — Git branch create/delete/list helpers for GitHub and GitLab.
 *
 * @package RiseupAsia\Traits\CloudStorage
 * @since   2.16.0
 */

namespace RiseupAsia\Traits\CloudStorage;

if (!defined('ABSPATH')) {
    exit;
}

use RuntimeException;

use RiseupAsia\Enums\CloudStorageProviderType;
use RiseupAsia\Enums\HttpStatusType;

trait CloudStorageBranchTrait {

    /**
     * List branches for a repository.
     *
     * @param array  $account Decrypted account row.
     * @param string $token   Decrypted access token.
     * @return array List of branch info arrays.
     */
    private function listBranches(array $account, string $token): array
    {
        $provider = CloudStorageProviderType::from($account['Provider']);

        return match(true) {
            $provider->isGitHub() => $this->githubListBranches($account, $token),
            $provider->isGitLab() => $this->gitlabListBranches($account, $token),
            default               => [],
        };
    }

    /**
     * Create a branch from a specific commit SHA.
     *
     * @param array  $account    Decrypted account row.
     * @param string $token      Decrypted access token.
     * @param string $branchName New branch name (e.g., 'incremental/2026-W11').
     * @param string $sha        Commit SHA to branch from.
     */
    private function createBranch(
        array $account,
        string $token,
        string $branchName,
        string $sha
    ): void {
        $provider = CloudStorageProviderType::from($account['Provider']);

        match(true) {
            $provider->isGitHub() => $this->githubCreateBranch($account, $token, $branchName, $sha),
            $provider->isGitLab() => $this->gitlabCreateBranch($account, $token, $branchName, $sha),
            default               => throw new RuntimeException('Branch creation not supported for ' . $provider->label()),
        };
    }

    /**
     * Delete a remote branch.
     *
     * @param array  $account    Decrypted account row.
     * @param string $token      Decrypted access token.
     * @param string $branchName Branch to delete (e.g., 'incremental/2026-W10').
     */
    private function deleteBranch(
        array $account,
        string $token,
        string $branchName
    ): void {
        $provider = CloudStorageProviderType::from($account['Provider']);

        match(true) {
            $provider->isGitHub() => $this->githubDeleteBranch($account, $token, $branchName),
            $provider->isGitLab() => $this->gitlabDeleteBranch($account, $token, $branchName),
            default               => throw new RuntimeException('Branch deletion not supported for ' . $provider->label()),
        };
    }

    /**
     * Check if a branch exists.
     *
     * @param array  $account    Decrypted account row.
     * @param string $token      Decrypted access token.
     * @param string $branchName Branch name to check.
     * @return bool Whether the branch exists.
     */
    private function branchExists(
        array $account,
        string $token,
        string $branchName
    ): bool {
        $provider = CloudStorageProviderType::from($account['Provider']);

        return match(true) {
            $provider->isGitHub() => $this->githubBranchExists($account, $token, $branchName),
            $provider->isGitLab() => $this->gitlabBranchExists($account, $token, $branchName),
            default               => false,
        };
    }

    // ── GitHub branch operations ─────────────────────────────────

    private function githubListBranches(array $account, string $token): array
    {
        $owner = $account['RepoOwner'] ?? '';
        $repo  = $account['RepoName'] ?? '';
        $path  = sprintf('/repos/%s/%s/branches?per_page=100', urlencode($owner), urlencode($repo));

        $branches = $this->githubApiRequest('GET', $path, $token);

        return array_map(
            fn(array $branch) => [
                'Name'           => $branch['name'] ?? '',
                'IsDefault'      => ($branch['name'] ?? '') === ($account['DefaultBranch'] ?? 'main'),
                'LastCommitSha'  => $branch['commit']['sha'] ?? '',
                'LastCommitDate' => '',
            ],
            $branches,
        );
    }

    private function githubCreateBranch(
        array $account,
        string $token,
        string $branchName,
        string $sha
    ): void {
        $owner = $account['RepoOwner'] ?? '';
        $repo  = $account['RepoName'] ?? '';
        $path  = sprintf('/repos/%s/%s/git/refs', urlencode($owner), urlencode($repo));

        $this->githubApiRequest('POST', $path, $token, [
            'ref' => 'refs/heads/' . $branchName,
            'sha' => $sha,
        ]);
    }

    private function githubDeleteBranch(
        array $account,
        string $token,
        string $branchName
    ): void {
        $owner     = $account['RepoOwner'] ?? '';
        $repo      = $account['RepoName'] ?? '';
        $refPath   = str_replace('/', '%2F', $branchName);
        $path      = sprintf('/repos/%s/%s/git/refs/heads/%s', urlencode($owner), urlencode($repo), $refPath);

        $statusCode = $this->githubApiStatusCode('DELETE', $path, $token);
        $isNotFound = ($statusCode === HttpStatusType::NotFound->value);

        if ($isNotFound) {
            $this->fileLogger->debug('[CLOUD-BRANCH] GitHub branch already deleted', ['branch' => $branchName]);
        }
    }

    private function githubBranchExists(
        array $account,
        string $token,
        string $branchName
    ): bool {
        $owner   = $account['RepoOwner'] ?? '';
        $repo    = $account['RepoName'] ?? '';
        $encoded = str_replace('/', '%2F', $branchName);
        $path    = sprintf('/repos/%s/%s/branches/%s', urlencode($owner), urlencode($repo), $encoded);

        $statusCode = $this->githubApiStatusCode('GET', $path, $token);

        return ($statusCode === HttpStatusType::Ok->value);
    }

    // ── GitLab branch operations ─────────────────────────────────

    private function gitlabListBranches(array $account, string $token): array
    {
        $projectId = $this->gitlabProjectId($account);
        $path      = sprintf('/projects/%s/repository/branches?per_page=100', urlencode($projectId));

        $branches = $this->gitlabApiRequest('GET', $path, $token, null, $account);

        return array_map(
            fn(array $branch) => [
                'Name'           => $branch['name'] ?? '',
                'IsDefault'      => $branch['default'] ?? false,
                'LastCommitSha'  => $branch['commit']['id'] ?? '',
                'LastCommitDate' => $branch['commit']['committed_date'] ?? '',
            ],
            $branches,
        );
    }

    private function gitlabCreateBranch(
        array $account,
        string $token,
        string $branchName,
        string $sha
    ): void {
        $projectId = $this->gitlabProjectId($account);
        $path      = sprintf('/projects/%s/repository/branches', urlencode($projectId));

        $this->gitlabApiRequest('POST', $path, $token, [
            'branch' => $branchName,
            'ref'    => $sha,
        ], $account);
    }

    private function gitlabDeleteBranch(
        array $account,
        string $token,
        string $branchName
    ): void {
        $projectId = $this->gitlabProjectId($account);
        $encoded   = urlencode($branchName);
        $path      = sprintf('/projects/%s/repository/branches/%s', urlencode($projectId), $encoded);

        $statusCode = $this->gitlabApiStatusCode('DELETE', $path, $token, $account);
        $isNotFound = ($statusCode === HttpStatusType::NotFound->value);

        if ($isNotFound) {
            $this->fileLogger->debug('[CLOUD-BRANCH] GitLab branch already deleted', ['branch' => $branchName]);
        }
    }

    private function gitlabBranchExists(
        array $account,
        string $token,
        string $branchName
    ): bool {
        $projectId = $this->gitlabProjectId($account);
        $encoded   = urlencode($branchName);
        $path      = sprintf('/projects/%s/repository/branches/%s', urlencode($projectId), $encoded);

        $statusCode = $this->gitlabApiStatusCode('GET', $path, $token, $account);

        return ($statusCode === HttpStatusType::Ok->value);
    }

    /** Build the GitLab project ID from RepoOwner/RepoName. */
    private function gitlabProjectId(array $account): string
    {
        $owner = $account['RepoOwner'] ?? '';
        $repo  = $account['RepoName'] ?? '';

        return urlencode($owner . '/' . $repo);
    }
}
