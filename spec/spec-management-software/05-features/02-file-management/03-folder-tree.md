# Folder Tree

**Version:** 0.1.0  
**Status:** Draft  
**Updated:** 2026-01-27  

---

## Overview

The Folder Tree component provides hierarchical file/folder navigation with drag-and-drop reorganization, context menus, and real-time sync with external changes.

---

## 4.1 Component Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                      FolderTree                              │
├─────────────────────────────────────────────────────────────┤
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────┐     │
│  │  TreeNode   │───▶│  DragDrop   │───▶│  Context    │     │
│  │  Renderer   │    │  Wrapper    │    │    Menu     │     │
│  └─────────────┘    └─────────────┘    └─────────────┘     │
│         │                  │                  │             │
│         ▼                  ▼                  ▼             │
│  ┌─────────────────────────────────────────────────┐       │
│  │                File Operations                   │       │
│  │        (create, rename, move, delete)           │       │
│  └─────────────────────────────────────────────────┘       │
└─────────────────────────────────────────────────────────────┘
```

---

## 4.2 Data Structure

```typescript
// types/file.ts
export interface FileNode {
  id: string;
  name: string;
  path: string;
  type: 'file' | 'folder';
  parentId: string | null;
  projectId: string;
  sortOrder: number;
  isModified: boolean;
  children?: FileNode[];
  createdAt: string;
  updatedAt: string;
}

export interface TreeState {
  expandedIds: Set<string>;
  selectedId: string | null;
  renamingId: string | null;
  dragOverId: string | null;
}
```

---

## 4.3 Visual Design

```
┌──────────────────────────────────────────┐
│  📁 spec-management-software         [+] │ ← Root with add button
├──────────────────────────────────────────┤
│  ▼ 📁 01-backend                         │ ← Expanded folder
│      📄 01-overview.md                   │
│      📄 02-database-schema.md        ●   │ ← Modified indicator
│      📄 03-api-endpoints.md              │
│      📄 05-git-integration.md            │
│      📄 06-history-system.md             │
│  ▶ 📁 02-frontend                        │ ← Collapsed folder
│  ▶ 📁 ideas                              │
│    📄 00-overview.md                     │ ← Selected (highlighted)
│    📄 01-roadmap.md                      │
└──────────────────────────────────────────┘
```

---

## 4.4 TreeNode Component

```typescript
// components/files/TreeNode.tsx
import { useState, useRef } from 'react';
import { useDrag, useDrop } from '@dnd-kit/core';
import { ChevronRight, ChevronDown, File, Folder, FolderOpen } from 'lucide-react';
import { cn } from '@/lib/utils';
import { FileNode, TreeState } from '@/types/file';
import { FileContextMenu } from './FileContextMenu';
import { Input } from '@/components/ui/input';

interface TreeNodeProps {
  node: FileNode;
  depth: number;
  state: TreeState;
  onSelect: (id: string) => void;
  onToggle: (id: string) => void;
  onRename: (id: string, newName: string) => void;
  onMove: (sourceId: string, targetId: string, position: 'before' | 'inside' | 'after') => void;
  onDelete: (id: string) => void;
  onCreate: (parentId: string, type: 'file' | 'folder') => void;
  onStartRename: (id: string) => void;
  onCancelRename: () => void;
}

