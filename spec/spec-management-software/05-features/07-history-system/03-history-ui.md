# History Management UI

**Version:** 0.1.0  
**Status:** Draft  
**Updated:** 2026-01-27  

---

## Overview

The History Management UI provides snapshot creation, viewing, restoration, and deletion with visual timeline, confirmation dialogs, and pre-restore safety measures.

---

## 6.1 Layout Structure

```
┌─────────────────────────────────────────────────────────────────────────┐
│  History                                              [+ New Snapshot]  │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │  Timeline View                                                   │   │
│  │                                                                  │   │
│  │  ●────●────●────●────●────●                                     │   │
│  │  V01  V02  V03  V04  V05  V06                                   │   │
│  │                    ↑                                             │   │
│  │               (selected)                                         │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                         │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │  V04-2026-01-25                                                  │   │
│  │  ─────────────────────────────────────────────────────────────── │   │
│  │                                                                  │   │
│  │  📅 Created: January 25, 2026 at 2:30 PM                        │   │
│  │  📝 Description: Added API endpoints spec                        │   │
│  │  📁 Files: 42 files in snapshot                                  │   │
│  │                                                                  │   │
│  │  ┌───────────────────────────────────────────────────────────┐  │   │
│  │  │  Files in this snapshot:                                   │  │   │
│  │  │  ├── 01-backend/                                           │  │   │
│  │  │  │   ├── 01-overview.md                                    │  │   │
│  │  │  │   ├── 02-database-schema.md                             │  │   │
│  │  │  │   └── 03-api-endpoints.md                               │  │   │
│  │  │  ├── 02-frontend/                                          │  │   │
│  │  │  │   └── ...                                               │  │   │
│  │  └───────────────────────────────────────────────────────────┘  │   │
│  │                                                                  │   │
│  │  [Restore]  [Download]  [Delete]                                │   │
│  │                                                                  │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 6.2 Snapshot List Component

```typescript
// components/history/SnapshotList.tsx
import { useState } from 'react';
import { Plus } from 'lucide-react';
import { useSnapshots } from '@/hooks/useSnapshots';
import { SnapshotTimeline } from './SnapshotTimeline';
import { SnapshotCard } from './SnapshotCard';
import { SnapshotCreateModal } from './SnapshotCreateModal';
import { RestoreConfirmModal } from './RestoreConfirmModal';
import { DeleteConfirmModal } from '@/components/ui/DeleteConfirmModal';
import { Button } from '@/components/ui/button';
import { Snapshot } from '@/types/snapshot';

interface SnapshotListProps {
  projectId: string;
}

