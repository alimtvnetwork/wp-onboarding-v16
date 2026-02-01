# Phase 6.4: Sharing UI Components

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  
**Parent:** [Cross-Project Memory](./06-cross-project-memory.md)

---

## Overview

Complete UI component library for memory sharing, including share management panels, memory browsers, and integration with the AI chat interface.

---

## 1. Component Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        SHARING UI COMPONENTS                                │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  Dialogs                          Panels                                    │
│  ┌─────────────────────┐         ┌─────────────────────────────────┐       │
│  │ ShareDialog         │         │ SharedMemoriesPanel             │       │
│  │ - Target selection  │         │ - List of incoming shares       │       │
│  │ - Permission picker │         │ - Filter/search                 │       │
│  │ - Expiration        │         │ - Quick actions                 │       │
│  └─────────────────────┘         └─────────────────────────────────┘       │
│                                                                             │
│  ┌─────────────────────┐         ┌─────────────────────────────────┐       │
│  │ ConflictDialog      │         │ OutgoingSharesPanel             │       │
│  │ - Diff viewer       │         │ - Shares you created            │       │
│  │ - Resolution picker │         │ - Revoke/manage                 │       │
│  │ - Merge editor      │         │ - Sync status                   │       │
│  └─────────────────────┘         └─────────────────────────────────┘       │
│                                                                             │
│  Browsers                         Widgets                                   │
│  ┌─────────────────────┐         ┌─────────────────────────────────┐       │
│  │ MemoryBrowser       │         │ ShareBadge                      │       │
│  │ - File tree view    │         │ - Inline indicator              │       │
│  │ - Search/filter     │         │                                 │       │
│  │ - Multi-select      │         │ ShareStatusIndicator            │       │
│  └─────────────────────┘         │ - Sync state display            │       │
│                                   │                                 │       │
│  ┌─────────────────────┐         │ ContextSourceBadge              │       │
│  │ ProjectPicker       │         │ - RAG attribution               │       │
│  │ - Project list      │         └─────────────────────────────────┘       │
│  │ - Search            │                                                   │
│  │ - Recent projects   │                                                   │
│  └─────────────────────┘                                                   │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Shared Memories Panel

### 2.1 Main Panel Component