export function TreeNode({
  node,
  depth,
  state,
  onSelect,
  onToggle,
  onRename,
  onMove,
  onDelete,
  onCreate,
  onStartRename,
  onCancelRename,
}: TreeNodeProps) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [renameValue, setRenameValue] = useState(node.name);
  
  const isExpanded = state.expandedIds.has(node.id);
  const isSelected = state.selectedId === node.id;
  const isRenaming = state.renamingId === node.id;
  const isDragOver = state.dragOverId === node.id;

  const handleClick = () => {
    if (node.type === 'folder') {
      onToggle(node.id);
    }
    onSelect(node.id);
  };

  const handleRenameSubmit = () => {
    if (renameValue.trim() && renameValue !== node.name) {
      onRename(node.id, renameValue.trim());
    }
    onCancelRename();
  };

  const handleRenameKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter') {
      handleRenameSubmit();
    } else if (e.key === 'Escape') {
      setRenameValue(node.name);
      onCancelRename();
    }
  };

  const Icon = node.type === 'folder' 
    ? (isExpanded ? FolderOpen : Folder)
    : File;

  return (
    <FileContextMenu
      node={node}
      onRename={() => onStartRename(node.id)}
      onDelete={() => onDelete(node.id)}
      onCreate={onCreate}
    >
      <div
        className={cn(
          'flex items-center gap-1 py-1 px-2 cursor-pointer rounded-sm',
          'hover:bg-sidebar-accent transition-colors',
          isSelected && 'bg-sidebar-accent text-sidebar-accent-foreground',
          isDragOver && 'ring-2 ring-primary'
        )}
        style={{ paddingLeft: `${depth * 16 + 8}px` }}
        onClick={handleClick}
      >
        {/* Expand/Collapse Toggle */}
        {node.type === 'folder' && (
          <button
            onClick={(e) => { e.stopPropagation(); onToggle(node.id); }}
            className="p-0.5 hover:bg-sidebar-accent rounded"
          >
            {isExpanded ? (
              <ChevronDown className="h-3 w-3" />
            ) : (
              <ChevronRight className="h-3 w-3" />
            )}
          </button>
        )}
        
        {/* Spacer for files */}
        {node.type === 'file' && <span className="w-4" />}

        {/* Icon */}
        <Icon className={cn(
          'h-4 w-4 flex-shrink-0',
          node.type === 'folder' ? 'text-primary' : 'text-foreground-muted'
        )} />

        {/* Name or Rename Input */}
        {isRenaming ? (
          <Input
            ref={inputRef}
            value={renameValue}
            onChange={(e) => setRenameValue(e.target.value)}
            onBlur={handleRenameSubmit}
            onKeyDown={handleRenameKeyDown}
            className="h-6 text-sm py-0 px-1"
            autoFocus
          />
        ) : (
          <span className="truncate text-sm flex-1">{node.name}</span>
        )}

        {/* Modified Indicator */}
        {node.isModified && (
          <span className="h-2 w-2 rounded-full bg-warning flex-shrink-0" />
        )}
      </div>

      {/* Children */}
      {node.type === 'folder' && isExpanded && node.children && (
        <div>
          {node.children.map((child) => (
            <TreeNode
              key={child.id}
              node={child}
              depth={depth + 1}
              state={state}
              onSelect={onSelect}
              onToggle={onToggle}
              onRename={onRename}
              onMove={onMove}
              onDelete={onDelete}
              onCreate={onCreate}
              onStartRename={onStartRename}
              onCancelRename={onCancelRename}
            />
          ))}
        </div>
      )}
    </FileContextMenu>
  );
}
```

---

## 4.5 Context Menu

```typescript
// components/files/FileContextMenu.tsx
import { ReactNode } from 'react';
import {
  ContextMenu,
  ContextMenuContent,
  ContextMenuItem,
  ContextMenuSeparator,
  ContextMenuTrigger,
} from '@/components/ui/context-menu';
import { File, Folder, Pencil, Trash2, Copy, Clipboard } from 'lucide-react';
import { FileNode } from '@/types/file';

interface FileContextMenuProps {
  node: FileNode;
  children: ReactNode;
  onRename: () => void;
  onDelete: () => void;
  onCreate: (parentId: string, type: 'file' | 'folder') => void;
}

