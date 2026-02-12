# 03 — Config System

> **Parent:** [00-overview.md](../00-overview.md)  
> **Status:** Draft

---

## Overview

The configuration system uses a **JSON seed file** for initial setup and version-controlled defaults, while **SQLite** serves as the runtime data store.

### Key Principles

1. **JSON is for seeding only** — Not read at runtime after initial seed
2. **SQLite is the source of truth** — All runtime reads come from the database
3. **Version-controlled seeding** — Changes to config.json trigger re-seeding of new data
4. **No duplicate entries** — Seeding skips existing records (matched by unique keys)

---

## Config File Structure

### config.json

```json
{
  "version": 3,
  "settings": {
    "port": 8080,
    "watchDebounceMs": 500,
    "backupRetentionDays": 30,
    "maxBackupsPerPlugin": 10,
    "tempDirectory": ".temp",
    "backupDirectory": "backups",
    "logLevel": "info"
  },
  "sites": [
    {
      "name": "Production Site",
      "url": "https://example.com",
      "username": "admin",
      "appPassword": ""
    }
  ],
  "plugins": [
    {
      "name": "My Plugin",
      "localPath": "/path/to/my-plugin",
      "remoteSlug": "my-plugin",
      "siteName": "Production Site"
    }
  ]
}
```

---

## Version-Based Seeding

### How It Works

```
┌─────────────────────────────────────────────────────────┐
│                    Application Start                     │
└─────────────────────────────────────────────────────────┘
                            │
                            ▼
                ┌───────────────────────┐
                │  Load config.json     │
                │  Read file version    │
                └───────────────────────┘
                            │
                            ▼
                ┌───────────────────────┐
                │  Get DB seed_version  │
                │  from AppConfig       │
                └───────────────────────┘
                            │
                            ▼
              ┌─────────────────────────────┐
              │  file_version > db_version? │
              └─────────────────────────────┘
                     │              │
                    Yes             No
                     │              │
                     ▼              ▼
          ┌──────────────────┐   ┌──────────────┐
          │  Seed new data   │   │  Skip seed   │
          │  (skip existing) │   │              │
          └──────────────────┘   └──────────────┘
                     │              │
                     ▼              ▼
          ┌──────────────────┐   ┌──────────────┐
          │  Update db       │   │  Continue    │
          │  seed_version    │   │  startup     │
          └──────────────────┘   └──────────────┘
```

### Implementation

```go
// internal/config/seed.go
package config

import (
    "database/sql"
    "encoding/json"
    "os"
    
    "wp-plugin-publish/internal/models"
    "wp-plugin-publish/pkg/apperror"
)

type SeedConfig struct {
    Version  int            `json:"version"`
    Settings Settings       `json:"settings"`
    Sites    []SiteSeed     `json:"sites"`
    Plugins  []PluginSeed   `json:"plugins"`
}

type Settings struct {
    Port                int    `json:"port"`
    WatchDebounceMs     int    `json:"watchDebounceMs"`
    BackupRetentionDays int    `json:"backupRetentionDays"`
    MaxBackupsPerPlugin int    `json:"maxBackupsPerPlugin"`
    TempDirectory       string `json:"tempDirectory"`
    BackupDirectory     string `json:"backupDirectory"`
    LogLevel            string `json:"logLevel"`
}

type SiteSeed struct {
    Name        string `json:"name"`
    URL         string `json:"url"`
    Username    string `json:"username"`
    AppPassword string `json:"appPassword"`
}

type PluginSeed struct {
    Name       string `json:"name"`
    LocalPath  string `json:"localPath"`
    RemoteSlug string `json:"remoteSlug"`
    SiteName   string `json:"siteName"`  // References site by name
}

func SeedIfNeeded(db *sql.DB, configPath string) error {
    // Load config file
    data, err := os.ReadFile(configPath)
    if err != nil {
        if os.IsNotExist(err) {
            return nil  // No config file, skip seeding
        }
        return apperror.Wrap(err, apperror.ErrConfigLoad, "failed to read config file")
    }
    
    var cfg SeedConfig
    if err := json.Unmarshal(data, &cfg); err != nil {
        return apperror.Wrap(err, apperror.ErrConfigParse, "failed to parse config file")
    }
    
    // Get current seed version from DB
    var dbVersion int
    err = db.QueryRow("SELECT Value FROM AppConfig WHERE Key = 'seed_version'").Scan(&dbVersion)
    if err == sql.ErrNoRows {
        dbVersion = 0
    } else if err != nil {
        return apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to get seed version")
    }
    
    // Skip if already seeded with this version
    if cfg.Version <= dbVersion {
        return nil
    }
    
    // Seed settings
    if err := seedSettings(db, cfg.Settings); err != nil {
        return err
    }
    
    // Seed sites (skip existing by URL)
    siteMap := make(map[string]int64)  // name -> id
    for _, site := range cfg.Sites {
        id, err := seedSite(db, site)
        if err != nil {
            return err
        }
        siteMap[site.Name] = id
    }
    
    // Seed plugins (skip existing by LocalPath + SiteId)
    for _, plugin := range cfg.Plugins {
        siteID, ok := siteMap[plugin.SiteName]
        if !ok {
            continue  // Site not found, skip
        }
        if err := seedPlugin(db, plugin, siteID); err != nil {
            return err
        }
    }
    
    // Update seed version
    _, err = db.Exec(
        "INSERT OR REPLACE INTO AppConfig (Key, Value, UpdatedAt) VALUES ('seed_version', ?, datetime('now'))",
        cfg.Version,
    )
    if err != nil {
        return apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to update seed version")
    }
    
    return nil
}

func seedSite(db *sql.DB, site SiteSeed) (int64, error) {
    // Check if exists
    var existingID int64
    err := db.QueryRow("SELECT Id FROM Sites WHERE Url = ?", site.URL).Scan(&existingID)
    if err == nil {
        return existingID, nil  // Already exists
    }
    if err != sql.ErrNoRows {
        return 0, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to check site existence")
    }
    
    // Insert new site
    result, err := db.Exec(
        `INSERT INTO Sites (Name, Url, Username, AppPassword, IsActive) 
         VALUES (?, ?, ?, ?, 1)`,
        site.Name, site.URL, site.Username, site.AppPassword,
    )
    if err != nil {
        return 0, apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to insert site")
    }
    
    return result.LastInsertId()
}

func seedPlugin(db *sql.DB, plugin PluginSeed, siteID int64) error {
    // Check if exists
    var exists bool
    err := db.QueryRow(
        "SELECT 1 FROM Plugins WHERE LocalPath = ? AND SiteId = ?",
        plugin.LocalPath, siteID,
    ).Scan(&exists)
    if err == nil {
        return nil  // Already exists
    }
    if err != sql.ErrNoRows {
        return apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to check plugin existence")
    }
    
    // Insert new plugin
    _, err = db.Exec(
        `INSERT INTO Plugins (Name, LocalPath, RemoteSlug, SiteId, IsActive) 
         VALUES (?, ?, ?, ?, 1)`,
        plugin.Name, plugin.LocalPath, plugin.RemoteSlug, siteID,
    )
    if err != nil {
        return apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to insert plugin")
    }
    
    return nil
}
```

