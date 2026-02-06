# Memory: features/publish/version-comparison
Updated: 2026-02-06

## Overview

The publish diff preview includes a version comparison banner that displays the remote site's version alongside the local version of the plugin (e.g., Remote Version -> Local Version). It provides visual indicators such as 'Upgrade' or 'New Install' badges to help users understand the deployment's impact at a glance.

## Implementation

### Publish Dialog - Inline Version Badges

The publish dialog now shows version info directly in the site list using the `SiteVersionBadge` component:

**Location:** `src/pages/Plugins.tsx` (Publish Dialog section)

Each mapped site displays:
- Remote version badge (or "new" if not installed)
- Arrow indicator
- Local version badge
- Status badge: "Install", "Upgrade", or "Downgrade"

**Component:** `src/components/publish/SiteVersionBadge.tsx`
- Fetches version info from `/plugins/{id}/sites/{siteId}/preview`
- Shows loading skeleton while fetching
- Handles errors gracefully

### DiffPreviewDialog - Full Version Banner

The DiffPreviewDialog shows a centered version comparison banner:

**Location:** `src/components/plugins/DiffPreviewDialog.tsx` (lines 262-295)

Banner includes:
- Tag icon with "Version:" label
- Remote version badge (or "Not installed")
- Arrow icon pointing right
- Local version badge (highlighted in primary color)
- Status badge: "Upgrade" or "New Install"

## Version Comparison Logic

```typescript
const isNewInstall = !remoteVersion;
const isUpgrade = !isNewInstall && localVersion > remoteVersion;
const isDowngrade = !isNewInstall && localVersion < remoteVersion;
```

## API Support

The backend provides version info in the preview response:

```typescript
interface PublishPreview {
  pluginId: number;
  pluginName: string;
  localVersion: string;
  remoteVersion: string;
  siteId: number;
  siteName: string;
  siteUrl: string;
  remoteSlug: string;
  totalFiles: number;
  totalSize: number;
  added: number;
  modified: number;
  deleted: number;
  files: FilePreview[];
}
```

## Related Files

- `src/components/publish/SiteVersionBadge.tsx` - Inline version display
- `src/components/plugins/DiffPreviewDialog.tsx` - Full preview with version banner
- `src/pages/Plugins.tsx` - Publish dialog integration
- `backend/internal/services/publish/service.go` - Backend version fetching