```typescript
// components/sharing/SharedMemoriesPanel.tsx

import { useState, useMemo } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  Search, Filter, SortAsc, FolderOpen, FileText, Link,
  Brain, ExternalLink, MoreHorizontal, RefreshCw, Trash2,
  Copy, Eye, Share2
} from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { ShareCard } from './ShareCard';
import { EmptyState } from './EmptyState';
import { useToast } from '@/hooks/use-toast';
import { formatDistanceToNow } from 'date-fns';
import type { MemoryShare, ShareResourceType } from '@/types/sharing';

interface SharedMemoriesPanelProps {
  projectId: string;
  onSelectShare: (share: MemoryShare) => void;
  onViewContent: (share: MemoryShare) => void;
}

export function SharedMemoriesPanel({
  projectId,
  onSelectShare,
  onViewContent,
}: SharedMemoriesPanelProps) {
  const [tab, setTab] = useState<'incoming' | 'outgoing'>('incoming');
  const [search, setSearch] = useState('');
  const [typeFilter, setTypeFilter] = useState<ShareResourceType | 'all'>('all');
  const [sortBy, setSortBy] = useState<'recent' | 'name' | 'project'>('recent');
  
  const queryClient = useQueryClient();
  const { toast } = useToast();
  
  // Fetch incoming shares
  const { data: incomingShares = [], isLoading: loadingIncoming } = useQuery({
    queryKey: ['shares', 'incoming', projectId],
    queryFn: async () => {
      const res = await fetch(`/api/v1/projects/${projectId}/shares/incoming`);
      return res.json() as Promise<MemoryShare[]>;
    },
  });
  
  // Fetch outgoing shares
  const { data: outgoingShares = [], isLoading: loadingOutgoing } = useQuery({
    queryKey: ['shares', 'outgoing', projectId],
    queryFn: async () => {
      const res = await fetch(`/api/v1/projects/${projectId}/shares/outgoing`);
      return res.json() as Promise<MemoryShare[]>;
    },
  });
  
  // Revoke mutation
  const revokeMutation = useMutation({
    mutationFn: async (shareId: string) => {
      const res = await fetch(`/api/v1/sharing/shares/${shareId}`, {
        method: 'DELETE',
      });
      if (!res.ok) throw new Error('Failed to revoke');
    },
    onSuccess: () => {
      toast({ title: 'Share revoked' });
      queryClient.invalidateQueries({ queryKey: ['shares'] });
    },
  });
  
  // Copy to local mutation
  const copyMutation = useMutation({
    mutationFn: async (shareId: string) => {
      const res = await fetch(`/api/v1/sharing/shares/${shareId}/copy`, {
        method: 'POST',
      });
      if (!res.ok) throw new Error('Failed to copy');
      return res.json();
    },
    onSuccess: (data) => {
      toast({ title: 'Copied to project', description: data.path });
    },
  });
  
  // Filter and sort shares
  const shares = tab === 'incoming' ? incomingShares : outgoingShares;
  const isLoading = tab === 'incoming' ? loadingIncoming : loadingOutgoing;
  
  const filteredShares = useMemo(() => {
    let result = [...shares];
    
    // Search filter
    if (search) {
      const lower = search.toLowerCase();
      result = result.filter(s => 
        s.resourceName.toLowerCase().includes(lower) ||
        s.resourcePath.toLowerCase().includes(lower) ||
        s.sourceProjectName.toLowerCase().includes(lower)
      );
    }
    
    // Type filter
    if (typeFilter !== 'all') {
      result = result.filter(s => s.resourceType === typeFilter);
    }
    
    // Sort
    result.sort((a, b) => {
      switch (sortBy) {
        case 'recent':
          return new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime();
        case 'name':
          return a.resourceName.localeCompare(b.resourceName);
        case 'project':
          return a.sourceProjectName.localeCompare(b.sourceProjectName);
        default:
          return 0;
      }
    });
    
    return result;
  }, [shares, search, typeFilter, sortBy]);
  
  return (
    <div className="flex flex-col h-full">
      {/* Header */}
      <div className="p-4 border-b space-y-4">
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-semibold flex items-center gap-2">
            <Share2 className="h-5 w-5" />
            Shared Memories
          </h2>
          <Button
            variant="ghost"
            size="icon"
            onClick={() => queryClient.invalidateQueries({ queryKey: ['shares'] })}
          >
            <RefreshCw className="h-4 w-4" />
          </Button>
        </div>
        
        <Tabs value={tab} onValueChange={(v) => setTab(v as 'incoming' | 'outgoing')}>
          <TabsList className="grid grid-cols-2">
            <TabsTrigger value="incoming" className="gap-2">
              Incoming
              {incomingShares.length > 0 && (
                <Badge variant="secondary" className="ml-1">
                  {incomingShares.length}
                </Badge>
              )}
            </TabsTrigger>
            <TabsTrigger value="outgoing" className="gap-2">
              Outgoing
              {outgoingShares.length > 0 && (
                <Badge variant="secondary" className="ml-1">
                  {outgoingShares.length}
                </Badge>
              )}
            </TabsTrigger>
          </TabsList>
        </Tabs>
        
        {/* Search and filters */}
        <div className="flex gap-2">
          <div className="relative flex-1">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <Input
              placeholder="Search shares..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="pl-9"
            />
          </div>
          
          <Select value={typeFilter} onValueChange={(v) => setTypeFilter(v as any)}>
            <SelectTrigger className="w-28">
              <Filter className="h-4 w-4 mr-2" />
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All types</SelectItem>
              <SelectItem value="spec">Specs</SelectItem>
              <SelectItem value="folder">Folders</SelectItem>
              <SelectItem value="memory">Memories</SelectItem>
              <SelectItem value="url">URLs</SelectItem>
            </SelectContent>
          </Select>
          
          <Select value={sortBy} onValueChange={(v) => setSortBy(v as any)}>
            <SelectTrigger className="w-28">
              <SortAsc className="h-4 w-4 mr-2" />
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="recent">Recent</SelectItem>
              <SelectItem value="name">Name</SelectItem>
              <SelectItem value="project">Project</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>
      
      {/* Content */}
      <ScrollArea className="flex-1">
        <div className="p-4 space-y-3">
          {isLoading ? (
            Array.from({ length: 3 }).map((_, i) => (
              <Skeleton key={i} className="h-24" />
            ))
          ) : filteredShares.length === 0 ? (
            <EmptyState
              icon={tab === 'incoming' ? Brain : Share2}
              title={tab === 'incoming' ? 'No shared memories' : 'No outgoing shares'}
              description={
                tab === 'incoming'
                  ? 'When others share memories with this project, they'll appear here.'
                  : 'Share specs and memories from this project with others.'
              }
            />
          ) : (
            filteredShares.map((share) => (
              <ShareCard
                key={share.id}
                share={share}
                variant={tab}
                onView={() => onViewContent(share)}
                onCopy={
                  share.permissions !== 'read'
                    ? () => copyMutation.mutate(share.id)
                    : undefined
                }
                onRevoke={
                  tab === 'outgoing'
                    ? () => revokeMutation.mutate(share.id)
                    : undefined
                }
                onClick={() => onSelectShare(share)}
              />
            ))
          )}
        </div>
      </ScrollArea>
    </div>
  );
}
```

