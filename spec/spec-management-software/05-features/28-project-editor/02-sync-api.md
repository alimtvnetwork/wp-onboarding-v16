# Sync API

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-30  
**Parent:** [Project Editor](./00-overview.md)

---

## Purpose

Define the backend API for synchronizing input state and drafts across devices. Enables premium users to seamlessly continue work on any device with automatic conflict resolution.

---

## API Overview

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v1/sync/state` | POST | Sync input state to server |
| `/api/v1/sync/state` | GET | Retrieve synced state |
| `/api/v1/sync/state/:key` | DELETE | Remove synced state |
| `/api/v1/sync/batch` | POST | Batch sync multiple keys |
| `/api/v1/sync/devices` | GET | List synced devices |
| `/api/v1/sync/devices/:id` | DELETE | Remove device from sync |

---

## Data Models

### SyncState

```go
// SyncState represents a single synchronized input state
type SyncState struct {
    ID          string    `json:"id" gorm:"primaryKey"`
    UserID      string    `json:"userId" gorm:"index:idx_user_key,priority:1"`
    Key         string    `json:"key" gorm:"index:idx_user_key,priority:2"`
    Value       string    `json:"value"`
    DeviceID    string    `json:"deviceId" gorm:"index"`
    Version     int64     `json:"version"`
    UpdatedAt   time.Time `json:"updatedAt"`
    CreatedAt   time.Time `json:"createdAt"`
    ExpiresAt   time.Time `json:"expiresAt" gorm:"index"`
}

// SyncDevice represents a user's synced device
type SyncDevice struct {
    ID          string    `json:"id" gorm:"primaryKey"`
    UserID      string    `json:"userId" gorm:"index"`
    DeviceName  string    `json:"deviceName"`
    DeviceType  string    `json:"deviceType"` // desktop, mobile, tablet
    UserAgent   string    `json:"userAgent"`
    LastSyncAt  time.Time `json:"lastSyncAt"`
    CreatedAt   time.Time `json:"createdAt"`
}
```

### Request/Response Types

```go
// SyncStateRequest for creating/updating state
type SyncStateRequest struct {
    Key       string `json:"key" validate:"required,max=500"`
    Value     string `json:"value" validate:"required,max=1048576"` // 1MB max
    DeviceID  string `json:"deviceId" validate:"required,uuid"`
    Version   int64  `json:"version"`
    Timestamp int64  `json:"timestamp"`
}

// SyncStateResponse returned after sync
type SyncStateResponse struct {
    Key       string    `json:"key"`
    Value     string    `json:"value"`
    Version   int64     `json:"version"`
    UpdatedAt time.Time `json:"updatedAt"`
    Conflict  bool      `json:"conflict"`
    DeviceID  string    `json:"deviceId"`
}

// BatchSyncRequest for syncing multiple states
type BatchSyncRequest struct {
    States []SyncStateRequest `json:"states" validate:"required,max=50,dive"`
}

// BatchSyncResponse with results and conflicts
type BatchSyncResponse struct {
    Results  []SyncStateResponse `json:"results"`
    Errors   []SyncError         `json:"errors,omitempty"`
}

