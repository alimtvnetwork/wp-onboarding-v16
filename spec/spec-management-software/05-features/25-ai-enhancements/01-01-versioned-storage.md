# Versioned Storage

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  
**Parent:** [Offline-First Storage](./01-offline-first-storage.md)

---

## Overview

Core localStorage wrapper providing version-keyed storage with automatic cleanup of old versions, quota management, and sync status tracking.

---

## Interface Definition

```typescript
// lib/storage/types.ts

export interface StorageConfig {
  version: string;      // App version (e.g., "1.2.0")
  namespace: string;    // App namespace (e.g., "specmgmt")
  onQuotaExceeded?: () => void;
  onVersionCleanup?: (removedCount: number) => void;
}

export interface StorageEntry<T> {
  data: T;              // The stored payload
  timestamp: number;    // Unix timestamp (ms)
  synced: boolean;      // Backend sync status
  version: number;      // Entry schema version
  checksum?: string;    // Integrity verification
}

export interface StorageStats {
  totalEntries: number;
  syncedEntries: number;
  unsyncedEntries: number;
  estimatedSizeBytes: number;
  oldestEntry: number | null;
  newestEntry: number | null;
}
```

---

## Class Implementation

```typescript
// lib/storage/VersionedStorage.ts

import { StorageConfig, StorageEntry, StorageStats } from './types';

export class VersionedStorage {
  private readonly rootKey: string;
  private readonly version: string;
  private readonly config: StorageConfig;
  
  constructor(config: StorageConfig) {
    this.config = config;
    this.version = config.version;
    this.rootKey = `${config.namespace}_v${config.version}`;
    
    // Run cleanup on initialization
    this.cleanupOldVersions();
  }
  
  // ─────────────────────────────────────────────────────────────────
  // PUBLIC METHODS
  // ─────────────────────────────────────────────────────────────────
  
  /**
   * Store data with automatic retry on quota exceeded
   */
  set<T>(key: string, data: T, synced = false): boolean {
    const entry: StorageEntry<T> = {
      data,
      timestamp: Date.now(),
      synced,
      version: 1,
    };
    
    const fullKey = this.getKey(key);
    const serialized = JSON.stringify(entry);
    
    try {
      localStorage.setItem(fullKey, serialized);
      return true;
    } catch (e) {
      if (this.isQuotaExceeded(e)) {
        this.handleQuotaExceeded();
        // Retry once after cleanup
        try {
          localStorage.setItem(fullKey, serialized);
          return true;
        } catch {
          console.error('[VersionedStorage] Write failed after cleanup:', key);
          return false;
        }
      }
      console.error('[VersionedStorage] Unexpected write error:', e);
      return false;
    }
  }
  
  /**
   * Retrieve data with error handling
   */
  get<T>(key: string): StorageEntry<T> | null {
    const fullKey = this.getKey(key);
    const raw = localStorage.getItem(fullKey);
    
    if (!raw) return null;
    
    try {
      return JSON.parse(raw) as StorageEntry<T>;
    } catch (e) {
      console.warn('[VersionedStorage] Corrupted entry:', key, e);
      // Quarantine corrupted entry
      this.quarantine(fullKey, raw);
      return null;
    }
  }
  
  /**
   * Remove a single entry
   */
  remove(key: string): void {
    localStorage.removeItem(this.getKey(key));
  }
  
  /**
   * Check if key exists
   */
  has(key: string): boolean {
    return localStorage.getItem(this.getKey(key)) !== null;
  }
  
  /**
   * Mark entry as synced to backend
   */
  markSynced(key: string): boolean {
    const entry = this.get(key);
    if (entry) {
      return this.set(key, entry.data, true);
    }
    return false;
  }
  
  /**
   * Get all unsynced entries for background sync
   */
  getUnsynced(): Array<{ key: string; entry: StorageEntry<unknown> }> {
    const unsynced: Array<{ key: string; entry: StorageEntry<unknown> }> = [];
    
    this.forEachEntry((key, entry) => {
      if (!entry.synced) {
        unsynced.push({ key, entry });
      }
    });
    
    // Sort by timestamp (oldest first for FIFO sync)
    return unsynced.sort((a, b) => a.entry.timestamp - b.entry.timestamp);
  }
  
  /**
   * Get storage statistics
   */
  getStats(): StorageStats {
    let totalEntries = 0;
    let syncedEntries = 0;
    let unsyncedEntries = 0;
    let estimatedSizeBytes = 0;
    let oldestEntry: number | null = null;
    let newestEntry: number | null = null;
    
    this.forEachEntry((key, entry, raw) => {
      totalEntries++;
      if (entry.synced) {
        syncedEntries++;
      } else {
        unsyncedEntries++;
      }
      estimatedSizeBytes += raw.length * 2; // UTF-16 encoding
      
      if (oldestEntry === null || entry.timestamp < oldestEntry) {
        oldestEntry = entry.timestamp;
      }
      if (newestEntry === null || entry.timestamp > newestEntry) {
        newestEntry = entry.timestamp;
      }
    });
    
    return {
      totalEntries,
      syncedEntries,
      unsyncedEntries,
      estimatedSizeBytes,
      oldestEntry,
      newestEntry,
    };
  }
  
  /**
   * Clear all entries for current version
   */
  clear(): void {
    const keysToRemove: string[] = [];
    
    for (let i = 0; i < localStorage.length; i++) {
      const key = localStorage.key(i);
      if (key?.startsWith(this.rootKey)) {
        keysToRemove.push(key);
      }
    }
    
    keysToRemove.forEach(key => localStorage.removeItem(key));
  }
  
  // ─────────────────────────────────────────────────────────────────
  // PRIVATE METHODS
  // ─────────────────────────────────────────────────────────────────
  
  private getKey(subKey: string): string {
    return `${this.rootKey}:${subKey}`;
  }
  
  private extractSubKey(fullKey: string): string {
    return fullKey.replace(`${this.rootKey}:`, '');
  }
  
  /**
   * Remove entries from previous app versions
   */
  private cleanupOldVersions(): void {
    const prefix = `${this.config.namespace}_v`;
    const keysToRemove: string[] = [];
    
    for (let i = 0; i < localStorage.length; i++) {
      const key = localStorage.key(i);
      if (key?.startsWith(prefix) && !key.startsWith(this.rootKey)) {
        keysToRemove.push(key);
      }
    }
    
    keysToRemove.forEach(key => localStorage.removeItem(key));
    
    if (keysToRemove.length > 0) {
      console.log(`[VersionedStorage] Cleaned up ${keysToRemove.length} old version keys`);
      this.config.onVersionCleanup?.(keysToRemove.length);
    }
  }
  
  /**
   * Handle quota exceeded by pruning old synced entries
   */
  private handleQuotaExceeded(): void {
    console.warn('[VersionedStorage] Quota exceeded, pruning old entries...');
    this.config.onQuotaExceeded?.();
    
    // Collect all entries with metadata
    const entries: Array<{
      key: string;
      timestamp: number;
      synced: boolean;
      size: number;
    }> = [];
    
    for (let i = 0; i < localStorage.length; i++) {
      const key = localStorage.key(i);
      if (key?.startsWith(this.rootKey)) {
        const raw = localStorage.getItem(key);
        if (raw) {
          try {
            const entry = JSON.parse(raw) as StorageEntry<unknown>;
            entries.push({
              key,
              timestamp: entry.timestamp,
              synced: entry.synced,
              size: raw.length * 2,
            });
          } catch {
            // Remove corrupted entries
            localStorage.removeItem(key);
          }
        }
      }
    }
    
    // Sort: synced first, then by timestamp (oldest first)
    entries.sort((a, b) => {
      if (a.synced !== b.synced) return a.synced ? -1 : 1;
      return a.timestamp - b.timestamp;
    });
    
    // Remove oldest 25% of synced entries
    const syncedEntries = entries.filter(e => e.synced);
    const removeCount = Math.ceil(syncedEntries.length * 0.25);
    
    for (let i = 0; i < removeCount && i < syncedEntries.length; i++) {
      localStorage.removeItem(syncedEntries[i].key);
    }
    
    console.log(`[VersionedStorage] Pruned ${removeCount} synced entries`);
  }
  
  /**
   * Move corrupted entry to quarantine for debugging
   */
  private quarantine(key: string, rawValue: string): void {
    const quarantineKey = `${this.config.namespace}_quarantine:${Date.now()}:${key}`;
    try {
      localStorage.setItem(quarantineKey, rawValue);
      localStorage.removeItem(key);
    } catch {
      // Just remove if we can't quarantine
      localStorage.removeItem(key);
    }
  }
  
  /**
   * Check if error is quota exceeded
   */
  private isQuotaExceeded(e: unknown): boolean {
    return (
      e instanceof DOMException &&
      (e.name === 'QuotaExceededError' || e.code === 22)
    );
  }
  
  /**
   * Iterate over all entries for current version
   */
  private forEachEntry(
    callback: (key: string, entry: StorageEntry<unknown>, raw: string) => void
  ): void {
    for (let i = 0; i < localStorage.length; i++) {
      const fullKey = localStorage.key(i);
      if (fullKey?.startsWith(this.rootKey)) {
        const raw = localStorage.getItem(fullKey);
        if (raw) {
          try {
            const entry = JSON.parse(raw) as StorageEntry<unknown>;
            const subKey = this.extractSubKey(fullKey);
            callback(subKey, entry, raw);
          } catch {
            // Skip corrupted entries
          }
        }
      }
    }
  }
}
```

