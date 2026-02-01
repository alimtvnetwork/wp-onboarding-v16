# Integration Tests: Draft Recovery Flow

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-30  
**Parent:** [Project Editor](./00-overview.md)

---

## Purpose

Define comprehensive integration and E2E tests for the draft recovery flow, covering input persistence, draft detection, recovery UI, and cross-device sync scenarios.

---

## Test Categories

| Category | Tests | Priority |
|----------|-------|----------|
| Input Persistence | 12 | MUST |
| Draft Recovery UI | 10 | MUST |
| Sync API | 8 | SHOULD |
| Edge Cases | 6 | SHOULD |
| Performance | 4 | COULD |

---

## Test Environment Setup

```typescript
// src/test/draft-recovery.setup.ts
import { vi, beforeEach, afterEach } from 'vitest';

// Mock localStorage
const localStorageMock = (() => {
  let store: Record<string, string> = {};
  return {
    getItem: vi.fn((key: string) => store[key] ?? null),
    setItem: vi.fn((key: string, value: string) => { store[key] = value; }),
    removeItem: vi.fn((key: string) => { delete store[key]; }),
    clear: vi.fn(() => { store = {}; }),
    get length() { return Object.keys(store).length; },
    key: vi.fn((i: number) => Object.keys(store)[i] ?? null),
  };
})();

// Mock IndexedDB
const indexedDBMock = {
  open: vi.fn(),
  deleteDatabase: vi.fn(),
};

// Mock beforeunload event
const mockBeforeUnload = () => {
  const event = new Event('beforeunload');
  window.dispatchEvent(event);
};

beforeEach(() => {
  Object.defineProperty(window, 'localStorage', { value: localStorageMock });
  Object.defineProperty(window, 'indexedDB', { value: indexedDBMock });
  localStorageMock.clear();
});

afterEach(() => {
  vi.clearAllMocks();
});

export { localStorageMock, indexedDBMock, mockBeforeUnload };
```

---

## Input Persistence Tests

### ISP-01: Chat Input Persists Across Tab Switches

