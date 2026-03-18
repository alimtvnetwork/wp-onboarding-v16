# Cloud Storage Providers — REST Endpoints

**Namespace**: `riseup-asia/v1`
**Permission**: `manage_options` (administrator only)

---

## Account Management

### 1. List All Accounts

```
GET /cloud-storage/accounts
```

**Response**:
```json
{
    "Success": true,
    "Accounts": [
        {
            "Id": 1,
            "Provider": "GitHub",
            "AccountLabel": "Work GitHub",
            "Username": "octocat",
            "Email": "octocat@github.com",
            "TokenMask": "ghp_****xyz",
            "BaseUrl": "",
            "RepoName": "wp-backups",
            "RepoOwner": "octocat",
            "IsActive": true,
            "LastUsedAt": "2026-03-14T10:30:00Z",
            "LastError": "",
            "CreatedAt": "2026-03-01T00:00:00Z"
        }
    ]
}
```

**CRITICAL**: `AccessToken` and `RefreshToken` are **never** returned. Only `TokenMask` is provided (last 3 chars with prefix).

### 2. Get Single Account

```
GET /cloud-storage/accounts/{id}
```

Same response shape as list, but single `Account` key instead of `Accounts` array.

### 3. Create Account

```
POST /cloud-storage/accounts
```

**Request Body — GitHub/GitLab**:
```json
{
    "Provider": "GitHub",
    "AccountLabel": "Work GitHub",
    "Username": "octocat",
    "Email": "octocat@github.com",
    "AccessToken": "ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
    "RepoName": "wp-backups",
    "RepoOwner": "octocat"
}
```

**Request Body — Google Drive** (after OAuth2 flow):
```json
{
    "Provider": "GoogleDrive",
    "AccountLabel": "My Google Drive",
    "Email": "user@gmail.com",
    "AccessToken": "ya29.xxxxxxxxxxxxxxxx",
    "RefreshToken": "1//xxxxxxxxxxxxxxxx",
    "TokenExpiresAt": "2026-03-14T11:30:00Z",
    "FolderName": "WordPress Backups"
}
```

**Validation**:
- `Provider` must be a valid `CloudStorageProviderType` value
- `AccountLabel` required, max 100 chars, `sanitize_text_field()`
- `AccessToken` required, `sanitize_text_field()`, encrypted before storage
- `Username` optional for GitHub/GitLab, `sanitize_text_field()`
- `Email` optional, `sanitize_email()`
- `RepoName` optional, defaults to `wp-backups`, `sanitize_file_name()`
- `RepoOwner` required for GitHub/GitLab if `RepoName` is set
- `BaseUrl` optional (GitLab self-hosted), `esc_url_raw()`
- `RefreshToken` required for GoogleDrive, encrypted before storage

**Response**: 201 Created with the new account (masked token).

### 4. Update Account

```
PUT /cloud-storage/accounts/{id}
```

Same body as create. If `AccessToken` is empty/missing, the existing token is preserved (allows updating label/repo without re-entering token).

### 5. Delete Account

```
DELETE /cloud-storage/accounts/{id}
```

**Response**: 200 OK. If this account is the `DefaultAccountId` in settings, that FK is set to NULL.

### 6. Test Connection

```
POST /cloud-storage/accounts/test
```

**Request Body**:
```json
{
    "AccountId": 1
}
```

**Actions by Provider**:
- **GitHub**: `GET /user` with the PAT → verify 200 + username match
- **GitLab**: `GET /user` with the PAT → verify 200 + username match
- **Google Drive**: `GET /about?fields=user` with access token → verify 200

**Response**:
```json
{
    "Success": true,
    "ConnectionStatus": "Connected",
    "Username": "octocat",
    "Message": "Successfully authenticated as octocat"
}
```

---

## Settings Management

### 7. Get All Provider Settings

```
GET /cloud-storage/settings
```

**Response**:
```json
{
    "Success": true,
    "ProviderSettings": {
        "GitHub": {
            "IsEnabled": true,
            "AutoBackupEnabled": true,
            "DefaultAccountId": 1,
            "RetentionCount": 10,
            "RotationEnabled": true,
            "BackupPrefix": "wp-backup"
        },
        "GitLab": { ... },
        "GoogleDrive": { ... }
    }
}
```

### 8. Update Provider Settings

```
PUT /cloud-storage/settings/{provider}
```

**Request Body**:
```json
{
    "IsEnabled": true,
    "AutoBackupEnabled": true,
    "DefaultAccountId": 1,
    "RetentionCount": 10,
    "RotationEnabled": true,
    "BackupPrefix": "wp-backup"
}
```

**Validation**:
- `{provider}` must be a valid `CloudStorageProviderType` value
- `DefaultAccountId` must reference an existing account of this provider
- `RetentionCount` must be 1–100