export function SnapshotList({ projectId }: SnapshotListProps) {
  const { snapshots, isLoading, createSnapshot, restoreSnapshot, deleteSnapshot } = useSnapshots(projectId);
  
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [restoringId, setRestoringId] = useState<string | null>(null);
  const [deletingId, setDeletingId] = useState<string | null>(null);

  const selectedSnapshot = snapshots.find((s) => s.id === selectedId);

  const handleCreate = async (description: string) => {
    await createSnapshot({ projectId, description });
    setIsCreateModalOpen(false);
  };

  const handleRestore = async () => {
    if (restoringId) {
      await restoreSnapshot(restoringId);
      setRestoringId(null);
    }
  };

  const handleDelete = async () => {
    if (deletingId) {
      await deleteSnapshot(deletingId);
      setDeletingId(null);
      if (selectedId === deletingId) {
        setSelectedId(null);
      }
    }
  };

  if (isLoading) {
    return <SnapshotListSkeleton />;
  }

  return (
    <div className="h-full flex flex-col">
      {/* Header */}
      <div className="flex items-center justify-between p-4 border-b">
        <h2 className="text-lg font-semibold">History</h2>
        <Button onClick={() => setIsCreateModalOpen(true)}>
          <Plus className="h-4 w-4 mr-2" />
          New Snapshot
        </Button>
      </div>

      {/* Timeline */}
      {snapshots.length > 0 && (
        <div className="p-4 border-b">
          <SnapshotTimeline
            snapshots={snapshots}
            selectedId={selectedId}
            onSelect={setSelectedId}
          />
        </div>
      )}

      {/* Selected Snapshot Details */}
      <div className="flex-1 overflow-auto p-4">
        {selectedSnapshot ? (
          <SnapshotCard
            snapshot={selectedSnapshot}
            onRestore={() => setRestoringId(selectedSnapshot.id)}
            onDelete={() => setDeletingId(selectedSnapshot.id)}
          />
        ) : snapshots.length === 0 ? (
          <EmptyState onCreateClick={() => setIsCreateModalOpen(true)} />
        ) : (
          <div className="flex items-center justify-center h-full text-foreground-muted">
            Select a snapshot to view details
          </div>
        )}
      </div>

      {/* Modals */}
      <SnapshotCreateModal
        isOpen={isCreateModalOpen}
        onClose={() => setIsCreateModalOpen(false)}
        onCreate={handleCreate}
      />

      <RestoreConfirmModal
        isOpen={!!restoringId}
        onClose={() => setRestoringId(null)}
        onConfirm={handleRestore}
        snapshotName={snapshots.find((s) => s.id === restoringId)?.name ?? ''}
      />

      <DeleteConfirmModal
        isOpen={!!deletingId}
        onClose={() => setDeletingId(null)}
        onConfirm={handleDelete}
        title="Delete Snapshot"
        description="This will permanently delete the snapshot and its files. This action cannot be undone."
      />
    </div>
  );
}

function EmptyState({ onCreateClick }: { onCreateClick: () => void }) {
  return (
    <div className="flex flex-col items-center justify-center h-full text-foreground-muted">
      <Camera className="h-12 w-12 mb-4 opacity-50" />
      <h3 className="text-lg font-medium mb-2">No snapshots yet</h3>
      <p className="text-sm mb-4">Create a snapshot to save the current state</p>
      <Button onClick={onCreateClick}>
        <Plus className="h-4 w-4 mr-2" />
        Create First Snapshot
      </Button>
    </div>
  );
}
```

---

## 6.3 Timeline Component

```typescript
// components/history/SnapshotTimeline.tsx
import { format } from 'date-fns';
import { cn } from '@/lib/utils';
import { Snapshot } from '@/types/snapshot';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';

interface SnapshotTimelineProps {
  snapshots: Snapshot[];
  selectedId: string | null;
  onSelect: (id: string) => void;
}

export function SnapshotTimeline({ snapshots, selectedId, onSelect }: SnapshotTimelineProps) {
  return (
    <ScrollArea className="w-full">
      <div className="flex items-center gap-2 py-4 px-2">
        {/* Timeline Line */}
        <div className="absolute h-0.5 bg-border" style={{ width: `${snapshots.length * 80}px` }} />
        
        {snapshots.map((snapshot, index) => (
          <TimelineNode
            key={snapshot.id}
            snapshot={snapshot}
            isSelected={snapshot.id === selectedId}
            isFirst={index === 0}
            isLast={index === snapshots.length - 1}
            onClick={() => onSelect(snapshot.id)}
          />
        ))}
      </div>
      <ScrollBar orientation="horizontal" />
    </ScrollArea>
  );
}

interface TimelineNodeProps {
  snapshot: Snapshot;
  isSelected: boolean;
  isFirst: boolean;
  isLast: boolean;
  onClick: () => void;
}

