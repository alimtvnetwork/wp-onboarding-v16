
# Backend Migration & Seeding Logging Fix (v1.18.0)

## Problem Summary

1. **No logging in migration/seeding** - When plugins fail to map to sites, there's no trace because errors are silently ignored
2. **Logger format too verbose** - Currently shows `[WP Plugin Publish v1.17.0]` but should just be `[v1.17.0]`
3. **No logger passed to migration/seeding functions** - They can't log anything currently

---

## Implementation Plan

### 1. Simplify Logger Prefix Format

**File: `backend/internal/logger/logger.go`**

Current format (line 68-75):
```go
prefix := ""
if cfg.AppName != "" {
    prefix = "[" + cfg.AppName
    if cfg.AppVersion != "" {
        prefix += " v" + cfg.AppVersion
    }
    prefix += "] "
}
```

New format - only version in one bracket:
```go
prefix := ""
if cfg.AppVersion != "" {
    prefix = "[v" + cfg.AppVersion + "] "
}
```

This changes output from:
```
[WP Plugin Publish v1.17.0] [2026-02-04 03:04:05 PM] INFO file.go:123 - message
```
To:
```
[v1.17.0] [2026-02-04 03:04:05 PM] INFO file.go:123 - message
```

---

### 2. Add Logger to Migration Function

**File: `backend/internal/database/migrations.go`**

Update `Migrate()` to accept and use a logger:

```go
func Migrate(db *DB, log *logger.Logger) error {
    log.Info("Starting database migrations")
    
    // ... existing migrations table creation ...
    
    log.Debug("Current migration version", "version", currentVersion)
    
    for _, m := range migrations {
        if m.Version <= currentVersion {
            continue
        }
        
        log.Info("Applying migration", "version", m.Version, "description", m.Description)
        
        // ... existing transaction logic ...
        
        if _, err := tx.Exec(m.SQL); err != nil {
            tx.Rollback()
            log.Error("Migration failed", "version", m.Version, "description", m.Description, "error", err)
            return fmt.Errorf("failed to apply migration %d (%s): %w", m.Version, m.Description, err)
        }
        
        log.Info("Migration completed", "version", m.Version)
    }
    
    log.Info("All migrations complete", "version", len(migrations))
    return nil
}
```

---

### 3. Add Logger to Seeding Functions

**File: `backend/internal/config/config.go`**

Update `SeedIfNeeded()` and helper functions to accept and use a logger:

```go
func SeedIfNeeded(db *database.DB, cfg *Config, log *logger.Logger) error {
    log.Info("Checking seed requirements", "configVersion", cfg.Version)
    
    currentVersion, err := db.GetSeedVersion()
    if err != nil {
        log.Error("Failed to get seed version", "error", err)
        return err
    }
    log.Debug("Current seed version", "version", currentVersion)
    
    if compareVersions(cfg.Version, currentVersion) > 0 {
        log.Info("Seeding database", "from", currentVersion, "to", cfg.Version)
        if err := seedFromConfig(db, cfg, log); err != nil {
            log.Error("Seeding failed", "error", err)
            return err
        }
        // ... version update ...
    }
    
    if cfg.Seed.Enabled {
        log.Info("Ensuring all plugin→site mappings exist")
        if err := ensureMappingsExist(db, cfg, log); err != nil {
            log.Error("Mapping verification failed", "error", err)
            return err
        }
    }
    
    return nil
}
```

Update `seedSitesAndPlugins()` with detailed logging:

```go
func seedSitesAndPlugins(db *database.DB, cfg *Config, log *logger.Logger) error {
    log.Info("Starting site and plugin seeding", 
        "siteCount", len(cfg.Seed.Sites),
        "pluginCount", len(cfg.Seed.Plugins))
    
    // ... for each site ...
    log.Debug("Processing seed site", "name", site.Name, "url", normalizedUrl)
    
    if existingId > 0 {
        log.Debug("Site already exists", "name", site.Name, "id", existingId)
    } else {
        log.Info("Created seed site", "name", site.Name, "id", id)
    }
    
    // ... for each plugin ...
    log.Debug("Processing seed plugin", "name", plugin.Name, "path", plugin.Path)
    
    // ... for each mapping ...
    if err := db.CreateSeedMapping(pluginId, siteId, remoteSlug); err != nil {
        log.Warn("Failed to create mapping", "pluginId", pluginId, "siteId", siteId, "error", err)
    } else {
        log.Debug("Created mapping", "pluginId", pluginId, "siteId", siteId)
    }
    
    log.Info("Seeding complete", "sitesTotal", len(allSiteIds), "pluginsTotal", len(cfg.Seed.Plugins))
    return nil
}
```

Update `ensureMappingsExist()` with logging:

```go
func ensureMappingsExist(db *database.DB, cfg *Config, log *logger.Logger) error {
    log.Debug("Verifying mappings exist for all seeded plugins")
    
    // ... get site IDs ...
    log.Debug("Found sites for mapping", "count", len(siteIds))
    
    mappingsCreated := 0
    for _, plugin := range cfg.Seed.Plugins {
        pluginId, err := db.GetPluginIdByPath(plugin.Path)
        if err != nil || pluginId == 0 {
            log.Warn("Plugin not found for mapping", "name", plugin.Name, "path", plugin.Path, "error", err)
            continue
        }
        
        for _, siteId := range siteIds {
            if err := db.CreateSeedMapping(pluginId, siteId, remoteSlug); err != nil {
                log.Warn("Mapping creation failed", "pluginId", pluginId, "siteId", siteId, "error", err)
            } else {
                mappingsCreated++
            }
        }
    }
    
    log.Info("Mapping verification complete", "mappingsVerified", mappingsCreated)
    return nil
}
```

---

### 4. Update main.go to Pass Logger

**File: `backend/cmd/server/main.go`**

Update calls to pass the logger:

```go
// Run migrations (now with logging)
if err := database.Migrate(db, log); err != nil {
    log.Fatal("Failed to run migrations", "error", err)
}

// Seed from config if needed (now with logging)
if err := config.SeedIfNeeded(db, cfg, log); err != nil {
    log.Fatal("Failed to seed database", "error", err)
}
```

---

### 5. Update Version and Documentation

**File: `backend/config.json`**

Bump version to `1.18.0`

**File: `public/version.json`**

Add changelog entry:
```json
{
  "version": "1.18.0",
  "date": "2026-02-04",
  "title": "Enhanced Migration & Seeding Logging",
  "changes": [
    "📊 Detailed logging for all migration steps with version tracking",
    "🌱 Comprehensive seeding logs showing site/plugin creation and mapping",
    "🔧 Simplified log prefix format: [vX.X.X] instead of full app name",
    "⚠️ Warning logs for failed mapping attempts with error details",
    "📋 Debug logs for each site/plugin processed during seed"
  ]
}
```

**File: `.lovable/memory/architecture/backend/migration-logging.md`**

Create new memory file documenting the logging architecture.

---

## Files to Modify

| File | Changes |
|------|---------|
| `backend/internal/logger/logger.go` | Simplify prefix to just `[vX.X.X]` |
| `backend/internal/database/migrations.go` | Add logger parameter, log each migration step |
| `backend/internal/config/config.go` | Add logger to SeedIfNeeded and helpers, log all operations |
| `backend/cmd/server/main.go` | Pass logger to Migrate() and SeedIfNeeded() |
| `backend/config.json` | Bump version to 1.18.0 |
| `public/version.json` | Add v1.18.0 changelog |
| `.lovable/memory/architecture/backend/migration-logging.md` | Document the logging architecture |

---

## Expected Log Output After Fix
I want this below formating


When the backend starts with seeding enabled:
```
[v1.18.0 - 2026-02-04 05:30:00 PM] Starting application version=WP Plugin  Publish v1.18.0 (INFO main.go:61)
[v1.18.0 - 2026-02-04 05:30:00 PM] Starting database migrations (INFO migrations.go:34)
[v1.18.0 - 2026-02-04 05:30:00 PM] Current migration version version=5 (DEBUG migrations.go:42)

```

If a mapping fails:
```
[v1.18.0 - 2026-02-04 05:30:00 PM] Failed to create mapping pluginId=2 siteId=1 error=UNIQUE constraint failed (WARN config.go:315)
```

---

## Testing Checklist

1. **Delete database** and restart backend - verify full migration + seeding logs appear
2. **Check mapping creation** - verify plugins show site badges
3. **Intentionally break a plugin path** in config.json - verify warning appears in logs
4. **Verify log format** - confirm only `[vX.X.X]` prefix, not full app name


Additional Instructins

So, uh, sites and plugins relationship, that i- still is buggy. So, if I create a relationship in the sites section, um, for the plugins, now the save persists in the s- sites section. That means sites section, if I open this site and go to plugin and then save, it works. It's, it's there persistent. But if I go to plugins now, open these sites, these are not selected. It's, it's pretty bad. The programming is pretty bad. You are not logging properly. Again, mentioning very clearly, you should log every time, uh, so that we can understand what's going on. Um, and there should be toast notification for, for saving the green, nice logo with very colorful notification needs to be there. And the saving is not correct. That means when I go to plugins and click on sites, I don't see the mapped, uh, sites. So it looks really, really bad. And, and when we switch the tabs, it should actually remain on that state. That re- state is gone. Okay? So let's say in the sites section, we, we went to the edit view. Okay? So, and from there we go to plugin and come back. It should actually have that edit view, which we worked on. And also in the plugin section, it shows a very big path. It actually shows a horizontal, um, scroll bar. It looks really bad. So the paths needs to be wrapped in a overflow wrap so that it does not look very bad. And also for the path, um, try to have the, let's say, copy option so that we can copy and check. So these are on top of my head. Try to write code in a, in a effective way so that it does not get broken every time.