export function FileContextMenu({
  node,
  children,
  onRename,
  onDelete,
  onCreate,
}: FileContextMenuProps) {
  const parentId = node.type === 'folder' ? node.id : node.parentId;

  return (
    <ContextMenu>
      <ContextMenuTrigger asChild>
        {children}
      </ContextMenuTrigger>
      <ContextMenuContent className="w-48">
        {node.type === 'folder' && (
          <>
            <ContextMenuItem onClick={() => onCreate(node.id, 'file')}>
              <File className="h-4 w-4 mr-2" />
              New File
            </ContextMenuItem>
            <ContextMenuItem onClick={() => onCreate(node.id, 'folder')}>
              <Folder className="h-4 w-4 mr-2" />
              New Folder
            </ContextMenuItem>
            <ContextMenuSeparator />
          </>
        )}
        <ContextMenuItem onClick={onRename}>
          <Pencil className="h-4 w-4 mr-2" />
          Rename
        </ContextMenuItem>
        <ContextMenuItem onClick={() => navigator.clipboard.writeText(node.path)}>
          <Copy className="h-4 w-4 mr-2" />
          Copy Path
        </ContextMenuItem>
        <ContextMenuSeparator />
        <ContextMenuItem onClick={onDelete} className="text-destructive">
          <Trash2 className="h-4 w-4 mr-2" />
          Delete
        </ContextMenuItem>
      </ContextMenuContent>
    </ContextMenu>
  );
}
```

---

## 4.6 Drag and Drop

Using `@dnd-kit/core` for accessible drag-and-drop:

```typescript
// components/files/DragDropWrapper.tsx
import { ReactNode, useState } from 'react';
import {
  DndContext,
  DragOverlay,
  closestCenter,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  DragStartEvent,
  DragEndEvent,
  DragOverEvent,
} from '@dnd-kit/core';
import {
  sortableKeyboardCoordinates,
} from '@dnd-kit/sortable';
import { FileNode } from '@/types/file';

interface DragDropWrapperProps {
  children: ReactNode;
  onMove: (sourceId: string, targetId: string, position: 'before' | 'inside' | 'after') => void;
}

export function DragDropWrapper({ children, onMove }: DragDropWrapperProps) {
  const [activeNode, setActiveNode] = useState<FileNode | null>(null);

  const sensors = useSensors(
    useSensor(PointerSensor, {
      activationConstraint: {
        distance: 8, // Minimum drag distance
      },
    }),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates,
    })
  );

  const handleDragStart = (event: DragStartEvent) => {
    setActiveNode(event.active.data.current as FileNode);
  };

  const handleDragOver = (event: DragOverEvent) => {
    // Update drag over state for visual feedback
  };

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;
    
    if (over && active.id !== over.id) {
      const position = determineDropPosition(event);
      onMove(active.id as string, over.id as string, position);
    }
    
    setActiveNode(null);
  };

  return (
    <DndContext
      sensors={sensors}
      collisionDetection={closestCenter}
      onDragStart={handleDragStart}
      onDragOver={handleDragOver}
      onDragEnd={handleDragEnd}
    >
      {children}
      <DragOverlay>
        {activeNode && (
          <div className="bg-card border rounded px-2 py-1 shadow-lg opacity-90">
            {activeNode.name}
          </div>
        )}
      </DragOverlay>
    </DndContext>
  );
}

function determineDropPosition(event: DragEndEvent): 'before' | 'inside' | 'after' {
  // Calculate based on mouse position relative to target
  const overRect = event.over?.rect;
  const pointerY = event.activatorEvent instanceof PointerEvent 
    ? event.activatorEvent.clientY 
    : 0;
  
  if (!overRect) return 'after';
  
  const threshold = overRect.height / 4;
  const relativeY = pointerY - overRect.top;
  
  if (relativeY < threshold) return 'before';
  if (relativeY > overRect.height - threshold) return 'after';
  return 'inside';
}
```

---

## 4.7 FolderTree Container

```typescript
// components/files/FolderTree.tsx
import { useState, useCallback, useMemo } from 'react';
import { Plus } from 'lucide-react';
import { useFiles } from '@/hooks/useFiles';
import { TreeNode } from './TreeNode';
import { DragDropWrapper } from './DragDropWrapper';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import { FileNode, TreeState } from '@/types/file';

interface FolderTreeProps {
  projectId: string;
  onFileSelect: (file: FileNode) => void;
}