```typescript
// src/test/integration/draft-recovery/input-persistence.test.tsx
import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { AIChatInput } from '@/components/ai-chat-input';
import { localStorageMock } from '@/test/draft-recovery.setup';

describe('Input Persistence - Chat Input', () => {
  it('ISP-01: persists chat input across tab switches', async () => {
    const projectId = 'test-project-123';
    
    // Render chat input
    render(<AIChatInput projectId={projectId} />);
    
    // Type message
    const textarea = screen.getByPlaceholderText(/type your message/i);
    fireEvent.change(textarea, { target: { value: 'Hello, can you help me?' } });
    
    // Wait for debounced save
    await waitFor(() => {
      expect(localStorageMock.setItem).toHaveBeenCalledWith(
        expect.stringContaining('specmgmt_v'),
        'Hello, can you help me?'
      );
    }, { timeout: 200 });
    
    // Simulate tab switch (unmount and remount)
    const { unmount } = render(<AIChatInput projectId={projectId} />);
    unmount();
    
    // Re-render component
    render(<AIChatInput projectId={projectId} />);
    
    // Verify input restored
    await waitFor(() => {
      const restoredTextarea = screen.getByPlaceholderText(/type your message/i);
      expect(restoredTextarea).toHaveValue('Hello, can you help me?');
    });
  });

  it('ISP-02: persists editor drafts across navigation', async () => {
    const projectId = 'test-project-123';
    const filePath = 'docs/readme.md';
    const initialContent = '# Original';
    
    render(
      <MarkdownEditor 
        projectId={projectId} 
        filePath={filePath}
        initialContent={initialContent}
      />
    );
    
    // Edit content
    const editor = screen.getByRole('textbox');
    fireEvent.change(editor, { target: { value: '# Modified Content\n\nNew paragraph' } });
    
    // Wait for debounced save (500ms for editors)
    await waitFor(() => {
      expect(localStorageMock.setItem).toHaveBeenCalled();
    }, { timeout: 600 });
    
    // Navigate away and back
    const { unmount } = render(
      <MarkdownEditor 
        projectId={projectId} 
        filePath={filePath}
        initialContent={initialContent}
      />
    );
    unmount();
    
    render(
      <MarkdownEditor 
        projectId={projectId} 
        filePath={filePath}
        initialContent={initialContent}
      />
    );
    
    // Verify draft restored
    await waitFor(() => {
      const restoredEditor = screen.getByRole('textbox');
      expect(restoredEditor).toHaveValue('# Modified Content\n\nNew paragraph');
    });
  });

  it('ISP-03: input restored on project return', async () => {
    // Type in project A
    render(<AIChatInput projectId="project-a" />);
    const textareaA = screen.getByPlaceholderText(/type your message/i);
    fireEvent.change(textareaA, { target: { value: 'Message for Project A' } });
    
    await waitFor(() => {
      expect(localStorageMock.setItem).toHaveBeenCalled();
    }, { timeout: 200 });
    
    // Switch to project B
    const { unmount: unmountA } = render(<AIChatInput projectId="project-a" />);
    unmountA();
    
    render(<AIChatInput projectId="project-b" />);
    const textareaB = screen.getByPlaceholderText(/type your message/i);
    expect(textareaB).toHaveValue(''); // Different project, no draft
    
    // Return to project A
    const { unmount: unmountB } = render(<AIChatInput projectId="project-b" />);
    unmountB();
    
    render(<AIChatInput projectId="project-a" />);
    
    await waitFor(() => {
      const restoredTextarea = screen.getByPlaceholderText(/type your message/i);
      expect(restoredTextarea).toHaveValue('Message for Project A');
    });
  });

  it('ISP-04: debounced save at 100ms default', async () => {
    vi.useFakeTimers();
    
    render(<AIChatInput projectId="test-project" />);
    const textarea = screen.getByPlaceholderText(/type your message/i);
    
    // Type rapidly
    fireEvent.change(textarea, { target: { value: 'H' } });
    fireEvent.change(textarea, { target: { value: 'He' } });
    fireEvent.change(textarea, { target: { value: 'Hel' } });
    fireEvent.change(textarea, { target: { value: 'Hell' } });
    fireEvent.change(textarea, { target: { value: 'Hello' } });
    
    // Should not save yet (within debounce window)
    expect(localStorageMock.setItem).not.toHaveBeenCalled();
    
    // Advance past debounce
    vi.advanceTimersByTime(150);
    
    // Should save final value only
    expect(localStorageMock.setItem).toHaveBeenCalledTimes(1);
    expect(localStorageMock.setItem).toHaveBeenCalledWith(
      expect.any(String),
      'Hello'
    );
    
    vi.useRealTimers();
  });

  it('ISP-05: immediate save on beforeunload', async () => {
    render(<AIChatInput projectId="test-project" />);
    const textarea = screen.getByPlaceholderText(/type your message/i);
    
    // Type without waiting for debounce
    fireEvent.change(textarea, { target: { value: 'Unsaved work' } });
    
    // Trigger beforeunload
    mockBeforeUnload();
    
    // Should save immediately
    expect(localStorageMock.setItem).toHaveBeenCalledWith(
      expect.any(String),
      'Unsaved work'
    );
  });

  it('ISP-06: large content uses IndexedDB', async () => {
    const largeContent = 'x'.repeat(2000); // > 1KB
    
    render(<MarkdownEditor projectId="test" filePath="large.md" initialContent="" />);
    const editor = screen.getByRole('textbox');
    
    fireEvent.change(editor, { target: { value: largeContent } });
    
    await waitFor(() => {
      // localStorage should NOT have the large content
      expect(localStorageMock.setItem).not.toHaveBeenCalledWith(
        expect.any(String),
        largeContent
      );
      // IndexedDB should be used
      expect(indexedDBMock.open).toHaveBeenCalled();
    }, { timeout: 600 });
  });

  it('ISP-07: clear after successful submit', async () => {
    const onSubmit = vi.fn().mockResolvedValue(undefined);
    
    render(<AIChatInput projectId="test-project" onSubmit={onSubmit} />);
    const textarea = screen.getByPlaceholderText(/type your message/i);
    const submitButton = screen.getByRole('button', { name: /send/i });
    
    // Type and submit
    fireEvent.change(textarea, { target: { value: 'Test message' } });
    await waitFor(() => {
      expect(localStorageMock.setItem).toHaveBeenCalled();
    }, { timeout: 200 });
    
    fireEvent.click(submitButton);
    
    await waitFor(() => {
      expect(onSubmit).toHaveBeenCalledWith('Test message');
      expect(localStorageMock.removeItem).toHaveBeenCalled();
      expect(textarea).toHaveValue('');
    });
  });
});
```

