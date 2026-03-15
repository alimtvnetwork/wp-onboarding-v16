# Cloud Storage Integration in Publish Flow

> **Updated:** 2026-03-15  
> **Status:** Frontend complete, backend pipeline pending

---

## Overview

Users can select cloud storage accounts as backup destinations during plugin publish. Backups are automatically uploaded to selected remote providers (GitHub, GitLab, Google Drive) after the backup stage completes.

## Architecture

```
Publish Flow:
  backup → cloud_upload → package → upload → activate → version_check
```

The `cloud_upload` stage runs after backup creation and before packaging. It uploads the backup ZIP to all selected cloud storage accounts.

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

## Backend Implementation (🔲 Pending)

### Required Changes
1. `ServicePublishPipeline.go` — after backup stage, invoke cloud upload for each selected account
2. Emit `publish_progress` WS events with `stage: "cloud_upload"` and per-account progress
3. Cloud upload failures should log warnings but not block publish (same as backup failures)
4. Skip `cloud_upload` stage entirely if no accounts selected (mark as `skipped`)

## Related Files

- `src/types/cloudStorage.ts` — TypeScript types matching Go backend
- `src/hooks/useCloudStorage.ts` — TanStack Query hooks for account CRUD
- `src/components/cloud-storage/` — All UI components
- `src/pages/CloudStorage.tsx` — Dashboard page
- `spec/10-wp-plugin-publish/02-frontend/27-quick-publish.md` — Quick publish spec