export function FolderTree({ projectId, onFileSelect }: FolderTreeProps) {
  const { tree, createFile, renameFile, moveFile, deleteFile } = useFiles(projectId);
  
  const [state, setState] = useState<TreeState>({
    expandedIds: new Set(),
    selectedId: null,
    renamingId: null,
    dragOverId: null,
  });

  const handleSelect = useCallback((id: string) => {
    setState((prev) => ({ ...prev, selectedId: id }));
    const file = findNodeById(tree, id);
    if (file && file.type === 'file') {
      onFileSelect(file);
    }
  }, [tree, onFileSelect]);

  const handleToggle = useCallback((id: string) => {
    setState((prev) => {
      const newExpanded = new Set(prev.expandedIds);
      if (newExpanded.has(id)) {
        newExpanded.delete(id);
      } else {
        newExpanded.add(id);
      }
      return { ...prev, expandedIds: newExpanded };
    });
  }, []);

  const handleStartRename = useCallback((id: string) => {
    setState((prev) => ({ ...prev, renamingId: id }));
  }, []);

  const handleCancelRename = useCallback(() => {
    setState((prev) => ({ ...prev, renamingId: null }));
  }, []);

  const handleRename = useCallback((id: string, newName: string) => {
    renameFile(id, newName);
  }, [renameFile]);

  const handleMove = useCallback((sourceId: string, targetId: string, position: string) => {
    moveFile(sourceId, targetId, position as 'before' | 'inside' | 'after');
  }, [moveFile]);

  const handleDelete = useCallback((id: string) => {
    deleteFile(id);
  }, [deleteFile]);

  const handleCreate = useCallback((parentId: string | null, type: 'file' | 'folder') => {
    const name = type === 'file' ? 'new-file.md' : 'new-folder';
    createFile({ parentId, name, type });
    if (parentId) {
      setState((prev) => ({
        ...prev,
        expandedIds: new Set([...prev.expandedIds, parentId]),
      }));
    }
  }, [createFile]);

  return (
    <div className="h-full flex flex-col bg-sidebar">
      {/* Header */}
      <div className="flex items-center justify-between p-3 border-b">
        <span className="text-sm font-medium">Files</span>
        <Button
          variant="ghost"
          size="icon"
          className="h-6 w-6"
          onClick={() => handleCreate(null, 'file')}
        >
          <Plus className="h-4 w-4" />
        </Button>
      </div>

      {/* Tree */}
      <ScrollArea className="flex-1">
        <DragDropWrapper onMove={handleMove}>
          <div className="py-2">
            {tree.map((node) => (
              <TreeNode
                key={node.id}
                node={node}
                depth={0}
                state={state}
                onSelect={handleSelect}
                onToggle={handleToggle}
                onRename={handleRename}
                onMove={handleMove}
                onDelete={handleDelete}
                onCreate={handleCreate}
                onStartRename={handleStartRename}
                onCancelRename={handleCancelRename}
              />
            ))}
          </div>
        </DragDropWrapper>
      </ScrollArea>
    </div>
  );
}

function findNodeById(nodes: FileNode[], id: string): FileNode | null {
  for (const node of nodes) {
    if (node.id === id) return node;
    if (node.children) {
      const found = findNodeById(node.children, id);
      if (found) return found;
    }
  }
  return null;
}
```

---

## 4.8 useFiles Hook

```typescript
// hooks/useFiles.ts
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { filesApi } from '@/api/files';
import { toast } from 'sonner';