### 2.2 Share Card Component

```typescript
// components/sharing/ShareCard.tsx

import { memo } from 'react';
import {
  FileText, FolderOpen, Link, Brain, ExternalLink,
  MoreHorizontal, Copy, Eye, Trash2, RefreshCw
} from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SyncStatusIndicator } from './SyncStatusIndicator';
import { formatDistanceToNow } from 'date-fns';
import { cn } from '@/lib/utils';
import type { MemoryShare } from '@/types/sharing';

interface ShareCardProps {
  share: MemoryShare;
  variant: 'incoming' | 'outgoing';
  onView?: () => void;
  onCopy?: () => void;
  onRevoke?: () => void;
  onSync?: () => void;
  onClick?: () => void;
}

export const ShareCard = memo(function ShareCard({
  share,
  variant,
  onView,
  onCopy,
  onRevoke,
  onSync,
  onClick,
}: ShareCardProps) {
  const typeIcons = {
    spec: FileText,
    folder: FolderOpen,
    url: Link,
    memory: Brain,
    file: FileText,
    collection: FolderOpen,
  };
  
  const Icon = typeIcons[share.resourceType] || FileText;
  
  const permissionColors = {
    read: 'bg-blue-500/10 text-blue-700 dark:text-blue-400',
    copy: 'bg-green-500/10 text-green-700 dark:text-green-400',
    sync: 'bg-purple-500/10 text-purple-700 dark:text-purple-400',
    edit: 'bg-orange-500/10 text-orange-700 dark:text-orange-400',
  };
  
  return (
    <Card
      className={cn(
        'hover:bg-muted/50 transition-colors cursor-pointer',
        share.status !== 'active' && 'opacity-60'
      )}
      onClick={onClick}
    >
      <CardContent className="p-4">
        <div className="flex items-start gap-4">
          {/* Icon */}
          <div className="p-2 rounded-lg bg-muted">
            <Icon className="h-5 w-5" />
          </div>
          
          {/* Content */}
          <div className="flex-1 min-w-0">
            <div className="flex items-start justify-between gap-2">
              <div className="min-w-0">
                <p className="font-medium truncate">{share.resourceName}</p>
                <p className="text-sm text-muted-foreground truncate">
                  {share.resourcePath}
                </p>
              </div>
              
              {/* Actions */}
              <DropdownMenu>
                <DropdownMenuTrigger asChild onClick={(e) => e.stopPropagation()}>
                  <Button variant="ghost" size="icon" className="h-8 w-8">
                    <MoreHorizontal className="h-4 w-4" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                  {onView && (
                    <DropdownMenuItem onClick={onView}>
                      <Eye className="h-4 w-4 mr-2" />
                      View content
                    </DropdownMenuItem>
                  )}
                  {onCopy && (
                    <DropdownMenuItem onClick={onCopy}>
                      <Copy className="h-4 w-4 mr-2" />
                      Copy to project
                    </DropdownMenuItem>
                  )}
                  {onSync && share.permissions === 'sync' && (
                    <DropdownMenuItem onClick={onSync}>
                      <RefreshCw className="h-4 w-4 mr-2" />
                      Sync now
                    </DropdownMenuItem>
                  )}
                  {onRevoke && (
                    <>
                      <DropdownMenuSeparator />
                      <DropdownMenuItem
                        onClick={onRevoke}
                        className="text-destructive"
                      >
                        <Trash2 className="h-4 w-4 mr-2" />
                        Revoke share
                      </DropdownMenuItem>
                    </>
                  )}
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
            
            {/* Metadata */}
            <div className="flex items-center gap-2 mt-2 flex-wrap">
              <Badge
                variant="outline"
                className={cn('text-xs', permissionColors[share.permissions])}
              >
                {share.permissions}
              </Badge>
              
              {variant === 'incoming' && (
                <span className="text-xs text-muted-foreground flex items-center gap-1">
                  from {share.sourceProjectName}
                  <ExternalLink className="h-3 w-3" />
                </span>
              )}
              
              {variant === 'outgoing' && (
                <span className="text-xs text-muted-foreground flex items-center gap-1">
                  to {share.targetProjectName}
                </span>
              )}
              
              <span className="text-xs text-muted-foreground">
                {formatDistanceToNow(new Date(share.createdAt))} ago
              </span>
            </div>
            
            {/* Sync status for sync shares */}
            {share.permissions === 'sync' && share.syncState && (
              <div className="mt-2">
                <SyncStatusIndicator
                  shareId={share.id}
                  syncState={share.syncState}
                  compact
                />
              </div>
            )}
          </div>
        </div>
      </CardContent>
    </Card>
  );
});
```

