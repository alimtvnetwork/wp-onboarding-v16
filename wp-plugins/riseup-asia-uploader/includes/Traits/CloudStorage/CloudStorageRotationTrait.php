<?php
/**
 * CloudStorageRotationTrait — Rotation status and manual trigger endpoints.
 *
 * @package RiseupAsia\Traits\CloudStorage
 * @since   2.32.0
 */

namespace RiseupAsia\Traits\CloudStorage;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;

use RiseupAsia\Enums\ActionType;
use RiseupAsia\Enums\CloudStorageProviderType;
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\TableType;

trait CloudStorageRotationTrait {

    /** GET /cloud-storage/rotation-status */
    public function handleCloudStorageRotationStatus(WP_REST_Request $request): WP_REST_Response
    {
        return $this->safeExecute(function() use ($request) {
            $accountId = (int) $request->get_param('account_id');
            $account   = $this->getCloudStorageAccountById($accountId);

            $isNotFound = ($account === false);

            if ($isNotFound) {
                return new WP_REST_Response([
                    ResponseKeyType::Success->value => false,
                    ResponseKeyType::Error->value   => 'Account not found',
                ], HttpStatusType::NotFound->value);
            }

            $provider = CloudStorageProviderType::from($account['Provider']);
            $token    = $provider->isGoogleDrive() ? '' : $this->decryptToken($account['AccessToken']);

            $settings = $this->getRotationSettings($provider->value);
            $files    = $this->listAccountFiles($account, $token);

            $currentCount  = count($files);
            $currentSizeMB = $this->calculateTotalSizeMB($files);
            $maxCount      = (int) ($settings['MaxBackupCount'] ?? $settings['RetentionCount'] ?? 30);
            $maxSizeMB     = (int) ($settings['MaxTotalSizeMB'] ?? 5000);

            $isOverCount = ($maxCount > 0 && $currentCount > $maxCount);
            $isOverSize  = ($maxSizeMB > 0 && $currentSizeMB > $maxSizeMB);
            $isOverLimit = ($isOverCount || $isOverSize);

            $nextAction = $this->resolveNextAction($isOverCount, $isOverSize, $currentCount, $maxCount, $currentSizeMB, $maxSizeMB, $settings);

            return new WP_REST_Response([
                ResponseKeyType::Success->value      => true,
                ResponseKeyType::CurrentCount->value  => $currentCount,
                ResponseKeyType::CurrentSizeMB->value => round($currentSizeMB, 1),
                ResponseKeyType::MaxCount->value      => $maxCount,
                ResponseKeyType::MaxSizeMB->value     => $maxSizeMB,
                ResponseKeyType::IsOverLimit->value   => $isOverLimit,
                ResponseKeyType::NextAction->value    => $nextAction,
            ], HttpStatusType::Ok->value);
        }, 'cloud-storage-rotation-status');
    }

    /** POST /cloud-storage/rotate */
    public function handleCloudStorageRotate(WP_REST_Request $request): WP_REST_Response
    {
        return $this->safeExecute(function() use ($request) {
            $body = $this->extractValidBody($request);
            $isBodyInvalid = ($body === null);

            if ($isBodyInvalid) {
                return $this->validationError('Invalid or missing JSON body', $request);
            }

            $accountId = (int) ($body[ResponseKeyType::AccountId->value] ?? 0);
            $account   = $this->getCloudStorageAccountById($accountId);

            $isNotFound = ($account === false);

            if ($isNotFound) {
                return new WP_REST_Response([
                    ResponseKeyType::Success->value => false,
                    ResponseKeyType::Error->value   => 'Account not found',
                ], HttpStatusType::NotFound->value);
            }

            $provider = CloudStorageProviderType::from($account['Provider']);
            $token    = $provider->isGoogleDrive() ? '' : $this->decryptToken($account['AccessToken']);

            $settings      = $this->getRotationSettings($provider->value);
            $policy        = $settings['RotationPolicy'] ?? 'delete_oldest';
            $maxCount      = (int) ($settings['MaxBackupCount'] ?? $settings['RetentionCount'] ?? 30);
            $maxSizeMB     = (int) ($settings['MaxTotalSizeMB'] ?? 5000);
            $archiveFolderId = $settings['ArchiveFolderId'] ?? '';

            $result = $this->executeRotation($account, $token, $policy, $maxCount, $maxSizeMB, $archiveFolderId);

            $this->logCloudStorageAction(ActionType::CloudStorageRotation, [
                ResponseKeyType::AccountId->value    => $accountId,
                ResponseKeyType::Provider->value     => $provider->value,
                ResponseKeyType::FilesDeleted->value => $result['deleted'],
                ResponseKeyType::FilesMoved->value   => $result['moved'],
            ]);

            return new WP_REST_Response([
                ResponseKeyType::Success->value      => true,
                ResponseKeyType::Applied->value      => true,
                ResponseKeyType::FilesDeleted->value => $result['deleted'],
                ResponseKeyType::FilesMoved->value   => $result['moved'],
                ResponseKeyType::DeletedFiles->value => $result['deletedFiles'],
                ResponseKeyType::MovedFiles->value   => $result['movedFiles'],
                ResponseKeyType::Message->value      => sprintf('Rotation complete: %d deleted, %d moved', $result['deleted'], $result['moved']),
            ], HttpStatusType::Ok->value);
        }, 'cloud-storage-rotate');
    }