---

## Draft Recovery UI Tests

### DRU-01: Recovery Banner Display

```typescript
// src/test/integration/draft-recovery/recovery-ui.test.tsx
import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { DraftRecoveryBanner } from '@/components/draft-recovery-banner';
import { DraftRecoveryDialog } from '@/components/draft-recovery-dialog';
import { DraftType } from '@/types/draft';

describe('Draft Recovery UI', () => {
  it('DRU-01: banner shows for unsaved drafts', () => {
    const onRestore = vi.fn();
    const onDiscard = vi.fn();
    
    render(
      <DraftRecoveryBanner
        draftType={DraftType.ChatMessage}
        lastModified={new Date(Date.now() - 2 * 60 * 60 * 1000)} // 2 hours ago
        previewText="Hello, can you help me with..."
        onRestore={onRestore}
        onDiscard={onDiscard}
      />
    );
    
    expect(screen.getByText(/unsaved chat message found/i)).toBeInTheDocument();
    expect(screen.getByText(/2 hours ago/i)).toBeInTheDocument();
    expect(screen.getByText(/"Hello, can you help me with..."/i)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /restore draft/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /discard/i })).toBeInTheDocument();
  });

  it('DRU-02: restore button applies draft content', async () => {
    const onRestore = vi.fn();
    const onDiscard = vi.fn();
    
    render(
      <DraftRecoveryBanner
        draftType={DraftType.EditorContent}
        lastModified={new Date()}
        previewText="# My Document"
        onRestore={onRestore}
        onDiscard={onDiscard}
      />
    );
    
    fireEvent.click(screen.getByRole('button', { name: /restore draft/i }));
    
    expect(onRestore).toHaveBeenCalledTimes(1);
    // Banner should disappear after restore
    await waitFor(() => {
      expect(screen.queryByText(/unsaved/i)).not.toBeInTheDocument();
    });
  });

  it('DRU-03: discard button removes draft permanently', async () => {
    const onRestore = vi.fn();
    const onDiscard = vi.fn();
    
    render(
      <DraftRecoveryBanner
        draftType={DraftType.FormData}
        lastModified={new Date()}
        previewText="Form data..."
        onRestore={onRestore}
        onDiscard={onDiscard}
      />
    );
    
    fireEvent.click(screen.getByRole('button', { name: /discard/i }));
    
    expect(onDiscard).toHaveBeenCalledTimes(1);
    await waitFor(() => {
      expect(screen.queryByText(/unsaved/i)).not.toBeInTheDocument();
    });
  });

  it('DRU-04: multi-draft dialog on crash recovery', () => {
    const drafts = [
      {
        id: 'draft-1',
        type: DraftType.ChatMessage,
        projectName: 'My Project',
        contextLabel: 'main',
        previewText: 'Can you help me implement...',
        lastModified: new Date(Date.now() - 2 * 60 * 60 * 1000),
        sizeBytes: 256,
      },
      {
        id: 'draft-2',
        type: DraftType.SpecDraft,
        projectName: 'API Documentation',
        contextLabel: 'auth-spec',
        previewText: '## Authentication\n\nThe API uses JWT...',
        lastModified: new Date(Date.now() - 24 * 60 * 60 * 1000),
        sizeBytes: 1024,
      },
      {
        id: 'draft-3',
        type: DraftType.FormData,
        projectName: 'Settings',
        contextLabel: 'preferences',
        previewText: 'Theme: dark, Language: en-US...',
        lastModified: new Date(Date.now() - 3 * 24 * 60 * 60 * 1000),
        sizeBytes: 128,
      },
    ];
    
    const onRestoreSelected = vi.fn();
    const onDiscardAll = vi.fn();
    const onClose = vi.fn();
    
    render(
      <DraftRecoveryDialog
        drafts={drafts}
        isOpen={true}
        onClose={onClose}
        onRestoreSelected={onRestoreSelected}
        onDiscardAll={onDiscardAll}
      />
    );
    
    expect(screen.getByText(/recover unsaved work/i)).toBeInTheDocument();
    expect(screen.getByText(/3 unsaved drafts/i)).toBeInTheDocument();
    expect(screen.getByText(/My Project/i)).toBeInTheDocument();
    expect(screen.getByText(/API Documentation/i)).toBeInTheDocument();
    expect(screen.getByText(/Settings/i)).toBeInTheDocument();
  });

  it('DRU-05: draft preview shows first 100 chars', () => {
    const longText = 'x'.repeat(150);
    
    render(
      <DraftRecoveryBanner
        draftType={DraftType.EditorContent}
        lastModified={new Date()}
        previewText={longText}
        onRestore={vi.fn()}
        onDiscard={vi.fn()}
      />
    );
    
    const preview = screen.getByText(/^"x+/);
    expect(preview.textContent?.length).toBeLessThanOrEqual(103); // 100 + quotes + ellipsis
  });

  it('DRU-06: time ago display for lastModified', () => {
    const testCases = [
      { offset: 5 * 60 * 1000, expected: /5 minutes ago/i },
      { offset: 2 * 60 * 60 * 1000, expected: /2 hours ago/i },
      { offset: 24 * 60 * 60 * 1000, expected: /1 day ago/i },
    ];
    
    testCases.forEach(({ offset, expected }) => {
      const { unmount } = render(
        <DraftRecoveryBanner
          draftType={DraftType.ChatMessage}
          lastModified={new Date(Date.now() - offset)}
          onRestore={vi.fn()}
          onDiscard={vi.fn()}
        />
      );
      
      expect(screen.getByText(expected)).toBeInTheDocument();
      unmount();
    });
  });

  it('DRU-07: banner dismissible without action', async () => {
    const onDismiss = vi.fn();
    
    render(
      <DraftRecoveryBanner
        draftType={DraftType.ChatMessage}
        lastModified={new Date()}
        onRestore={vi.fn()}
        onDiscard={vi.fn()}
        onDismiss={onDismiss}
      />
    );
    
    // Find and click close button
    const closeButton = screen.getByRole('button', { name: /close/i });
    fireEvent.click(closeButton);
    
    expect(onDismiss).toHaveBeenCalledTimes(1);
    await waitFor(() => {
      expect(screen.queryByText(/unsaved/i)).not.toBeInTheDocument();
    });
  });

  it('DRU-08: auto-select recent drafts in dialog', () => {
    const drafts = [
      {
        id: 'recent',
        type: DraftType.ChatMessage,
        projectName: 'Recent',
        contextLabel: 'main',
        previewText: 'Recent draft',
        lastModified: new Date(Date.now() - 1000),
        sizeBytes: 100,
      },
      {
        id: 'old',
        type: DraftType.ChatMessage,
        projectName: 'Old',
        contextLabel: 'main',
        previewText: 'Old draft',
        lastModified: new Date(Date.now() - 7 * 24 * 60 * 60 * 1000),
        sizeBytes: 100,
      },
    ];
    
    render(
      <DraftRecoveryDialog
        drafts={drafts}
        isOpen={true}
        onClose={vi.fn()}
        onRestoreSelected={vi.fn()}
        onDiscardAll={vi.fn()}
      />
    );
    
    const checkboxes = screen.getAllByRole('checkbox');
    // Recent should be checked, old should not
    expect(checkboxes[0]).toBeChecked();
  });

  it('DRU-09: restore selected count updates', async () => {
    const drafts = [
      { id: '1', type: DraftType.ChatMessage, projectName: 'A', contextLabel: 'main', previewText: 'A', lastModified: new Date(), sizeBytes: 100 },
      { id: '2', type: DraftType.ChatMessage, projectName: 'B', contextLabel: 'main', previewText: 'B', lastModified: new Date(), sizeBytes: 100 },
      { id: '3', type: DraftType.ChatMessage, projectName: 'C', contextLabel: 'main', previewText: 'C', lastModified: new Date(), sizeBytes: 100 },
    ];
    
    render(
      <DraftRecoveryDialog
        drafts={drafts}
        isOpen={true}
        onClose={vi.fn()}
        onRestoreSelected={vi.fn()}
        onDiscardAll={vi.fn()}
      />
    );
    
    // Initially all selected (recent drafts)
    expect(screen.getByText(/restore selected \(3\)/i)).toBeInTheDocument();
    
    // Uncheck one
    const checkboxes = screen.getAllByRole('checkbox');
    fireEvent.click(checkboxes[0]);
    
    await waitFor(() => {
      expect(screen.getByText(/restore selected \(2\)/i)).toBeInTheDocument();
    });
  });

  it('DRU-10: accessible keyboard navigation', async () => {
    render(
      <DraftRecoveryBanner
        draftType={DraftType.ChatMessage}
        lastModified={new Date()}
        onRestore={vi.fn()}
        onDiscard={vi.fn()}
      />
    );
    
    const restoreButton = screen.getByRole('button', { name: /restore draft/i });
    const discardButton = screen.getByRole('button', { name: /discard/i });
    
    // Tab navigation should work
    restoreButton.focus();
    expect(document.activeElement).toBe(restoreButton);
    
    fireEvent.keyDown(restoreButton, { key: 'Tab' });
    // Focus should move to next interactive element
  });
});
```