---

## Usage Examples

### Basic Usage

```typescript
import { VersionedStorage } from '@/lib/storage/VersionedStorage';
import { APP_VERSION } from '@/config/version';

const storage = new VersionedStorage({
  version: APP_VERSION,
  namespace: 'specmgmt',
});

// Save data (will be marked as unsynced)
storage.set('user:preferences', { theme: 'dark', fontSize: 14 });

// Retrieve data
const prefs = storage.get('user:preferences');
console.log(prefs?.data); // { theme: 'dark', fontSize: 14 }

// Mark as synced after successful API call
storage.markSynced('user:preferences');

// Get all unsynced entries for background sync
const pending = storage.getUnsynced();
console.log(`${pending.length} entries waiting to sync`);
```

### With Callbacks

```typescript
const storage = new VersionedStorage({
  version: '1.2.0',
  namespace: 'specmgmt',
  onQuotaExceeded: () => {
    toast.warning('Storage nearly full, old data cleaned up');
  },
  onVersionCleanup: (count) => {
    console.log(`Migrated from previous version, removed ${count} old entries`);
  },
});
```

---

## Testing Checklist

| Test | Description | Priority |
|------|-------------|----------|
| set/get roundtrip | Data integrity preserved | Critical |
| Version cleanup | Old version keys removed | Critical |
| Quota exceeded | Pruning occurs correctly | High |
| Corrupted entry | Returns null, quarantines entry | High |
| markSynced | Updates sync flag correctly | High |
| getUnsynced | Returns only unsynced, sorted by timestamp | High |
| getStats | Accurate counts and sizes | Medium |
| clear | Removes all current version entries | Medium |

---

## Related Specs

- [Offline-First Storage](./01-offline-first-storage.md)
- [Sync Queue](./01-02-sync-queue.md)