---

## Settings Management

Settings are stored in AppConfig table as key-value pairs:

```go
// internal/config/settings.go
func seedSettings(db *sql.DB, settings Settings) error {
    pairs := []struct{ key, value string }{
        {"port", fmt.Sprintf("%d", settings.Port)},
        {"watch_debounce_ms", fmt.Sprintf("%d", settings.WatchDebounceMs)},
        {"backup_retention_days", fmt.Sprintf("%d", settings.BackupRetentionDays)},
        {"max_backups_per_plugin", fmt.Sprintf("%d", settings.MaxBackupsPerPlugin)},
        {"temp_directory", settings.TempDirectory},
        {"backup_directory", settings.BackupDirectory},
        {"log_level", settings.LogLevel},
    }
    
    for _, p := range pairs {
        // Only insert if not exists (don't overwrite user changes)
        _, err := db.Exec(
            `INSERT OR IGNORE INTO AppConfig (Key, Value, UpdatedAt) 
             VALUES (?, ?, datetime('now'))`,
            p.key, p.value,
        )
        if err != nil {
            return apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to seed setting: "+p.key)
        }
    }
    
    return nil
}

func GetSetting(db *sql.DB, key string) (string, error) {
    var value string
    err := db.QueryRow("SELECT Value FROM AppConfig WHERE Key = ?", key).Scan(&value)
    if err != nil {
        return "", apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to get setting: "+key)
    }
    return value, nil
}

func SetSetting(db *sql.DB, key, value string) error {
    _, err := db.Exec(
        `INSERT OR REPLACE INTO AppConfig (Key, Value, UpdatedAt) 
         VALUES (?, ?, datetime('now'))`,
        key, value,
    )
    if err != nil {
        return apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to set setting: "+key)
    }
    return nil
}
```

---

## Workflow Example

### First Run (Fresh Install)

1. User copies `config.json.example` to `config.json`
2. User edits with their sites and plugin paths
3. User sets `version: 1`
4. Application starts, reads `version: 1`
5. DB has `seed_version: 0`
6. All sites and plugins are seeded
7. DB updated to `seed_version: 1`

### Adding a New Site via Config

1. User adds new site to `config.json`
2. User increments to `version: 2`
3. Application restarts
4. DB has `seed_version: 1`
5. New site is seeded (existing sites skipped)
6. DB updated to `seed_version: 2`

### User Edits via UI

1. User changes site password via React UI
2. Change saved directly to SQLite
3. `config.json` not touched
4. Re-seeding won't overwrite (unique constraint)

---

## Next Document

See [04-site-service.md](./04-site-service.md) for site management implementation.