    /** Get rotation settings for a given provider. */
    private function getRotationSettings(string $provider): array
    {
        $table = TableType::CloudStorageSettings->value;

        $settings = $this->db->querySingle(
            "SELECT * FROM {$table} WHERE Provider = ?",
            [$provider],
        );

        $isNotFound = ($settings === false);

        if ($isNotFound) {
            return [];
        }

        return $settings;
    }

    /** List files for an account using the correct provider method. */
    private function listAccountFiles(array $account, string $token): array
    {
        $provider = CloudStorageProviderType::from($account['Provider']);

        return match(true) {
            $provider->isGitHub()      => $this->githubListFiles($account, $token, ''),
            $provider->isGitLab()      => $this->gitlabListFiles($account, $token, ''),
            $provider->isGoogleDrive() => $this->googleDriveListFiles($account, $token, ''),
            default                    => [],
        };
    }

    /** Calculate total size in MB from a file list. */
    private function calculateTotalSizeMB(array $files): float
    {
        $totalBytes = 0;

        foreach ($files as $file) {
            $totalBytes += (int) ($file['Size'] ?? 0);
        }

        return $totalBytes / (1024 * 1024);
    }

    /** Determine the next rotation action description. */
    private function resolveNextAction(bool $isOverCount, bool $isOverSize, int $currentCount, int $maxCount, float $currentSizeMB, int $maxSizeMB, array $settings): string
    {
        $isWithinLimits = (!$isOverCount && !$isOverSize);

        if ($isWithinLimits) {
            return 'none';
        }

        $policy = $settings['RotationPolicy'] ?? 'delete_oldest';

        $excessCount = max(0, $currentCount - $maxCount);
        $verb = ($policy === 'archive_oldest') ? 'archive' : 'delete';

        if ($isOverCount && $isOverSize) {
            return sprintf('%s %d files (over count by %d, over size by %.1f MB)', $verb, $excessCount, $excessCount, $currentSizeMB - $maxSizeMB);
        }

        if ($isOverCount) {
            return sprintf('%s %d files', $verb, $excessCount);
        }

        return sprintf('%s files until under %.0f MB', $verb, (float) $maxSizeMB);
    }

    /** Execute rotation based on the configured policy. */
    private function executeRotation(array $account, string $token, string $policy, int $maxCount, int $maxSizeMB, string $archiveFolderId): array
    {
        $files = $this->listAccountFiles($account, $token);

        usort($files, fn($a, $b) => strcmp($a['Name'], $b['Name']));

        $deleted      = 0;
        $moved        = 0;
        $deletedFiles = [];
        $movedFiles   = [];

        $isArchivePolicy = ($policy === 'archive_oldest');
        $isKeepFullPolicy = ($policy === 'keep_full_delete_incremental');

        if ($isKeepFullPolicy) {
            $result = $this->executeKeepFullDeleteIncremental($account, $token, $files, $maxCount, $maxSizeMB);
            return $result;
        }

        $filesToProcess = $this->identifyExcessFiles($files, $maxCount, $maxSizeMB);

        $provider = CloudStorageProviderType::from($account['Provider']);

        foreach ($filesToProcess as $file) {
            if ($isArchivePolicy && !empty($archiveFolderId)) {
                $wasMoved = $this->moveFileToArchive($account, $token, $provider, $file, $archiveFolderId);

                if ($wasMoved) {
                    $moved++;
                    $movedFiles[] = $file['Name'];
                }

                continue;
            }

            $wasDeleted = match(true) {
                $provider->isGitHub()      => $this->githubDeleteFile($account, $token, $file['Path']),
                $provider->isGitLab()      => $this->gitlabDeleteFile($account, $token, $file['Path']),
                $provider->isGoogleDrive() => $this->googleDriveDeleteFile($account, $token, $file['Path']),
                default                    => false,
            };

            if ($wasDeleted) {
                $deleted++;
                $deletedFiles[] = $file['Name'];
            }
        }

        return [
            'deleted'      => $deleted,
            'moved'        => $moved,
            'deletedFiles' => $deletedFiles,
            'movedFiles'   => $movedFiles,
        ];
    }

