# Memory: features/seedable-configuration
Updated: 2026-02-04

---

## Overview

The application supports pre-configured seed data for sites and plugins, enabling quick setup for development and testing. This is configured in `backend/config.json` under the `seed` section.

---

## Configuration Structure

```json
{
  "seed": {
    "enabled": true,
    "sites": [
      {
        "name": "Demo Site",
        "url": "https://example.com",
        "username": "admin",
        "applicationPassword": "BASE64_ENCODED_PASSWORD",
        "category": "Development"
      }
    ],
    "plugins": [
      {
        "name": "My Plugin",
        "path": "D:\\path\\to\\plugin",
        "category": "Development",
        "gitEnabled": true,
        "autoPublish": false,
        "siteNames": ["Demo Site"]
      }
    ]
  }
}
```

---

## Behavior

1. **Version Tracking**: Seeding only runs when `config.version` is newer than `db.seed.version`
2. **Idempotent**: Existing sites (by URL) and plugins (by path) are skipped
3. **Auto-Mapping**: Plugins reference sites by name to create PluginMappings automatically
4. **Password Security**: Application passwords are stored as base64 in config

---

## Database Helpers

| Function | Purpose |
|----------|---------|
| `GetSiteIDByURL(url)` | Check if site already exists |
| `GetPluginIDByPath(path)` | Check if plugin already exists |
| `CreateSeedSite(...)` | Insert site with encrypted password |
| `CreateSeedPlugin(...)` | Insert plugin with optional git config |
| `CreateSeedMapping(...)` | Create plugin-site relationship |

---

## Files

- `backend/config.json` - Seed configuration
- `backend/internal/config/config.go` - SeedConfig structs and seeding logic
- `backend/internal/database/database.go` - Database helper methods
- `backend/internal/database/migrations.go` - v4 migration for AutoPublish

---

*Seeding runs on application startup via `config.SeedIfNeeded(db, cfg)`*