// SyncError for individual sync failures
type SyncError struct {
    Key     string `json:"key"`
    Code    int    `json:"code"`
    Message string `json:"message"`
}
```

---

## API Endpoints

### POST /api/v1/sync/state

Sync a single input state to the server.

**Request:**

```json
{
  "key": "specmgmt_v1_chat_proj123_main_message",
  "value": "Hello, can you help me with...",
  "deviceId": "550e8400-e29b-41d4-a716-446655440000",
  "version": 1706620800000,
  "timestamp": 1706620800123
}
```

**Response (200):**

```json
{
  "key": "specmgmt_v1_chat_proj123_main_message",
  "value": "Hello, can you help me with...",
  "version": 1706620800123,
  "updatedAt": "2026-01-30T12:00:00Z",
  "conflict": false,
  "deviceId": "550e8400-e29b-41d4-a716-446655440000"
}
```

**Response (409 Conflict):**

```json
{
  "key": "specmgmt_v1_chat_proj123_main_message",
  "value": "Different content from another device...",
  "version": 1706620900000,
  "updatedAt": "2026-01-30T12:01:40Z",
  "conflict": true,
  "deviceId": "different-device-id"
}
```

---

### GET /api/v1/sync/state

Retrieve synced state for the current user.

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `key` | string | Specific key to retrieve (optional) |
| `prefix` | string | Key prefix filter (optional) |
| `since` | int64 | Unix timestamp for incremental sync |
| `limit` | int | Max results (default: 100, max: 500) |

**Response (200):**

```json
{
  "states": [
    {
      "key": "specmgmt_v1_chat_proj123_main_message",
      "value": "Hello, can you help me with...",
      "version": 1706620800123,
      "updatedAt": "2026-01-30T12:00:00Z",
      "deviceId": "550e8400-e29b-41d4-a716-446655440000"
    }
  ],
  "nextCursor": "eyJsYXN0SWQiOiAiYWJjMTIzIn0=",
  "hasMore": false
}
```

---

### POST /api/v1/sync/batch

Batch sync multiple states in a single request.

**Request:**

```json
{
  "states": [
    {
      "key": "specmgmt_v1_chat_proj123_main_message",
      "value": "Chat content...",
      "deviceId": "device-uuid",
      "version": 1706620800000
    },
    {
      "key": "specmgmt_v1_editor_proj123_readme_content",
      "value": "# README\n\nProject documentation...",
      "deviceId": "device-uuid",
      "version": 1706620800000
    }
  ]
}
```

**Response (200):**

```json
{
  "results": [
    {
      "key": "specmgmt_v1_chat_proj123_main_message",
      "version": 1706620800123,
      "conflict": false
    },
    {
      "key": "specmgmt_v1_editor_proj123_readme_content",
      "version": 1706620800456,
      "conflict": false
    }
  ],
  "errors": []
}
```

---

## Conflict Resolution

### Last-Write-Wins (Default)

```go
func (s *SyncService) resolveConflict(existing, incoming *SyncState) *SyncState {
    // Compare timestamps - newer wins
    if incoming.Version > existing.Version {
        return incoming
    }
    
    // If same timestamp, prefer larger content (likely more work)
    if incoming.Version == existing.Version {
        if len(incoming.Value) > len(existing.Value) {
            return incoming
        }
    }
    
    return existing
}
```

### Client-Side Merge Option

```typescript
interface ConflictResolution {
  readonly strategy: 'server' | 'client' | 'merge' | 'manual';
  readonly mergedValue?: string;
}

async function handleConflict(
  local: SyncStateResponse,
  server: SyncStateResponse
): Promise<ConflictResolution> {
  // Auto-merge for chat inputs (append)
  if (local.key.includes('_chat_')) {
    return {
      strategy: 'merge',
      mergedValue: `${server.value}\n\n---\n\n${local.value}`,
    };
  }
  
  // Show conflict dialog for editor content
  if (local.key.includes('_editor_')) {
    const resolution = await showConflictDialog(local, server);
    return resolution;
  }
  
  // Default: server wins
  return { strategy: 'server' };
}
```

---

## Client Integration

### SyncManager

```typescript
class SyncManager {
  private pendingSync: Map<string, SyncStateRequest> = new Map();
  private syncInterval: NodeJS.Timeout | null = null;
  private readonly SYNC_DEBOUNCE_MS = 2000;
  private readonly BATCH_SIZE = 50;
  
  constructor(private readonly apiClient: ApiClient) {}
  
  start(): void {
    // Periodic sync for pending changes
    this.syncInterval = setInterval(() => {
      this.flushPendingSync();
    }, this.SYNC_DEBOUNCE_MS);
    
    // Sync on visibility change (tab focus)
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible') {
        this.pullFromServer();
      } else {
        this.flushPendingSync();
      }
    });
    
    // Sync before unload
    window.addEventListener('beforeunload', () => {
      this.flushPendingSyncSync(); // Synchronous version
    });
  }
  
  queueSync(key: string, value: string): void {
    this.pendingSync.set(key, {
      key,
      value,
      deviceId: getDeviceId(),
      version: Date.now(),
      timestamp: Date.now(),
    });
  }
  
  private async flushPendingSync(): Promise<void> {
    if (this.pendingSync.size === 0) return;
    
    const states = Array.from(this.pendingSync.values());
    this.pendingSync.clear();
    
    // Batch in chunks
    for (let i = 0; i < states.length; i += this.BATCH_SIZE) {
      const batch = states.slice(i, i + this.BATCH_SIZE);
      try {
        const response = await this.apiClient.post<BatchSyncResponse>(
          '/sync/batch',
          { states: batch }
        );
        
        // Handle conflicts
        for (const result of response.results) {
          if (result.conflict) {
            await this.handleConflict(result);
          }
        }
      } catch (error) {
        // Re-queue failed syncs
        for (const state of batch) {
          this.pendingSync.set(state.key, state);
        }
        console.error('Sync failed, will retry:', error);
      }
    }
  }
  
  private async pullFromServer(): Promise<void> {
    const lastSync = localStorage.getItem('specmgmt_last_sync');
    const since = lastSync ? parseInt(lastSync) : 0;
    
    const response = await this.apiClient.get<{ states: SyncStateResponse[] }>(
      `/sync/state?since=${since}`
    );
    
    for (const state of response.states) {
      // Only update if server is newer
      const local = await inputStateManager.get(state.key);
      if (!local || state.version > parseInt(localStorage.getItem(`${state.key}_version`) ?? '0')) {
        await inputStateManager.set(state.key, state.value);
        localStorage.setItem(`${state.key}_version`, String(state.version));
      }
    }
    
    localStorage.setItem('specmgmt_last_sync', String(Date.now()));
  }
}

