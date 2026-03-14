# Cloud Storage Providers — Google Drive Implementation

## Authentication

Google Drive uses **OAuth 2.0** with authorization code flow. Unlike GitHub/GitLab (where the user pastes a PAT), Google Drive requires the plugin to redirect the user to Google's consent screen, receive an authorization code, and exchange it for tokens.

### Required OAuth2 Scopes

| Scope | Purpose |
|---|---|
| `https://www.googleapis.com/auth/drive.file` | Create and manage files created by this app |

This is the most restrictive scope — it only allows access to files the plugin itself creates, not the user's entire Drive.

### OAuth2 Credentials

The plugin needs a **Google Cloud OAuth2 Client ID and Secret**. These are configured in the plugin's WordPress settings (not per-account — they're global).

**Admin must provide** (stored in WordPress options, encrypted):
- `google_oauth_client_id` — From Google Cloud Console
- `google_oauth_client_secret` — From Google Cloud Console

**UI help text for admin setup**:
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a project (or select existing)
3. Enable "Google Drive API" under APIs & Services
4. Go to Credentials → Create OAuth 2.0 Client ID
5. Application type: "Web application"
6. Authorized redirect URI: `https://your-site.com/wp-json/riseup-asia/v1/cloud-storage/oauth/callback`
7. Copy Client ID and Client Secret into the plugin settings

---

## OAuth2 Flow

### Step 1: Initiate (Plugin → Google)

```php
public function handleCloudStorageOAuthInitiate(WP_REST_Request $request): WP_REST_Response
{
    $accountLabel = sanitize_text_field($request->get_param('AccountLabel'));
    $state = wp_generate_password(32, false);

    // Store state + label in transient for 10 minutes
    set_transient('riseup_oauth_state_' . $state, array(
        'label' => $accountLabel,
        'time'  => time(),
    ), 600);

    $clientId    = $this->getEncryptedOption('google_oauth_client_id');
    $redirectUri = rest_url('riseup-asia/v1/' . EndpointType::CloudStorageOAuthCallback->value);

    $oauthUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query(array(
        'client_id'     => $clientId,
        'redirect_uri'  => $redirectUri,
        'response_type' => 'code',
        'scope'         => 'https://www.googleapis.com/auth/drive.file',
        'access_type'   => 'offline',     // Request refresh token
        'prompt'        => 'consent',     // Force consent to get refresh token
        'state'         => $state,
    ));

    return new WP_REST_Response(array(
        ResponseKeyType::Success->value  => true,
        ResponseKeyType::OAuthUrl->value => $oauthUrl,
        ResponseKeyType::OAuthState->value => $state,
    ), HttpStatusType::Ok->value);
}
```

### Step 2: User Consent (Google)

User sees Google consent screen, grants permission, Google redirects to callback.

### Step 3: Callback (Google → Plugin)

```php
public function handleCloudStorageOAuthCallback(WP_REST_Request $request): WP_REST_Response
{
    $code  = sanitize_text_field($request->get_param('code'));
    $state = sanitize_text_field($request->get_param('state'));

    // Verify CSRF state
    $stored = get_transient('riseup_oauth_state_' . $state);
    $isStateMissing = ($stored === false);

    if ($isStateMissing) {
        return new WP_REST_Response(array(
            ResponseKeyType::Success->value => false,
            ResponseKeyType::Error->value   => 'Invalid or expired OAuth state',
        ), HttpStatusType::BadRequest->value);
    }

    delete_transient('riseup_oauth_state_' . $state);

    // Exchange code for tokens
    $clientId     = $this->getEncryptedOption('google_oauth_client_id');
    $clientSecret = $this->getEncryptedOption('google_oauth_client_secret');
    $redirectUri  = rest_url('riseup-asia/v1/' . EndpointType::CloudStorageOAuthCallback->value);

    $tokenOptions = HttpConfigType::defaultGetOptions();
    $tokenOptions['method'] = 'POST';
    $tokenOptions['body'] = array(
        'code'          => $code,
        'client_id'     => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri'  => $redirectUri,
        'grant_type'    => 'authorization_code',
    );

    $tokenResponse = wp_remote_post('https://oauth2.googleapis.com/token', $tokenOptions);
    $tokenBody     = json_decode(wp_remote_retrieve_body($tokenResponse), true);

    $isTokenError = isset($tokenBody['error']);

    if ($isTokenError) {
        $this->fileLogger->error('Google OAuth token exchange failed', $tokenBody);

        return new WP_REST_Response(array(
            ResponseKeyType::Success->value => false,
            ResponseKeyType::Error->value   => 'Token exchange failed: ' . ($tokenBody['error_description'] ?? $tokenBody['error']),
        ), HttpStatusType::BadRequest->value);
    }

    // Get user info
    $userOptions = HttpConfigType::authenticatedOptions('GET', 'Bearer ' . $tokenBody['access_token']);
    $userResponse = wp_remote_get('https://www.googleapis.com/drive/v3/about?fields=user', $userOptions);
    $userData = json_decode(wp_remote_retrieve_body($userResponse), true);

    // Calculate expiry
    $expiresAt = gmdate('Y-m-d\TH:i:s\Z', time() + (int) $tokenBody['expires_in']);

    // Create account
    $accountData = array(
        'Provider'       => CloudStorageProviderType::GoogleDrive->value,
        'AccountLabel'   => $stored['label'],
        'Email'          => $userData['user']['emailAddress'] ?? '',
        'AccessToken'    => $tokenBody['access_token'],
        'RefreshToken'   => $tokenBody['refresh_token'] ?? '',
        'TokenExpiresAt' => $expiresAt,
    );

    $accountId = $this->createCloudStorageAccountFromOAuth($accountData);

    // Redirect to settings page with success
    $redirectUrl = admin_url('admin.php?page=' . AdminPageType::Settings->value . '&cloud_storage_oauth=success&account_id=' . $accountId);

    wp_redirect($redirectUrl);

    exit;
}
```