function TimelineNode({ snapshot, isSelected, onClick }: TimelineNodeProps) {
  const displayName = snapshot.name.split('-').slice(0, 1).join(''); // V01, V02, etc.
  
  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <button
          onClick={onClick}
          className={cn(
            'relative flex flex-col items-center gap-1 w-16 transition-all',
            'focus:outline-none focus-visible:ring-2 focus-visible:ring-primary'
          )}
        >
          {/* Node Circle */}
          <div
            className={cn(
              'h-4 w-4 rounded-full border-2 transition-all',
              isSelected
                ? 'bg-primary border-primary scale-125'
                : 'bg-background border-border hover:border-primary'
            )}
          />
          
          {/* Label */}
          <span
            className={cn(
              'text-xs font-mono',
              isSelected ? 'text-primary font-medium' : 'text-foreground-muted'
            )}
          >
            {displayName}
          </span>
        </button>
      </TooltipTrigger>
      <TooltipContent>
        <div className="text-sm">
          <p className="font-medium">{snapshot.name}</p>
          <p className="text-foreground-muted">
            {format(new Date(snapshot.createdAt), 'PPp')}
          </p>
        </div>
      </TooltipContent>
    </Tooltip>
  );
}
```

---

## 6.4 Snapshot Card

```typescript
// components/history/SnapshotCard.tsx
import { format } from 'date-fns';
import { Calendar, FileText, FolderTree, RotateCcw, Download, Trash2 } from 'lucide-react';
import { Card, CardHeader, CardContent, CardFooter } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Snapshot } from '@/types/snapshot';

interface SnapshotCardProps {
  snapshot: Snapshot;
  onRestore: () => void;
  onDelete: () => void;
}

export function SnapshotCard({ snapshot, onRestore, onDelete }: SnapshotCardProps) {
  const isPreRestore = snapshot.name.includes('PRE-RESTORE');

  return (
    <Card>
      <CardHeader className="pb-3">
        <div className="flex items-center justify-between">
          <h3 className="text-lg font-semibold">{snapshot.name}</h3>
          {isPreRestore && (
            <Badge variant="secondary">Auto-created</Badge>
          )}
        </div>
      </CardHeader>

      <CardContent className="space-y-4">
        {/* Metadata */}
        <div className="space-y-2 text-sm">
          <div className="flex items-center gap-2 text-foreground-muted">
            <Calendar className="h-4 w-4" />
            <span>Created: {format(new Date(snapshot.createdAt), 'PPPp')}</span>
          </div>
          
          {snapshot.description && (
            <div className="flex items-start gap-2 text-foreground-muted">
              <FileText className="h-4 w-4 mt-0.5" />
              <span>{snapshot.description}</span>
            </div>
          )}
          
          <div className="flex items-center gap-2 text-foreground-muted">
            <FolderTree className="h-4 w-4" />
            <span>{snapshot.fileCount} files in snapshot</span>
          </div>
        </div>

        {/* File Tree Preview */}
        {snapshot.files && snapshot.files.length > 0 && (
          <div className="border rounded-lg">
            <div className="px-3 py-2 bg-muted/50 border-b text-sm font-medium">
              Files in this snapshot
            </div>
            <ScrollArea className="h-48">
              <div className="p-3 text-sm font-mono">
                <FileTreePreview files={snapshot.files} />
              </div>
            </ScrollArea>
          </div>
        )}
      </CardContent>

      <CardFooter className="flex gap-2">
        <Button onClick={onRestore} className="flex-1">
          <RotateCcw className="h-4 w-4 mr-2" />
          Restore
        </Button>
        <Button variant="outline" onClick={() => downloadSnapshot(snapshot.id)}>
          <Download className="h-4 w-4" />
        </Button>
        <Button variant="outline" className="text-destructive" onClick={onDelete}>
          <Trash2 className="h-4 w-4" />
        </Button>
      </CardFooter>
    </Card>
  );
}

function FileTreePreview({ files }: { files: string[] }) {
  // Build tree structure from flat file paths
  const tree = buildTreeFromPaths(files);
  
  return (
    <ul className="space-y-0.5">
      {renderTree(tree, 0)}
    </ul>
  );
}

function renderTree(node: TreeNode, depth: number): JSX.Element[] {
  return Object.entries(node).map(([name, children]) => (
    <li key={name} style={{ paddingLeft: `${depth * 16}px` }}>
      {typeof children === 'object' && Object.keys(children).length > 0 ? (
        <>
          <span className="text-primary">📁 {name}/</span>
          <ul>{renderTree(children as TreeNode, depth + 1)}</ul>
        </>
      ) : (
        <span className="text-foreground-muted">📄 {name}</span>
      )}
    </li>
  ));
}

