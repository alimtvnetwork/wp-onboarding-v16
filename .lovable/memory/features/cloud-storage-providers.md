# Cloud Storage Providers

> **Updated:** 2026-03-15  
> **Status:** Frontend complete, backup engine + scheduling + restore implemented

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
| `CloudStorageBranchTrait.php` | Git branch create/delete/list for GitHub + GitLab |
| `CloudStorageHistoryTrait.php` | Backup history CRUD (CloudStorageBackupHistory table) |
| `CloudStorageScheduleTrait.php` | WP-Cron registration + full/incremental backup handlers |
| `CloudStorageRestoreTrait.php` | Git-first shallow clone restore with API fallback |

## Enums (Phase 5)

| Enum | Purpose |
|------|---------|
| `BackupStrategyType.php` | FullOnly / FullAndIncremental |
| `BackupScheduleType.php` | Hourly / Daily / Weekly / Biweekly / Monthly / Manual |
| `CloudStorageBackupType.php` | Full / Incremental |
| `CloudStorageBackupStatusType.php` | Pending / Uploading / Success / Failed |

## Database Migrations

| Migration | Tables/Columns |
|-----------|---------------|
| v18 | CloudStorageSettings table (base) |
| v19 | Accounts: RepoSelectionMode, DefaultBranch; Settings: BackupType, schedule columns |
| v20 | CloudStorageBackupHistory table (full/incremental tracking with branch + commit SHA) |

## React Components

| Component | Location | Purpose |
|-----------|----------|---------|
| `CloudStorageSettingsPage` | `src/pages/CloudStorage.tsx` | Main dashboard with provider tabs |
| `CloudStorageAccountCard` | `src/components/cloud-storage/` | Account display with masked tokens |
| `CloudStorageAccountDialog` | `src/components/cloud-storage/` | Dynamic form per provider |
| `CloudStorageProviderSettings` | `src/components/cloud-storage/` | Auto-backup, retention, prefix settings |
| `CloudStorageBackupSelector` | `src/components/cloud-storage/` | Publish dialog selector |
| `CloudStorageRepoSelector` | `src/components/cloud-storage/` | Create new / select existing repo |
| `CloudStorageBackupTimeline` | `src/components/cloud-storage/` | Weekly grouped backup timeline |
| `CloudStorageScheduleSettings` | `src/components/cloud-storage/` | Strategy + schedule configuration |

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
| `getCloudStorageRepos` | `GET /cloud-storage/repos` |
| `getCloudStorageBranches` | `GET /cloud-storage/branches` |
| `getCloudStorageBackupHistory` | `GET /cloud-storage/backup-history` |
| `getCloudStorageBackupHistoryRecord` | `GET /cloud-storage/backup-history/{id}` |
| `deleteCloudStorageBackupHistoryRecord` | `DELETE /cloud-storage/backup-history/{id}` |
| `restoreCloudStorageBackup` | `POST /cloud-storage/restore` |

## Git Backup Strategy (Confirmed Decisions)

| Decision | Choice |
|----------|--------|
| Incremental detection | Timestamp-based (`post_modified_gmt`, `filemtime`) |
| Cron reliability | WP-Cron default + real cron documentation |
| Restore method | Git-first (shallow clone), API fallback |
| Branch cleanup | Auto-delete with full backup rotation |

## Pending Backend Work

1. Wire `cloud_upload` stage into `ServicePublishPipeline.go`
2. Emit WebSocket progress events for cloud upload
3. Cloud upload failures should warn, not block publish
4. Skip stage if no accounts selected
5. Implement `createFullBackupZip()` and `createIncrementalBackupZip()` stubs
6. Implement `dispatchCloudUpload()` stub with branch-aware commit
7. Implement `restoreFromZip()` stub in CloudStorageRestoreTrait
8. Implement `gitlabApiRequestRaw()` for raw file downloads
