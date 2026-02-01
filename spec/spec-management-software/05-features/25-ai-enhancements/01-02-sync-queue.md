# Sync Queue

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  
**Parent:** [Offline-First Storage](./01-offline-first-storage.md)

---

## Overview

Background sync queue manager that persists operations during offline periods and automatically syncs when connection is restored. Supports retry logic, exponential backoff, and batch operations.

---

## Interface Definition

```typescript
// lib/storage/sync-types.ts

export type Operation = 'create' | 'update' | 'delete';

export type EntityType = 
  | 'message'     // Chat messages
  | 'audio'       // Voice recordings
  | 'file'        // Spec files
  | 'plan'        // Execution plans
  | 'memory'      // Knowledge memory items
  | 'settings';   // User settings

export interface QueuedOperation {
  id: string;                    // Unique operation ID
  operation: Operation;          // CRUD operation type
  entityType: EntityType;        // Target entity type
  entityId: string;              // Target entity ID
  payload: unknown;              // Data to sync
  createdAt: number;             // Queue timestamp
  retries: number;               // Retry count
  lastAttempt?: number;          // Last retry timestamp
  lastError?: string;            // Last error message
  priority: number;              // 0 = highest priority
}

export interface SyncResult {
  success: boolean;
  error?: string;
  statusCode?: number;
  retryable?: boolean;
}

export interface SyncQueueConfig {
  retryIntervalMs: number;       // Base retry interval
  maxRetries: number;            // Max attempts before giving up
  batchSize: number;             // Max operations per batch
  exponentialBackoff: boolean;   // Enable exponential backoff
  maxBackoffMs: number;          // Cap for exponential backoff
  onSyncStart?: () => void;
  onSyncComplete?: (results: SyncResult[]) => void;
  onSyncError?: (error: Error) => void;
  onQueueChange?: (count: number) => void;
}

export interface SyncQueueStats {
  pending: number;
  failed: number;
  processing: boolean;
  lastSyncAt: number | null;
  nextRetryAt: number | null;
}
```

---

## Class Implementation