export function useFiles(projectId: string) {
  const queryClient = useQueryClient();

  const { data: tree = [], isLoading } = useQuery({
    queryKey: ['files', projectId, 'tree'],
    queryFn: () => filesApi.getTree(projectId),
    enabled: !!projectId,
  });

  const createMutation = useMutation({
    mutationFn: filesApi.create,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['files', projectId] });
      toast.success('Created successfully');
    },
    onError: (error: Error) => toast.error(error.message),
  });

  const renameMutation = useMutation({
    mutationFn: ({ id, name }: { id: string; name: string }) =>
      filesApi.rename(id, name),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['files', projectId] });
      toast.success('Renamed successfully');
    },
    onError: (error: Error) => toast.error(error.message),
  });

  const moveMutation = useMutation({
    mutationFn: ({ sourceId, targetId, position }: {
      sourceId: string;
      targetId: string;
      position: 'before' | 'inside' | 'after';
    }) => filesApi.move(sourceId, targetId, position),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['files', projectId] });
      toast.success('Moved successfully');
    },
    onError: (error: Error) => toast.error(error.message),
  });

  const deleteMutation = useMutation({
    mutationFn: filesApi.delete,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['files', projectId] });
      toast.success('Deleted successfully');
    },
    onError: (error: Error) => toast.error(error.message),
  });

  return {
    tree,
    isLoading,
    createFile: createMutation.mutate,
    renameFile: (id: string, name: string) => renameMutation.mutate({ id, name }),
    moveFile: (sourceId: string, targetId: string, position: 'before' | 'inside' | 'after') =>
      moveMutation.mutate({ sourceId, targetId, position }),
    deleteFile: deleteMutation.mutate,
  };
}
```

---

## 4.9 Keyboard Navigation

| Key | Action |
|-----|--------|
| `↑` / `↓` | Navigate between items |
| `←` | Collapse folder / go to parent |
| `→` | Expand folder / enter folder |
| `Enter` | Open file / toggle folder |
| `F2` | Start rename |
| `Delete` | Delete with confirmation |
| `Ctrl+N` | New file in current folder |
| `Ctrl+Shift+N` | New folder in current folder |

---

## 4.10 External Change Detection

Real-time sync with filesystem changes made outside the app:

```typescript
// hooks/useExternalChanges.ts
import { useEffect } from 'react';
import { useQueryClient } from '@tanstack/react-query';

export function useExternalChanges(projectId: string) {
  const queryClient = useQueryClient();

  useEffect(() => {
    // SSE or WebSocket connection for real-time updates
    const eventSource = new EventSource(`/api/v1/projects/${projectId}/watch`);

    eventSource.onmessage = (event) => {
      const change = JSON.parse(event.data);
      
      // Invalidate queries to refresh tree
      queryClient.invalidateQueries({ queryKey: ['files', projectId] });
      
      // Show notification for external changes
      toast.info(`External change: ${change.type} ${change.path}`);
    };

    return () => eventSource.close();
  }, [projectId, queryClient]);
}
```

---

## 4.11 Acceptance Criteria

### Functional Requirements

- [ ] Tree displays files and folders hierarchically
- [ ] Single click selects file/folder
- [ ] Double click opens file in editor
- [ ] Expand/collapse folders with chevron toggle
- [ ] Create new file via context menu
- [ ] Create new folder via context menu
- [ ] Rename file/folder with inline editing (F2)
- [ ] Delete file/folder with confirmation modal
- [ ] Drag and drop reorders files within folder
- [ ] Drag and drop moves files between folders
- [ ] Copy path to clipboard via context menu

### Visual Requirements

- [ ] Selected item has highlight background
- [ ] Drag indicator shows valid drop targets
- [ ] Modified indicator (dot) appears for unsaved files
- [ ] Folder icons change when expanded
- [ ] Proper indentation for nested items
- [ ] External changes sync automatically (visual update)

### Keyboard Navigation

- [ ] Arrow keys navigate up/down through tree
- [ ] Enter opens selected file
- [ ] Space toggles folder expand/collapse
- [ ] F2 starts rename mode
- [ ] Delete key triggers delete confirmation

### Performance Requirements

- [ ] Tree renders 500+ files without lag
- [ ] Sort order preserved after operations
- [ ] Deep nesting (10 levels) renders correctly

---

## Related Specs

- [File Management Overview](./00-overview.md)
- [File Operations](./01-file-operations.md)
- [Path Manager](./02-path-manager.md)