---

## 3. Memory Browser

```typescript
// components/sharing/MemoryBrowser.tsx

import { useState, useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import {
  ChevronRight, ChevronDown, FileText, FolderOpen, FolderClosed,
  Search, Check, X
} from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { ScrollArea } from '@/components/ui/scroll-area';
import { cn } from '@/lib/utils';

interface FileNode {
  name: string;
  path: string;
  type: 'file' | 'folder';
  children?: FileNode[];
}

interface MemoryBrowserProps {
  projectId: string;
  selectedPaths: string[];
  onSelectionChange: (paths: string[]) => void;
  multiSelect?: boolean;
  fileTypes?: string[];
}

export function MemoryBrowser({
  projectId,
  selectedPaths,
  onSelectionChange,
  multiSelect = true,
  fileTypes = ['.md'],
}: MemoryBrowserProps) {
  const [search, setSearch] = useState('');
  const [expandedFolders, setExpandedFolders] = useState<Set<string>>(new Set());
  
  // Fetch file tree
  const { data: fileTree = [] } = useQuery({
    queryKey: ['file-tree', projectId],
    queryFn: async () => {
      const res = await fetch(`/api/v1/projects/${projectId}/files/tree`);
      return res.json() as Promise<FileNode[]>;
    },
  });
  
  // Filter tree by search
  const filteredTree = useMemo(() => {
    if (!search) return fileTree;
    
    const filterNode = (node: FileNode): FileNode | null => {
      const matches = node.name.toLowerCase().includes(search.toLowerCase());
      
      if (node.type === 'folder') {
        const filteredChildren = node.children
          ?.map(filterNode)
          .filter((n): n is FileNode => n !== null);
        
        if (filteredChildren?.length || matches) {
          return { ...node, children: filteredChildren };
        }
        return null;
      }
      
      return matches ? node : null;
    };
    
    return fileTree.map(filterNode).filter((n): n is FileNode => n !== null);
  }, [fileTree, search]);
  
  const toggleFolder = (path: string) => {
    setExpandedFolders(prev => {
      const next = new Set(prev);
      if (next.has(path)) {
        next.delete(path);
      } else {
        next.add(path);
      }
      return next;
    });
  };
  
  const toggleSelection = (path: string) => {
    if (multiSelect) {
      if (selectedPaths.includes(path)) {
        onSelectionChange(selectedPaths.filter(p => p !== path));
      } else {
        onSelectionChange([...selectedPaths, path]);
      }
    } else {
      onSelectionChange([path]);
    }
  };
  
  const selectAll = () => {
    const allPaths = getAllFilePaths(fileTree);
    onSelectionChange(allPaths);
  };
  
  const clearAll = () => {
    onSelectionChange([]);
  };
  
  return (
    <div className="flex flex-col h-full border rounded-lg">
      {/* Search header */}
      <div className="p-3 border-b space-y-2">
        <div className="relative">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            placeholder="Search files..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="pl-9"
          />
        </div>
        
        {multiSelect && (
          <div className="flex items-center justify-between text-xs">
            <span className="text-muted-foreground">
              {selectedPaths.length} selected
            </span>
            <div className="flex gap-2">
              <Button variant="ghost" size="sm" className="h-6 text-xs" onClick={selectAll}>
                Select all
              </Button>
              <Button variant="ghost" size="sm" className="h-6 text-xs" onClick={clearAll}>
                Clear
              </Button>
            </div>
          </div>
        )}
      </div>
      
      {/* Tree view */}
      <ScrollArea className="flex-1">
        <div className="p-2">
          {filteredTree.map((node) => (
            <TreeNode
              key={node.path}
              node={node}
              depth={0}
              expandedFolders={expandedFolders}
              selectedPaths={selectedPaths}
              onToggleFolder={toggleFolder}
              onToggleSelection={toggleSelection}
              multiSelect={multiSelect}
            />
          ))}
        </div>
      </ScrollArea>
    </div>
  );
}

interface TreeNodeProps {
  node: FileNode;
  depth: number;
  expandedFolders: Set<string>;
  selectedPaths: string[];
  onToggleFolder: (path: string) => void;
  onToggleSelection: (path: string) => void;
  multiSelect: boolean;
}

function TreeNode({
  node,
  depth,
  expandedFolders,
  selectedPaths,
  onToggleFolder,
  onToggleSelection,
  multiSelect,
}: TreeNodeProps) {
  const isFolder = node.type === 'folder';
  const isExpanded = expandedFolders.has(node.path);
  const isSelected = selectedPaths.includes(node.path);
  
  const Icon = isFolder
    ? isExpanded ? FolderOpen : FolderClosed
    : FileText;
  
  return (
    <div>
      <div
        className={cn(
          'flex items-center gap-2 px-2 py-1.5 rounded-md cursor-pointer',
          'hover:bg-muted transition-colors',
          isSelected && 'bg-primary/10'
        )}
        style={{ paddingLeft: `${depth * 16 + 8}px` }}
        onClick={() => isFolder ? onToggleFolder(node.path) : onToggleSelection(node.path)}
      >
        {isFolder && (
          <span className="w-4 h-4 flex items-center justify-center">
            {isExpanded ? (
              <ChevronDown className="h-3 w-3" />
            ) : (
              <ChevronRight className="h-3 w-3" />
            )}
          </span>
        )}
        
        {!isFolder && multiSelect && (
          <Checkbox
            checked={isSelected}
            onCheckedChange={() => onToggleSelection(node.path)}
            onClick={(e) => e.stopPropagation()}
          />
        )}
        
        <Icon className="h-4 w-4 flex-shrink-0" />
        <span className="text-sm truncate">{node.name}</span>
        
        {!isFolder && !multiSelect && isSelected && (
          <Check className="h-4 w-4 text-primary ml-auto" />
        )}
      </div>
      
      {isFolder && isExpanded && node.children?.map((child) => (
        <TreeNode
          key={child.path}
          node={child}
          depth={depth + 1}
          expandedFolders={expandedFolders}
          selectedPaths={selectedPaths}
          onToggleFolder={onToggleFolder}
          onToggleSelection={onToggleSelection}
          multiSelect={multiSelect}
        />
      ))}
    </div>
  );
}

function getAllFilePaths(nodes: FileNode[]): string[] {
  const paths: string[] = [];
  
  const traverse = (node: FileNode) => {
    if (node.type === 'file') {
      paths.push(node.path);
    } else if (node.children) {
      node.children.forEach(traverse);
    }
  };
  
  nodes.forEach(traverse);
  return paths;
}
```