```typescript
// lib/storage/SyncQueue.ts

import { VersionedStorage } from './VersionedStorage';
import {
  Operation,
  EntityType,
  QueuedOperation,
  SyncResult,
  SyncQueueConfig,
  SyncQueueStats,
} from './sync-types';

const DEFAULT_CONFIG: SyncQueueConfig = {
  retryIntervalMs: 5000,
  maxRetries: 10,
  batchSize: 10,
  exponentialBackoff: true,
  maxBackoffMs: 60000, // 1 minute max
};

export class SyncQueue {
  private readonly storage: VersionedStorage;
  private readonly config: SyncQueueConfig;
  private readonly queueKey = 'sync:queue';
  
  private isProcessing = false;
  private retryTimeout: ReturnType<typeof setTimeout> | null = null;
  private lastSyncAt: number | null = null;
  private onlineDebounceTimeout: ReturnType<typeof setTimeout> | null = null;
  
  constructor(storage: VersionedStorage, config: Partial<SyncQueueConfig> = {}) {
    this.storage = storage;
    this.config = { ...DEFAULT_CONFIG, ...config };
    this.setupNetworkListeners();
  }
  
  // ─────────────────────────────────────────────────────────────────
  // PUBLIC METHODS
  // ─────────────────────────────────────────────────────────────────
  
  /**
   * Add operation to sync queue
   */
  enqueue(
    operation: Operation,
    entityType: EntityType,
    entityId: string,
    payload: unknown,
    priority: number = 5
  ): string {
    const queue = this.getQueue();
    const id = crypto.randomUUID();
    
    const queuedOp: QueuedOperation = {
      id,
      operation,
      entityType,
      entityId,
      payload,
      createdAt: Date.now(),
      retries: 0,
      priority,
    };
    
    queue.push(queuedOp);
    this.saveQueue(queue);
    
    this.config.onQueueChange?.(queue.length);
    
    // Trigger sync if online
    if (navigator.onLine) {
      this.processQueue();
    }
    
    return id;
  }
  
  /**
   * Remove operation from queue (e.g., user cancelled)
   */
  dequeue(operationId: string): boolean {
    const queue = this.getQueue();
    const index = queue.findIndex(op => op.id === operationId);
    
    if (index !== -1) {
      queue.splice(index, 1);
      this.saveQueue(queue);
      this.config.onQueueChange?.(queue.length);
      return true;
    }
    
    return false;
  }
  
  /**
   * Process all pending operations
   */
  async processQueue(): Promise<void> {
    if (this.isProcessing || !navigator.onLine) {
      return;
    }
    
    this.isProcessing = true;
    this.config.onSyncStart?.();
    
    const queue = this.getQueue();
    const results: SyncResult[] = [];
    
    // Sort by priority (lower = higher priority), then by timestamp
    queue.sort((a, b) => {
      if (a.priority !== b.priority) return a.priority - b.priority;
      return a.createdAt - b.createdAt;
    });
    
    // Process in batches
    const batch = queue.slice(0, this.config.batchSize);
    
    for (const op of batch) {
      // Skip if max retries exceeded
      if (op.retries >= this.config.maxRetries) {
        continue;
      }
      
      const result = await this.executeOperation(op);
      results.push(result);
      
      if (result.success) {
        this.removeFromQueue(op.id);
      } else {
        this.handleFailure(op, result);
      }
    }
    
    this.lastSyncAt = Date.now();
    this.isProcessing = false;
    this.config.onSyncComplete?.(results);
    
    // Schedule retry if there are still pending items
    const remaining = this.getQueue();
    if (remaining.length > 0 && navigator.onLine) {
      this.scheduleRetry();
    }
  }
  
  /**
   * Force immediate sync attempt
   */
  async forceSync(): Promise<void> {
    this.cancelRetry();
    await this.processQueue();
  }
  
  /**
   * Get queue statistics
   */
  getStats(): SyncQueueStats {
    const queue = this.getQueue();
    const failed = queue.filter(op => op.retries >= this.config.maxRetries);
    
    return {
      pending: queue.length,
      failed: failed.length,
      processing: this.isProcessing,
      lastSyncAt: this.lastSyncAt,
      nextRetryAt: this.retryTimeout ? Date.now() + this.config.retryIntervalMs : null,
    };
  }
  
  /**
   * Get number of pending operations
   */
  getPendingCount(): number {
    return this.getQueue().length;
  }
  
  /**
   * Get operations that have exceeded max retries
   */
  getFailedOperations(): QueuedOperation[] {
    return this.getQueue().filter(op => op.retries >= this.config.maxRetries);
  }
  
  /**
   * Retry a failed operation
   */
  retryFailed(operationId: string): void {
    const queue = this.getQueue();
    const index = queue.findIndex(op => op.id === operationId);
    
    if (index !== -1) {
      queue[index].retries = 0;
      queue[index].lastError = undefined;
      this.saveQueue(queue);
      this.processQueue();
    }
  }
  
  /**
   * Clear all failed operations
   */
  clearFailed(): number {
    const queue = this.getQueue();
    const original = queue.length;
    const filtered = queue.filter(op => op.retries < this.config.maxRetries);
    this.saveQueue(filtered);
    this.config.onQueueChange?.(filtered.length);
    return original - filtered.length;
  }
  
  /**
   * Cleanup and stop all timers
   */
  destroy(): void {
    this.cancelRetry();
    this.removeNetworkListeners();
  }
  
  // ─────────────────────────────────────────────────────────────────
  // PRIVATE METHODS
  // ─────────────────────────────────────────────────────────────────
  
  private getQueue(): QueuedOperation[] {
    const entry = this.storage.get<QueuedOperation[]>(this.queueKey);
    return entry?.data ?? [];
  }
  
  private saveQueue(queue: QueuedOperation[]): void {
    this.storage.set(this.queueKey, queue, true); // Queue itself is always "synced"
  }
  
  private removeFromQueue(id: string): void {
    const queue = this.getQueue().filter(op => op.id !== id);
    this.saveQueue(queue);
    this.config.onQueueChange?.(queue.length);
  }
  
  private async executeOperation(op: QueuedOperation): Promise<SyncResult> {
    const endpoint = this.getEndpoint(op.entityType, op.operation, op.entityId);
    const method = this.getMethod(op.operation);
    
    try {
      const response = await fetch(endpoint, {
        method,
        headers: {
          'Content-Type': 'application/json',
          // Add auth headers here
        },
        body: method !== 'DELETE' ? JSON.stringify(op.payload) : undefined,
      });
      
      if (!response.ok) {
        const retryable = response.status >= 500 || response.status === 429;
        return {
          success: false,
          error: `HTTP ${response.status}: ${response.statusText}`,
          statusCode: response.status,
          retryable,
        };
      }
      
      return { success: true, statusCode: response.status };
    } catch (error) {
      return {
        success: false,
        error: error instanceof Error ? error.message : 'Network error',
        retryable: true,
      };
    }
  }
  
  private handleFailure(op: QueuedOperation, result: SyncResult): void {
    const queue = this.getQueue();
    const index = queue.findIndex(q => q.id === op.id);
    
    if (index !== -1) {
      queue[index].retries++;
      queue[index].lastAttempt = Date.now();
      queue[index].lastError = result.error;
      
      // Don't retry non-retryable errors
      if (result.retryable === false) {
        queue[index].retries = this.config.maxRetries;
      }
      
      this.saveQueue(queue);
    }
  }
  
  private scheduleRetry(): void {
    if (this.retryTimeout) return;
    
    // Calculate backoff
    const queue = this.getQueue();
    const maxRetries = Math.max(...queue.map(op => op.retries), 0);
    
    let delay = this.config.retryIntervalMs;
    if (this.config.exponentialBackoff && maxRetries > 0) {
      delay = Math.min(
        this.config.retryIntervalMs * Math.pow(2, maxRetries - 1),
        this.config.maxBackoffMs
      );
    }
    
    this.retryTimeout = setTimeout(() => {
      this.retryTimeout = null;
      this.processQueue();
    }, delay);
  }
  
  private cancelRetry(): void {
    if (this.retryTimeout) {
      clearTimeout(this.retryTimeout);
      this.retryTimeout = null;
    }
  }
  
  private setupNetworkListeners(): void {
    window.addEventListener('online', this.handleOnline);
    window.addEventListener('offline', this.handleOffline);
  }
  
  private removeNetworkListeners(): void {
    window.removeEventListener('online', this.handleOnline);
    window.removeEventListener('offline', this.handleOffline);
  }
  
  private handleOnline = (): void => {
    // Debounce to avoid rapid online/offline toggling
    if (this.onlineDebounceTimeout) {
      clearTimeout(this.onlineDebounceTimeout);
    }
    
    this.onlineDebounceTimeout = setTimeout(() => {
      console.log('[SyncQueue] Online - processing queue');
      this.processQueue();
    }, 500);
  };
  
  private handleOffline = (): void => {
    console.log('[SyncQueue] Offline - pausing sync');
    this.cancelRetry();
  };
  
  private getEndpoint(
    entityType: EntityType,
    operation: Operation,
    entityId: string
  ): string {
    const base = '/api/v1';
    const endpoints: Record<EntityType, string> = {
      message: `${base}/messages`,
      audio: `${base}/audio`,
      file: `${base}/files`,
      plan: `${base}/plans`,
      memory: `${base}/memory`,
      settings: `${base}/settings`,
    };
    
    if (operation === 'create') {
      return endpoints[entityType];
    }
    return `${endpoints[entityType]}/${entityId}`;
  }
  
  private getMethod(operation: Operation): string {
    const methods: Record<Operation, string> = {
      create: 'POST',
      update: 'PUT',
      delete: 'DELETE',
    };
    return methods[operation];
  }
}
```

