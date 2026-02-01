# Draft Recovery UI

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-30  
**Parent:** [Project Editor](./00-overview.md)

---

## Purpose

Define the UI components for recovering unsaved drafts after browser crashes, accidental navigation, or session timeouts. Provides non-intrusive notification of available drafts with clear restore/discard actions.

---

## Components

### DraftRecoveryBanner

A dismissible banner shown when unsaved changes are detected.

```typescript
interface DraftRecoveryBannerProps {
  readonly draftType: DraftType;
  readonly lastModified: Date;
  readonly previewText?: string;
  readonly onRestore: () => void;
  readonly onDiscard: () => void;
  readonly onDismiss?: () => void;
}

enum DraftType {
  ChatMessage = 'chat_message',
  EditorContent = 'editor_content',
  FormData = 'form_data',
  SpecDraft = 'spec_draft',
}
```

#### Visual Design

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ ⚠️  Unsaved draft found from 2 hours ago                                    │
│                                                                             │
│ "Lorem ipsum dolor sit amet, consectetur adipiscing..."                     │
│                                                                             │
│ [Restore Draft]  [Discard]                                        [✕ Close] │
└─────────────────────────────────────────────────────────────────────────────┘
```

#### Implementation

```tsx
import { useState } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { formatDistanceToNow } from 'date-fns';
import { FileWarning, RotateCcw, Trash2, X } from 'lucide-react';
import { cn } from '@/lib/utils';

export function DraftRecoveryBanner({
  draftType,
  lastModified,
  previewText,
  onRestore,
  onDiscard,
  onDismiss,
}: DraftRecoveryBannerProps) {
  const [isVisible, setIsVisible] = useState(true);
  
  if (!isVisible) return null;
  
  const handleDismiss = () => {
    setIsVisible(false);
    onDismiss?.();
  };
  
  const handleRestore = () => {
    onRestore();
    setIsVisible(false);
  };
  
  const handleDiscard = () => {
    onDiscard();
    setIsVisible(false);
  };
  
  const typeLabels: Record<DraftType, string> = {
    [DraftType.ChatMessage]: 'chat message',
    [DraftType.EditorContent]: 'editor content',
    [DraftType.FormData]: 'form data',
    [DraftType.SpecDraft]: 'spec draft',
  };
  
  return (
    <Alert 
      className={cn(
        "relative border-warning bg-warning/10",
        "animate-in slide-in-from-top-2 duration-300"
      )}
    >
      <FileWarning className="h-4 w-4" />
      <AlertTitle className="flex items-center justify-between">
        <span>
          Unsaved {typeLabels[draftType]} found from{' '}
          {formatDistanceToNow(lastModified, { addSuffix: true })}
        </span>
        <Button
          variant="ghost"
          size="icon"
          className="h-6 w-6"
          onClick={handleDismiss}
        >
          <X className="h-4 w-4" />
        </Button>
      </AlertTitle>
      
      {previewText && (
        <AlertDescription className="mt-2 text-muted-foreground italic truncate max-w-md">
          "{previewText}"
        </AlertDescription>
      )}
      
      <div className="mt-3 flex gap-2">
        <Button size="sm" onClick={handleRestore}>
          <RotateCcw className="h-4 w-4 mr-1" />
          Restore Draft
        </Button>
        <Button size="sm" variant="outline" onClick={handleDiscard}>
          <Trash2 className="h-4 w-4 mr-1" />
          Discard
        </Button>
      </div>
    </Alert>
  );
}
```

---

### DraftRecoveryDialog

Modal for recovering multiple drafts (e.g., after browser crash).

```typescript
interface DraftRecoveryDialogProps {
  readonly drafts: readonly DraftItem[];
  readonly isOpen: boolean;
  readonly onClose: () => void;
  readonly onRestoreSelected: (draftIds: readonly string[]) => void;
  readonly onDiscardAll: () => void;
}