---

## Token Refresh

Access tokens expire after ~1 hour. Before every API call, check and refresh:

```php
private function googleDriveEnsureValidToken(array &$account): string
{
    $isTokenValid = !empty($account['TokenExpiresAt'])
        && strtotime($account['TokenExpiresAt']) > (time() + 60); // 60s buffer

    if ($isTokenValid) {
        return $this->decryptToken($account['AccessToken']);
    }

    // Refresh the token
    $refreshToken = $this->decryptToken($account['RefreshToken']);
    $clientId     = $this->getEncryptedOption('google_oauth_client_id');
    $clientSecret = $this->getEncryptedOption('google_oauth_client_secret');

    $refreshOptions = HttpConfigType::defaultGetOptions();
    $refreshOptions['method'] = 'POST';
    $refreshOptions['body'] = array(
        'client_id'     => $clientId,
        'client_secret' => $clientSecret,
        'refresh_token' => $refreshToken,
        'grant_type'    => 'refresh_token',
    );

    $response = wp_remote_post('https://oauth2.googleapis.com/token', $refreshOptions);
    $body     = json_decode(wp_remote_retrieve_body($response), true);

    $isRefreshError = isset($body['error']);

    if ($isRefreshError) {
        $this->fileLogger->error('Google OAuth refresh failed', $body);

        throw new RuntimeException(
            'Failed to refresh Google Drive token: ' . ($body['error_description'] ?? $body['error'])
        );
    }

    // Update stored tokens
    $newExpiresAt = gmdate('Y-m-d\TH:i:s\Z', time() + (int) $body['expires_in']);

    $this->db->exec(sprintf(
        "UPDATE %s SET AccessToken = ?, TokenExpiresAt = ?, UpdatedAt = datetime('now') WHERE Id = ?",
        TableType::CloudStorageAccounts->value,
    ), array(
        $this->encryptToken($body['access_token']),
        $newExpiresAt,
        $account['Id'],
    ));

    return $body['access_token'];
}
```

---

## Operations

### API Base URL

```
https://www.googleapis.com/drive/v3       (metadata operations)
https://www.googleapis.com/upload/drive/v3  (file uploads)
```

### 1. Test Connection

```
GET https://www.googleapis.com/drive/v3/about?fields=user
```

**Success**: HTTP 200, response contains `user.emailAddress`.

### 2. Create Folder (If Missing)

```php
$folderMetadata = wp_json_encode(array(
    'name'     => $folderName,
    'mimeType' => 'application/vnd.google-apps.folder',
));

$options = HttpConfigType::authenticatedOptions('POST', 'Bearer ' . $token);
$options['headers']['Content-Type'] = 'application/json';
$options['body'] = $folderMetadata;

$response = wp_remote_post(
    'https://www.googleapis.com/drive/v3/files',
    $options,
);

$data     = json_decode(wp_remote_retrieve_body($response), true);
$folderId = $data['id'];
```

Store the `folderId` back to the account for future uploads.

### 3. Upload File

Google Drive supports two upload methods:

#### Simple Upload (files < 5 MB)

```
POST https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart
```

#### Resumable Upload (files > 5 MB — recommended for backups)

**Step 1: Initiate upload session**

```php
$metadata = wp_json_encode(array(
    'name'    => basename($remotePath),
    'parents' => array($folderId),
));

$initOptions = HttpConfigType::authenticatedOptions('POST', 'Bearer ' . $token);
$initOptions['headers']['Content-Type']   = 'application/json; charset=UTF-8';
$initOptions['headers']['X-Upload-Content-Type'] = 'application/zip';
$initOptions['headers']['X-Upload-Content-Length'] = filesize($localFilePath);
$initOptions['body'] = $metadata;

$initResponse = wp_remote_post(
    'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable',
    $initOptions,
);

$uploadUri = wp_remote_retrieve_header($initResponse, HttpHeaderType::Location->value);
```

**Step 2: Upload the file content**

```php
$fileContent  = file_get_contents($localFilePath);
$uploadOptions = HttpConfigType::authenticatedOptions('PUT', 'Bearer ' . $token);
$uploadOptions['headers']['Content-Type'] = 'application/zip';
$uploadOptions['body'] = $fileContent;

$uploadResponse = wp_remote_request($uploadUri, $uploadOptions);
$fileData = json_decode(wp_remote_retrieve_body($uploadResponse), true);
```

