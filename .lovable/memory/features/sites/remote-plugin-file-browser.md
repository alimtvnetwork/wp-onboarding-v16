# Memory: features/sites/remote-plugin-file-browser
Updated: 2026-02-06

## Overview

Phase 10 implements a remote plugin file browser that allows users to browse and view files from plugins installed on remote WordPress sites via the Riseup Asia Uploader API.

## Backend Implementation

### Service Methods (site/service.go)

```go
GetRemotePluginFiles(ctx, siteID, pluginSlug) (*RemotePluginFilesResult, error)
GetRemotePluginFileContent(ctx, siteID, pluginSlug, filePath) (string, error)
```

### API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/sites/{id}/remote-plugins/{plugin:.+}/files` | List all files in plugin |
| POST | `/sites/{id}/remote-plugins/{plugin:.+}/file` | Get file content (body: `{path}`) |

### Types

```go
type RemotePluginFile struct {
    Path       string    `json:"path"`       // external key
    Hash       string    `json:"hash"`       // external key
    Size       int64     `json:"size"`       // external key
    ModifiedAt time.Time `json:"modifiedAt,omitempty"` // external key
}

type RemotePluginFilesResult struct {
    PluginSlug string             `json:"pluginSlug"`  // external key
    TotalFiles int                `json:"totalFiles"`  // external key
    Files      []RemotePluginFile `json:"files"`       // external key
}
```

## Frontend Implementation

### Component: RemotePluginFileBrowser

Located at `src/components/sites/RemotePluginFileBrowser.tsx`

Features:
- Tree view with folder expansion/collapse
- File search with highlighting
- File content viewer with syntax-aware icons
- Copy and download functionality
- MD5 hash display for files
- Expand All / Collapse All buttons

### API Client (src/lib/api.ts)

```typescript
getRemotePluginFiles(siteId, pluginSlug)
getRemotePluginFileContent(siteId, pluginSlug, filePath)
```

## Magic String Cleanup

Also fixed hardcoded WordPress Core API endpoints in `backend/internal/wordpress/client.go`:
- Added constants: `WPCoreUsersMe`, `WPCorePlugins`, `WPCorePluginBySlug`, `WPCorePosts`, `WPCorePostByID`
- Added constants: `PluginStatusActive`, `PluginStatusInactive`

## Related Files

- `backend/internal/services/site/service.go` - Service methods
- `backend/internal/api/handlers/handlers.go` - HTTP handlers
- `backend/internal/api/router.go` - Route registration
- `backend/internal/wordpress/constants.go` - New WP Core constants
- `backend/internal/wordpress/client.go` - Updated to use constants
- `src/components/sites/RemotePluginFileBrowser.tsx` - UI component
- `src/lib/api.ts` - Frontend API client
