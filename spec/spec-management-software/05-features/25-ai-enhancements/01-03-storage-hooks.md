# Storage Hooks

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  
**Parent:** [Offline-First Storage](./01-offline-first-storage.md)

---

## Overview

React hooks providing easy access to offline-first storage patterns. Includes hooks for general storage, chat drafts, and sync status monitoring.

---

## useOfflineStorage Hook

Main hook for accessing versioned storage and sync queue.

```typescript
// hooks/useOfflineStorage.ts

import { useCallback, useEffect, useMemo, useState, useRef } from 'react';
import { VersionedStorage } from '@/lib/storage/VersionedStorage';
import { SyncQueue } from '@/lib/storage/SyncQueue';
import { EntityType, Operation, SyncQueueStats } from '@/lib/storage/sync-types';
import { APP_VERSION } from '@/config/version';
import { STORAGE_CONFIG } from '@/config/storage';

interface OfflineStorageState {
  isOnline: boolean;
  pendingSyncs: number;
  failedSyncs: number;
  isSyncing: boolean;
  lastSyncAt: number | null;
}

interface UseOfflineStorageReturn extends OfflineStorageState {
  storage: VersionedStorage;
  syncQueue: SyncQueue;
  saveWithSync: <T>(
    key: string,
    data: T,
    entityType: EntityType,
    operation?: Operation
  ) => void;
  getLocal: <T>(key: string) => T | null;
  removeLocal: (key: string) => void;
  forceSync: () => Promise<void>;
  getQueueStats: () => SyncQueueStats;
}

// Singleton instances to share across components
let storageInstance: VersionedStorage | null = null;
let syncQueueInstance: SyncQueue | null = null;

function getStorageInstance(): VersionedStorage {
  if (!storageInstance) {
    storageInstance = new VersionedStorage({
      version: APP_VERSION,
      namespace: 'specmgmt',
      onQuotaExceeded: () => {
        console.warn('[Storage] Quota exceeded, cleaning up...');
      },
      onVersionCleanup: (count) => {
        console.log(`[Storage] Cleaned up ${count} entries from previous version`);
      },
    });
  }
  return storageInstance;
}

function getSyncQueueInstance(storage: VersionedStorage): SyncQueue {
  if (!syncQueueInstance) {
    syncQueueInstance = new SyncQueue(storage, {
      retryIntervalMs: STORAGE_CONFIG.SYNC_RETRY_INTERVAL_MS,
      maxRetries: STORAGE_CONFIG.SYNC_MAX_RETRIES,
      exponentialBackoff: true,
    });
  }
  return syncQueueInstance;
}

export function useOfflineStorage(): UseOfflineStorageReturn {
  const [state, setState] = useState<OfflineStorageState>({
    isOnline: typeof navigator !== 'undefined' ? navigator.onLine : true,
    pendingSyncs: 0,
    failedSyncs: 0,
    isSyncing: false,
    lastSyncAt: null,
  });
  
  // Get singleton instances
  const storage = useMemo(() => getStorageInstance(), []);
  const syncQueue = useMemo(() => getSyncQueueInstance(storage), [storage]);
  
  // Track mounted state to avoid updates after unmount
  const mountedRef = useRef(true);
  
  useEffect(() => {
    mountedRef.current = true;
    return () => {
      mountedRef.current = false;
    };
  }, []);
  
  // Listen to online/offline events
  useEffect(() => {
    const handleOnline = () => {
      if (mountedRef.current) {
        setState(s => ({ ...s, isOnline: true }));
      }
    };
    
    const handleOffline = () => {
      if (mountedRef.current) {
        setState(s => ({ ...s, isOnline: false }));
      }
    };
    
    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);
    
    return () => {
      window.removeEventListener('online', handleOnline);
      window.removeEventListener('offline', handleOffline);
    };
  }, []);
  
  // Poll sync queue status
  useEffect(() => {
    const updateStats = () => {
      if (!mountedRef.current) return;
      
      const stats = syncQueue.getStats();
      setState(s => ({
        ...s,
        pendingSyncs: stats.pending,
        failedSyncs: stats.failed,
        isSyncing: stats.processing,
        lastSyncAt: stats.lastSyncAt,
      }));
    };
    
    // Initial update
    updateStats();
    
    // Poll every second
    const interval = setInterval(updateStats, 1000);
    
    return () => clearInterval(interval);
  }, [syncQueue]);
  
  /**
   * Save data locally and queue for sync
   */
  const saveWithSync = useCallback(
    <T>(
      key: string,
      data: T,
      entityType: EntityType,
      operation: Operation = 'create'
    ) => {
      // 1. Always save locally first (never fails)
      storage.set(key, data, false);
      
      // 2. Queue for backend sync
      syncQueue.enqueue(operation, entityType, key, data);
    },
    [storage, syncQueue]
  );
  
  /**
   * Get data from local storage
   */
  const getLocal = useCallback(
    <T>(key: string): T | null => {
      const entry = storage.get<T>(key);
      return entry?.data ?? null;
    },
    [storage]
  );
  
  /**
   * Remove data from local storage (also queues delete sync)
   */
  const removeLocal = useCallback(
    (key: string) => {
      storage.remove(key);
    },
    [storage]
  );
  
  /**
   * Force immediate sync
   */
  const forceSync = useCallback(async () => {
    await syncQueue.forceSync();
  }, [syncQueue]);
  
  /**
   * Get detailed queue statistics
   */
  const getQueueStats = useCallback(() => {
    return syncQueue.getStats();
  }, [syncQueue]);
  
  return {
    ...state,
    storage,
    syncQueue,
    saveWithSync,
    getLocal,
    removeLocal,
    forceSync,
    getQueueStats,
  };
}
```

