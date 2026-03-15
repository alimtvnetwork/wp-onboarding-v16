<?php
/**
 * CloudStorageUploadTrait — Upload dispatch handler for cloud storage.
 *
 * Routes uploads to provider-specific traits and applies rotation after upload.
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
use RiseupAsia\Enums\TableType;
use RiseupAsia\Enums\ActionType;
use RiseupAsia\Helpers\PathHelper;

trait CloudStorageUploadTrait {

    /** POST /cloud-storage/upload */
    public function handleCloudStorageUpload(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $params    = $request->get_json_params();
            $accountId = (int) ($params[ResponseKeyType::AccountId->value] ?? 0);
            $filePath  = $params['FilePath'] ?? '';
            $remotePath = $params[ResponseKeyType::RemotePath->value] ?? '';

            $account = $this->getCloudStorageAccountById($accountId);

            $isNotFound = ($account === false);

            if ($isNotFound) {
                return new WP_REST_Response(array(
                    ResponseKeyType::Success->value => false,
                    ResponseKeyType::Error->value   => 'Account not found',
                ), HttpStatusType::NotFound->value);
            }

            $isFileMissing = PathHelper::isFileMissing($filePath);

            if ($isFileMissing) {
                return new WP_REST_Response(array(
                    ResponseKeyType::Success->value => false,
                    ResponseKeyType::Error->value   => 'Backup file not found: ' . $filePath,
                ), HttpStatusType::BadRequest->value);
            }

            $provider = CloudStorageProviderType::from($account['Provider']);
            $token    = $provider->isGoogleDrive() ? '' : $this->decryptToken($account['AccessToken']);
            $startTime = microtime(true);

            $uploadResult = match(true) {
                $provider->isGitHub()      => $this->githubUploadFile($account, $token, $filePath, $remotePath),
                $provider->isGitLab()      => $this->gitlabUploadFile($account, $token, $filePath, $remotePath),
                $provider->isGoogleDrive() => $this->googleDriveUploadFile($account, $token, $filePath, $remotePath),
                default                    => throw new \RuntimeException('Provider not yet supported: ' . $provider->value),
            };

            $duration = round(microtime(true) - $startTime, 2);
            $uploadResult[ResponseKeyType::Duration->value] = $duration;

            $this->updateAccountLastUsed($accountId, array(ResponseKeyType::Success->value => true));

            $rotationResult = $this->applyRotationIfEnabled($account, $token, $remotePath);

            $this->logCloudStorageAction(ActionType::CloudStorageUpload, array(
                ResponseKeyType::AccountId->value  => $accountId,
                ResponseKeyType::Provider->value   => $provider->value,
                ResponseKeyType::RemotePath->value => $remotePath,
                ResponseKeyType::Duration->value   => $duration,
            ));

            return new WP_REST_Response(array(
                ResponseKeyType::Success->value         => true,
                ResponseKeyType::UploadResult->value    => $uploadResult,
                ResponseKeyType::RotationApplied->value => $rotationResult['applied'] ?? false,
                ResponseKeyType::FilesDeleted->value    => $rotationResult['deleted'] ?? 0,
            ), HttpStatusType::Ok->value);
        } catch (Throwable $e) {
            $this->fileLogger->logException($e, 'Cloud storage upload failed');

            if (isset($accountId)) {
                $this->updateAccountLastUsed($accountId, array(
                    ResponseKeyType::Success->value => false,
                    ResponseKeyType::Error->value   => $e->getMessage(),
                ));
            }

            return new WP_REST_Response(array(
                ResponseKeyType::Success->value => false,
                ResponseKeyType::Error->value   => $e->getMessage(),
            ), HttpStatusType::InternalServerError->value);
        }
    }

    /** Apply rotation if enabled for the account's provider. */
    private function applyRotationIfEnabled(array $account, string $token, string $remotePath): array
    {
        $table    = TableType::CloudStorageSettings->value;
        $provider = $account['Provider'];

        $settings = $this->db->querySingle(
            "SELECT * FROM {$table} WHERE Provider = ?",
            array($provider),
        );

        $isRotationDisabled = ($settings === false) || !((bool) ($settings['RotationEnabled'] ?? false));

        if ($isRotationDisabled) {
            return array('applied' => false, 'deleted' => 0);
        }

        $backupDir      = dirname($remotePath);
        $retentionCount = (int) ($settings['RetentionCount'] ?? 10);

        $result = $this->applyRotation($account, $token, $backupDir, $retentionCount);

        return array(
            'applied' => true,
            'deleted' => $result['deleted'] ?? 0,
        );
    }
}
