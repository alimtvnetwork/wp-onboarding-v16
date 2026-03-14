# Cloud Storage Providers — Implementation Plan

## Phase 1: GitHub (Implement First)

### Step 1: Enums

1. Create `wp-plugins/riseup-asia-uploader/includes/Enums/CloudStorageProviderType.php`
   - As specified in [03-enums.md](./03-enums.md)

2. Create `wp-plugins/riseup-asia-uploader/includes/Enums/CloudStorageAccountFieldType.php`
   - As specified in [03-enums.md](./03-enums.md)

3. Update `EndpointType.php` — add cloud storage endpoint cases
4. Update `ResponseKeyType.php` — add cloud storage response keys
5. Update `ActionType.php` — add cloud storage action cases
6. Update `LogCategoryType.php` — add `CloudStorage` case
7. Update `TableType.php` — add `CloudStorageAccounts` and `CloudStorageSettings` cases

### Step 2: Database Migrations

1. Create `DatabaseMigrationsV17Trait.php` — `CloudStorageAccounts` table
2. Create `DatabaseMigrationsV18Trait.php` — `CloudStorageSettings` table with default seeds
3. Update `DatabaseMigrationsTrait.php` to compose the new migration traits

### Step 3: PHP Traits (Cloud Storage)

Create all traits in `wp-plugins/riseup-asia-uploader/includes/Traits/CloudStorage/`:

1. **`CloudStorageEncryptionTrait.php`**
   - `encryptToken(string $plain): string`
   - `decryptToken(string $encrypted): string`
   - `maskToken(string $provider, string $token): string`

2. **`CloudStorageAccountCrudTrait.php`**
   - `handleListCloudStorageAccounts(WP_REST_Request): WP_REST_Response`
   - `handleGetCloudStorageAccount(WP_REST_Request): WP_REST_Response`
   - `handleCreateCloudStorageAccount(WP_REST_Request): WP_REST_Response`
   - `handleUpdateCloudStorageAccount(WP_REST_Request): WP_REST_Response`
   - `handleDeleteCloudStorageAccount(WP_REST_Request): WP_REST_Response`
   - `handleTestCloudStorageAccount(WP_REST_Request): WP_REST_Response`
   - Private helpers: `validateAccountFields()`, `buildAccountRow()`, `formatAccountForResponse()`

3. **`CloudStorageSettingsTrait.php`**
   - `handleGetCloudStorageSettings(WP_REST_Request): WP_REST_Response`
   - `handleUpdateCloudStorageSettings(WP_REST_Request): WP_REST_Response`

4. **`CloudStorageGitHubTrait.php`**
   - `githubTestConnection(array $account): array`
   - `githubEnsureRepo(array $account): array`
   - `githubUploadFile(array $account, string $localPath, string $remotePath): array`
   - `githubUploadLargeFile(array $account, string $localPath, string $remotePath): array`
   - `githubListFiles(array $account, string $dir): array`
   - `githubDeleteFile(array $account, string $remotePath): bool`
   - `githubBuildOptions(string $method, string $token): array`

5. **`CloudStorageUploadTrait.php`**
   - `handleCloudStorageUpload(WP_REST_Request): WP_REST_Response`
   - Dispatches to provider-specific trait based on account's `Provider` field
   - Applies rotation after upload

6. **`CloudStorageFileTrait.php`**
   - `handleListCloudStorageFiles(WP_REST_Request): WP_REST_Response`
   - `handleDeleteCloudStorageFile(WP_REST_Request): WP_REST_Response`
   - `applyRotation(int $accountId, string $dir, int $retentionCount): array`

7. **`CloudStorageTrait.php`** (Shell trait)
   - Composes all sub-traits

### Step 4: Auth Permission

Update `AuthPermissionTrait.php`:
- Add `checkCloudStoragePermission()` using `manage_options` capability

### Step 5: Route Registration

Update `RouteRegistrationTrait.php`:
- Register all 13 cloud storage endpoints (see [04-endpoints.md](./04-endpoints.md))

### Step 6: Plugin Core

Update `Plugin.php`:
- Add `use CloudStorageTrait;` in the main Plugin class

### Step 7: Go Backend Types

Create `backend/internal/wordpress/CloudStorageTypes.go`:
- `CloudStorageAccount` struct (response shape)
- `CloudStorageAccountCreateRequest` struct
- `CloudStorageAccountUpdateRequest` struct
- `CloudStorageSettings` struct
- `CloudStorageUploadRequest` struct
- `CloudStorageUploadResult` struct
- `CloudStorageFileInfo` struct

Update `backend/internal/enums/endpointtype/Variant.go`:
- Add cloud storage endpoint variants

Update `backend/internal/enums/endpointtype/VariantMethods.go`:
- Add `IsCloudStorage()` helper

---

## Phase 2: GitLab

After GitHub is validated:

1. Create `CloudStorageGitLabTrait.php` — all GitLab API operations
2. Update `CloudStorageUploadTrait.php` dispatch to include GitLab
3. Update `CloudStorageFileTrait.php` to handle GitLab file listing/deletion
4. Update `CloudStorageAccountCrudTrait.php` — GitLab-specific validation (BaseUrl for self-hosted)
5. Test: create GitLab project, push backup, verify rotation

---

## Phase 3: Google Drive

After GitLab is validated:

1. Create `CloudStorageGoogleDriveTrait.php` — all Google Drive API operations
2. Create `CloudStorageOAuthTrait.php` — OAuth2 initiate + callback
3. Update `CloudStorageUploadTrait.php` dispatch to include Google Drive
4. Update `CloudStorageAccountCrudTrait.php` — Google Drive OAuth flow
5. Add Google OAuth Client ID/Secret settings to admin settings page
6. Test: OAuth flow, folder creation, upload, refresh token, rotation

