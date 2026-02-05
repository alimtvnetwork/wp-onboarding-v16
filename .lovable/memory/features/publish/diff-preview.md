# Memory: features/publish/diff-preview
Updated: 2026-02-05

The publish flow includes a "Diff Preview" feature that displays all files that will be deployed before initiating the publish operation. Users can review the file list, filter by change type (added/modified/deleted), and search for specific files before confirming the deployment.

## UI Flow

1. User clicks "Publish" on a plugin card
2. In the publish dialog, each site shows a "Files" preview button
3. Clicking the preview button opens the DiffPreviewDialog
4. Dialog shows: total files, file sizes, grouped by directory
5. User can filter by change type or search for files
6. "Confirm Publish" proceeds with the deployment

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

## Components

- `src/components/plugins/DiffPreviewDialog.tsx` - Main preview dialog
- `src/lib/api.ts` - `previewPublish()` method
- `backend/internal/services/publish/service.go` - `PreviewPublish()` method

## Future Enhancements

- True diff comparison with remote files via WordPress API
- Show actual content diff for modified files
- Selective file publishing based on preview