---

## 4. Chat Integration

### 4.1 Memory Reference in Chat

```typescript
// components/ai/MemoryReferenceChip.tsx

import { memo } from 'react';
import { FileText, FolderOpen, Brain, ExternalLink, X } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import type { MemoryShare } from '@/types/sharing';

interface MemoryReferenceChipProps {
  share: MemoryShare;
  onRemove?: () => void;
  onClick?: () => void;
  className?: string;
}

export const MemoryReferenceChip = memo(function MemoryReferenceChip({
  share,
  onRemove,
  onClick,
  className,
}: MemoryReferenceChipProps) {
  const icons = {
    spec: FileText,
    folder: FolderOpen,
    memory: Brain,
    file: FileText,
    url: ExternalLink,
    collection: FolderOpen,
  };
  
  const Icon = icons[share.resourceType] || FileText;
  
  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <Badge
          variant="secondary"
          className={cn(
            'gap-1.5 pr-1 cursor-pointer hover:bg-secondary/80',
            className
          )}
          onClick={onClick}
        >
          <Icon className="h-3 w-3" />
          <span className="max-w-24 truncate">{share.resourceName}</span>
          {share.sourceProjectId && (
            <ExternalLink className="h-3 w-3 opacity-50" />
          )}
          {onRemove && (
            <button
              onClick={(e) => {
                e.stopPropagation();
                onRemove();
              }}
              className="ml-0.5 hover:bg-muted rounded-full p-0.5"
            >
              <X className="h-3 w-3" />
            </button>
          )}
        </Badge>
      </TooltipTrigger>
      <TooltipContent>
        <div className="space-y-1 text-xs">
          <p className="font-medium">{share.resourceName}</p>
          <p className="text-muted-foreground">{share.resourcePath}</p>
          {share.sourceProjectName && (
            <p className="text-muted-foreground">
              From: {share.sourceProjectName}
            </p>
          )}
        </div>
      </TooltipContent>
    </Tooltip>
  );
});
```

