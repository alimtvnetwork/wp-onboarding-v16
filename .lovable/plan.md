
# Critical Fix: Seeding, Mapping Persistence & Bidirectional Sync (v1.19.0)

## Problem Analysis

Based on the screenshot and code analysis, multiple interconnected issues are causing mapping failures:

### Issue 1: INSERT OR IGNORE Silent Failures
The `CreateSeedMapping` function in `backend/internal/database/database.go` uses `INSERT OR IGNORE`, which:
- Returns `nil` error even when no row is inserted (constraint violation)
- Causes the code to incorrectly count "created" mappings
- No `RowsAffected()` check to verify actual insertion

### Issue 2: Config Uses siteNames But Logic Ignores It
The `SeedPlugin` struct has `siteNames []string` but `seedSitesAndPlugins()` maps ALL plugins to ALL sites regardless. This is confusing and the user wants explicit site IDs.

### Issue 3: Frontend Bidirectional Sync Broken
- Edit Site dialog saves to `/sites/{id}/mappings` (PUT)
- Plugins page reads from `plugin.mappings` (populated by `List()`)
- Cache invalidation happens but queries may not refetch properly
- Tab state resets when switching between tabs

### Issue 4: Version Comparison May Prevent Seeding
If the database already has `seed_version = 1.18.0` and config is `1.18.0`, seeding is skipped entirely. The `ensureMappingsExist` function runs but may not find sites/plugins if they were created with different paths.

---

## Solution Architecture

### Phase 1: Fix Database Seeding with Proper Validation

**File: `backend/internal/database/database.go`**

Replace `CreateSeedMapping` to return actual insertion status:

```go
// CreateSeedMapping creates a plugin-site mapping for seeding
// Returns (created bool, error) - created is true only if a new row was inserted
func (db *DB) CreateSeedMapping(pluginID, siteID int64, remoteSlug string) (bool, error) {
    result, err := db.Exec(`
        INSERT OR IGNORE INTO PluginMappings (PluginId, SiteId, RemoteSlug, SyncStatus, CreatedAt, UpdatedAt)
        VALUES (?, ?, ?, 'pending', datetime('now'), datetime('now'))
    `, pluginID, siteID, remoteSlug)
    if err != nil {
        return false, err
    }
    rows, _ := result.RowsAffected()
    return rows > 0, nil
}
```

**File: `backend/internal/config/config.go`**

Update callers to use new signature and add detailed logging:

```go
// In seedSitesAndPlugins
created, err := db.CreateSeedMapping(pluginId, siteId, remoteSlug)
if err != nil {
    log.Warn("Failed to create mapping", "pluginId", pluginId, "siteId", siteId, "error", err)
} else if created {
    mappingsCreated++
    log.Info("Created seed mapping", "pluginId", pluginId, "siteId", siteId, "remoteSlug", remoteSlug)
} else {
    log.Debug("Mapping already exists (skipped)", "pluginId", pluginId, "siteId", siteId)
}
```

### Phase 2: Simplify Config with Site IDs (Optional Approach)

**File: `backend/internal/config/config.go`**

Add `siteIds` field to `SeedPlugin` as an alternative to `siteNames`:

```go
type SeedPlugin struct {
    Name        string   `json:"name"`
    Path        string   `json:"path"`
    Category    string   `json:"category"`
    GitEnabled  bool     `json:"gitEnabled"`
    AutoPublish bool     `json:"autoPublish"`
    SiteNames   []string `json:"siteNames"`   // Names of sites to link (legacy)
    SiteIds     []int64  `json:"siteIds"`     // Explicit site IDs (preferred)
    MapToAll    bool     `json:"mapToAll"`    // If true, map to ALL seeded sites
}
```

**File: `backend/config.json`**

Update config to use explicit mappings or `mapToAll`:

```json
"plugins": [
  {
    "name": "Plugins Onboard",
    "path": "D:\\wp-work\\...",
    "mapToAll": true
  }
]
```

### Phase 3: Force Fresh Seeding on Version Bump

**File: `backend/config.json`**

Bump version to `1.19.0` to force re-seeding:

```json
"version": "1.19.0",
```

**File: `backend/internal/config/config.go`**

Add a `forceReseed` option or clear mappings before re-seeding:

```go
// Before creating mappings, optionally clear existing seed mappings
// This ensures fresh state when version bumps
if cfg.Seed.ClearOnReseed {
    db.Exec("DELETE FROM PluginMappings WHERE SyncStatus = 'pending'")
}
```

### Phase 4: Fix Frontend Bidirectional Cache Sync

**File: `src/components/sites/EditSiteDialog.tsx`**

Ensure mappings are refetched after save:

```tsx
// After successful save
queryClient.invalidateQueries({ queryKey: ["sites"] });
queryClient.invalidateQueries({ queryKey: ["plugins"] });
queryClient.invalidateQueries({ queryKey: ["sites", site.id, "mappings"] });

// Force refetch plugins to get fresh mappings
await queryClient.refetchQueries({ queryKey: ["plugins"] });
```

