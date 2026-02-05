# Memory: features/publish/diff-preview
Updated: 2026-02-05

The publish flow includes a "Diff Preview" feature that displays all files that will be deployed before initiating the publish operation. Users can review the file list, filter by change type (added/modified/deleted), search for specific files, and **selectively choose which files to deploy** before confirming.

## UI Flow

1. User clicks "Publish" on a plugin card
2. In the publish dialog, each site shows a "Files" preview button
3. Clicking the preview button opens the DiffPreviewDialog
4. Dialog shows: total files, file sizes, grouped by directory with checkboxes
5. User can filter by change type, search, and toggle file selection
6. Selection controls: "All" / "None" buttons + per-file checkboxes
7. "Publish X Files" button shows count and proceeds with deployment

## Selective Publishing

When users select specific files:
- The `onConfirm` callback receives an array of selected file paths
- If all files are selected, `undefined` is passed (full publish)
- Backend receives `mode: "selected"` with `files: [...]` array
- Only selected files are included in the ZIP and deployed

## API Endpoint

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
  "added": 42,
  "modified": 0,
  "deleted": 0,
  "files": [
    { "path": "includes/class-main.php", "changeType": "added", "size": 4500, "localHash": "abc123" }
  ]
}
```

## Publish Endpoint (with selective files)

```
POST /api/v1/plugins/{id}/sites/{siteId}/publish
{
  "mode": "selected",
  "files": ["includes/class-main.php", "assets/style.css"],
  "createBackup": true
}
```

## Components

- `src/components/plugins/DiffPreviewDialog.tsx` - Main preview dialog with file selection
- `src/lib/api.ts` - `previewPublish()` and `publishPlugin()` methods
- `backend/internal/services/publish/service.go` - `PreviewPublish()` and `createSelectiveZip()` methods

## Selection Features

- Checkbox per file with click-to-toggle
- "Select All" / "Select None" buttons
- "Select all visible" checkbox (respects current filter)
- Selection count and size displayed in header
- Disabled confirm button when no files selected
