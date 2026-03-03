# Memory: features/publish/diff-preview
Updated: 2026-03-03

The publish flow includes a **true diff comparison** feature that compares local files with remote WordPress files via the sync-manifest endpoint to accurately identify added, modified, deleted, and unchanged files before deployment. Users can review the file list, filter by change type, search, and selectively choose which files to deploy.

## Diff Comparison Logic

1. **Local scan**: Backend scans the local plugin directory and calculates MD5 hashes
2. **Remote fetch**: Backend calls the sync-manifest endpoint first (cached on both PHP and Go sides), falls back to files endpoint
3. **Manifest caching**: Go-side in-memory TTL cache (default 5 min) per plugin+site avoids redundant API calls
4. **Comparison**: Files are categorized as:
   - **Added**: Exists locally but not on remote
   - **Modified**: Exists on both but hash differs
   - **Deleted**: Exists on remote but not locally
   - **Unchanged**: Exists on both with matching hash
5. **Fallback**: If remote files can't be fetched (plugin not installed), all files show as "added"

## Endpoints

### Preview (cached manifest)
```
GET /api/v1/plugins/{id}/sites/{siteId}/preview
```
Uses cached manifest. Returns `PublishPreviewResult` with unchanged count.

### Compute Diff (fresh comparison)
```
GET /api/v1/plugins/{id}/sites/{siteId}/diff
```
Invalidates cache, fetches fresh manifest. Returns `DiffResult`.

### File Content Diff
```
POST /api/v1/plugins/{id}/sites/{siteId}/file-diff
```
Returns local and remote file content for side-by-side comparison.

## WordPress Plugin Endpoint

```
POST /wp-json/riseup-asia-uploader/v1/sync-manifest
Body: {"plugin": "my-plugin"}
```

Returns cached file manifest with MD5 hashes.

## UI Flow

1. User clicks "Publish" on a plugin card
2. In the publish dialog, each site shows a "Files" preview button
3. Clicking opens the DiffPreviewDialog with "Changed" tab as default
4. Dialog shows: total files, file sizes, grouped by directory with checkboxes
5. Tabs: All | Changed | Added | Modified | Unchanged
6. Selection defaults to changed files only (unchanged excluded)
7. User can filter by change type, search, and toggle file selection
8. "Publish X Files" button shows count and proceeds with deployment

## Components

- `backend/internal/services/publish/ManifestCache.go` - In-memory TTL cache for remote manifests
- `backend/internal/services/publish/ServiceDiff.go` - Standalone `ComputeDiff()` method
- `backend/internal/services/publish/ServicePreviewDiff.go` - File scanning and diff logic with unchanged tracking
- `backend/internal/services/publish/ServicePreview.go` - Preview with cached manifest fetching
- `backend/internal/api/handlers/PublishBackupHandlers.go` - `ComputeDiff` handler
- `wp-plugins/riseup-asia-uploader/includes/Traits/Sync/SyncManifestTrait.php` - PHP sync manifest endpoint
- `wp-plugins/riseup-asia-uploader/includes/Database/FileCache.php` - PHP-side file hash cache
- `src/components/plugins/DiffPreviewDialog.tsx` - UI with file selection and unchanged tab
- `src/components/plugins/ContentDiffViewer.tsx` - Content diff viewer for modified files
- `src/lib/api/types.ts` - `FilePreview`, `PublishPreview`, `DiffResult` types
- `src/lib/api/methods.ts` - `previewPublish()`, `computeDiff()`, `getFileDiff()` methods

## Selection Features

- Checkbox per file with click-to-toggle
- "Select All" / "Select None" buttons (Select All excludes unchanged)
- "Select all visible" checkbox (respects current filter)
- Selection count and size displayed in header
- Disabled confirm button when no files selected
- Default: only changed files selected

## Content Diff Viewer

For modified files, users can click the "eye" icon to open a diff viewer that:
- Fetches both local and remote file content
- Displays a side-by-side unified diff with line numbers
- Highlights added lines (green) and removed lines (red)
- Provides tabs to view "Diff", "Local", or "Remote" content separately
- Includes copy button for each view
