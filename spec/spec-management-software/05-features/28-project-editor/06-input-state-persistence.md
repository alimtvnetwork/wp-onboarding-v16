# Input State Persistence

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-30  
**Parent:** [Project Editor](./00-overview.md)

---

## Purpose

Define a Lovable-style input persistence system that **never loses user input**. All text typed into input fields, chat boxes, and editors persists across tab switches, project changes, and browser sessions.

---

## Core Principle

> **"If you type it, we save it."**

Every keystroke is preserved. Users should never lose work due to:
- Switching tabs
- Changing projects
- Browser refresh
- Accidental navigation
- Session timeout

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                  INPUT STATE PERSISTENCE                         │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────────┐   ┌──────────────────┐                    │
│  │  Input Monitor   │──▶│  State Manager   │                    │
│  │                  │   │                  │                    │
│  │  • onChange      │   │  • Debounce      │                    │
│  │  • onBlur        │   │  • Serialize     │                    │
│  │  • beforeUnload  │   │  • Encrypt       │                    │
│  └──────────────────┘   └────────┬─────────┘                    │
│                                  │                               │
│         ┌────────────────────────┼────────────────────────┐     │
│         │                        │                        │     │
│         ▼                        ▼                        ▼     │
│  ┌──────────────┐   ┌──────────────────┐   ┌──────────────┐    │
│  │  LocalStorage│   │    IndexedDB     │   │   Backend    │    │
│  │  (< 1KB)     │   │   (> 1KB)        │   │   (Sync)     │    │
│  └──────────────┘   └──────────────────┘   └──────────────┘    │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Storage Strategy

### Tiered Storage

| Data Size | Storage | Key Format |
|-----------|---------|------------|
| < 1KB | localStorage | `specmgmt_v{version}_{inputId}` |
| 1KB - 5MB | IndexedDB | Same key, different store |
| > 5MB | Not supported (warn user) |

### State Keys

```typescript
enum InputType {
  ChatInput = 'chat',
  FileEditor = 'editor',
  SearchBox = 'search',
  FormField = 'form',
  AIPanelInput = 'ai_panel',
  ModalInput = 'modal',
}

interface InputStateKey {
  readonly type: InputType;
  readonly projectId: string;
  readonly contextId: string; // Tab ID, file path, form ID, etc.
  readonly fieldId: string;   // Specific field within context
}

// Key format: specmgmt_v1_chat_proj123_main_message
function buildKey(key: InputStateKey): string {
  return `specmgmt_v${VERSION}_${key.type}_${key.projectId}_${key.contextId}_${key.fieldId}`;
}
```

---

## Input Monitor Hook

```typescript
import { useState, useCallback, useEffect, useRef } from 'react';

interface UsePersistedInputOptions {
  readonly type: InputType;
  readonly projectId: string;
  readonly contextId: string;
  readonly fieldId: string;
  readonly debounceMs?: number;
  readonly onRestore?: (value: string) => void;
}

interface UsePersistedInputReturn {
  readonly value: string;
  readonly setValue: (value: string) => void;
  readonly isRestored: boolean;
  readonly clear: () => void;
}

export function usePersistedInput(options: UsePersistedInputOptions): UsePersistedInputReturn {
  const {
    type,
    projectId,
    contextId,
    fieldId,
    debounceMs = 100,
    onRestore,
  } = options;
  
  const key = buildKey({ type, projectId, contextId, fieldId });
  const [value, setValueInternal] = useState<string>('');
  const [isRestored, setIsRestored] = useState(false);
  const debounceRef = useRef<NodeJS.Timeout | null>(null);
  
  // Restore on mount
  useEffect(() => {
    const restore = async () => {
      const stored = await inputStateManager.get(key);
      if (stored) {
        setValueInternal(stored);
        onRestore?.(stored);
      }
      setIsRestored(true);
    };
    restore();
  }, [key, onRestore]);
  
  // Debounced save
  const setValue = useCallback((newValue: string) => {
    setValueInternal(newValue);
    
    if (debounceRef.current) {
      clearTimeout(debounceRef.current);
    }
    
    debounceRef.current = setTimeout(() => {
      inputStateManager.set(key, newValue);
    }, debounceMs);
  }, [key, debounceMs]);
  
  // Immediate save on blur/unload
  useEffect(() => {
    const handleBeforeUnload = () => {
      if (debounceRef.current) {
        clearTimeout(debounceRef.current);
      }
      inputStateManager.setSync(key, value);
    };
    
    window.addEventListener('beforeunload', handleBeforeUnload);
    return () => window.removeEventListener('beforeunload', handleBeforeUnload);
  }, [key, value]);
  
  const clear = useCallback(() => {
    setValueInternal('');
    inputStateManager.remove(key);
  }, [key]);
  
  return { value, setValue, isRestored, clear };
}
```