---

## useChatDraft Hook

Specialized hook for persisting chat input drafts with debouncing.

```typescript
// hooks/useChatDraft.ts

import { useCallback, useEffect, useState, useRef } from 'react';
import { useOfflineStorage } from './useOfflineStorage';

interface ChatDraft {
  text: string;
  audioId?: string;        // Reference to audio in IndexedDB
  references?: string[];   // Attached file/URL references
  timestamp: number;
}

interface UseChatDraftReturn {
  draft: ChatDraft;
  updateText: (text: string) => void;
  attachAudio: (audioId: string) => void;
  addReference: (refId: string) => void;
  removeReference: (refId: string) => void;
  clearDraft: () => void;
  isOnline: boolean;
  hasDraft: boolean;
}

const DRAFT_DEBOUNCE_MS = 100;

export function useChatDraft(sessionId: string): UseChatDraftReturn {
  const { storage, isOnline } = useOfflineStorage();
  const [draft, setDraft] = useState<ChatDraft>({
    text: '',
    timestamp: 0,
  });
  
  const draftKey = `chat:draft:${sessionId}`;
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  
  // Load draft on mount
  useEffect(() => {
    const saved = storage.get<ChatDraft>(draftKey);
    if (saved?.data) {
      setDraft(saved.data);
    }
  }, [storage, draftKey]);
  
  // Debounced save function
  const saveDraft = useCallback(
    (newDraft: ChatDraft) => {
      if (debounceRef.current) {
        clearTimeout(debounceRef.current);
      }
      
      debounceRef.current = setTimeout(() => {
        storage.set(draftKey, newDraft, false);
      }, DRAFT_DEBOUNCE_MS);
    },
    [storage, draftKey]
  );
  
  // Cleanup debounce on unmount
  useEffect(() => {
    return () => {
      if (debounceRef.current) {
        clearTimeout(debounceRef.current);
      }
    };
  }, []);
  
  /**
   * Update text content (called on every keystroke)
   */
  const updateText = useCallback(
    (text: string) => {
      const newDraft: ChatDraft = {
        ...draft,
        text,
        timestamp: Date.now(),
      };
      setDraft(newDraft);
      saveDraft(newDraft);
    },
    [draft, saveDraft]
  );
  
  /**
   * Attach audio recording reference
   */
  const attachAudio = useCallback(
    (audioId: string) => {
      const newDraft: ChatDraft = {
        ...draft,
        audioId,
        timestamp: Date.now(),
      };
      setDraft(newDraft);
      saveDraft(newDraft);
    },
    [draft, saveDraft]
  );
  
  /**
   * Add file/URL reference
   */
  const addReference = useCallback(
    (refId: string) => {
      const newDraft: ChatDraft = {
        ...draft,
        references: [...(draft.references || []), refId],
        timestamp: Date.now(),
      };
      setDraft(newDraft);
      saveDraft(newDraft);
    },
    [draft, saveDraft]
  );
  
  /**
   * Remove reference
   */
  const removeReference = useCallback(
    (refId: string) => {
      const newDraft: ChatDraft = {
        ...draft,
        references: (draft.references || []).filter(r => r !== refId),
        timestamp: Date.now(),
      };
      setDraft(newDraft);
      saveDraft(newDraft);
    },
    [draft, saveDraft]
  );
  
  /**
   * Clear draft after successful send
   */
  const clearDraft = useCallback(() => {
    if (debounceRef.current) {
      clearTimeout(debounceRef.current);
    }
    storage.remove(draftKey);
    setDraft({ text: '', timestamp: 0 });
  }, [storage, draftKey]);
  
  const hasDraft = draft.text.length > 0 || !!draft.audioId || (draft.references?.length ?? 0) > 0;
  
  return {
    draft,
    updateText,
    attachAudio,
    addReference,
    removeReference,
    clearDraft,
    isOnline,
    hasDraft,
  };
}
```

---

## useSyncStatus Hook

Hook for displaying sync status in UI components.

