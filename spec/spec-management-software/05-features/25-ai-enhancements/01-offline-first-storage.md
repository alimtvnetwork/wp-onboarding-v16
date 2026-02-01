# Phase 1: Offline-First Storage

**Version:** 1.1.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

Implement localStorage-first architecture with application version keying and background sync to ensure zero data loss even during network failures or offline usage.

**Cross-References:**
- [State Management](../16-state-management/00-overview.md)
- [Realtime](../18-realtime/00-overview.md)
- [API Client](../15-api-client/00-overview.md)

---

## Components

| # | Component | Type | Description |
|---|-----------|------|-------------|
| 01 | [Versioned Storage](./01-01-versioned-storage.md) | Frontend | Version-keyed localStorage with auto-cleanup |
| 02 | [Sync Queue](./01-02-sync-queue.md) | Frontend | Background sync with retry logic |
| 03 | [Storage Hooks](./01-03-storage-hooks.md) | Frontend | React hooks for offline-first patterns |
| 04 | [Sync API](./01-04-sync-api.md) | Backend | Batch sync endpoint for queued operations |

---

## User Stories

### US-1.1: Zero Data Loss on Network Failure
**As a** user typing a message  
**I want** my input saved instantly to local storage  
**So that** I never lose work even if the network fails mid-sentence

**Acceptance Criteria:**
- Every keystroke triggers debounced save (100ms)
- Data persists across browser refresh
- No user action required for local save
- Works with text input, voice recordings, and file references

### US-1.2: Transparent Background Sync
**As a** user  
**I want** my local changes to sync automatically when online  
**So that** I don't have to manually trigger syncs

**Acceptance Criteria:**
- Sync starts automatically when network detected
- Visual indicator shows sync status
- Failed syncs retry automatically
- User can force retry via UI

### US-1.3: Version Migration
**As a** user upgrading to a new app version  
**I want** old cached data cleaned up automatically  
**So that** stale data doesn't cause issues or consume storage

**Acceptance Criteria:**
- Old version keys removed on app init
- Migration runs before any other storage access
- Cleanup logged for debugging
- No user action required

### US-1.4: Offline Mode Awareness
**As a** user working offline  
**I want** to see that I'm offline and changes are saved locally  
**So that** I know my work is safe

**Acceptance Criteria:**
- Clear offline indicator in UI
- "Saved locally" confirmation on actions
- Pending sync count visible
- Automatic sync when back online

---

## Architecture

### Data Flow

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           User Action                                    │
│  (type text, record audio, add reference, modify file)                  │
└───────────────────────────────────┬─────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                      1. SAVE LOCAL (Immediate)                           │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  VersionedStorage.set(key, data, synced=false)                  │    │
│  │  - Debounced 100ms for rapid input                              │    │
│  │  - Quota exceeded? Prune oldest synced entries                  │    │
│  │  - Key format: specmgmt_v{version}:{entity}:{id}                │    │
│  └─────────────────────────────────────────────────────────────────┘    │
└───────────────────────────────────┬─────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                      2. QUEUE FOR SYNC                                   │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  SyncQueue.enqueue(operation, entityType, entityId, payload)    │    │
│  │  - Add to persistent queue in localStorage                      │    │
│  │  - Trigger processQueue() if online                             │    │
│  └─────────────────────────────────────────────────────────────────┘    │
└───────────────────────────────────┬─────────────────────────────────────┘
                                    │
                    ┌───────────────┴───────────────┐
                    │                               │
               Online?                          Offline?
                    │                               │
                    ▼                               ▼
┌───────────────────────────────┐   ┌───────────────────────────────────┐
│  3A. SYNC TO BACKEND          │   │  3B. WAIT FOR CONNECTION          │
│  - POST/PUT/DELETE to API     │   │  - Queue persists in localStorage │
│  - On success: markSynced()   │   │  - Listen for 'online' event      │
│  - On failure: increment retry│   │  - Process queue when triggered   │
└───────────────────────────────┘   └───────────────────────────────────┘
```

### Storage Key Schema

```
specmgmt_v{version}:{namespace}:{entity_type}:{entity_id}