interface TreeNode {
  [key: string]: TreeNode | null;
}

function buildTreeFromPaths(paths: string[]): TreeNode {
  const tree: TreeNode = {};
  
  paths.forEach((path) => {
    const parts = path.split('/');
    let current = tree;
    
    parts.forEach((part, index) => {
      if (!current[part]) {
        current[part] = index === parts.length - 1 ? null : {};
      }
      if (current[part] !== null) {
        current = current[part] as TreeNode;
      }
    });
  });
  
  return tree;
}

async function downloadSnapshot(snapshotId: string) {
  const response = await fetch(`/api/v1/snapshots/${snapshotId}/download`);
  const blob = await response.blob();
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `snapshot-${snapshotId}.zip`;
  a.click();
  window.URL.revokeObjectURL(url);
}
```

---

## 6.5 Create Snapshot Modal

```typescript
// components/history/SnapshotCreateModal.tsx
import { useState } from 'react';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Button } from '@/components/ui/button';

interface SnapshotCreateModalProps {
  isOpen: boolean;
  onClose: () => void;
  onCreate: (description: string) => Promise<void>;
}

export function SnapshotCreateModal({ isOpen, onClose, onCreate }: SnapshotCreateModalProps) {
  const [description, setDescription] = useState('');
  const [isCreating, setIsCreating] = useState(false);

  const handleCreate = async () => {
    setIsCreating(true);
    try {
      await onCreate(description);
      setDescription('');
      onClose();
    } finally {
      setIsCreating(false);
    }
  };

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Create Snapshot</DialogTitle>
        </DialogHeader>

        <div className="space-y-4 py-4">
          <div className="space-y-2">
            <Label htmlFor="description">Description (optional)</Label>
            <Textarea
              id="description"
              placeholder="What changes are included in this snapshot?"
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              rows={3}
            />
          </div>

          <div className="rounded-lg bg-muted p-3 text-sm text-foreground-muted">
            <p>
              A snapshot will be created with the name pattern{' '}
              <code className="font-mono">V{'{nn}'}-{'{YYYY-MM-DD}'}</code>
            </p>
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={onClose} disabled={isCreating}>
            Cancel
          </Button>
          <Button onClick={handleCreate} disabled={isCreating}>
            {isCreating ? 'Creating...' : 'Create Snapshot'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
```

---

## 6.6 Restore Confirmation Modal

```typescript
// components/history/RestoreConfirmModal.tsx
import { AlertTriangle } from 'lucide-react';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';

interface RestoreConfirmModalProps {
  isOpen: boolean;
  onClose: () => void;
  onConfirm: () => void;
  snapshotName: string;
}

export function RestoreConfirmModal({
  isOpen,
  onClose,
  onConfirm,
  snapshotName,
}: RestoreConfirmModalProps) {
  return (
    <AlertDialog open={isOpen} onOpenChange={onClose}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <div className="flex items-center gap-3">
            <div className="p-2 rounded-full bg-warning/10">
              <AlertTriangle className="h-5 w-5 text-warning" />
            </div>
            <AlertDialogTitle>Restore Snapshot</AlertDialogTitle>
          </div>
          <AlertDialogDescription className="space-y-2">
            <p>
              You are about to restore <strong>{snapshotName}</strong>.
            </p>
            <p>
              This will replace all current files with the snapshot version.
              A <strong>PRE-RESTORE</strong> snapshot will be created automatically
              so you can recover your current state if needed.
            </p>
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>Cancel</AlertDialogCancel>
          <AlertDialogAction onClick={onConfirm}>
            Restore Snapshot
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
}
```

---

## 6.7 useSnapshots Hook

```typescript
// hooks/useSnapshots.ts
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { snapshotsApi } from '@/api/snapshots';
import { toast } from 'sonner';

export function useSnapshots(projectId: string) {
  const queryClient = useQueryClient();

  const { data: snapshots = [], isLoading } = useQuery({
    queryKey: ['snapshots', projectId],
    queryFn: () => snapshotsApi.getAll(projectId),
    enabled: !!projectId,
  });

  const createMutation = useMutation({
    mutationFn: snapshotsApi.create,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['snapshots', projectId] });
      toast.success('Snapshot created successfully');
    },
    onError: (error: Error) => toast.error(error.message),
  });

  const restoreMutation = useMutation({
    mutationFn: snapshotsApi.restore,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['snapshots', projectId] });
      queryClient.invalidateQueries({ queryKey: ['files', projectId] });
      toast.success('Snapshot restored successfully. PRE-RESTORE snapshot created.');
    },
    onError: (error: Error) => toast.error(error.message),
  });

  const deleteMutation = useMutation({
    mutationFn: snapshotsApi.delete,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['snapshots', projectId] });
      toast.success('Snapshot deleted successfully');
    },
    onError: (error: Error) => toast.error(error.message),
  });

  return {
    snapshots,
    isLoading,
    createSnapshot: createMutation.mutate,
    restoreSnapshot: restoreMutation.mutate,
    deleteSnapshot: deleteMutation.mutate,
    isCreating: createMutation.isPending,
    isRestoring: restoreMutation.isPending,
    isDeleting: deleteMutation.isPending,
  };
}
```

---

## 6.8 Snapshot Types

```typescript
// types/snapshot.ts
export interface Snapshot {
  id: string;
  projectId: string;
  name: string;           // V01-2026-01-27
  description: string | null;
  fileCount: number;
  files?: string[];       // Optional: list of file paths
  createdAt: string;
}