---

## State Manager

```typescript
const DB_NAME = 'specmgmt_input_state';
const DB_VERSION = 1;
const STORE_NAME = 'inputs';
const LOCALSTORAGE_THRESHOLD = 1024; // 1KB

class InputStateManager {
  private db: IDBDatabase | null = null;
  
  async init(): Promise<void> {
    return new Promise((resolve, reject) => {
      const request = indexedDB.open(DB_NAME, DB_VERSION);
      
      request.onerror = () => reject(request.error);
      request.onsuccess = () => {
        this.db = request.result;
        resolve();
      };
      
      request.onupgradeneeded = (event) => {
        const db = (event.target as IDBOpenDBRequest).result;
        if (!db.objectStoreNames.contains(STORE_NAME)) {
          db.createObjectStore(STORE_NAME);
        }
      };
    });
  }
  
  async get(key: string): Promise<string | null> {
    // Try localStorage first (faster)
    const localValue = localStorage.getItem(key);
    if (localValue !== null) {
      return localValue;
    }
    
    // Fall back to IndexedDB
    return this.getFromIndexedDB(key);
  }
  
  async set(key: string, value: string): Promise<void> {
    if (value.length < LOCALSTORAGE_THRESHOLD) {
      // Small values go to localStorage
      localStorage.setItem(key, value);
      // Clean up IndexedDB if migrating
      this.removeFromIndexedDB(key);
    } else {
      // Large values go to IndexedDB
      localStorage.removeItem(key);
      await this.setToIndexedDB(key, value);
    }
  }
  
  // Synchronous save for beforeunload
  setSync(key: string, value: string): void {
    if (value.length < LOCALSTORAGE_THRESHOLD) {
      localStorage.setItem(key, value);
    }
    // IndexedDB is async, so we can only guarantee localStorage
  }
  
  async remove(key: string): Promise<void> {
    localStorage.removeItem(key);
    await this.removeFromIndexedDB(key);
  }
  
  private async getFromIndexedDB(key: string): Promise<string | null> {
    if (!this.db) await this.init();
    
    return new Promise((resolve, reject) => {
      const transaction = this.db!.transaction(STORE_NAME, 'readonly');
      const store = transaction.objectStore(STORE_NAME);
      const request = store.get(key);
      
      request.onsuccess = () => resolve(request.result ?? null);
      request.onerror = () => reject(request.error);
    });
  }
  
  private async setToIndexedDB(key: string, value: string): Promise<void> {
    if (!this.db) await this.init();
    
    return new Promise((resolve, reject) => {
      const transaction = this.db!.transaction(STORE_NAME, 'readwrite');
      const store = transaction.objectStore(STORE_NAME);
      const request = store.put(value, key);
      
      request.onsuccess = () => resolve();
      request.onerror = () => reject(request.error);
    });
  }
  
  private async removeFromIndexedDB(key: string): Promise<void> {
    if (!this.db) return;
    
    return new Promise((resolve, reject) => {
      const transaction = this.db!.transaction(STORE_NAME, 'readwrite');
      const store = transaction.objectStore(STORE_NAME);
      const request = store.delete(key);
      
      request.onsuccess = () => resolve();
      request.onerror = () => reject(request.error);
    });
  }
}

export const inputStateManager = new InputStateManager();
```

---

## Integration Examples

### Chat Input

```tsx
import { usePersistedInput } from '@/hooks/use-persisted-input';

export function AIChatInput({ projectId }: { projectId: string }) {
  const { value, setValue, clear, isRestored } = usePersistedInput({
    type: InputType.ChatInput,
    projectId,
    contextId: 'main',
    fieldId: 'message',
  });
  
  const handleSubmit = async () => {
    if (!value.trim()) return;
    
    await sendMessage(value);
    clear(); // Clear only after successful send
  };
  
  if (!isRestored) {
    return <InputSkeleton />;
  }
  
  return (
    <div className="flex gap-2">
      <Textarea
        value={value}
        onChange={(e) => setValue(e.target.value)}
        placeholder="Type your message..."
        className="flex-1"
      />
      <Button onClick={handleSubmit}>Send</Button>
    </div>
  );
}
```

### File Editor