interface DraftItem {
  readonly id: string;
  readonly type: DraftType;
  readonly projectName: string;
  readonly contextLabel: string;
  readonly previewText: string;
  readonly lastModified: Date;
  readonly sizeBytes: number;
}
```

#### Visual Design

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        Recover Unsaved Work                                 │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│ We found 3 unsaved drafts. Select which ones to restore:                    │
│                                                                             │
│ ┌─────────────────────────────────────────────────────────────────────────┐ │
│ │ ☑ Chat message in "My Project"                            2 hours ago  │ │
│ │   "Can you help me implement the login feature..."                     │ │
│ ├─────────────────────────────────────────────────────────────────────────┤ │
│ │ ☑ Spec draft in "API Documentation"                       1 day ago    │ │
│ │   "## Authentication\n\nThe API uses JWT tokens..."                    │ │
│ ├─────────────────────────────────────────────────────────────────────────┤ │
│ │ ☐ Form data in "Settings"                                 3 days ago   │ │
│ │   "Theme: dark, Language: en-US..."                                    │ │
│ └─────────────────────────────────────────────────────────────────────────┘ │
│                                                                             │
│ [Restore Selected (2)]                   [Discard All]           [Cancel]   │
└─────────────────────────────────────────────────────────────────────────────┘
```

#### Implementation

```tsx
import { useState } from 'react';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { ScrollArea } from '@/components/ui/scroll-area';
import { formatDistanceToNow } from 'date-fns';
import { formatBytes } from '@/lib/format';

export function DraftRecoveryDialog({
  drafts,
  isOpen,
  onClose,
  onRestoreSelected,
  onDiscardAll,
}: DraftRecoveryDialogProps) {
  const [selectedIds, setSelectedIds] = useState<Set<string>>(
    new Set(drafts.slice(0, 5).map(d => d.id)) // Auto-select recent drafts
  );
  
  const toggleDraft = (id: string) => {
    const newSelected = new Set(selectedIds);
    if (newSelected.has(id)) {
      newSelected.delete(id);
    } else {
      newSelected.add(id);
    }
    setSelectedIds(newSelected);
  };
  
  const handleRestore = () => {
    onRestoreSelected(Array.from(selectedIds));
  };
  
  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>Recover Unsaved Work</DialogTitle>
          <DialogDescription>
            We found {drafts.length} unsaved draft{drafts.length > 1 ? 's' : ''}. 
            Select which ones to restore:
          </DialogDescription>
        </DialogHeader>
        
        <ScrollArea className="max-h-[400px]">
          <div className="space-y-2">
            {drafts.map((draft) => (
              <DraftListItem
                key={draft.id}
                draft={draft}
                isSelected={selectedIds.has(draft.id)}
                onToggle={() => toggleDraft(draft.id)}
              />
            ))}
          </div>
        </ScrollArea>
        
        <DialogFooter className="flex justify-between">
          <Button variant="destructive" onClick={onDiscardAll}>
            Discard All
          </Button>
          <div className="flex gap-2">
            <Button variant="outline" onClick={onClose}>
              Cancel
            </Button>
            <Button 
              onClick={handleRestore}
              disabled={selectedIds.size === 0}
            >
              Restore Selected ({selectedIds.size})
            </Button>
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function DraftListItem({
  draft,
  isSelected,
  onToggle,
}: {
  readonly draft: DraftItem;
  readonly isSelected: boolean;
  readonly onToggle: () => void;
}) {
  const typeLabels: Record<DraftType, string> = {
    [DraftType.ChatMessage]: 'Chat message',
    [DraftType.EditorContent]: 'Editor content',
    [DraftType.FormData]: 'Form data',
    [DraftType.SpecDraft]: 'Spec draft',
  };
  
  return (
    <div 
      className="flex items-start gap-3 p-3 rounded-lg border cursor-pointer hover:bg-muted/50"
      onClick={onToggle}
    >
      <Checkbox checked={isSelected} />
      <div className="flex-1 min-w-0">
        <div className="flex items-center justify-between">
          <span className="font-medium">
            {typeLabels[draft.type]} in "{draft.projectName}"
          </span>
          <span className="text-sm text-muted-foreground">
            {formatDistanceToNow(draft.lastModified, { addSuffix: true })}
          </span>
        </div>
        <p className="text-sm text-muted-foreground truncate mt-1">
          "{draft.previewText}"
        </p>
        <span className="text-xs text-muted-foreground">
          {formatBytes(draft.sizeBytes)}
        </span>
      </div>
    </div>
  );
}
```

---

### useDraftRecovery Hook