---

## Usage Examples

### Basic Usage

```typescript
import { VersionedStorage } from '@/lib/storage/VersionedStorage';
import { SyncQueue } from '@/lib/storage/SyncQueue';

const storage = new VersionedStorage({ version: '1.0.0', namespace: 'specmgmt' });
const syncQueue = new SyncQueue(storage);

// Queue a create operation
const opId = syncQueue.enqueue(
  'create',
  'message',
  'msg_123',
  { text: 'Hello world', timestamp: Date.now() }
);

// Check status
const stats = syncQueue.getStats();
console.log(`Pending: ${stats.pending}, Failed: ${stats.failed}`);

// Force sync
await syncQueue.forceSync();
```

### With Callbacks

```typescript
const syncQueue = new SyncQueue(storage, {
  retryIntervalMs: 3000,
  maxRetries: 5,
  onSyncStart: () => console.log('Sync started...'),
  onSyncComplete: (results) => {
    const failed = results.filter(r => !r.success);
    if (failed.length) {
      toast.error(`${failed.length} operations failed to sync`);
    }
  },
  onQueueChange: (count) => {
    updateBadge(count); // Update UI badge with pending count
  },
});
```

### Priority Operations

```typescript
// High priority (0 = highest)
syncQueue.enqueue('create', 'message', 'msg_urgent', data, 0);

// Normal priority
syncQueue.enqueue('create', 'file', 'file_123', data, 5);

// Low priority (background sync)
syncQueue.enqueue('update', 'settings', 'user_prefs', data, 10);
```

---

## Batch Sync API (Backend)

For efficiency, the backend should support batch operations:

```typescript
// POST /api/v1/sync/batch
interface BatchSyncRequest {
  operations: Array<{
    id: string;
    operation: 'create' | 'update' | 'delete';
    entityType: string;
    entityId: string;
    payload: unknown;
  }>;
}

interface BatchSyncResponse {
  results: Array<{
    id: string;
    success: boolean;
    error?: string;
  }>;
}
```

---

## Testing Checklist

| Test | Description | Priority |
|------|-------------|----------|
| Enqueue operation | Operation added with correct metadata | Critical |
| Process online | API called, success removes from queue | Critical |
| Process offline | No API call, queue unchanged | Critical |
| Retry on failure | Retry count incremented, backoff applied | High |
| Max retries | Operation marked as failed, not retried | High |
| Network flapping | Debounced, no duplicate syncs | High |
| Priority ordering | Higher priority processed first | Medium |
| Force sync | Immediate processing, bypass timers | Medium |
| Clear failed | Removes only failed operations | Medium |

---

## Related Specs

- [Offline-First Storage](./01-offline-first-storage.md)
- [Versioned Storage](./01-01-versioned-storage.md)
- [Storage Hooks](./01-03-storage-hooks.md)