**File: `src/pages/Plugins.tsx`**

When opening mapping dialog, fetch fresh data:

```tsx
const openMappingDialog = async (plugin: Plugin) => {
    // Fetch fresh mappings from API instead of using stale plugin.mappings
    try {
        const response = await api.getPluginMappings(plugin.id);
        if (response.success && response.data) {
            setSelectedSites(response.data.map((m) => m.siteId));
            setRemoteSlug(response.data[0]?.remoteSlug || plugin.name.toLowerCase().replace(/\s+/g, '-'));
        } else {
            setSelectedSites([]);
            setRemoteSlug(plugin.name.toLowerCase().replace(/\s+/g, '-'));
        }
    } catch {
        setSelectedSites(plugin.mappings?.map((m) => m.siteId) || []);
    }
    setSelectedPlugin(plugin);
    setShowMappingDialog(true);
};
```

### Phase 5: Add Colorful Success Toast

**File: `src/components/sites/EditSiteDialog.tsx`**

Use styled success toast:

```tsx
toast.success("Site updated successfully!", {
    description: `${selectedPlugins.length} plugin(s) linked`,
    icon: "✅",
    style: {
        background: "linear-gradient(to right, #22c55e, #16a34a)",
        color: "white",
        border: "none",
    },
});
```

**File: `src/pages/Plugins.tsx`**

Same for plugin mapping save:

```tsx
toast.success("Site mappings saved!", {
    description: `${selectedSites.length} site(s) linked to ${selectedPlugin.name}`,
    icon: "🔗",
    style: {
        background: "linear-gradient(to right, #3b82f6, #2563eb)",
        color: "white",
        border: "none",
    },
});
```

### Phase 6: Comprehensive Backend Logging

**File: `backend/internal/config/config.go`**

Add granular logging to trace every step:

```go
func seedSitesAndPlugins(db *database.DB, cfg *Config, log *logger.Logger) error {
    log.Info("=== SEEDING START ===", "sites", len(cfg.Seed.Sites), "plugins", len(cfg.Seed.Plugins))
    
    // Log each site being processed
    for i, site := range cfg.Seed.Sites {
        normalizedUrl := normalizeUrl(site.URL)
        log.Info("Processing site", "index", i+1, "name", site.Name, "rawUrl", site.URL, "normalizedUrl", normalizedUrl)
        
        existingId, err := db.GetSiteIdByUrl(normalizedUrl)
        if err != nil && err != sql.ErrNoRows {
            log.Error("DB error checking site", "error", err)
        }
        
        if existingId > 0 {
            log.Info("Site exists in DB", "id", existingId, "name", site.Name)
            allSiteIds = append(allSiteIds, existingId)
        } else {
            // Create site...
            log.Info("Creating new site", "name", site.Name)
        }
    }
    
    log.Info("Site processing complete", "siteIds", allSiteIds)
    
    // Log each plugin being processed
    for i, plugin := range cfg.Seed.Plugins {
        log.Info("Processing plugin", "index", i+1, "name", plugin.Name, "path", plugin.Path)
        
        existingId, err := db.GetPluginIdByPath(plugin.Path)
        if err != nil && err != sql.ErrNoRows {
            log.Error("DB error checking plugin", "error", err)
        }
        
        if existingId > 0 {
            log.Info("Plugin exists in DB", "id", existingId, "name", plugin.Name)
        } else {
            // Create plugin...
            log.Info("Creating new plugin", "name", plugin.Name)
        }
        
        // Log mapping creation attempts
        for _, siteId := range allSiteIds {
            log.Debug("Attempting mapping", "pluginId", pluginId, "siteId", siteId)
            created, err := db.CreateSeedMapping(pluginId, siteId, remoteSlug)
            if err != nil {
                log.Error("Mapping INSERT failed", "pluginId", pluginId, "siteId", siteId, "error", err)
            } else if created {
                log.Info("Mapping CREATED", "pluginId", pluginId, "siteId", siteId)
            } else {
                log.Debug("Mapping EXISTS (skipped)", "pluginId", pluginId, "siteId", siteId)
            }
        }
    }
    
    log.Info("=== SEEDING COMPLETE ===")
    return nil
}
```

---

## Files to Modify

| File | Changes |
|------|---------|
| `backend/internal/database/database.go` | Update `CreateSeedMapping` to return `(bool, error)` with `RowsAffected()` check |
| `backend/internal/config/config.go` | Update callers, add granular logging, support `mapToAll` option |
| `backend/config.json` | Bump to v1.19.0, add `mapToAll: true` to plugins |
| `src/components/sites/EditSiteDialog.tsx` | Fix cache sync, add colorful success toast |
| `src/pages/Plugins.tsx` | Fetch fresh mappings on dialog open, add colorful success toast |
| `public/version.json` | Add v1.19.0 changelog |
| `.lovable/memory/features/seeding/automatic-mappings.md` | Update documentation |

