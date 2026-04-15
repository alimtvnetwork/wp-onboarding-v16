<?php
/**
 * CloudStorageSettingsTrait — Settings handlers for cloud storage providers.
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

use RiseupAsia\Enums\CloudStorageProviderType;
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\TableType;

trait CloudStorageSettingsTrait {

    /** GET /cloud-storage/settings */
    public function handleGetCloudStorageSettings(WP_REST_Request $request): WP_REST_Response
    {
        return $this->safeExecute(function() use ($request) {
            $table = TableType::CloudStorageSettings->value;
            $rows  = $this->db->queryAll("SELECT * FROM {$table} ORDER BY Provider ASC");

            $settings = [];

            foreach ($rows as $row) {
                $settings[$row['Provider']] = $this->formatSettingsRow($row);
            }

            return new WP_REST_Response([
                ResponseKeyType::Success->value          => true,
                ResponseKeyType::ProviderSettings->value => $settings,
            ], HttpStatusType::Ok->value);
        }, 'get-cloud-storage-settings');
    }

    /** PUT /cloud-storage/settings/{provider} */
    public function handleUpdateCloudStorageSettings(WP_REST_Request $request): WP_REST_Response
    {
        return $this->safeExecute(function() use ($request) {
            $providerParam = $request->get_param('provider');
            $providerType  = CloudStorageProviderType::tryFrom($providerParam);

            $isInvalidProvider = ($providerType === false);

            if ($isInvalidProvider) {
                return new WP_REST_Response([
                    ResponseKeyType::Success->value => false,
                    ResponseKeyType::Error->value   => 'Invalid provider: ' . $providerParam,
                ], HttpStatusType::BadRequest->value);
            }

            $params = $this->extractValidBody($request);
            $isBodyInvalid = ($params === null);

            if ($isBodyInvalid) {
                return $this->validationError('Invalid or missing JSON body', $request);
            }
            $table  = TableType::CloudStorageSettings->value;
            $sets   = [];
            $values = [];

            $this->applySettingsUpdate($params, $sets, $values);

            $sets[]   = "UpdatedAt = datetime('now')";
            $values[] = $providerType->value;

            $setClause = implode(', ', $sets);

            $this->db->execute(
                "UPDATE {$table} SET {$setClause} WHERE Provider = ?",
                $values,
            );

            $row = $this->db->querySingle(
                "SELECT * FROM {$table} WHERE Provider = ?",
                [$providerType->value],
            );

            return new WP_REST_Response([
                ResponseKeyType::Success->value          => true,
                ResponseKeyType::ProviderSettings->value => $this->formatSettingsRow($row),
            ], HttpStatusType::Ok->value);
        }, 'update-cloud-storage-settings');
    }

    /** Format a settings row for API response. */
    private function formatSettingsRow(array|false $row): array
    {
        $isNull = ($row === false);

        if ($isNull) {
            return [];
        }

        return [
            'IsEnabled'         => (bool) ($row['IsEnabled'] ?? false),
            'AutoBackupEnabled' => (bool) ($row['AutoBackupEnabled'] ?? false),
            'DefaultAccountId'  => $row['DefaultAccountId'] !== null ? (int) $row['DefaultAccountId'] : null,
            'RetentionCount'    => (int) ($row['RetentionCount'] ?? 10),
            'RotationEnabled'   => (bool) ($row['RotationEnabled'] ?? true),
            'BackupPrefix'      => $row['BackupPrefix'] ?? 'wp-backup',
        ];
    }

    /** Apply settings update fields to SET clause arrays. */
    private function applySettingsUpdate(array $params, array &$sets, array &$values): void
    {
        $boolFields = ['IsEnabled', 'AutoBackupEnabled', 'RotationEnabled'];

        foreach ($boolFields as $field) {
            $hasField = isset($params[$field]);

            if ($hasField) {
                $sets[]   = "{$field} = ?";
                $values[] = (int) $params[$field];
            }
        }

        $hasDefaultAccount = isset($params['DefaultAccountId']);

        if ($hasDefaultAccount) {
            $sets[]   = 'DefaultAccountId = ?';
            $values[] = $params['DefaultAccountId'] !== null ? (int) $params['DefaultAccountId'] : null;
        }

        $hasRetention = isset($params['RetentionCount']);

        if ($hasRetention) {
            $count      = max(1, min(100, (int) $params['RetentionCount']));
            $sets[]     = 'RetentionCount = ?';
            $values[]   = $count;
        }

        $hasPrefix = isset($params['BackupPrefix']);

        if ($hasPrefix) {
            $sets[]   = 'BackupPrefix = ?';
            $values[] = sanitize_file_name($params['BackupPrefix']);
        }
    }
}