    /** Identify files exceeding count and size limits (oldest first). */
    private function identifyExcessFiles(array $files, int $maxCount, int $maxSizeMB): array
    {
        $excess = [];
        $totalSizeMB = $this->calculateTotalSizeMB($files);
        $currentCount = count($files);

        foreach ($files as $file) {
            $isWithinLimits = ($currentCount <= $maxCount) && ($maxSizeMB <= 0 || $totalSizeMB <= $maxSizeMB);

            if ($isWithinLimits) {
                break;
            }

            $excess[] = $file;
            $currentCount--;
            $totalSizeMB -= ((int) ($file['Size'] ?? 0)) / (1024 * 1024);
        }

        return $excess;
    }

    /** Keep full backups, delete oldest incrementals first. */
    private function executeKeepFullDeleteIncremental(array $account, string $token, array $files, int $maxCount, int $maxSizeMB): array
    {
        $incrementals = [];
        $fulls        = [];

        foreach ($files as $file) {
            $isIncremental = (stripos($file['Name'], 'incremental') !== false);

            if ($isIncremental) {
                $incrementals[] = $file;
            } else {
                $fulls[] = $file;
            }
        }

        $deleted      = 0;
        $deletedFiles = [];
        $totalCount   = count($files);
        $totalSizeMB  = $this->calculateTotalSizeMB($files);
        $provider     = CloudStorageProviderType::from($account['Provider']);

        foreach ($incrementals as $file) {
            $isWithinLimits = ($totalCount <= $maxCount) && ($maxSizeMB <= 0 || $totalSizeMB <= $maxSizeMB);

            if ($isWithinLimits) {
                break;
            }

            $wasDeleted = match(true) {
                $provider->isGitHub()      => $this->githubDeleteFile($account, $token, $file['Path']),
                $provider->isGitLab()      => $this->gitlabDeleteFile($account, $token, $file['Path']),
                $provider->isGoogleDrive() => $this->googleDriveDeleteFile($account, $token, $file['Path']),
                default                    => false,
            };

            if ($wasDeleted) {
                $deleted++;
                $deletedFiles[] = $file['Name'];
                $totalCount--;
                $totalSizeMB -= ((int) ($file['Size'] ?? 0)) / (1024 * 1024);
            }
        }

        return [
            'deleted'      => $deleted,
            'moved'        => 0,
            'deletedFiles' => $deletedFiles,
            'movedFiles'   => [],
        ];
    }

    /** Move a file to the archive folder (Google Drive only). */
    private function moveFileToArchive(array $account, string $token, CloudStorageProviderType $provider, array $file, string $archiveFolderId): bool
    {
        $isGoogleDrive = $provider->isGoogleDrive();

        if (!$isGoogleDrive) {
            return false;
        }

        $validToken = $this->googleDriveEnsureValidToken($account);
        $fileId     = $file['Path'];

        $url = self::GDRIVE_API . '/files/' . urlencode($fileId) . '?' . http_build_query([
            'addParents'    => $archiveFolderId,
            'removeParents' => $account['FolderId'] ?? '',
        ]);

        $options = $this->googleDriveBuildOptions('PATCH', $validToken);
        $response   = wp_remote_request($url, $options);
        $statusCode = (int) wp_remote_retrieve_response_code($response);
        $isMoved    = ($statusCode === 200);

        return $isMoved;
    }
}