### 4.2 Memory Picker for Chat Input

```typescript
// components/ai/MemoryPickerDialog.tsx

import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { MemoryBrowser } from '../sharing/MemoryBrowser';
import { SharedMemoriesList } from '../sharing/SharedMemoriesList';
import { Brain, FileText, Share2 } from 'lucide-react';
import type { MemoryShare } from '@/types/sharing';

interface MemoryPickerDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  projectId: string;
  onSelect: (items: MemoryPickerSelection[]) => void;
}

export interface MemoryPickerSelection {
  type: 'local' | 'shared';
  path: string;
  name: string;
  share?: MemoryShare;
}

export function MemoryPickerDialog({
  open,
  onOpenChange,
  projectId,
  onSelect,
}: MemoryPickerDialogProps) {
  const [tab, setTab] = useState<'local' | 'shared'>('local');
  const [selectedLocalPaths, setSelectedLocalPaths] = useState<string[]>([]);
  const [selectedShares, setSelectedShares] = useState<MemoryShare[]>([]);
  
  const handleConfirm = () => {
    const selections: MemoryPickerSelection[] = [
      ...selectedLocalPaths.map(path => ({
        type: 'local' as const,
        path,
        name: path.split('/').pop() || path,
      })),
      ...selectedShares.map(share => ({
        type: 'shared' as const,
        path: share.resourcePath,
        name: share.resourceName,
        share,
      })),
    ];
    
    onSelect(selections);
    onOpenChange(false);
    
    // Reset
    setSelectedLocalPaths([]);
    setSelectedShares([]);
  };
  
  const totalSelected = selectedLocalPaths.length + selectedShares.length;
  
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl max-h-[80vh]">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Brain className="h-5 w-5" />
            Add Context
          </DialogTitle>
        </DialogHeader>
        
        <Tabs value={tab} onValueChange={(v) => setTab(v as 'local' | 'shared')}>
          <TabsList className="grid grid-cols-2">
            <TabsTrigger value="local" className="gap-2">
              <FileText className="h-4 w-4" />
              Project Files
            </TabsTrigger>
            <TabsTrigger value="shared" className="gap-2">
              <Share2 className="h-4 w-4" />
              Shared Memories
            </TabsTrigger>
          </TabsList>
          
          <TabsContent value="local" className="mt-4 h-96">
            <MemoryBrowser
              projectId={projectId}
              selectedPaths={selectedLocalPaths}
              onSelectionChange={setSelectedLocalPaths}
              multiSelect
            />
          </TabsContent>
          
          <TabsContent value="shared" className="mt-4 h-96">
            <SharedMemoriesList
              projectId={projectId}
              selectedShares={selectedShares}
              onSelectionChange={setSelectedShares}
              multiSelect
            />
          </TabsContent>
        </Tabs>
        
        <DialogFooter>
          <div className="flex items-center gap-4">
            <span className="text-sm text-muted-foreground">
              {totalSelected} item{totalSelected !== 1 ? 's' : ''} selected
            </span>
            <Button variant="outline" onClick={() => onOpenChange(false)}>
              Cancel
            </Button>
            <Button onClick={handleConfirm} disabled={totalSelected === 0}>
              Add to Context
            </Button>
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
```

---

## 5. Testing Requirements

| Test | Description | Priority |
|------|-------------|----------|
| Share card rendering | All share types render correctly | Critical |
| Panel filtering | Search and type filters work | High |
| Memory browser | File tree navigation works | High |
| Multi-select | Multiple items can be selected | High |
| Chat integration | References show in chat input | High |
| Responsive design | Works on mobile viewport | Medium |
| Empty states | Appropriate messages shown | Medium |

---

## Related Specs

- [Sharing Architecture](./06-01-sharing-architecture.md)
- [Sync Mechanism](./06-02-sync-mechanism.md)
- [RAG Integration](./06-03-rag-integration.md)
- [Chat UI](./05-chat-ui-redesign.md)
