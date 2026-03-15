<?php
/**
 * CloudStorageOAuthTrait — OAuth2 initiate/callback handlers for Google Drive.
 *
 * Manages the OAuth2 authorization code flow: state generation, token exchange,
 * account creation from OAuth, and encrypted option storage for client credentials.
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

use RiseupAsia\Enums\AdminPageType;
use RiseupAsia\Enums\ActionType;
use RiseupAsia\Enums\CloudStorageProviderType;
use RiseupAsia\Enums\EndpointType;
use RiseupAsia\Enums\HttpConfigType;
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\TableType;

trait CloudStorageOAuthTrait {

    /** POST /cloud-storage/oauth/initiate — Start Google OAuth2 flow. */
    public function handleCloudStorageOAuthInitiate(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $params       = $request->get_json_params();
            $accountLabel = sanitize_text_field($params['AccountLabel'] ?? 'Google Drive');

            $clientId = $this->getEncryptedOption('riseup_google_oauth_client_id');
            $isClientMissing = empty($clientId);

            if ($isClientMissing) {
                return new WP_REST_Response(array(
                    ResponseKeyType::Success->value => false,
                    ResponseKeyType::Error->value   => 'Google OAuth Client ID not configured. Go to Settings to add your Google Cloud credentials.',
                ), HttpStatusType::BadRequest->value);
            }

            $state = wp_generate_password(32, false);

            set_transient('riseup_oauth_state_' . $state, array(
                'label' => $accountLabel,
                'time'  => time(),
            ), 600);

            $namespace  = PluginConfigType::ApiNamespace->value;
            $redirectUri = rest_url($namespace . '/' . EndpointType::CloudStorageOAuthCallback->value);

            $oauthUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query(array(
                'client_id'     => $clientId,
                'redirect_uri'  => $redirectUri,
                'response_type' => 'code',
                'scope'         => 'https://www.googleapis.com/auth/drive.file',
                'access_type'   => 'offline',
                'prompt'        => 'consent',
                'state'         => $state,
            ));

            return new WP_REST_Response(array(
                ResponseKeyType::Success->value    => true,
                ResponseKeyType::OAuthUrl->value   => $oauthUrl,
                ResponseKeyType::OAuthState->value => $state,
            ), HttpStatusType::Ok->value);
        } catch (Throwable $e) {
            $this->fileLogger->logException($e, 'Failed to initiate Google OAuth');

            return new WP_REST_Response(array(
                ResponseKeyType::Success->value => false,
                ResponseKeyType::Error->value   => $e->getMessage(),
            ), HttpStatusType::InternalServerError->value);
        }
    }

    /** GET /cloud-storage/oauth/callback — Handle Google OAuth2 redirect. */
    public function handleCloudStorageOAuthCallback(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $code  = sanitize_text_field($request->get_param('code') ?? '');
            $state = sanitize_text_field($request->get_param('state') ?? '');
            $error = sanitize_text_field($request->get_param('error') ?? '');

            $hasError = !empty($error);

            if ($hasError) {
                $redirectUrl = admin_url('admin.php?page=' . AdminPageType::Settings->value . '&cloud_storage_oauth=error&reason=' . urlencode($error));

                wp_redirect($redirectUrl);

                exit;
            }

            $stored        = get_transient('riseup_oauth_state_' . $state);
            $isStateMissing = ($stored === false);

            if ($isStateMissing) {
                return new WP_REST_Response(array(
                    ResponseKeyType::Success->value => false,
                    ResponseKeyType::Error->value   => 'Invalid or expired OAuth state. Please try again.',
                ), HttpStatusType::BadRequest->value);
            }

            delete_transient('riseup_oauth_state_' . $state);

            $clientId     = $this->getEncryptedOption('riseup_google_oauth_client_id');
            $clientSecret = $this->getEncryptedOption('riseup_google_oauth_client_secret');
            $namespace    = PluginConfigType::ApiNamespace->value;
            $redirectUri  = rest_url($namespace . '/' . EndpointType::CloudStorageOAuthCallback->value);

            $tokenOptions           = HttpConfigType::defaultGetOptions();
            $tokenOptions['method'] = 'POST';
            $tokenOptions['body']   = array(
                'code'          => $code,
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri'  => $redirectUri,
                'grant_type'    => 'authorization_code',
            );

            $tokenResponse = wp_remote_post('https://oauth2.googleapis.com/token', $tokenOptions);
            $tokenBody     = json_decode(wp_remote_retrieve_body($tokenResponse), true) ?? array();

            $isTokenError = isset($tokenBody['error']);

            if ($isTokenError) {
                $this->fileLogger->error('Google OAuth token exchange failed', $tokenBody);

                $redirectUrl = admin_url(
                    'admin.php?page=' . AdminPageType::Settings->value
                    . '&cloud_storage_oauth=error&reason=' . urlencode($tokenBody['error_description'] ?? $tokenBody['error']),
                );

                wp_redirect($redirectUrl);

                exit;
            }

            $accessToken = $tokenBody['access_token'] ?? '';

            $userOptions  = HttpConfigType::authenticatedOptions('GET', 'Bearer ' . $accessToken);
            $userResponse = wp_remote_get('https://www.googleapis.com/drive/v3/about?fields=user', $userOptions);
            $userData     = json_decode(wp_remote_retrieve_body($userResponse), true) ?? array();

            $expiresAt = gmdate('Y-m-d\TH:i:s\Z', time() + (int) ($tokenBody['expires_in'] ?? 3600));

            $accountData = array(
                'Provider'       => CloudStorageProviderType::GoogleDrive->value,
                'AccountLabel'   => $stored['label'],
                'Email'          => $userData['user']['emailAddress'] ?? '',
                'AccessToken'    => $accessToken,
                'RefreshToken'   => $tokenBody['refresh_token'] ?? '',
                'TokenExpiresAt' => $expiresAt,
            );

            $accountId = $this->createCloudStorageAccountFromOAuth($accountData);

            $this->logCloudStorageAction(ActionType::CloudStorageAccountAdd, array(
                ResponseKeyType::AccountId->value    => $accountId,
                ResponseKeyType::AccountLabel->value => $stored['label'],
                ResponseKeyType::Provider->value     => CloudStorageProviderType::GoogleDrive->value,
            ));

            $redirectUrl = admin_url(
                'admin.php?page=' . AdminPageType::Settings->value
                . '&cloud_storage_oauth=success&account_id=' . $accountId,
            );

            wp_redirect($redirectUrl);

            exit;
        } catch (Throwable $e) {
            $this->fileLogger->logException($e, 'Google OAuth callback failed');

            $redirectUrl = admin_url(
                'admin.php?page=' . AdminPageType::Settings->value
                . '&cloud_storage_oauth=error&reason=' . urlencode($e->getMessage()),
            );

            wp_redirect($redirectUrl);

            exit;
        }
    }

    /** Create a cloud storage account from OAuth token exchange. */
    private function createCloudStorageAccountFromOAuth(array $data): int
    {
        $table = TableType::CloudStorageAccounts->value;

        $row = array(
            'Provider'       => $data['Provider'],
            'AccountLabel'   => sanitize_text_field($data['AccountLabel']),
            'Username'       => '',
            'Email'          => sanitize_email($data['Email'] ?? ''),
            'AccessToken'    => $this->encryptToken($data['AccessToken']),
            'RefreshToken'   => $this->encryptToken($data['RefreshToken'] ?? ''),
            'TokenExpiresAt' => $data['TokenExpiresAt'] ?? '',
            'BaseUrl'        => '',
            'RepoName'       => '',
            'RepoOwner'      => '',
            'FolderId'       => '',
            'FolderName'     => '',
            'IsActive'       => 1,
        );

        $columns      = implode(', ', array_keys($row));
        $placeholders = implode(', ', array_fill(0, count($row), '?'));

        $this->db->execute(
            "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})",
            array_values($row),
        );

        return (int) $this->db->lastInsertId();
    }

    /** Get an encrypted WordPress option (decrypted). */
    private function getEncryptedOption(string $key): string
    {
        $stored  = get_option($key, '');
        $isEmpty = empty($stored);

        if ($isEmpty) {
            return '';
        }

        return $this->decryptToken($stored);
    }

    /** Set an encrypted WordPress option. */
    private function setEncryptedOption(string $key, string $value): void
    {
        $encrypted = $this->encryptToken($value);

        update_option($key, $encrypted, false);
    }
}
