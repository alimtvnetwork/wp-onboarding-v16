# Cloud Storage Providers

> **Updated:** 2026-03-15  
> **Status:** Frontend complete, backend pipeline integration pending

---

## Overview

The cloud storage provider system supports remote backups to three providers:

| Provider | Auth | API | Upload Strategy |
|----------|------|-----|-----------------|
| GitHub | Personal Access Token (PAT) | Git Data API (Blobs + Trees) | Base64-encoded blobs |
| GitLab | Private-Token header | REST API v4 | Multipart file upload |
| Google Drive | OAuth2 (authorization code flow) | Drive v3 API | Resumable uploads (262KB chunks) for files >5MB |

## Security

- **Credential storage:** AES-256-CBC encryption via `getEncryptedOption`/`setEncryptedOption`
- **Token refresh:** Google Drive tokens auto-refresh with 60-second buffer
- **OAuth CSRF:** State parameter stored as WordPress transient (`riseup_oauth_state_{userId}`)

## PHP Traits

| Trait | Purpose |
|-------|---------|
| `CloudStorageTrait.php` | Dispatcher — composes all sub-traits |
| `CloudStorageAccountCrudTrait.php` | Account CRUD operations |
| `CloudStorageUploadTrait.php` | Provider-agnostic upload dispatch |
| `CloudStorageFileTrait.php` | File listing and deletion dispatch |
| `CloudStorageGitHubTrait.php` | GitHub Git Data API operations |
| `CloudStorageGitLabTrait.php` | GitLab REST API operations |
| `CloudStorageGoogleDriveTrait.php` | Google Drive v3 API + resumable uploads |
| `CloudStorageOAuthTrait.php` | OAuth2 initiate/callback flow |

## React Components

| Component | Location | Purpose |
|-----------|----------|---------|
| `CloudStorageSettingsPage` | `src/pages/CloudStorage.tsx` | Main dashboard with provider tabs |
| `CloudStorageAccountCard` | `src/components/cloud-storage/` | Account display with masked tokens |
| `CloudStorageAccountDialog` | `src/components/cloud-storage/` | Dynamic form per provider |
| `CloudStorageProviderSettings` | `src/components/cloud-storage/` | Auto-backup, retention, prefix settings |
| `CloudStorageBackupSelector` | `src/components/cloud-storage/` | Publish dialog selector |

## Publish Integration

- `cloudStorageAccountIds` parameter flows through:
  - Publish dialog (`Plugins.tsx`) — via selector state
  - `useQuickPublish` — reads from `localStorage`
  - `useBulkQuickPublish` — reads from `localStorage`
- `cloud_upload` stage in `PublishProgressDialog` (between backup and package)
- localStorage key: `wppp_cloud_storage_accounts`

## API Methods

| Method | Endpoint |
|--------|----------|
| `getCloudStorageAccounts` | `GET /cloud-storage/accounts` |
| `createCloudStorageAccount` | `POST /cloud-storage/accounts` |
| `updateCloudStorageAccount` | `PUT /cloud-storage/accounts/{id}` |
| `deleteCloudStorageAccount` | `DELETE /cloud-storage/accounts/{id}` |
| `testCloudStorageAccount` | `POST /cloud-storage/accounts/{id}/test` |
| `getCloudStorageSettings` | `GET /cloud-storage/settings` |
| `updateCloudStorageSettings` | `PUT /cloud-storage/settings` |
| `getCloudStorageFiles` | `GET /cloud-storage/accounts/{id}/files` |
| `initiateCloudStorageOAuth` | `POST /cloud-storage/oauth/initiate` |

## Pending Backend Work

1. Wire `cloud_upload` stage into `ServicePublishPipeline.go`
2. Emit WebSocket progress events for cloud upload
3. Cloud upload failures should warn, not block publish
4. Skip stage if no accounts selected