```typescript
// hooks/useSyncStatus.ts

import { useMemo } from 'react';
import { useOfflineStorage } from './useOfflineStorage';

type SyncStatusType = 'synced' | 'syncing' | 'pending' | 'offline' | 'error';

interface UseSyncStatusReturn {
  status: SyncStatusType;
  message: string;
  pendingCount: number;
  failedCount: number;
  canRetry: boolean;
  retry: () => Promise<void>;
}

export function useSyncStatus(): UseSyncStatusReturn {
  const {
    isOnline,
    pendingSyncs,
    failedSyncs,
    isSyncing,
    forceSync,
  } = useOfflineStorage();
  
  const status = useMemo((): SyncStatusType => {
    if (!isOnline) return 'offline';
    if (failedSyncs > 0) return 'error';
    if (isSyncing) return 'syncing';
    if (pendingSyncs > 0) return 'pending';
    return 'synced';
  }, [isOnline, failedSyncs, isSyncing, pendingSyncs]);
  
  const message = useMemo((): string => {
    switch (status) {
      case 'offline':
        return 'Offline - changes saved locally';
      case 'error':
        return `${failedSyncs} sync${failedSyncs > 1 ? 's' : ''} failed`;
      case 'syncing':
        return `Syncing ${pendingSyncs} change${pendingSyncs > 1 ? 's' : ''}...`;
      case 'pending':
        return `${pendingSyncs} change${pendingSyncs > 1 ? 's' : ''} pending`;
      case 'synced':
        return 'All changes synced';
    }
  }, [status, pendingSyncs, failedSyncs]);
  
  return {
    status,
    message,
    pendingCount: pendingSyncs,
    failedCount: failedSyncs,
    canRetry: failedSyncs > 0 && isOnline,
    retry: forceSync,
  };
}
```

---

## usePersistedState Hook

Generic hook for persisting any state to localStorage with sync.

```typescript
// hooks/usePersistedState.ts

import { useState, useCallback, useEffect } from 'react';
import { useOfflineStorage } from './useOfflineStorage';
import { EntityType } from '@/lib/storage/sync-types';

interface UsePersistedStateOptions<T> {
  key: string;
  entityType: EntityType;
  defaultValue: T;
  syncOnChange?: boolean;
}

export function usePersistedState<T>({
  key,
  entityType,
  defaultValue,
  syncOnChange = true,
}: UsePersistedStateOptions<T>): [T, (value: T | ((prev: T) => T)) => void] {
  const { storage, saveWithSync } = useOfflineStorage();
  
  // Initialize with stored value or default
  const [value, setValue] = useState<T>(() => {
    const stored = storage.get<T>(key);
    return stored?.data ?? defaultValue;
  });
  
  // Persist on change
  const setPersistedValue = useCallback(
    (newValue: T | ((prev: T) => T)) => {
      setValue(prev => {
        const resolved = typeof newValue === 'function'
          ? (newValue as (prev: T) => T)(prev)
          : newValue;
        
        if (syncOnChange) {
          saveWithSync(key, resolved, entityType, 'update');
        } else {
          storage.set(key, resolved, false);
        }
        
        return resolved;
      });
    },
    [key, entityType, syncOnChange, saveWithSync, storage]
  );
  
  return [value, setPersistedValue];
}
```

---

## Usage Examples

### Basic Storage

```tsx
function ChatInput({ sessionId }: { sessionId: string }) {
  const { draft, updateText, clearDraft, isOnline } = useChatDraft(sessionId);
  
  const handleSend = async () => {
    await sendMessage(draft.text);
    clearDraft();
  };
  
  return (
    <div>
      <textarea
        value={draft.text}
        onChange={(e) => updateText(e.target.value)}
        placeholder="Type a message..."
      />
      <button onClick={handleSend}>Send</button>
      {!isOnline && <span>Offline - will sync when online</span>}
    </div>
  );
}
```

### Sync Status Indicator

```tsx
function SyncIndicator() {
  const { status, message, canRetry, retry } = useSyncStatus();
  
  const icons = {
    synced: <CloudCheck />,
    syncing: <Loader className="animate-spin" />,
    pending: <CloudUpload />,
    offline: <CloudOff />,
    error: <AlertCircle />,
  };
  
  return (
    <div className="flex items-center gap-2">
      {icons[status]}
      <span>{message}</span>
      {canRetry && <button onClick={retry}>Retry</button>}
    </div>
  );
}
```

### Persisted Settings

```tsx
function ThemeToggle() {
  const [theme, setTheme] = usePersistedState({
    key: 'settings:theme',
    entityType: 'settings',
    defaultValue: 'system',
    syncOnChange: true,
  });
  
  return (
    <select value={theme} onChange={(e) => setTheme(e.target.value)}>
      <option value="light">Light</option>
      <option value="dark">Dark</option>
      <option value="system">System</option>
    </select>
  );
}
```

---

## Testing Checklist

| Test | Description | Priority |
|------|-------------|----------|
| useOfflineStorage init | Singleton instances created | Critical |
| saveWithSync | Data saved locally and queued | Critical |
| useChatDraft persistence | Draft survives refresh | Critical |
| Debounce behavior | Rapid input doesn't thrash storage | High |
| Online/offline detection | State updates correctly | High |
| useSyncStatus | Correct status derived | Medium |
| usePersistedState | Generic persistence works | Medium |

---

## Related Specs

- [Offline-First Storage](./01-offline-first-storage.md)
- [Versioned Storage](./01-01-versioned-storage.md)
- [Sync Queue](./01-02-sync-queue.md)
