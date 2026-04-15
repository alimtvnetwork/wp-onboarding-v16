<?php
/**
 * RouteRegistrationCloudStorageTrait — Cloud storage route registration.
 *
 * @package RiseupAsia\Traits\Route
 * @since   2.37.0
 */

namespace RiseupAsia\Traits\Route;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\HttpMethodType;
use RiseupAsia\Enums\EndpointType;

trait RouteRegistrationCloudStorageTrait
{
    /**
     * Register cloud storage routes.
     *
     * @param callable $safeRegister Route registration closure.
     */
    private function registerCloudStorageRoutes(callable $safeRegister): void {
        $csPerm = [$this, 'checkCloudStoragePermission'];

        // GET + POST /cloud-storage/accounts
        $safeRegister(EndpointType::CloudStorageAccounts->route(), [
            [
                'methods'             => HttpMethodType::Get->value,
                'callback'            => [$this, 'handleListCloudStorageAccounts'],
                'permission_callback' => $this->buildPermissionCallback('cloud_storage_accounts', $csPerm),
            ],
            [
                'methods'             => HttpMethodType::Post->value,
                'callback'            => [$this, 'handleCreateCloudStorageAccount'],
                'permission_callback' => $this->buildPermissionCallback('cloud_storage_accounts_create', $csPerm),
            ],
        ]);

        // GET + PUT + DELETE /cloud-storage/accounts/{id}
        $safeRegister(EndpointType::CloudStorageAccountId->route(), [
            [
                'methods'             => HttpMethodType::Get->value,
                'callback'            => [$this, 'handleGetCloudStorageAccount'],
                'permission_callback' => $this->buildPermissionCallback('cloud_storage_account_get', $csPerm),
            ],
            [
                'methods'             => HttpMethodType::Put->value,
                'callback'            => [$this, 'handleUpdateCloudStorageAccount'],
                'permission_callback' => $this->buildPermissionCallback('cloud_storage_account_update', $csPerm),
            ],
            [
                'methods'             => HttpMethodType::Delete->value,
                'callback'            => [$this, 'handleDeleteCloudStorageAccount'],
                'permission_callback' => $this->buildPermissionCallback('cloud_storage_account_delete', $csPerm),
            ],
        ]);

        // POST /cloud-storage/accounts/test
        $safeRegister(EndpointType::CloudStorageAccountTest->route(), [
            'methods'             => HttpMethodType::Post->value,
            'callback'            => [$this, 'handleTestCloudStorageAccount'],
            'permission_callback' => $this->buildPermissionCallback('cloud_storage_account_test', $csPerm),
        ]);

        // GET /cloud-storage/settings
        $safeRegister(EndpointType::CloudStorageSettings->route(), [
            'methods'             => HttpMethodType::Get->value,
            'callback'            => [$this, 'handleGetCloudStorageSettings'],
            'permission_callback' => $this->buildPermissionCallback('cloud_storage_settings', $csPerm),
        ]);

        // PUT /cloud-storage/settings/{provider}
        $safeRegister(EndpointType::CloudStorageSettingsProvider->route(), [
            'methods'             => HttpMethodType::Put->value,
            'callback'            => [$this, 'handleUpdateCloudStorageSettings'],
            'permission_callback' => $this->buildPermissionCallback('cloud_storage_settings_update', $csPerm),
        ]);

        // POST /cloud-storage/upload
        $safeRegister(EndpointType::CloudStorageUpload->route(), [
            'methods'             => HttpMethodType::Post->value,
            'callback'            => [$this, 'handleCloudStorageUpload'],
            'permission_callback' => $this->buildPermissionCallback('cloud_storage_upload', $csPerm),
        ]);

        // GET /cloud-storage/files
        $safeRegister(EndpointType::CloudStorageFiles->route(), [
            'methods'             => HttpMethodType::Get->value,
            'callback'            => [$this, 'handleListCloudStorageFiles'],
            'permission_callback' => $this->buildPermissionCallback('cloud_storage_files', $csPerm),
        ]);

        // DELETE /cloud-storage/delete
        $safeRegister(EndpointType::CloudStorageDelete->route(), [
            'methods'             => HttpMethodType::Delete->value,
            'callback'            => [$this, 'handleDeleteCloudStorageFile'],
            'permission_callback' => $this->buildPermissionCallback('cloud_storage_delete', $csPerm),
        ]);

        // POST /cloud-storage/oauth/initiate
        $safeRegister(EndpointType::CloudStorageOAuthInitiate->route(), [
            'methods'             => HttpMethodType::Post->value,
            'callback'            => [$this, 'handleCloudStorageOAuthInitiate'],
            'permission_callback' => $this->buildPermissionCallback('cloud_storage_oauth_initiate', $csPerm),
        ]);

        // GET /cloud-storage/oauth/callback
        $safeRegister(EndpointType::CloudStorageOAuthCallback->route(), [
            'methods'             => HttpMethodType::Get->value,
            'callback'            => [$this, 'handleCloudStorageOAuthCallback'],
            'permission_callback' => $this->buildPermissionCallback('cloud_storage_oauth_callback', $csPerm),
        ]);

        // GET /cloud-storage/repos
        $safeRegister(EndpointType::CloudStorageRepos->route(), [
            'methods'             => HttpMethodType::Get->value,
            'callback'            => [$this, 'handleListCloudStorageRepos'],
            'permission_callback' => $this->buildPermissionCallback('cloud_storage_repos', $csPerm),
        ]);

        // GET /cloud-storage/branches
        $safeRegister(EndpointType::CloudStorageBranches->route(), [
            'methods'             => HttpMethodType::Get->value,
            'callback'            => [$this, 'handleListCloudStorageBranches'],
            'permission_callback' => $this->buildPermissionCallback('cloud_storage_branches', $csPerm),
        ]);

        // GET + DELETE /cloud-storage/backup-history
        $safeRegister(EndpointType::CloudStorageBackupHistory->route(), [
            'methods'             => HttpMethodType::Get->value,
            'callback'            => [$this, 'handleListBackupHistory'],
            'permission_callback' => $this->buildPermissionCallback('cloud_storage_backup_history', $csPerm),
        ]);

        // GET + DELETE /cloud-storage/backup-history/{id}
        $safeRegister(EndpointType::CloudStorageBackupHistoryId->route(), [
            [
                'methods'             => HttpMethodType::Get->value,
                'callback'            => [$this, 'handleGetBackupHistoryRecord'],
                'permission_callback' => $this->buildPermissionCallback('cloud_storage_backup_history_get', $csPerm),
            ],
            [
                'methods'             => HttpMethodType::Delete->value,
                'callback'            => [$this, 'handleDeleteBackupHistoryRecord'],
                'permission_callback' => $this->buildPermissionCallback('cloud_storage_backup_history_delete', $csPerm),
            ],
        ]);

        // POST /cloud-storage/restore
        $safeRegister(EndpointType::CloudStorageRestore->route(), [
            'methods'             => HttpMethodType::Post->value,
            'callback'            => [$this, 'handleCloudStorageRestore'],
            'permission_callback' => $this->buildPermissionCallback('cloud_storage_restore', $csPerm),
        ]);

        // GET /cloud-storage/rotation-status
        $safeRegister(EndpointType::CloudStorageRotationStatus->route(), [
            'methods'             => HttpMethodType::Get->value,
            'callback'            => [$this, 'handleCloudStorageRotationStatus'],
            'permission_callback' => $this->buildPermissionCallback('cloud_storage_rotation_status', $csPerm),
        ]);

        // POST /cloud-storage/rotate
        $safeRegister(EndpointType::CloudStorageRotate->route(), [
            'methods'             => HttpMethodType::Post->value,
            'callback'            => [$this, 'handleCloudStorageRotate'],
            'permission_callback' => $this->buildPermissionCallback('cloud_storage_rotate', $csPerm),
        ]);
    }
}
