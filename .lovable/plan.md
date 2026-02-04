
# Bug Fix Plan: Mapping Persistence & Publish Flow

## Summary

The user has identified three interconnected bugs affecting the plugin-site mapping system and publish workflow:

1. **Plugin-Site Mappings Not Persisting** - When selecting sites for a plugin (from Plugins page) or plugins for a site (from Edit Site dialog), selections don't persist after save/refresh
2. **Seeding Config Missing Automatic Mappings** - Seeded plugins should automatically map to seeded sites during database initialization
3. **Publish Button Not Working** - The publish flow has issues with WebSocket payload handling and progress display

---

## Root Cause Analysis

### Issue 1: Mappings Not Persisting

**Frontend Side (Plugins Page - `src/pages/Plugins.tsx`):**
- When `openMappingDialog` is called, it correctly reads existing mappings from `plugin.mappings`
- When `handleSaveMappings` is called, it sends the correct API request
- **Problem**: The `plugin.mappings` array is populated by the backend when listing plugins, but we need to verify the backend is correctly returning mappings with the plugin list

**Backend Side (`backend/internal/services/plugin/service.go`):**
- The `List` function fetches plugins but may not be joining with `PluginMappings` table
- Need to verify that `GetMappings` is called for each plugin or that mappings are fetched in a single query

**Frontend Side (Edit Site Dialog - `src/components/sites/EditSiteDialog.tsx`):**
- The component correctly fetches current mappings via `api.getSiteMappings`
- It correctly calls `api.updateSiteMappings` on save
- **Verified**: The backend handler (`UpdateSiteMappings`) correctly handles float64→int64 conversion

### Issue 2: Seeding Config Not Creating Mappings

**Backend Config (`backend/internal/config/config.go`):**
- The `seedSitesAndPlugins` function (lines 243-309) does create mappings for all sites
- **However**: The seeding logic only runs when `cfg.Version > currentVersion`
- **Problem**: If the database already has a seed version, new mappings won't be created even if site/plugin combinations are missing

### Issue 3: Publish Button Not Working

**Frontend (`src/components/plugins/PublishProgressDialog.tsx`):**
- The component listens for WebSocket events correctly
- **Problem**: The stage name mapping logic may not match what the backend sends

**Backend (`backend/internal/services/publish/service.go`):**
- `broadcastProgress` sends events with both `stage` and `step` fields
- The frontend expects specific stage names (`backup`, `package`, `upload`, `activate`)

---

## Technical Implementation Plan

### Phase 1: Fix Plugin List to Include Mappings

**File: `backend/internal/services/plugin/service.go`**

The `List` function needs to fetch mappings for each plugin. Currently, it may return plugins without their mappings populated.

```text
Changes:
1. After fetching all plugins, call GetMappings for each
2. OR use a single JOIN query to fetch all mappings in one go
3. Populate plugin.Mappings field before returning
```

### Phase 2: Fix Mapping Persistence in Plugins Page

**File: `src/pages/Plugins.tsx`**

The `openMappingDialog` function reads from `plugin.mappings` which comes from the API response. If the backend correctly returns mappings, this should work. Need to verify query invalidation triggers a refetch.

```text
Verify:
1. After handleSaveMappings succeeds, queryClient.invalidateQueries triggers
2. The plugins query refetches with updated mappings
3. The plugin list re-renders with new mapping badges
```

### Phase 3: Fix Seed Mapping Logic

**File: `backend/internal/config/config.go`**

The current seeding creates all-to-all mappings but may be skipped if seed version hasn't changed.

```text
Changes:
1. In seedSitesAndPlugins, check if mapping already exists before creating
2. Update version to trigger re-seeding OR
3. Add a separate "ensure mappings" function that runs on every startup
```

### Phase 4: Fix Publish Progress WebSocket Events

**File: `backend/internal/services/publish/service.go`**

The `broadcastProgress` function needs to ensure stage names match frontend expectations.

```text
Verify/Fix:
1. Ensure stage names are exactly: backup, package, upload, activate, cleanup
2. Ensure status values are: running, success, error (not completed/failed)
3. Include filesUpdated in the complete event
```

**File: `src/components/plugins/PublishProgressDialog.tsx`**

```text
Verify/Fix:
1. Status mapping handles all backend statuses correctly
2. Stage name matching is case-sensitive and exact
```

---

## Files to Modify

| File | Changes |
|------|---------|
| `backend/internal/services/plugin/service.go` | Ensure List() returns plugins with mappings populated |
| `backend/internal/config/config.go` | Add mapping existence check in seeding, bump version |
| `backend/internal/services/publish/service.go` | Verify/fix WebSocket event payload format |
| `src/components/plugins/PublishProgressDialog.tsx` | Ensure robust status mapping |
| `public/version.json` | Bump to v1.16.0, add changelog entry |
| `backend/config.json` | Bump version to trigger re-seed |