Examples:
- specmgmt_v1.2.0:chat:draft:session_abc123
- specmgmt_v1.2.0:audio:recording:rec_xyz789
- specmgmt_v1.2.0:plan:execution:plan_def456
- specmgmt_v1.2.0:sync:queue
- specmgmt_v1.2.0:settings:user_prefs
```

### Storage Entry Schema

```typescript
interface StorageEntry<T> {
  data: T;              // The actual payload
  timestamp: number;    // Unix timestamp of last modification
  synced: boolean;      // Whether successfully synced to backend
  version: number;      // Schema version for migrations
  checksum?: string;    // Optional integrity check
}
```

---

## Edge Cases & Error Handling

### EC-1: Quota Exceeded
**Scenario:** localStorage quota (typically 5-10MB) is full  
**Handling:**
1. Catch `QuotaExceededError`
2. Calculate current usage
3. Prune oldest 25% of **synced** entries (never unsynced)
4. Retry the write
5. If still failing, show user warning with option to export data

### EC-2: Corrupted Storage Entry
**Scenario:** JSON parsing fails for a stored entry  
**Handling:**
1. Log error with key and raw value
2. Return `null` from `get()` method
3. Optionally quarantine entry for debugging
4. Never crash the application

### EC-3: Version Mismatch During Cleanup
**Scenario:** Cleanup runs while background tab has old version  
**Handling:**
1. Version comparison is prefix-based
2. Cleanup only removes entries with different version prefix
3. Current version entries are never touched
4. Log cleanup actions for debugging

### EC-4: Sync Conflict
**Scenario:** Local and server data diverge during offline period  
**Handling:**
1. Default: Last-write-wins (server timestamp)
2. For critical data: Store conflict for user resolution
3. Log conflicts for debugging
4. Future: Implement CRDT for collaborative editing

### EC-5: Network Flapping
**Scenario:** Network repeatedly connects/disconnects  
**Handling:**
1. Debounce `online` event processing (500ms)
2. Don't restart sync if already in progress
3. Exponential backoff after repeated failures
4. Cap retry rate at 1 per 30 seconds during flapping

### EC-6: Large Audio Blob
**Scenario:** Audio recording exceeds IndexedDB limits  
**Handling:**
1. Use IndexedDB for blobs > 1MB (not localStorage)
2. Store reference in localStorage, blob in IndexedDB
3. Chunk large files for upload
4. Resume upload on reconnection

---

## Migration Strategy

### Version Upgrade Path

```typescript
interface MigrationStep {
  fromVersion: string;
  toVersion: string;
  migrate: (oldData: unknown) => unknown;
}

const migrations: MigrationStep[] = [
  {
    fromVersion: '1.0.0',
    toVersion: '1.1.0',
    migrate: (data) => {
      // Add new 'version' field to entries
      return { ...data, version: 1 };
    },
  },
  {
    fromVersion: '1.1.0',
    toVersion: '1.2.0',
    migrate: (data) => {
      // Rename 'synced' to 'syncStatus' with enum
      const { synced, ...rest } = data as { synced: boolean };
      return { ...rest, syncStatus: synced ? 'synced' : 'pending' };
    },
  },
];
```

### Migration Execution

1. On app init, detect current version from package.json
2. Scan localStorage for version prefixes
3. If old versions found:
   a. Extract all entries for that version
   b. Run migration chain to current version
   c. Store migrated entries with new version key
   d. Delete old version entries
4. Log migration results
5. Continue with normal initialization

---

## IndexedDB Integration

For large binary data (audio, images), use IndexedDB alongside localStorage:

```typescript
// lib/storage/BlobStorage.ts

const DB_NAME = 'specmgmt_blobs';
const DB_VERSION = 1;
const STORE_NAME = 'blobs';

interface BlobEntry {
  id: string;
  blob: Blob;
  type: string;
  size: number;
  createdAt: number;
  synced: boolean;
}

export class BlobStorage {
  private db: IDBDatabase | null = null;
  
  async init(): Promise<void> {
    return new Promise((resolve, reject) => {
      const request = indexedDB.open(DB_NAME, DB_VERSION);
      
      request.onupgradeneeded = (event) => {
        const db = (event.target as IDBOpenDBRequest).result;
        if (!db.objectStoreNames.contains(STORE_NAME)) {
          const store = db.createObjectStore(STORE_NAME, { keyPath: 'id' });
          store.createIndex('synced', 'synced', { unique: false });
          store.createIndex('createdAt', 'createdAt', { unique: false });
        }
      };
      
      request.onsuccess = (event) => {
        this.db = (event.target as IDBOpenDBRequest).result;
        resolve();
      };
      
      request.onerror = () => reject(request.error);
    });
  }
  
  async save(id: string, blob: Blob): Promise<void> {
    if (!this.db) await this.init();
    
    const entry: BlobEntry = {
      id,
      blob,
      type: blob.type,
      size: blob.size,
      createdAt: Date.now(),
      synced: false,
    };
    
    return new Promise((resolve, reject) => {
      const tx = this.db!.transaction(STORE_NAME, 'readwrite');
      tx.objectStore(STORE_NAME).put(entry);
      tx.oncomplete = () => resolve();
      tx.onerror = () => reject(tx.error);
    });
  }
  
  async get(id: string): Promise<Blob | null> {
    if (!this.db) await this.init();
    
    return new Promise((resolve, reject) => {
      const tx = this.db!.transaction(STORE_NAME, 'readonly');
      const request = tx.objectStore(STORE_NAME).get(id);
      request.onsuccess = () => {
        const entry = request.result as BlobEntry | undefined;
        resolve(entry?.blob ?? null);
      };
      request.onerror = () => reject(request.error);
    });
  }
  
  async getUnsynced(): Promise<BlobEntry[]> {
    if (!this.db) await this.init();
    
    return new Promise((resolve, reject) => {
      const tx = this.db!.transaction(STORE_NAME, 'readonly');
      const index = tx.objectStore(STORE_NAME).index('synced');
      const request = index.getAll(IDBKeyRange.only(false));
      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });
  }
  
