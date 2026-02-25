# Memory: features/sites/remote-plugins-caching
Updated: 2026-02-05

## Overview

Remote plugin lists fetched from WordPress sites are cached in SQLite to reduce API calls and improve UX.

## Configuration

In `config.json`:
```json
"RemotePlugins": {
  "CacheEnabled": true,
  "CacheTTLMinutes": 60
}
```

## Database Schema

```sql
CREATE TABLE RemotePluginsCache (
  Id INTEGER PRIMARY KEY AUTOINCREMENT,
  SiteId INTEGER NOT NULL UNIQUE,
  PluginsJSON TEXT NOT NULL,
  CachedAt TEXT DEFAULT (datetime('now')),
  ExpiresAt TEXT NOT NULL,
  FOREIGN KEY (SiteId) REFERENCES Sites(Id) ON DELETE CASCADE
);
```

## API Endpoints

- `GET /api/v1/sites/{id}/remote-plugins` - Returns cached if valid, else fetches fresh
- `POST /api/v1/sites/{id}/remote-plugins/force-sync` - Clears cache, fetches fresh
- `DELETE /api/v1/sites/{id}/remote-plugins/cache` - Clears cache only

## Cache Invalidation

Cache is automatically invalidated when:
- Plugin is enabled/disabled
- Plugin is deleted
- Force sync is triggered
- Cache TTL expires (default: 1 hour)

## Frontend Integration

- `api.getRemotePlugins(siteId)` - Uses cache
- `api.forceSyncRemotePlugins(siteId)` - Bypasses cache
- `api.clearRemotePluginsCache(siteId)` - Clears cache only