```typescript
interface UseDraftRecoveryOptions {
  readonly projectId: string;
  readonly contextId: string;
  readonly currentContent: string;
}

interface UseDraftRecoveryReturn {
  readonly hasDraft: boolean;
  readonly draft: DraftItem | null;
  readonly restoreDraft: () => void;
  readonly discardDraft: () => void;
  readonly dismissDraft: () => void;
}

export function useDraftRecovery(options: UseDraftRecoveryOptions): UseDraftRecoveryReturn {
  const { projectId, contextId, currentContent } = options;
  const [draft, setDraft] = useState<DraftItem | null>(null);
  const [isDismissed, setIsDismissed] = useState(false);
  
  useEffect(() => {
    const checkForDraft = async () => {
      const storedDraft = await inputStateManager.getDraft(projectId, contextId);
      
      // Only show if draft differs from current content
      if (storedDraft && storedDraft.content !== currentContent) {
        setDraft(storedDraft);
      }
    };
    
    checkForDraft();
  }, [projectId, contextId, currentContent]);
  
  const restoreDraft = useCallback(() => {
    if (draft) {
      // Emit restore event for parent to handle
      window.dispatchEvent(
        new CustomEvent('draft:restore', { detail: draft })
      );
      setDraft(null);
    }
  }, [draft]);
  
  const discardDraft = useCallback(async () => {
    if (draft) {
      await inputStateManager.remove(draft.id);
      setDraft(null);
    }
  }, [draft]);
  
  const dismissDraft = useCallback(() => {
    setIsDismissed(true);
  }, []);
  
  return {
    hasDraft: draft !== null && !isDismissed,
    draft,
    restoreDraft,
    discardDraft,
    dismissDraft,
  };
}
```

---

## Global Recovery Service

Checks for drafts on app startup:

```typescript
class DraftRecoveryService {
  async checkForRecoverableDrafts(): Promise<readonly DraftItem[]> {
    const allDrafts: DraftItem[] = [];
    
    // Check localStorage
    for (let i = 0; i < localStorage.length; i++) {
      const key = localStorage.key(i);
      if (key?.startsWith('specmgmt_v')) {
        const metadata = this.parseKeyMetadata(key);
        if (metadata) {
          allDrafts.push({
            id: key,
            ...metadata,
            previewText: localStorage.getItem(key)?.slice(0, 100) ?? '',
            sizeBytes: new Blob([localStorage.getItem(key) ?? '']).size,
          });
        }
      }
    }
    
    // Check IndexedDB
    const idbDrafts = await this.getIndexedDBDrafts();
    allDrafts.push(...idbDrafts);
    
    // Sort by lastModified descending
    return allDrafts.sort((a, b) => 
      b.lastModified.getTime() - a.lastModified.getTime()
    );
  }
  
  private parseKeyMetadata(key: string): Partial<DraftItem> | null {
    // Parse: specmgmt_v1_chat_proj123_main_message
    const match = key.match(/^specmgmt_v\d+_(\w+)_([^_]+)_([^_]+)_(.+)$/);
    if (!match) return null;
    
    const [, type, projectId, contextId] = match;
    
    return {
      type: type as DraftType,
      projectName: projectId, // Would need lookup for actual name
      contextLabel: contextId,
      lastModified: new Date(), // Would need timestamp storage
    };
  }
}

export const draftRecoveryService = new DraftRecoveryService();
```

---

## Acceptance Criteria

| ID | Criterion | Priority | Validation |
|----|-----------|----------|------------|
| DRU-01 | Banner shows for unsaved drafts | MUST | E2E test |
| DRU-02 | Restore button applies draft content | MUST | E2E test |
| DRU-03 | Discard button removes draft permanently | MUST | E2E test |
| DRU-04 | Multi-draft dialog on crash recovery | SHOULD | E2E test |
| DRU-05 | Draft preview shows first 100 chars | SHOULD | Unit test |
| DRU-06 | Time ago display for lastModified | MUST | Unit test |
| DRU-07 | Banner dismissible without action | SHOULD | E2E test |
| DRU-08 | Auto-select recent drafts in dialog | SHOULD | Unit test |
| DRU-09 | Restore selected count updates | MUST | E2E test |
| DRU-10 | Accessible keyboard navigation | MUST | A11y test |

---

## Related Specs

- [Input State Persistence](./06-input-state-persistence.md) — Storage layer
- [Sync API](./02-sync-api.md) — Cross-device sync
- [Theme System](../10-theme-system/00-overview.md) — Styling tokens
