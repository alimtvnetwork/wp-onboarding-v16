# Memory: features/publish/diff-preview
Updated: 2026-02-05

The publish flow includes a **true diff comparison** feature that compares local files with remote WordPress files to accurately identify added, modified, and deleted files before deployment. Users can review the file list, filter by change type, search, and selectively choose which files to deploy.

## Diff Comparison Logic

1. **Local scan**: Backend scans the local plugin directory and calculates MD5 hashes
2. **Remote fetch**: Backend calls the WordPress plugin's `/plugins/{slug}/files` endpoint to get remote file hashes
3. **Comparison**: Files are categorized as:
   - **Added**: Exists locally but not on remote
   - **Modified**: Exists on both but hash differs
   - **Deleted**: Exists on remote but not locally
4. **Fallback**: If remote files can't be fetched (plugin not installed), all files show as "added"

## UI Flow

1. User clicks "Publish" on a plugin card
2. In the publish dialog, each site shows a "Files" preview button
3. Clicking the preview button opens the DiffPreviewDialog
4. Dialog shows: total files, file sizes, grouped by directory with checkboxes
5. User can filter by change type, search, and toggle file selection
6. Selection controls: "All" / "None" buttons + per-file checkboxes
7. "Publish X Files" button shows count and proceeds with deployment

## WordPress Plugin Endpoint

```
GET /wp-json/riseup-asia-uploader/v1/plugins/{slug}/files
```

Returns:
```json
{
  "success": true,
  "plugin": "my-plugin",
  "totalFiles": 42,
  "files": [
    { "path": "includes/class-main.php", "hash": "abc123def456", "size": 4500, "modifiedAt": "2026-02-05T10:30:00Z" }
  ]
}
```

## Backend Preview Endpoint

```
GET /api/v1/plugins/{id}/sites/{siteId}/preview
```

Returns:
```json
{
  "pluginId": 3,
  "pluginName": "My Plugin",
  "siteId": 1,
  "siteName": "Production",
  "siteUrl": "https://example.com",
  "remoteSlug": "my-plugin",
  "totalFiles": 42,
  "totalSize": 156789,
  "added": 10,
  "modified": 5,
  "deleted": 2,
  "files": [
    { "path": "includes/class-main.php", "changeType": "modified", "size": 4500, "localHash": "abc123" }
  ]
}
```

## Components

- `wp-plugins/riseup-asia-uploader/riseup-asia-uploader.php` - `handle_plugin_files()` endpoint
- `backend/internal/wordpress/remote_files.go` - `GetPluginFilesViaRiseup()` method
- `backend/internal/services/publish/service.go` - `PreviewPublish()` with diff logic
- `src/components/plugins/DiffPreviewDialog.tsx` - UI with file selection
- `src/lib/api.ts` - `previewPublish()` and `publishPlugin()` methods

## Selection Features

- Checkbox per file with click-to-toggle
- "Select All" / "Select None" buttons
- "Select all visible" checkbox (respects current filter)
- Selection count and size displayed in header
- Disabled confirm button when no files selected