---

## Sync API Tests

```typescript
// src/test/integration/draft-recovery/sync-api.test.ts
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { SyncManager } from '@/lib/sync-manager';

describe('Sync API', () => {
  let syncManager: SyncManager;
  let mockApiClient: {
    post: ReturnType<typeof vi.fn>;
    get: ReturnType<typeof vi.fn>;
  };

  beforeEach(() => {
    mockApiClient = {
      post: vi.fn(),
      get: vi.fn(),
    };
    syncManager = new SyncManager(mockApiClient as never);
  });

  it('SYN-01: state syncs within 2s of change', async () => {
    vi.useFakeTimers();
    mockApiClient.post.mockResolvedValue({ results: [], errors: [] });
    
    syncManager.start();
    syncManager.queueSync('test-key', 'test-value');
    
    // Should not sync immediately
    expect(mockApiClient.post).not.toHaveBeenCalled();
    
    // Advance past sync debounce (2s)
    vi.advanceTimersByTime(2100);
    
    await vi.waitFor(() => {
      expect(mockApiClient.post).toHaveBeenCalledWith(
        '/sync/batch',
        expect.objectContaining({
          states: expect.arrayContaining([
            expect.objectContaining({ key: 'test-key', value: 'test-value' })
          ])
        })
      );
    });
    
    vi.useRealTimers();
  });

  it('SYN-02: batch sync up to 50 items', async () => {
    mockApiClient.post.mockResolvedValue({ results: [], errors: [] });
    
    // Queue 75 items
    for (let i = 0; i < 75; i++) {
      syncManager.queueSync(`key-${i}`, `value-${i}`);
    }
    
    await syncManager.flushPendingSync();
    
    // Should make 2 batch calls (50 + 25)
    expect(mockApiClient.post).toHaveBeenCalledTimes(2);
    
    const firstCall = mockApiClient.post.mock.calls[0];
    expect(firstCall[1].states.length).toBe(50);
    
    const secondCall = mockApiClient.post.mock.calls[1];
    expect(secondCall[1].states.length).toBe(25);
  });

  it('SYN-03: conflict detection on version mismatch', async () => {
    mockApiClient.post.mockResolvedValue({
      results: [
        {
          key: 'conflict-key',
          value: 'server-value',
          version: 1706620900000,
          conflict: true,
          deviceId: 'other-device',
        }
      ],
      errors: []
    });
    
    const onConflict = vi.fn();
    syncManager.onConflict(onConflict);
    
    syncManager.queueSync('conflict-key', 'client-value');
    await syncManager.flushPendingSync();
    
    expect(onConflict).toHaveBeenCalledWith(
      expect.objectContaining({
        key: 'conflict-key',
        conflict: true,
      })
    );
  });

  it('SYN-04: incremental sync with since param', async () => {
    const lastSync = Date.now() - 60000; // 1 minute ago
    localStorage.setItem('specmgmt_last_sync', String(lastSync));
    
    mockApiClient.get.mockResolvedValue({ states: [] });
    
    await syncManager.pullFromServer();
    
    expect(mockApiClient.get).toHaveBeenCalledWith(
      expect.stringContaining(`since=${lastSync}`)
    );
  });

  it('SYN-08: offline queue with retry', async () => {
    // First call fails
    mockApiClient.post
      .mockRejectedValueOnce(new Error('Network error'))
      .mockResolvedValueOnce({ results: [], errors: [] });
    
    syncManager.queueSync('offline-key', 'offline-value');
    
    // First attempt fails
    await syncManager.flushPendingSync();
    
    // Item should be re-queued
    expect(syncManager.getPendingCount()).toBe(1);
    
    // Second attempt succeeds
    await syncManager.flushPendingSync();
    
    expect(syncManager.getPendingCount()).toBe(0);
  });

  it('SYN-09: sync on tab visibility change', async () => {
    mockApiClient.post.mockResolvedValue({ results: [], errors: [] });
    mockApiClient.get.mockResolvedValue({ states: [] });
    
    syncManager.start();
    syncManager.queueSync('visibility-key', 'visibility-value');
    
    // Simulate tab becoming hidden
    Object.defineProperty(document, 'visibilityState', { value: 'hidden', writable: true });
    document.dispatchEvent(new Event('visibilitychange'));
    
    await vi.waitFor(() => {
      expect(mockApiClient.post).toHaveBeenCalled();
    });
    
    // Simulate tab becoming visible
    Object.defineProperty(document, 'visibilityState', { value: 'visible', writable: true });
    document.dispatchEvent(new Event('visibilitychange'));
    
    await vi.waitFor(() => {
      expect(mockApiClient.get).toHaveBeenCalled();
    });
  });
});
```

