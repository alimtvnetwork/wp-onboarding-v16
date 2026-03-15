<?php
/**
 * CloudStorageAccountCrudTrait — CRUD handlers for cloud storage accounts.
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
use RiseupAsia\Enums\CloudStorageAccountFieldType;
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\TableType;
use RiseupAsia\Enums\ActionType;
use RiseupAsia\Enums\LogCategoryType;

trait CloudStorageAccountCrudTrait {

    /** GET /cloud-storage/accounts */
    public function handleListCloudStorageAccounts(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $table = TableType::CloudStorageAccounts->value;
            $rows  = $this->db->queryAll("SELECT * FROM {$table} ORDER BY CreatedAt DESC");

            $accounts = array_map(
                fn(array $row) => $this->formatAccountForResponse($row),
                $rows,
            );

            return new WP_REST_Response(array(
                ResponseKeyType::Success->value  => true,
                ResponseKeyType::Accounts->value => $accounts,
            ), HttpStatusType::Ok->value);
        } catch (Throwable $e) {
            $this->fileLogger->logException($e, 'Failed to list cloud storage accounts');

            return new WP_REST_Response(array(
                ResponseKeyType::Success->value => false,
                ResponseKeyType::Error->value   => $e->getMessage(),
            ), HttpStatusType::InternalServerError->value);
        }
    }

    /** GET /cloud-storage/accounts/{id} */
    public function handleGetCloudStorageAccount(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $id      = (int) $request->get_param('id');
            $account = $this->getCloudStorageAccountById($id);

            $isNotFound = ($account === false);

            if ($isNotFound) {
                return new WP_REST_Response(array(
                    ResponseKeyType::Success->value => false,
                    ResponseKeyType::Error->value   => 'Account not found',
                ), HttpStatusType::NotFound->value);
            }

            return new WP_REST_Response(array(
                ResponseKeyType::Success->value => true,
                ResponseKeyType::Account->value => $this->formatAccountForResponse($account),
            ), HttpStatusType::Ok->value);
        } catch (Throwable $e) {
            $this->fileLogger->logException($e, 'Failed to get cloud storage account');

            return new WP_REST_Response(array(
                ResponseKeyType::Success->value => false,
                ResponseKeyType::Error->value   => $e->getMessage(),
            ), HttpStatusType::InternalServerError->value);
        }
    }

    /** POST /cloud-storage/accounts */
    public function handleCreateCloudStorageAccount(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $params     = $request->get_json_params();
            $validation = $this->validateAccountFields($params);

            $hasErrors = !empty($validation);

            if ($hasErrors) {
                return new WP_REST_Response(array(
                    ResponseKeyType::Success->value => false,
                    ResponseKeyType::Errors->value  => $validation,
                ), HttpStatusType::BadRequest->value);
            }

            $row   = $this->buildAccountRow($params);
            $table = TableType::CloudStorageAccounts->value;

            $columns     = implode(', ', array_keys($row));
            $placeholders = implode(', ', array_fill(0, count($row), '?'));

            $this->db->execute(
                "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})",
                array_values($row),
            );

            $newId   = $this->db->lastInsertId();
            $account = $this->getCloudStorageAccountById((int) $newId);

            $this->logCloudStorageAction(ActionType::CloudStorageAccountAdd, array(
                ResponseKeyType::AccountId->value    => $newId,
                ResponseKeyType::AccountLabel->value => $params[CloudStorageAccountFieldType::AccountLabel->value] ?? '',
                ResponseKeyType::Provider->value     => $params[CloudStorageAccountFieldType::Provider->value] ?? '',
            ));

            return new WP_REST_Response(array(
                ResponseKeyType::Success->value => true,
                ResponseKeyType::Account->value => $this->formatAccountForResponse($account),
            ), HttpStatusType::Created->value);
        } catch (Throwable $e) {
            $this->fileLogger->logException($e, 'Failed to create cloud storage account');

            return new WP_REST_Response(array(
                ResponseKeyType::Success->value => false,
                ResponseKeyType::Error->value   => $e->getMessage(),
            ), HttpStatusType::InternalServerError->value);
        }
    }

    /** PUT /cloud-storage/accounts/{id} */
    public function handleUpdateCloudStorageAccount(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $id       = (int) $request->get_param('id');
            $existing = $this->getCloudStorageAccountById($id);

            $isNotFound = ($existing === false);

            if ($isNotFound) {
                return new WP_REST_Response(array(
                    ResponseKeyType::Success->value => false,
                    ResponseKeyType::Error->value   => 'Account not found',
                ), HttpStatusType::NotFound->value);
            }

            $params = $request->get_json_params();
            $sets   = array();
            $values = array();

            $this->applyAccountUpdate($params, $existing, $sets, $values);

            $sets[]   = "UpdatedAt = datetime('now')";
            $values[] = $id;

            $table   = TableType::CloudStorageAccounts->value;
            $setClause = implode(', ', $sets);

            $this->db->execute(
                "UPDATE {$table} SET {$setClause} WHERE Id = ?",
                $values,
            );

            $updated = $this->getCloudStorageAccountById($id);

            return new WP_REST_Response(array(
                ResponseKeyType::Success->value => true,
                ResponseKeyType::Account->value => $this->formatAccountForResponse($updated),
            ), HttpStatusType::Ok->value);
        } catch (Throwable $e) {
            $this->fileLogger->logException($e, 'Failed to update cloud storage account');

            return new WP_REST_Response(array(
                ResponseKeyType::Success->value => false,
                ResponseKeyType::Error->value   => $e->getMessage(),
            ), HttpStatusType::InternalServerError->value);
        }
    }

    /** DELETE /cloud-storage/accounts/{id} */
    public function handleDeleteCloudStorageAccount(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $id       = (int) $request->get_param('id');
            $existing = $this->getCloudStorageAccountById($id);

            $isNotFound = ($existing === false);

            if ($isNotFound) {
                return new WP_REST_Response(array(
                    ResponseKeyType::Success->value => false,
                    ResponseKeyType::Error->value   => 'Account not found',
                ), HttpStatusType::NotFound->value);
            }

            $table = TableType::CloudStorageAccounts->value;
            $this->db->execute("DELETE FROM {$table} WHERE Id = ?", array($id));

            $settingsTable = TableType::CloudStorageSettings->value;

            $this->db->execute(
                "UPDATE {$settingsTable} SET DefaultAccountId = NULL WHERE DefaultAccountId = ?",
                array($id),
            );

            $this->logCloudStorageAction(ActionType::CloudStorageAccountRemove, array(
                ResponseKeyType::AccountId->value    => $id,
                ResponseKeyType::AccountLabel->value => $existing['AccountLabel'] ?? '',
                ResponseKeyType::Provider->value     => $existing['Provider'] ?? '',
            ));

            return new WP_REST_Response(array(
                ResponseKeyType::Success->value => true,
                ResponseKeyType::Message->value => 'Account deleted',
            ), HttpStatusType::Ok->value);
        } catch (Throwable $e) {
            $this->fileLogger->logException($e, 'Failed to delete cloud storage account');

            return new WP_REST_Response(array(
                ResponseKeyType::Success->value => false,
                ResponseKeyType::Error->value   => $e->getMessage(),
            ), HttpStatusType::InternalServerError->value);
        }
    }

    /** POST /cloud-storage/accounts/test */
    public function handleTestCloudStorageAccount(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $params    = $request->get_json_params();
            $accountId = (int) ($params[ResponseKeyType::AccountId->value] ?? 0);
            $account   = $this->getCloudStorageAccountById($accountId);

            $isNotFound = ($account === false);

            if ($isNotFound) {
                return new WP_REST_Response(array(
                    ResponseKeyType::Success->value => false,
                    ResponseKeyType::Error->value   => 'Account not found',
                ), HttpStatusType::NotFound->value);
            }

            $provider = CloudStorageProviderType::from($account['Provider']);
            $token    = $provider->isGoogleDrive() ? '' : $this->decryptToken($account['AccessToken']);

            $result = match(true) {
                $provider->isGitHub()      => $this->githubTestConnection($account, $token),
                $provider->isGitLab()      => $this->gitlabTestConnection($account, $token),
                $provider->isGoogleDrive() => $this->googleDriveTestConnection($account, $token),
                default                    => array('Success' => false, 'Error' => 'Provider not yet supported'),
            };

            $this->updateAccountLastUsed($accountId, $result);

            return new WP_REST_Response($result, HttpStatusType::Ok->value);
        } catch (Throwable $e) {
            $this->fileLogger->logException($e, 'Failed to test cloud storage connection');

            return new WP_REST_Response(array(
                ResponseKeyType::Success->value => false,
                ResponseKeyType::Error->value   => $e->getMessage(),
            ), HttpStatusType::InternalServerError->value);
        }
    }

    // ── Private helpers ─────────────────────────────────────────────

    /** Validate account creation/update fields. */
    private function validateAccountFields(array $params): array
    {
        $errors   = array();
        $provider = $params[CloudStorageAccountFieldType::Provider->value] ?? '';

        $isProviderEmpty = empty($provider);

        if ($isProviderEmpty) {
            $errors[] = 'Provider is required';

            return $errors;
        }

        $providerType     = CloudStorageProviderType::tryFrom($provider);
        $isProviderInvalid = ($providerType === false);

        if ($isProviderInvalid) {
            $errors[] = 'Invalid provider: ' . $provider;

            return $errors;
        }

        $requiredFields = $providerType->isPat()
            ? CloudStorageAccountFieldType::gitRequiredFields()
            : CloudStorageAccountFieldType::googleDriveRequiredFields();

        foreach ($requiredFields as $field) {
            $value   = $params[$field->value] ?? '';
            $isEmpty = empty($value);

            if ($isEmpty) {
                $errors[] = $field->value . ' is required';
            }
        }

        return $errors;
    }

    /** Build a database row from validated params. */
    private function buildAccountRow(array $params): array
    {
        $token        = $params[CloudStorageAccountFieldType::AccessToken->value] ?? '';
        $refreshToken = $params[CloudStorageAccountFieldType::RefreshToken->value] ?? '';

        $row = array(
            'Provider'     => $params[CloudStorageAccountFieldType::Provider->value],
            'AccountLabel' => sanitize_text_field($params[CloudStorageAccountFieldType::AccountLabel->value] ?? ''),
            'Username'     => sanitize_text_field($params[CloudStorageAccountFieldType::Username->value] ?? ''),
            'Email'        => sanitize_email($params[CloudStorageAccountFieldType::Email->value] ?? ''),
            'AccessToken'  => $this->encryptToken($token),
            'RefreshToken' => !empty($refreshToken) ? $this->encryptToken($refreshToken) : '',
            'BaseUrl'      => esc_url_raw($params[CloudStorageAccountFieldType::BaseUrl->value] ?? ''),
            'RepoName'     => sanitize_file_name($params[CloudStorageAccountFieldType::RepoName->value] ?? 'wp-backups'),
            'RepoOwner'    => sanitize_text_field($params[CloudStorageAccountFieldType::RepoOwner->value] ?? ''),
            'FolderId'     => sanitize_text_field($params[CloudStorageAccountFieldType::FolderId->value] ?? ''),
            'FolderName'   => sanitize_text_field($params[CloudStorageAccountFieldType::FolderName->value] ?? ''),
            'IsActive'     => 1,
        );

        return $row;
    }

    /** Format account row for API response (mask tokens, never expose plaintext). */
    private function formatAccountForResponse(array|false $row): array
    {
        $isNull = ($row === false);

        if ($isNull) {
            return array();
        }

        $token = $this->decryptToken($row['AccessToken'] ?? '');

        return array(
            'Id'           => (int) $row['Id'],
            'Provider'     => $row['Provider'],
            'AccountLabel' => $row['AccountLabel'],
            'Username'     => $row['Username'] ?? '',
            'Email'        => $row['Email'] ?? '',
            'TokenMask'    => !empty($token) ? $this->maskToken($row['Provider'], $token) : '',
            'BaseUrl'      => $row['BaseUrl'] ?? '',
            'RepoName'     => $row['RepoName'] ?? '',
            'RepoOwner'    => $row['RepoOwner'] ?? '',
            'FolderId'     => $row['FolderId'] ?? '',
            'FolderName'   => $row['FolderName'] ?? '',
            'IsActive'     => (bool) ($row['IsActive'] ?? true),
            'LastUsedAt'   => $row['LastUsedAt'] ?? '',
            'LastError'    => $row['LastError'] ?? '',
            'CreatedAt'    => $row['CreatedAt'] ?? '',
        );
    }

    /** Fetch an account by its primary key. */
    private function getCloudStorageAccountById(int $id): array|false
    {
        $table = TableType::CloudStorageAccounts->value;

        return $this->db->querySingle("SELECT * FROM {$table} WHERE Id = ?", array($id));
    }

    /** Apply update fields to SET clause arrays. */
    private function applyAccountUpdate(array $params, array $existing, array &$sets, array &$values): void
    {
        $textFields = array('AccountLabel', 'Username', 'Email', 'BaseUrl', 'RepoName', 'RepoOwner', 'FolderId', 'FolderName');

        foreach ($textFields as $field) {
            $hasField = isset($params[$field]);

            if ($hasField) {
                $sets[]   = "{$field} = ?";
                $values[] = sanitize_text_field($params[$field]);
            }
        }

        $hasNewToken = !empty($params[CloudStorageAccountFieldType::AccessToken->value]);

        if ($hasNewToken) {
            $sets[]   = 'AccessToken = ?';
            $values[] = $this->encryptToken($params[CloudStorageAccountFieldType::AccessToken->value]);
        }

        $hasNewRefresh = !empty($params[CloudStorageAccountFieldType::RefreshToken->value]);

        if ($hasNewRefresh) {
            $sets[]   = 'RefreshToken = ?';
            $values[] = $this->encryptToken($params[CloudStorageAccountFieldType::RefreshToken->value]);
        }

        $hasIsActive = isset($params[CloudStorageAccountFieldType::IsActive->value]);

        if ($hasIsActive) {
            $sets[]   = 'IsActive = ?';
            $values[] = (int) $params[CloudStorageAccountFieldType::IsActive->value];
        }
    }

    /** Update account last-used timestamp and clear/set error. */
    private function updateAccountLastUsed(int $accountId, array $result): void
    {
        $table     = TableType::CloudStorageAccounts->value;
        $isSuccess = ($result[ResponseKeyType::Success->value] ?? false);

        if ($isSuccess) {
            $this->db->execute(
                "UPDATE {$table} SET LastUsedAt = datetime('now'), LastError = '' WHERE Id = ?",
                array($accountId),
            );

            return;
        }

        $error = $result[ResponseKeyType::Error->value] ?? 'Unknown error';

        $this->db->execute(
            "UPDATE {$table} SET LastError = ? WHERE Id = ?",
            array($error, $accountId),
        );
    }

    /** Log a cloud storage action to the transaction log. */
    private function logCloudStorageAction(ActionType $action, array $data): void
    {
        $hasLogger = ($this->logger !== null);

        if ($hasLogger) {
            $this->logger->logAction(
                $action->value,
                LogCategoryType::CloudStorage->value,
                $data,
            );
        }
    }
}