---

## React Dashboard Components (Phase 4)

1. **`CloudStorageSettingsPage.tsx`**
   - Tab layout: GitHub | GitLab | Google Drive
   - Each tab shows accounts list and provider settings

2. **`CloudStorageAccountCard.tsx`**
   - Account label, provider icon, masked token, status indicator
   - Actions: Test, Edit, Delete

3. **`CloudStorageAccountDialog.tsx`**
   - Form for adding/editing accounts
   - Dynamic fields based on provider (PAT fields vs OAuth button)
   - Placeholder examples for each field

4. **`CloudStorageProviderSettings.tsx`**
   - Enable/disable toggle
   - Auto-backup toggle
   - Default account selector
   - Retention count slider
   - Rotation enabled toggle

5. **`CloudStorageBackupSelector.tsx`**
   - When creating a backup, show dropdown to select cloud storage destination
   - Multi-select: push to GitHub AND Google Drive simultaneously

---

## File Inventory

### New Files (Phase 1)

| File | Purpose |
|---|---|
| `Enums/CloudStorageProviderType.php` | Provider enum |
| `Enums/CloudStorageAccountFieldType.php` | Account field enum |
| `Database/Traits/DatabaseMigrationsV17Trait.php` | Accounts table migration |
| `Database/Traits/DatabaseMigrationsV18Trait.php` | Settings table migration |
| `Traits/CloudStorage/CloudStorageTrait.php` | Shell trait |
| `Traits/CloudStorage/CloudStorageEncryptionTrait.php` | Token encrypt/decrypt |
| `Traits/CloudStorage/CloudStorageAccountCrudTrait.php` | Account CRUD handlers |
| `Traits/CloudStorage/CloudStorageSettingsTrait.php` | Settings handlers |
| `Traits/CloudStorage/CloudStorageGitHubTrait.php` | GitHub API operations |
| `Traits/CloudStorage/CloudStorageUploadTrait.php` | Upload dispatch + rotation |
| `Traits/CloudStorage/CloudStorageFileTrait.php` | List/delete remote files |
| `backend/internal/wordpress/CloudStorageTypes.go` | Go request/response types |

### Modified Files (Phase 1)

| File | Changes |
|---|---|
| `Enums/EndpointType.php` | Add cloud storage endpoints |
| `Enums/ResponseKeyType.php` | Add cloud storage response keys |
| `Enums/ActionType.php` | Add cloud storage actions |
| `Enums/LogCategoryType.php` | Add CloudStorage case |
| `Enums/TableType.php` | Add CloudStorageAccounts, CloudStorageSettings |
| `Traits/Auth/AuthPermissionTrait.php` | Add checkCloudStoragePermission() |
| `Traits/Route/RouteRegistrationTrait.php` | Register 13 new endpoints |
| `Core/Plugin.php` | Use CloudStorageTrait |
| `Database/Traits/DatabaseMigrationsTrait.php` | Compose v17, v18 traits |
| `backend/internal/enums/endpointtype/Variant.go` | Add cloud storage variants |
| `backend/internal/enums/endpointtype/VariantMethods.go` | Add IsCloudStorage() |

### New Files (Phase 2)

| File | Purpose |
|---|---|
| `Traits/CloudStorage/CloudStorageGitLabTrait.php` | GitLab API operations |

### New Files (Phase 3)

| File | Purpose |
|---|---|
| `Traits/CloudStorage/CloudStorageGoogleDriveTrait.php` | Google Drive API operations |
| `Traits/CloudStorage/CloudStorageOAuthTrait.php` | OAuth2 flow handlers |

---

## Testing Checklist

### GitHub (Phase 1)
- [ ] Add GitHub account with PAT → verify token encrypted in DB
- [ ] Test connection → verify 200 + username match
- [ ] GET accounts → verify token is masked, never returned in plaintext
- [ ] Upload backup → verify repo created if missing, file committed
- [ ] Upload large file (>100 MB) → verify Git Data API used
- [ ] List remote files → verify correct files returned
- [ ] Delete remote file → verify file removed from repo
- [ ] Rotation → upload 12 files with retention=10, verify oldest 2 deleted
- [ ] Update account (change label, keep token) → verify token preserved
- [ ] Delete account → verify FK in settings nulled
- [ ] Test with invalid token → verify error message and LastError updated

### GitLab (Phase 2)
- [ ] Same as GitHub checklist, plus:
- [ ] Self-hosted GitLab URL → verify correct API base
- [ ] Group/namespace project creation

### Google Drive (Phase 3)
- [ ] OAuth2 initiate → verify redirect URL correct
- [ ] OAuth2 callback → verify tokens stored encrypted
- [ ] Token refresh → verify new access token obtained
- [ ] Upload via resumable → verify file in correct folder
- [ ] Chunked upload for large files
- [ ] List files in folder
- [ ] Delete file
- [ ] Rotation

---

## Naming Convention Summary

| Item | Convention | Example |
|---|---|---|
| PHP Enum | PascalCase value | `CloudStorageProviderType::GitHub` |
| PHP Trait | PascalCase filename | `CloudStorageGitHubTrait.php` |
| SQLite table | PascalCase | `CloudStorageAccounts` |
| SQLite column | PascalCase | `AccessToken`, `RepoOwner` |
| REST endpoint | kebab-case | `cloud-storage/accounts` |
| Go struct | PascalCase | `CloudStorageAccount` |
| Go field | PascalCase (Id not ID) | `AccountId`, `RepoUrl` |
| Response key | PascalCase enum | `ResponseKeyType::AccountLabel` |