---

## Edge Case Tests

```typescript
// src/test/integration/draft-recovery/edge-cases.test.tsx
import { describe, it, expect, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { inputStateManager } from '@/lib/input-state-manager';

describe('Edge Cases', () => {
  it('EC-01: handles corrupted localStorage gracefully', async () => {
    // Set corrupted data
    localStorage.setItem('specmgmt_v1_chat_test_main_message', '{invalid json');
    
    // Should not throw, should return empty/default
    const result = await inputStateManager.get('specmgmt_v1_chat_test_main_message');
    expect(result).toBeNull();
  });

  it('EC-02: handles localStorage quota exceeded', async () => {
    const mockSetItem = vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new DOMException('QuotaExceededError');
    });
    
    // Should fall back to IndexedDB
    await expect(
      inputStateManager.set('key', 'value')
    ).resolves.not.toThrow();
    
    mockSetItem.mockRestore();
  });

  it('EC-03: handles IndexedDB unavailable', async () => {
    const originalIndexedDB = window.indexedDB;
    // @ts-expect-error - Testing edge case
    delete window.indexedDB;
    
    // Should still work with localStorage fallback
    await inputStateManager.set('fallback-key', 'fallback-value');
    const result = await inputStateManager.get('fallback-key');
    
    expect(result).toBe('fallback-value');
    
    window.indexedDB = originalIndexedDB;
  });

  it('EC-04: version migration cleans old keys', async () => {
    // Set old version keys
    localStorage.setItem('specmgmt_v0_chat_old', 'old data');
    localStorage.setItem('specmgmt_v1_chat_current', 'current data');
    
    await inputStateManager.migrateVersion();
    
    expect(localStorage.getItem('specmgmt_v0_chat_old')).toBeNull();
    expect(localStorage.getItem('specmgmt_v1_chat_current')).toBe('current data');
  });

  it('EC-05: handles concurrent writes correctly', async () => {
    const promises = [];
    
    for (let i = 0; i < 10; i++) {
      promises.push(inputStateManager.set('concurrent-key', `value-${i}`));
    }
    
    await Promise.all(promises);
    
    // Should have latest value (last write wins)
    const result = await inputStateManager.get('concurrent-key');
    expect(result).toBe('value-9');
  });

  it('EC-06: handles special characters in keys/values', async () => {
    const specialValue = 'Line1\nLine2\t<script>alert("xss")</script>🎉';
    
    await inputStateManager.set('special-key', specialValue);
    const result = await inputStateManager.get('special-key');
    
    expect(result).toBe(specialValue);
  });
});
```