  async markSynced(id: string): Promise<void> {
    if (!this.db) await this.init();
    
    return new Promise((resolve, reject) => {
      const tx = this.db!.transaction(STORE_NAME, 'readwrite');
      const store = tx.objectStore(STORE_NAME);
      const request = store.get(id);
      
      request.onsuccess = () => {
        const entry = request.result as BlobEntry;
        if (entry) {
          entry.synced = true;
          store.put(entry);
        }
      };
      
      tx.oncomplete = () => resolve();
      tx.onerror = () => reject(tx.error);
    });
  }
  
  async pruneOldSynced(keepCount: number = 50): Promise<number> {
    if (!this.db) await this.init();
    
    return new Promise((resolve, reject) => {
      const tx = this.db!.transaction(STORE_NAME, 'readwrite');
      const store = tx.objectStore(STORE_NAME);
      const index = store.index('synced');
      
      const request = index.openCursor(IDBKeyRange.only(true));
      const toDelete: string[] = [];
      let count = 0;
      
      request.onsuccess = (event) => {
        const cursor = (event.target as IDBRequest<IDBCursorWithValue>).result;
        if (cursor) {
          count++;
          if (count > keepCount) {
            toDelete.push(cursor.value.id);
          }
          cursor.continue();
        } else {
          // Delete collected entries
          toDelete.forEach(id => store.delete(id));
        }
      };
      
      tx.oncomplete = () => resolve(toDelete.length);
      tx.onerror = () => reject(tx.error);
    });
  }
}
```

---

## Configuration

### config/storage.ts

```typescript
export const STORAGE_CONFIG = {
  // Version key format
  VERSION_PREFIX: 'specmgmt_v',
  
  // Sync settings
  SYNC_RETRY_INTERVAL_MS: 5000,
  SYNC_MAX_RETRIES: 10,
  SYNC_DEBOUNCE_MS: 500,
  
  // Storage limits
  MAX_LOCALSTORAGE_MB: 10,
  PRUNE_THRESHOLD_PERCENT: 80,
  PRUNE_AMOUNT_PERCENT: 25,
  
  // Blob storage
  BLOB_SIZE_THRESHOLD_BYTES: 1024 * 1024, // 1MB
  MAX_BLOB_COUNT: 100,
  MAX_BLOB_SIZE_MB: 50,
  
  // Draft settings
  DRAFT_DEBOUNCE_MS: 100,
  DRAFT_MAX_AGE_HOURS: 24,
};
```

---

## Testing Strategy

### Unit Tests

| Test ID | Component | Scenario | Expected Result |
|---------|-----------|----------|-----------------|
| UT-1.1 | VersionedStorage | set/get roundtrip | Data retrieved matches stored |
| UT-1.2 | VersionedStorage | Version cleanup | Old keys removed, current preserved |
| UT-1.3 | VersionedStorage | Quota exceeded | Pruning occurs, write succeeds |
| UT-1.4 | SyncQueue | Enqueue operation | Entry added with correct metadata |
| UT-1.5 | SyncQueue | Process online | API called, entry removed on success |
| UT-1.6 | SyncQueue | Process offline | No API call, queue unchanged |
| UT-1.7 | SyncQueue | Retry on failure | Retry count incremented |
| UT-1.8 | BlobStorage | Save/get blob | Blob retrieved matches stored |

### Integration Tests

| Test ID | Scenario | Steps | Expected Result |
|---------|----------|-------|-----------------|
| IT-1.1 | Offline → Online | 1. Go offline 2. Save data 3. Come online | Data synced to backend |
| IT-1.2 | Network flapping | Rapidly toggle online/offline | No duplicate syncs, no data loss |
| IT-1.3 | Version upgrade | 1. Store with v1 2. Reload with v2 | Old data migrated, old keys removed |
| IT-1.4 | Quota recovery | Fill storage, try to write | Pruning occurs, write succeeds |

### E2E Tests

| Test ID | User Flow | Validation |
|---------|-----------|------------|
| E2E-1.1 | Type message, close tab, reopen | Draft text restored |
| E2E-1.2 | Record audio offline, go online | Audio synced to server |
| E2E-1.3 | Work offline for 10 mins, go online | All changes synced in order |

---

## Implementation Notes

### Dependencies Required
- `use-debounce` — For debounced draft saves
- No external storage library needed (native APIs)

### Browser Compatibility
- localStorage: All modern browsers
- IndexedDB: All modern browsers
- `navigator.onLine`: All modern browsers (may be inaccurate on some networks)

### Performance Considerations
- Debounce rapid writes to prevent thrashing
- Use Web Workers for large sync operations (future)
- Batch multiple operations into single API call (future)

---

## Related Specs

- [01-01-versioned-storage.md](./01-01-versioned-storage.md) — Detailed storage implementation
- [01-02-sync-queue.md](./01-02-sync-queue.md) — Queue manager details
- [01-03-storage-hooks.md](./01-03-storage-hooks.md) — React integration
- [01-04-sync-api.md](./01-04-sync-api.md) — Backend batch sync endpoint
- [State Management](../16-state-management/00-overview.md)
- [Voice Resilience](./02-voice-resilience.md) — Uses this storage system