export const syncManager = new SyncManager(apiClient);
```

---

## useSyncedInput Hook

Extension of `usePersistedInput` with sync support:

```typescript
interface UseSyncedInputOptions extends UsePersistedInputOptions {
  readonly enableSync?: boolean;
}

export function useSyncedInput(options: UseSyncedInputOptions): UsePersistedInputReturn {
  const { enableSync = true, ...persistOptions } = options;
  const baseHook = usePersistedInput(persistOptions);
  const { isPremium } = useUser();
  
  // Queue sync when value changes
  useEffect(() => {
    if (enableSync && isPremium && baseHook.value) {
      const key = buildKey(persistOptions);
      syncManager.queueSync(key, baseHook.value);
    }
  }, [baseHook.value, enableSync, isPremium]);
  
  return baseHook;
}
```

---

## Database Schema

```sql
-- SyncStates table
CREATE TABLE SyncStates (
    ID          TEXT PRIMARY KEY,
    UserID      TEXT NOT NULL,
    Key         TEXT NOT NULL,
    Value       TEXT NOT NULL,
    DeviceID    TEXT NOT NULL,
    Version     INTEGER NOT NULL,
    UpdatedAt   DATETIME NOT NULL,
    CreatedAt   DATETIME NOT NULL,
    ExpiresAt   DATETIME NOT NULL
);

CREATE INDEX idx_sync_user_key ON SyncStates(UserID, Key);
CREATE INDEX idx_sync_device ON SyncStates(DeviceID);
CREATE INDEX idx_sync_expires ON SyncStates(ExpiresAt);

-- SyncDevices table
CREATE TABLE SyncDevices (
    ID          TEXT PRIMARY KEY,
    UserID      TEXT NOT NULL,
    DeviceName  TEXT NOT NULL,
    DeviceType  TEXT NOT NULL,
    UserAgent   TEXT,
    LastSyncAt  DATETIME NOT NULL,
    CreatedAt   DATETIME NOT NULL
);

CREATE INDEX idx_devices_user ON SyncDevices(UserID);
```

---

## Rate Limiting & Quotas

| Tier | Requests/min | Max Keys | Max Value Size | Retention |
|------|--------------|----------|----------------|-----------|
| Free | 0 (disabled) | 0 | - | - |
| Pro | 60 | 1,000 | 1MB | 30 days |
| Team | 120 | 10,000 | 5MB | 90 days |

---

## Error Codes

| Code | Name | Description |
|------|------|-------------|
| 10001 | SyncDisabled | Sync not enabled for this account |
| 10002 | QuotaExceeded | Too many synced keys |
| 10003 | ValueTooLarge | Value exceeds size limit |
| 10004 | RateLimited | Too many sync requests |
| 10005 | ConflictUnresolved | Manual conflict resolution required |
| 10006 | DeviceLimitReached | Maximum devices reached |
| 10007 | ExpiredState | State has expired and was deleted |

---

## Acceptance Criteria

| ID | Criterion | Priority | Validation |
|----|-----------|----------|------------|
| SYN-01 | State syncs within 2s of change | MUST | E2E test |
| SYN-02 | Batch sync up to 50 items | MUST | Load test |
| SYN-03 | Conflict detection on version mismatch | MUST | Unit test |
| SYN-04 | Incremental sync with `since` param | MUST | Integration test |
| SYN-05 | Rate limiting enforced per tier | MUST | Load test |
| SYN-06 | Expired states auto-deleted | SHOULD | Scheduled job test |
| SYN-07 | Device list management | SHOULD | E2E test |
| SYN-08 | Offline queue with retry | MUST | E2E test |
| SYN-09 | Sync on tab visibility change | SHOULD | E2E test |
| SYN-10 | Premium-only access enforced | MUST | Auth test |

---

## Related Specs

- [Input State Persistence](./06-input-state-persistence.md) — Local storage layer
- [Draft Recovery UI](./01-draft-recovery-ui.md) — Recovery interface
- [Gateway Service](../../14-microservices/01-gateway.md) — API routing
- [Authentication](../01-authentication/01-authentication.md) — Premium validation