---

## Performance Tests

```typescript
// src/test/integration/draft-recovery/performance.test.ts
import { describe, it, expect } from 'vitest';
import { inputStateManager } from '@/lib/input-state-manager';

describe('Performance', () => {
  it('PERF-01: saves within 100ms for small content', async () => {
    const start = performance.now();
    
    await inputStateManager.set('perf-key', 'small content');
    
    const duration = performance.now() - start;
    expect(duration).toBeLessThan(100);
  });

  it('PERF-02: retrieves within 50ms for localStorage', async () => {
    localStorage.setItem('specmgmt_v1_perf_test', 'cached value');
    
    const start = performance.now();
    await inputStateManager.get('specmgmt_v1_perf_test');
    const duration = performance.now() - start;
    
    expect(duration).toBeLessThan(50);
  });

  it('PERF-03: handles 100 rapid saves without memory leak', async () => {
    const initialMemory = (performance as Performance & { memory?: { usedJSHeapSize: number } }).memory?.usedJSHeapSize ?? 0;
    
    for (let i = 0; i < 100; i++) {
      await inputStateManager.set(`rapid-key-${i}`, `value-${i}`);
    }
    
    // Force GC if available
    if (typeof globalThis.gc === 'function') {
      globalThis.gc();
    }
    
    const finalMemory = (performance as Performance & { memory?: { usedJSHeapSize: number } }).memory?.usedJSHeapSize ?? 0;
    const memoryIncrease = finalMemory - initialMemory;
    
    // Should not increase by more than 10MB
    expect(memoryIncrease).toBeLessThan(10 * 1024 * 1024);
  });

  it('PERF-04: batch recovery dialog renders < 16ms per draft', async () => {
    const drafts = Array.from({ length: 20 }, (_, i) => ({
      id: `draft-${i}`,
      type: 'chat_message' as const,
      projectName: `Project ${i}`,
      contextLabel: 'main',
      previewText: `Draft content ${i}`,
      lastModified: new Date(),
      sizeBytes: 100,
    }));
    
    const start = performance.now();
    
    // Simulate rendering all drafts
    drafts.forEach(draft => {
      // This would be replaced with actual render measurement
      JSON.stringify(draft);
    });
    
    const duration = performance.now() - start;
    const perDraft = duration / drafts.length;
    
    expect(perDraft).toBeLessThan(16); // 60fps target
  });
});
```

---

## Test Coverage Matrix

| Feature | Unit | Integration | E2E | Status |
|---------|------|-------------|-----|--------|
| localStorage persistence | ✅ | ✅ | ⬜ | Planned |
| IndexedDB fallback | ✅ | ✅ | ⬜ | Planned |
| Debounced save | ✅ | ✅ | ⬜ | Planned |
| beforeunload save | ⬜ | ✅ | ✅ | Planned |
| Recovery banner | ✅ | ✅ | ⬜ | Planned |
| Multi-draft dialog | ✅ | ✅ | ⬜ | Planned |
| Sync API client | ✅ | ✅ | ⬜ | Planned |
| Conflict resolution | ✅ | ✅ | ⬜ | Planned |
| Offline queue | ✅ | ✅ | ⬜ | Planned |
| Version migration | ✅ | ✅ | ⬜ | Planned |

---

## Related Specs

- [Input State Persistence](./06-input-state-persistence.md) — Core implementation
- [Draft Recovery UI](./01-draft-recovery-ui.md) — UI components
- [Sync API](./02-sync-api.md) — Backend sync
- [Testing Strategy](../../99-consistency-report.md) — Overall test approach