export interface CreateSnapshotRequest {
  projectId: string;
  description?: string;
}

export interface RestoreSnapshotRequest {
  snapshotId: string;
}
```

---

## 6.9 Retention Display

Show retention policy info in the UI:

```typescript
// components/history/RetentionInfo.tsx
import { Info } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';

interface RetentionInfoProps {
  maxAge: number;      // days
  maxCount: number;    // snapshots
  currentCount: number;
}

export function RetentionInfo({ maxAge, maxCount, currentCount }: RetentionInfoProps) {
  return (
    <Alert variant="default" className="bg-muted/50">
      <Info className="h-4 w-4" />
      <AlertDescription className="text-sm">
        Snapshots are retained for {maxAge} days or up to {maxCount} snapshots.
        You currently have {currentCount} snapshot{currentCount !== 1 ? 's' : ''}.
      </AlertDescription>
    </Alert>
  );
}
```

---

## 6.10 Acceptance Criteria

### Functional Requirements

- [ ] Snapshot list displays all snapshots chronologically
- [ ] Timeline shows visual snapshot history
- [ ] Clicking timeline node selects snapshot
- [ ] Snapshot card shows full details (date, description, file count)
- [ ] Create snapshot modal opens with description input
- [ ] Description is optional when creating snapshot
- [ ] Snapshot name auto-generated (V{nn}-{YYYY-MM-DD})
- [ ] Restore shows confirmation dialog with warning
- [ ] Restore creates PRE-RESTORE snapshot automatically
- [ ] Delete shows confirmation dialog
- [ ] Delete removes files and database record
- [ ] Download exports snapshot as ZIP file

### Visual Requirements

- [ ] Timeline scrolls horizontally for many snapshots
- [ ] Selected node highlighted on timeline
- [ ] Empty state shows when no snapshots exist
- [ ] Loading states during create/restore/delete operations
- [ ] Auto-created snapshots labeled with badge

### Confirmation Dialogs

- [ ] Restore dialog warns about overwriting current state
- [ ] Delete dialog warns action is irreversible
- [ ] Both dialogs require explicit button click

### Error Handling

- [ ] Error handling with toast notifications
- [ ] Failed operations show retry option
- [ ] Retention policy info displayed in UI

### Accessibility Requirements

- [ ] Timeline navigable with keyboard
- [ ] Dialog focus trapped when open
- [ ] Screen reader announces snapshot selection

---

## Related Specs

- [History System Overview](./00-overview.md)
- [History System](./02-history-system.md)
- [File History Comparison](./04-file-history-comparison.md)