---

## Implementation Order

1. Fix `CreateSeedMapping` to return actual insertion status
2. Update `config.go` callers with proper error handling
3. Add comprehensive seeding logs
4. Bump version to 1.19.0 to force re-seed
5. Fix frontend cache invalidation and refetch
6. Add colorful success toasts
7. Test end-to-end: delete database, restart backend, verify mappings appear

---

## Expected Log Output After Fix

```
[v1.19.0 - 2026-02-04 06:30:00 PM] === SEEDING START === sites=1 plugins=3 (INFO config.go:268)
[v1.19.0 - 2026-02-04 06:30:00 PM] Processing site index=1 name=Atto Property Demo rawUrl=https://demoat.attoproperty.com.au normalizedUrl=https://demoat.attoproperty.com.au (INFO config.go:272)
[v1.19.0 - 2026-02-04 06:30:00 PM] Creating new site name=Atto Property Demo (INFO config.go:285)
[v1.19.0 - 2026-02-04 06:30:00 PM] Site created id=1 name=Atto Property Demo (INFO config.go:295)
[v1.19.0 - 2026-02-04 06:30:00 PM] Site processing complete siteIds=[1] (INFO config.go:300)
[v1.19.0 - 2026-02-04 06:30:00 PM] Processing plugin index=1 name=Plugins Onboard path=D:\wp-work\... (INFO config.go:305)
[v1.19.0 - 2026-02-04 06:30:00 PM] Creating new plugin name=Plugins Onboard (INFO config.go:315)
[v1.19.0 - 2026-02-04 06:30:00 PM] Plugin created id=1 name=Plugins Onboard (INFO config.go:325)
[v1.19.0 - 2026-02-04 06:30:00 PM] Mapping CREATED pluginId=1 siteId=1 (INFO config.go:340)
[v1.19.0 - 2026-02-04 06:30:00 PM] Processing plugin index=2 name=Category Generator path=D:\wp-work\... (INFO config.go:305)
[v1.19.0 - 2026-02-04 06:30:00 PM] Creating new plugin name=Category Generator (INFO config.go:315)
[v1.19.0 - 2026-02-04 06:30:00 PM] Plugin created id=2 name=Category Generator (INFO config.go:325)
[v1.19.0 - 2026-02-04 06:30:00 PM] Mapping CREATED pluginId=2 siteId=1 (INFO config.go:340)
[v1.19.0 - 2026-02-04 06:30:00 PM] Processing plugin index=3 name=Link Manager path=D:\wp-work\... (INFO config.go:305)
[v1.19.0 - 2026-02-04 06:30:00 PM] Creating new plugin name=Link Manager (INFO config.go:315)
[v1.19.0 - 2026-02-04 06:30:00 PM] Plugin created id=3 name=Link Manager (INFO config.go:325)
[v1.19.0 - 2026-02-04 06:30:00 PM] Mapping CREATED pluginId=3 siteId=1 (INFO config.go:340)
[v1.19.0 - 2026-02-04 06:30:00 PM] === SEEDING COMPLETE === (INFO config.go:350)
```

---

## Testing Checklist

1. **Delete `data/app.db`** and restart backend
2. **Verify logs** show all 3 mappings created with `Mapping CREATED` messages
3. **Check database** - PluginMappings table should have 3 rows
4. **Open Plugins page** - each plugin card should show site badge
5. **Edit Site → Plugins tab** - verify all 3 plugins are checked
6. **Edit Plugin → Sites** - verify site is selected
7. **Toggle a plugin off from Site, save** - verify change persists on refresh
8. **Toggle site on from Plugin, save** - verify change appears in Edit Site dialog

Additional Strict Instructions, must follow and override:

Okay. So if there is a error found, make sure the stack trace is there. And for the database affected rows or anything, try to have a global... I mean, a root level function that is shared across everywhere. And that function can also check the database, what, what is expected and what kind of change it should do, and what are the affected rows. If not, then it will create a stack trace of from where it is calling and how it is calling. Also, the table name should be there in the log so that it can be understood very well. And also, one more thing that is very cru- critical, as I can see, that you are using, uh, raw SQL. I mean, raw SQL should be avoided 99% of the time. Why you are doing this? I have in the specification that you should use ORM everywhere. So what is the reason behind using the raw SQL? It doesn't make any sense. Uh, there is a very good way that you could avoid the raw SQL and use the ORM to make the relationship. Try to find the record first and then insert, uh, inside its relationship data so that model... Work with the model, okay? That should be the priority. Update the memory regarding this and plan ahead. Okay? Do you understand? Do you have any question, confusion? If yes, let me know.

Make sure the saving and retrival is okay for the screen for the data, use util functions to avoid redundant codes always 