---

## File Operations

### 9. Upload Backup to Provider

```
POST /cloud-storage/upload
```

**Request Body**:
```json
{
    "AccountId": 1,
    "FilePath": "/absolute/path/to/backup.zip",
    "RemotePath": "backups/2026-03-14/wp-backup-full.zip"
}
```

**Actions by Provider**:
- **GitHub**: See [05-github-implementation.md](./05-github-implementation.md)
- **GitLab**: See [06-gitlab-implementation.md](./06-gitlab-implementation.md)
- **Google Drive**: See [07-google-drive-implementation.md](./07-google-drive-implementation.md)

**Response**:
```json
{
    "Success": true,
    "UploadResult": {
        "RemotePath": "backups/2026-03-14/wp-backup-full.zip",
        "RemoteUrl": "https://github.com/octocat/wp-backups/blob/main/backups/...",
        "Bytes": 5242880,
        "Duration": 3.2
    },
    "RotationApplied": true,
    "FilesDeleted": 2
}
```

### 10. List Remote Files

```
GET /cloud-storage/files?account_id=1&path=backups/
```

**Response**:
```json
{
    "Success": true,
    "Files": [
        {
            "Name": "wp-backup-2026-03-14.zip",
            "Path": "backups/wp-backup-2026-03-14.zip",
            "Size": 5242880,
            "CreatedAt": "2026-03-14T10:30:00Z",
            "RemoteUrl": "https://..."
        }
    ],
    "Total": 1
}
```

### 11. Delete Remote File

```
DELETE /cloud-storage/delete
```

**Request Body**:
```json
{
    "AccountId": 1,
    "RemotePath": "backups/wp-backup-2026-03-01.zip"
}
```

---

## OAuth2 (Google Drive Only)

### 12. Initiate OAuth2 Flow

```
POST /cloud-storage/oauth/initiate
```

**Request Body**:
```json
{
    "AccountLabel": "My Google Drive"
}
```

**Response**:
```json
{
    "Success": true,
    "OAuthUrl": "https://accounts.google.com/o/oauth2/v2/auth?client_id=...&redirect_uri=...&scope=...&state=...",
    "OAuthState": "random-csrf-token-stored-in-transient"
}
```

The user is redirected to this URL. After consent, Google redirects back to the callback endpoint.

### 13. OAuth2 Callback

```
GET /cloud-storage/oauth/callback?code=...&state=...
```

**Actions**:
1. Verify `state` matches stored transient (CSRF protection)
2. Exchange `code` for access token + refresh token via `https://oauth2.googleapis.com/token`
3. Encrypt both tokens
4. Create `CloudStorageAccounts` row
5. Redirect user back to plugin settings page with success message

---

## Route Registration

All endpoints registered in `RouteRegistrationTrait.php` using:

```php
$this->registerRoute(HttpMethodType::GET, EndpointType::CloudStorageAccounts, 'handleListCloudStorageAccounts');
$this->registerRoute(HttpMethodType::POST, EndpointType::CloudStorageAccounts, 'handleCreateCloudStorageAccount');
$this->registerRoute(HttpMethodType::GET, EndpointType::CloudStorageAccountId, 'handleGetCloudStorageAccount');
$this->registerRoute(HttpMethodType::PUT, EndpointType::CloudStorageAccountId, 'handleUpdateCloudStorageAccount');
$this->registerRoute(HttpMethodType::DELETE, EndpointType::CloudStorageAccountId, 'handleDeleteCloudStorageAccount');
$this->registerRoute(HttpMethodType::POST, EndpointType::CloudStorageAccountTest, 'handleTestCloudStorageAccount');
$this->registerRoute(HttpMethodType::GET, EndpointType::CloudStorageSettings, 'handleGetCloudStorageSettings');
$this->registerRoute(HttpMethodType::PUT, EndpointType::CloudStorageSettingsProvider, 'handleUpdateCloudStorageSettings');
$this->registerRoute(HttpMethodType::POST, EndpointType::CloudStorageUpload, 'handleCloudStorageUpload');
$this->registerRoute(HttpMethodType::GET, EndpointType::CloudStorageFiles, 'handleListCloudStorageFiles');
$this->registerRoute(HttpMethodType::DELETE, EndpointType::CloudStorageDelete, 'handleDeleteCloudStorageFile');
$this->registerRoute(HttpMethodType::POST, EndpointType::CloudStorageOAuthInitiate, 'handleCloudStorageOAuthInitiate');
$this->registerRoute(HttpMethodType::GET, EndpointType::CloudStorageOAuthCallback, 'handleCloudStorageOAuthCallback');
```

**Permission**: All endpoints use `checkCloudStoragePermission()` which verifies `manage_options` capability.