```tsx
export function MarkdownEditor({ projectId, filePath }: EditorProps) {
  const { value, setValue, isRestored } = usePersistedInput({
    type: InputType.FileEditor,
    projectId,
    contextId: filePath.replace(/\//g, '_'),
    fieldId: 'content',
    debounceMs: 500, // Longer debounce for editor
  });
  
  // Only show persisted draft if file content differs
  const [showDraftBanner, setShowDraftBanner] = useState(false);
  
  useEffect(() => {
    if (isRestored && value && value !== fileContent) {
      setShowDraftBanner(true);
    }
  }, [isRestored, value, fileContent]);
  
  return (
    <>
      {showDraftBanner && (
        <DraftRecoveryBanner
          onRestore={() => setValue(value)}
          onDiscard={() => {
            setValue(fileContent);
            setShowDraftBanner(false);
          }}
        />
      )}
      <Editor value={value} onChange={setValue} />
    </>
  );
}
```

---

## Cross-Project Persistence

Unlike tab-specific state, some inputs persist across all projects:

```typescript
// Global search persists across projects
const { value: globalSearch, setValue: setGlobalSearch } = usePersistedInput({
  type: InputType.SearchBox,
  projectId: 'global',
  contextId: 'header',
  fieldId: 'search',
});

// Each project has its own chat history
const { value: projectChat, setValue: setProjectChat } = usePersistedInput({
  type: InputType.ChatInput,
  projectId: currentProjectId, // Project-specific
  contextId: 'main',
  fieldId: 'message',
});
```

---

## Cleanup and Migration

### Version Migration

```typescript
const CURRENT_VERSION = 1;

async function migrateInputState(): Promise<void> {
  const storedVersion = parseInt(localStorage.getItem('specmgmt_input_version') ?? '0');
  
  if (storedVersion < CURRENT_VERSION) {
    // Clear old keys with different version prefix
    for (let i = 0; i < localStorage.length; i++) {
      const key = localStorage.key(i);
      if (key?.startsWith('specmgmt_v') && !key.startsWith(`specmgmt_v${CURRENT_VERSION}`)) {
        localStorage.removeItem(key);
      }
    }
    
    localStorage.setItem('specmgmt_input_version', String(CURRENT_VERSION));
  }
}
```

### Storage Quota Management

```typescript
async function checkStorageQuota(): Promise<void> {
  if ('storage' in navigator && 'estimate' in navigator.storage) {
    const estimate = await navigator.storage.estimate();
    const usedPercent = (estimate.usage ?? 0) / (estimate.quota ?? 1) * 100;
    
    if (usedPercent > 80) {
      // Warn user and suggest cleanup
      showStorageWarning();
    }
    
    if (usedPercent > 95) {
      // Auto-cleanup old drafts (> 30 days)
      await cleanupOldDrafts(30);
    }
  }
}
```

---

## Backend Sync (Optional)

For premium features, sync input state to backend:

```typescript
interface InputStateSync {
  readonly userId: string;
  readonly key: string;
  readonly value: string;
  readonly updatedAt: string;
  readonly deviceId: string;
}

async function syncToBackend(key: string, value: string): Promise<void> {
  if (!user.hasPremium) return;
  
  await api.post('/input-state/sync', {
    key,
    value,
    deviceId: getDeviceId(),
  });
}

async function syncFromBackend(key: string): Promise<string | null> {
  if (!user.hasPremium) return null;
  
  const response = await api.get(`/input-state/${key}`);
  return response.data.value;
}
```

---

## Acceptance Criteria

| ID | Criterion | Priority | Validation |
|----|-----------|----------|------------|
| ISP-01 | Chat input persists across tab switches | MUST | E2E test |
| ISP-02 | Editor drafts persist across navigation | MUST | E2E test |
| ISP-03 | Input restored on project return | MUST | E2E test |
| ISP-04 | Debounced save (100ms default) | MUST | Unit test |
| ISP-05 | Immediate save on beforeunload | MUST | E2E test |
| ISP-06 | Large content uses IndexedDB | SHOULD | Unit test |
| ISP-07 | Clear after successful submit | MUST | E2E test |
| ISP-08 | Draft recovery banner shown | SHOULD | E2E test |
| ISP-09 | Version migration cleans old keys | MUST | Unit test |
| ISP-10 | Storage quota warning at 80% | SHOULD | Integration test |

---

## Related Specs

- [data-resilience memory](/.lovable/memories/constraints/data-resilience.md) — Zero data loss principle
- [chat-ui memory](/.lovable/memories/style/chat-ui.md) — Chat input persistence
- [01-editor-component.md](./01-editor-component.md) — Editor integration
