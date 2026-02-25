# Memory: features/sites/remote-plugins-caching-complete
Updated: 2026-02-06

## Overview

Remote plugin lists are cached in SQLite with a 60-minute TTL. Users can force refresh from the site at any time using the "Force Sync" button.

## Backend Implementation

### Configuration

Located in `backend/config.json`:

```json
{
  "RemotePlugins": {
    "CacheEnabled": true,
    "CacheTTLMinutes": 60
  }
}
```

### Database Table

Migration v6 creates `RemotePluginsCache`:

```sql
CREATE TABLE IF NOT EXISTS RemotePluginsCache (
    Id INTEGER PRIMARY KEY AUTOINCREMENT,
    SiteId INTEGER NOT NULL UNIQUE,
    PluginsJSON TEXT NOT NULL,
    CachedAt TEXT DEFAULT (datetime('now')),
    ExpiresAt TEXT NOT NULL,
    FOREIGN KEY (SiteId) REFERENCES Sites(Id) ON DELETE CASCADE
);
```

### Service Methods

Located in `backend/internal/services/site/service.go`:

| Method | Description |
|--------|-------------|
| `GetRemotePlugins(ctx, siteID)` | Returns cached or fetches fresh |
| `GetRemotePluginsWithCache(ctx, siteID, forceRefresh)` | Optional cache bypass |
| `ForceSyncRemotePlugins(ctx, siteID)` | Invalidates cache, fetches fresh |
| `InvalidateRemotePluginsCache(ctx, siteID)` | Deletes cached entry |
| `GetRemotePluginsCacheStatus(ctx, siteID)` | Returns cache validity, cachedAt, expiresAt |

### API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/sites/{id}/remote-plugins` | Returns cached if valid |
| POST | `/sites/{id}/remote-plugins/force-sync` | Bypasses cache |
| DELETE | `/sites/{id}/remote-plugins/cache` | Clears cache only |

## Frontend Implementation

### RemotePluginsPanel Updates

- Added "Force Sync" button with Zap icon
- Shows last fetched timestamp with relative time (e.g., "2m ago")
- Force sync mutation calls `/force-sync` endpoint
- Visual loading states during sync operations

### API Client

Located in `src/lib/api.ts`:

```typescript
forceSyncRemotePlugins: (siteId: number) =>
  request<RemotePlugin[]>(`/sites/${siteId}/remote-plugins/force-sync`, { method: "POST" }),

clearRemotePluginsCache: (siteId: number) =>
  request<{ cleared: boolean }>(`/sites/${siteId}/remote-plugins/cache`, { method: "DELETE" }),
```

## Cache Invalidation

Cache is automatically invalidated when:
- User clicks "Force Sync"
- A plugin enable/disable/delete action is performed on the site
- Cache TTL expires (60 minutes)

## Related Files

- `backend/internal/config/config.go` - RemotePluginsConfig struct
- `backend/internal/services/site/service.go` - Cache methods
- `backend/internal/database/migrations.go` - Migration v6
- `src/components/sites/RemotePluginsPanel.tsx` - UI with force sync
- `src/lib/api.ts` - Frontend API client
