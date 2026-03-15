# Cloud Storage Integration in Publish Flow

> **Updated:** 2026-03-15  
> **Status:** ✅ Complete (frontend + backend)

---

## Overview

Users can select cloud storage accounts as backup destinations during plugin publish. Backups are automatically uploaded to selected remote providers (GitHub, GitLab, Google Drive) after the backup stage completes.

## Architecture

```
Publish Flow:
  backup → remote_backup → cloud_upload → package → upload → activate → version_check
```

The `cloud_upload` stage runs after remote backup and before packaging. It uploads the backup to all selected cloud storage accounts via the WordPress `POST /cloud-storage/upload` endpoint.

## Frontend Implementation (✅ Complete)

### CloudStorageBackupSelector
- **Location:** `src/components/cloud-storage/CloudStorageBackupSelector.tsx`
- Collapsible UI listing active cloud storage accounts with checkboxes
- Persists selections to `localStorage` key `wppp_cloud_storage_accounts`
- Integrated into `src/pages/Plugins.tsx` publish dialog

### Publish Hooks
- `useQuickPublish` — reads `cloudStorageAccountIds` from localStorage, passes to `api.publishPlugin()`
- `useBulkQuickPublish` — reads `cloudStorageAccountIds` from localStorage, passes to `api.bulkPublish()`
- Both hooks read at call-time (not stale closures)

### PublishProgressDialog
- `cloud_upload` stage added between `backup` and `package` stages
- Label: "Uploading to Cloud Storage"
- Backend drives status via existing WebSocket progress events

### API
- `publishPlugin()` accepts `cloudStorageAccountIds?: number[]`
- `bulkPublish()` accepts `cloudStorageAccountIds?: number[]`

## Backend Implementation (✅ Complete)

### Changes Made
1. **`publishsteptype`** — Added `CloudUpload` variant with value `cloud_upload`
2. **`operationtype`** — Added `CloudStorageUpload` variant
3. **`PublishOptions`** — Added `CloudStorageAccountIds []int` field
4. **`ClientCloudStorage.go`** — WordPress client method `UploadToCloudStorage()` calling `POST /cloud-storage/upload`
5. **`ServicePublishCloudUpload.go`** — Cloud upload stage with per-account iteration, WS progress, and non-blocking failure handling
6. **`ServicePublishPipeline.go`** — Wired `runCloudUploadStage()` between remote backup and upload stages
7. **`DetailTypes.go`** — Added `CloudUploadInitDetails`, `CloudUploadAccountResultDetails`, `CloudUploadSummaryDetails`
8. **API handlers** — Both `PublishInput` and `BulkPublishInput` accept `cloudStorageAccountIds`

### Behavior
- If no accounts selected → stage is skipped with info log
- Per-account upload broadcasts progress events with `stage: "cloud_upload"`
- Individual account failures log warnings but do NOT block the publish pipeline
- Summary log emitted after all accounts processed

## Related Files

- `src/types/cloudStorage.ts` — TypeScript types matching Go backend
- `src/hooks/useCloudStorage.ts` — TanStack Query hooks for account CRUD
- `src/components/cloud-storage/` — All UI components
- `src/pages/CloudStorage.tsx` — Dashboard page
- `backend/internal/wordpress/ClientCloudStorage.go` — WP client cloud upload method
- `backend/internal/services/publish/ServicePublishCloudUpload.go` — Cloud upload stage logic