**For very large files (chunked upload)**:

Split into 256 KB chunks (must be multiples of 256 KB except last chunk):

```php
$chunkSize = 256 * 1024; // 256 KB
$fileSize  = filesize($localFilePath);
$handle    = fopen($localFilePath, 'rb');
$offset    = 0;

while ($offset < $fileSize) {
    $chunk      = fread($handle, $chunkSize);
    $chunkLen   = strlen($chunk);
    $rangeEnd   = $offset + $chunkLen - 1;
    $isLastChunk = ($rangeEnd >= $fileSize - 1);

    $chunkOptions = HttpConfigType::authenticatedOptions('PUT', 'Bearer ' . $token);
    $chunkOptions['headers']['Content-Length'] = $chunkLen;
    $chunkOptions['headers']['Content-Range']  = sprintf(
        'bytes %d-%d/%d',
        $offset,
        $rangeEnd,
        $fileSize,
    );
    $chunkOptions['body'] = $chunk;

    $chunkResponse = wp_remote_request($uploadUri, $chunkOptions);
    $chunkStatus   = wp_remote_retrieve_response_code($chunkResponse);

    $isIncomplete = ($chunkStatus === 308);

    if ($isIncomplete) {
        $offset += $chunkLen;

        continue;
    }

    $isComplete = ($chunkStatus === 200 || $chunkStatus === 201);

    if ($isComplete) {
        break;
    }

    throw new RuntimeException(
        sprintf('Google Drive chunk upload failed at offset %d [%d]', $offset, $chunkStatus)
    );
}

fclose($handle);
```

### 4. List Files in Folder

```php
$query = sprintf("'%s' in parents and trashed=false", $folderId);

$url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query(array(
    'q'      => $query,
    'fields' => 'files(id,name,size,createdTime,webViewLink)',
    'orderBy' => 'name',
));

$response = wp_remote_get($url, $options);
$data     = json_decode(wp_remote_retrieve_body($response), true);
// $data['files'] is the array
```

### 5. Delete File

```
DELETE https://www.googleapis.com/drive/v3/files/{fileId}
```

Returns HTTP 204 on success (no body).

### 6. Rotation

Same rotation logic — list files in folder, sort by name, delete oldest excess.

---

## OAuth2 Settings (WordPress Options)

These are **global** settings (not per-account), stored encrypted in WordPress options:

| Option Key | Purpose |
|---|---|
| `riseup_google_oauth_client_id` | Google Cloud OAuth2 Client ID |
| `riseup_google_oauth_client_secret` | Google Cloud OAuth2 Client Secret (encrypted) |

---

## UI Flow for Adding Google Drive Account

1. User clicks "Add Google Drive Account" button
2. User enters an account label
3. Plugin calls `POST /cloud-storage/oauth/initiate` with the label
4. Plugin opens Google consent URL in new window/tab
5. User grants permission on Google's page
6. Google redirects to `GET /cloud-storage/oauth/callback`
7. Plugin exchanges code for tokens, creates account, redirects to settings
8. Settings page shows new account with "Connected" status

---

## Differences from GitHub/GitLab

| Aspect | GitHub/GitLab | Google Drive |
|---|---|---|
| Auth method | PAT (user pastes token) | OAuth2 (redirect flow) |
| Token refresh | Not needed (PATs don't expire unless set) | Required (access tokens expire ~1 hour) |
| File structure | Git repo with commits | Flat folder with file uploads |
| File versioning | Git history | Google Drive versioning (automatic) |
| Delete | Requires file SHA (GitHub) or commit msg (GitLab) | Simple DELETE by file ID |
| Size limit | 100 MB (Contents API) | 5 TB (resumable upload) |
| Global config | None | OAuth Client ID + Secret |

---

## PHP Trait

`CloudStorageGoogleDriveTrait.php` implements:

```php
trait CloudStorageGoogleDriveTrait {
    private function googleDriveTestConnection(array $account): array { ... }
    private function googleDriveEnsureValidToken(array &$account): string { ... }
    private function googleDriveEnsureFolder(array $account): string { ... }
    private function googleDriveUploadFile(array $account, string $localPath, string $remotePath): array { ... }
    private function googleDriveUploadResumable(string $token, string $folderId, string $localPath, string $name): array { ... }
    private function googleDriveListFiles(array $account): array { ... }
    private function googleDriveDeleteFile(array $account, string $fileId): bool { ... }
}
```

`CloudStorageOAuthTrait.php` implements:

```php
trait CloudStorageOAuthTrait {
    public function handleCloudStorageOAuthInitiate(WP_REST_Request $request): WP_REST_Response { ... }
    public function handleCloudStorageOAuthCallback(WP_REST_Request $request): WP_REST_Response { ... }
    private function createCloudStorageAccountFromOAuth(array $data): int { ... }
    private function getEncryptedOption(string $key): string { ... }
    private function setEncryptedOption(string $key, string $value): void { ... }
}
```
