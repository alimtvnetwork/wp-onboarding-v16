<?php
/**
 * CloudStorageFileTrait — List/delete remote files and rotation enforcement.
 *
 * @package RiseupAsia\Traits\CloudStorage
 * @since   2.15.0
 */

namespace RiseupAsia\Traits\CloudStorage;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;
use Throwable;

use RiseupAsia\Enums\CloudStorageProviderType;
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\ActionType;

trait CloudStorageFileTrait {

    /** GET /cloud-storage/files */
    public function handleListCloudStorageFiles(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $accountId = (int) $request->get_param('account_id');
            $path      = $request->get_param('path') ?? '';
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

            $files = match(true) {
                $provider->isGitHub()      => $this->githubListFiles($account, $token, $path),
                $provider->isGitLab()      => $this->gitlabListFiles($account, $token, $path),
                $provider->isGoogleDrive() => $this->googleDriveListFiles($account, $token, $path),
                default                    => [],
            };

            return new WP_REST_Response([
                ResponseKeyType::Success->value => true,
                ResponseKeyType::Files->value   => $files,
                ResponseKeyType::Total->value   => count($files),
            ], HttpStatusType::Ok->value);
        } catch (Throwable $e) {
            $this->fileLogger->logException($e, 'Failed to list cloud storage files');

            return new WP_REST_Response([
                ResponseKeyType::Success->value => false,
                ResponseKeyType::Error->value   => $e->getMessage(),
            ], HttpStatusType::InternalServerError->value);
        }
    }

    /** DELETE /cloud-storage/delete */
    public function handleDeleteCloudStorageFile(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $body = $this->extractValidBody($request);
            $isBodyInvalid = ($body === null);

            if ($isBodyInvalid) {
                return $this->validationError('Invalid or missing JSON body', $request);
            }

            $accountId  = (int) ($body[ResponseKeyType::AccountId->value] ?? 0);
            $remotePath = $body[ResponseKeyType::RemotePath->value] ?? '';
            $account    = $this->getCloudStorageAccountById($accountId);

            $isNotFound = ($account === false);

            if ($isNotFound) {
                return new WP_REST_Response([
                    ResponseKeyType::Success->value => false,
                    ResponseKeyType::Error->value   => 'Account not found',
                ], HttpStatusType::NotFound->value);
            }

            $provider = CloudStorageProviderType::from($account['Provider']);
            $token    = $provider->isGoogleDrive() ? '' : $this->decryptToken($account['AccessToken']);

            $deleted = match(true) {
                $provider->isGitHub()      => $this->githubDeleteFile($account, $token, $remotePath),
                $provider->isGitLab()      => $this->gitlabDeleteFile($account, $token, $remotePath),
                $provider->isGoogleDrive() => $this->googleDriveDeleteFile($account, $token, $remotePath),
                default                    => false,
            };

            $this->logCloudStorageAction(ActionType::CloudStorageDelete, [
                ResponseKeyType::AccountId->value  => $accountId,
                ResponseKeyType::RemotePath->value => $remotePath,
                ResponseKeyType::Provider->value   => $provider->value,
            ]);

            return new WP_REST_Response([
                ResponseKeyType::Success->value => true,
                ResponseKeyType::Deleted->value => $deleted,
            ], HttpStatusType::Ok->value);
        } catch (Throwable $e) {
            $this->fileLogger->logException($e, 'Failed to delete cloud storage file');

            return new WP_REST_Response([
                ResponseKeyType::Success->value => false,
                ResponseKeyType::Error->value   => $e->getMessage(),
            ], HttpStatusType::InternalServerError->value);
        }
    }

    /** Apply rotation: delete oldest files exceeding retention count. */
    private function applyRotation(array $account, string $token, string $backupDir, int $retentionCount): array
    {
        $provider = CloudStorageProviderType::from($account['Provider']);

        $files = match(true) {
            $provider->isGitHub()      => $this->githubListFiles($account, $token, $backupDir),
            $provider->isGitLab()      => $this->gitlabListFiles($account, $token, $backupDir),
            $provider->isGoogleDrive() => $this->googleDriveListFiles($account, $token, $backupDir),
            default                    => [],
        };

        usort($files, fn($a, $b) => strcmp($a['Name'], $b['Name']));

        $excess        = count($files) - $retentionCount;
        $isWithinLimit = ($excess <= 0);

        if ($isWithinLimit) {
            return ['deleted' => 0, 'files' => []];
        }

        $deleted       = [];
        $filesToDelete = array_slice($files, 0, $excess);

        foreach ($filesToDelete as $file) {
            $wasDeleted = match(true) {
                $provider->isGitHub()      => $this->githubDeleteFile($account, $token, $file['Path']),
                $provider->isGitLab()      => $this->gitlabDeleteFile($account, $token, $file['Path']),
                $provider->isGoogleDrive() => $this->googleDriveDeleteFile($account, $token, $file['Path']),
                default                    => false,
            };

            if ($wasDeleted) {
                $deleted[] = $file['Name'];
            }
        }

        $hasDeleted = !empty($deleted);

        if ($hasDeleted) {
            $this->logCloudStorageAction(ActionType::CloudStorageRotation, [
                ResponseKeyType::Provider->value     => $provider->value,
                ResponseKeyType::FilesDeleted->value => count($deleted),
                ResponseKeyType::Files->value        => $deleted,
            ]);
        }

        return ['deleted' => count($deleted), 'files' => $deleted];
    }
}