---

## Detailed Changes

### 1. Plugin Service - Populate Mappings in List

**File: `backend/internal/services/plugin/service.go`**

Add mapping population to the `List` method:

```go
func (s *Service) List(ctx context.Context) ([]models.Plugin, error) {
    // ... existing query to get plugins ...
    
    // Populate mappings for each plugin
    for i := range plugins {
        mappings, err := s.GetMappings(ctx, plugins[i].ID)
        if err == nil {
            plugins[i].Mappings = mappings
        }
    }
    
    return plugins, nil
}
```

### 2. Config - Ensure Mappings on Startup

**File: `backend/internal/config/config.go`**

Modify `seedSitesAndPlugins` to check for existing mappings:

```go
// Before creating mapping, check if it exists
func (db *DB) MappingExists(pluginId, siteId int64) bool {
    var exists int
    err := db.QueryRow("SELECT 1 FROM PluginMappings WHERE PluginId = ? AND SiteId = ?", pluginId, siteId).Scan(&exists)
    return err == nil
}

// In seedSitesAndPlugins:
for _, siteId := range allSiteIds {
    if !db.MappingExists(pluginId, siteId) {
        _ = db.CreateSeedMapping(pluginId, siteId, remoteSlug)
    }
}
```

Also bump `config.json` version to trigger re-seeding.

### 3. Publish Service - Fix WebSocket Payload

**File: `backend/internal/services/publish/service.go`**

The `broadcastProgress` function needs minor fixes:

```go
func (s *Service) broadcastProgress(pluginID, siteID int64, step string, progress int, message string) {
    // ... existing code ...
    
    // Ensure consistent status values
    status := "running"
    if step == "completed" {
        status = "success"  // Frontend expects "success" not "completed"
    } else if step == "failed" {
        status = "error"    // Frontend expects "error" not "failed"
    }
    
    s.wsHub.Broadcast(eventType, map[string]interface{}{
        "pluginId": pluginID,
        "siteId":   siteID,
        "stage":    stage,
        "status":   status,
        "progress": progress,
        "total":    100,
        "message":  message,
    })
}
```

### 4. PublishProgressDialog - Robust Status Handling

**File: `src/components/plugins/PublishProgressDialog.tsx`**

The component already handles status mapping at lines 123-125, but should be more robust:

```typescript
// Add more backend status variations
let mappedStatus: PublishStage["status"] = "running";
const normalizedStatus = (payload.status || "").toLowerCase();
if (["success", "completed", "done"].includes(normalizedStatus)) {
    mappedStatus = "success";
} else if (["error", "failed", "failure"].includes(normalizedStatus)) {
    mappedStatus = "error";
}
```

### 5. Version Bump

**File: `public/version.json`**

```json
{
  "version": "1.16.0",
  "changelog": [
    {
      "version": "1.16.0",
      "date": "2026-02-04",
      "title": "Mapping Persistence & Publish Fixes",
      "changes": [
        "🔧 Fixed plugin mappings not persisting after save in both Plugins and Sites views",
        "🌱 Seeding now ensures all plugins are mapped to all sites on every startup",
        "📡 Fixed publish progress WebSocket events to correctly update UI stages",
        "✅ Plugin list now correctly includes mappings for display"
      ]
    }
  ]
}
```

**File: `backend/config.json`**

Bump version string to trigger re-seeding:
```json
{
  "version": "1.16.0",
  ...
}
```

---

## Testing Checklist

After implementation, verify:

1. **Plugin Mappings (Plugins Page)**
   - Open Plugins page
   - Click "Sites" on a plugin
   - Select multiple sites
   - Click "Save Mappings"
   - Refresh page
   - Verify selected sites still appear as badges on plugin card

2. **Site Mappings (Edit Site Dialog)**
   - Open Sites page
   - Click Edit on a site
   - Go to Plugins tab
   - Select multiple plugins
   - Click Save
   - Refresh page
   - Re-open Edit dialog
   - Verify plugins are still selected

3. **Seeded Mappings**
   - Delete database file
   - Restart backend
   - Verify all seeded plugins show all seeded sites as badges

4. **Publish Flow**
   - Select a plugin with sites mapped
   - Click Publish
   - Select a site
   - Verify progress dialog shows stages updating
   - Verify completion message appears

---

## Summary

This fix addresses three interconnected issues in the mapping and publish system:

1. **Backend Plugin List** - Add mapping population to ensure frontend receives complete data
2. **Seed Logic** - Add idempotent mapping creation that runs on every startup
3. **WebSocket Payloads** - Normalize status values between backend and frontend
4. **Version Bump** - Trigger re-seeding and document changes
