# File History & Comparison

**Version:** 0.1.0  
**Status:** Draft  
**Updated:** 2026-01-27  

---

## Overview

The File History & Comparison feature provides granular version tracking for individual files, combining Git commit history with `.history` file-based change tracking. Users can view, compare, and restore any previous version of a file.

---

## 13.1 History Sources

### Source 1: Git Commits
- Full file history from Git repository
- Commit hash, author, date, message
- Available when Git integration is enabled

### Source 2: .history File Tracking
- Automatic local versioning on each save
- Stored in `.history/{filepath}/` directory
- Filename format: `{timestamp}_{hash}.md`
- Faster access, more granular than Git

### Merged Timeline
Both sources are merged into a single unified timeline, with each entry tagged by source.

---

## 13.2 File History Panel Layout

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  History: 02-database-schema.md                                        [×] │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  Timeline                                               Filter: [All ▼]     │
│  ─────────────────────────────────────────────────────────────────────────  │
│                                                                              │
│  Today                                                                       │
│  ├─ 🕐 2:45 PM    .history    Auto-save                     [View] [Restore]│
│  ├─ 🕐 2:30 PM    .history    Auto-save                     [View] [Restore]│
│  ├─ 🕐 1:15 PM    🔀 Git      Added GORM models             [View] [Restore]│
│  │                            abc1234 by John                               │
│  │                                                                          │
│  Yesterday                                                                   │
│  ├─ 🕐 4:20 PM    🔀 Git      Initial schema design         [View] [Restore]│
│  │                            def5678 by Jane                               │
│  │                                                                          │
│  Jan 25, 2026                                                               │
│  └─ 🕐 11:00 AM   🔀 Git      Created file                  [View] [Restore]│
│                               ghi9012 by John                               │
│                                                                              │
│  ─────────────────────────────────────────────────────────────────────────  │
│                                                                              │
│  [Compare Selected] (select 2 versions to compare)                          │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 13.3 Version Comparison View

### Side-by-Side Diff

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  Compare Versions                                                      [×] │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌───────────────────────────────┐  ┌───────────────────────────────┐       │
│  │ Version A (Older)             │  │ Version B (Newer)             │       │
│  │ Jan 25, 4:20 PM  🔀 Git       │  │ Today, 1:15 PM  🔀 Git        │       │
│  │ abc1234                       │  │ def5678                       │       │
│  └───────────────────────────────┘  └───────────────────────────────┘       │
│                                                                              │
│  View: ● Side-by-Side  ○ Unified  ○ Split (Synced Scroll)                   │
│                                                                              │
│  ┌─────────────────────────────────┬─────────────────────────────────┐      │
│  │  ## 2.1 Schema Overview         │  ## 2.1 Schema Overview         │      │
│  │                                 │                                 │      │
│  │  The database uses SQLite       │  The database uses SQLite       │      │
│  │- with raw SQL queries.          │+ with GORM ORM models.          │      │
│  │                                 │                                 │      │
│  │  ### Tables                     │  ### Tables                     │      │
│  │                                 │                                 │      │
│  │  | Table | Description |        │  | Table | Description |        │      │
│  │  |-------|-------------|        │  |-------|-------------|        │      │
│  │  | users | User data   |        │  | users | User data   |        │      │
│  │                                 │+ | sessions | Auth tokens |     │      │
│  │                                 │                                 │      │
│  │                                 │+ ### GORM Models                │      │
│  │                                 │+                                │      │
│  │                                 │+ ```go                          │      │
│  │                                 │+ type User struct {             │      │
│  │                                 │+   gorm.Model                   │      │
│  │                                 │+   Email string `gorm:"unique"` │      │
│  │                                 │+ }                              │      │
│  │                                 │+ ```                            │      │
│  └─────────────────────────────────┴─────────────────────────────────┘      │
│                                                                              │
│  Summary: +18 lines, -2 lines, 3 sections changed                           │
│                                                                              │
│  [Close]                     [Restore Version A]  [Restore Version B]       │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Unified Diff View

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  Compare Versions                                                      [×] │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  Comparing: Jan 25, 4:20 PM → Today, 1:15 PM                                │
│                                                                              │
│  View: ○ Side-by-Side  ● Unified  ○ Split (Synced Scroll)                   │
│                                                                              │
│  ┌────────────────────────────────────────────────────────────────────────┐ │
│  │  @@ -3,4 +3,4 @@ ## 2.1 Schema Overview                                │ │
│  │                                                                        │ │
│  │    The database uses SQLite                                            │ │
│  │  - with raw SQL queries.                                               │ │
│  │  + with GORM ORM models.                                               │ │
│  │                                                                        │ │
│  │  @@ -8,2 +8,3 @@ ### Tables                                             │ │
│  │                                                                        │ │
│  │    | users | User data   |                                             │ │
│  │  + | sessions | Auth tokens |                                          │ │
│  │                                                                        │ │
│  │  @@ +15,12 @@                                                          │ │
│  │  + ### GORM Models                                                     │ │
│  │  +                                                                     │ │
│  │  + ```go                                                               │ │
│  │  + type User struct {                                                  │ │
│  │  +   gorm.Model                                                        │ │
│  │  +   Email string `gorm:"unique"`                                      │ │
│  │  + }                                                                   │ │
│  │  + ```                                                                 │ │
│  └────────────────────────────────────────────────────────────────────────┘ │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 13.4 Component Implementation

### History Panel

```typescript
// components/history/FileHistoryPanel.tsx
import { useState, useMemo } from 'react';
import { X, GitBranch, Clock, ChevronDown } from 'lucide-react';
import { format, isToday, isYesterday } from 'date-fns';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useFileHistory } from '@/hooks/useFileHistory';
import { ComparisonModal } from './ComparisonModal';
import { HistoryEntry, HistorySource } from '@/types/history';

interface FileHistoryPanelProps {
  fileId: string;
  filePath: string;
  onClose: () => void;
  onRestore: (versionId: string) => Promise<void>;
}

type FilterType = 'all' | 'git' | 'history';

export function FileHistoryPanel({ fileId, filePath, onClose, onRestore }: FileHistoryPanelProps) {
  const [filter, setFilter] = useState<FilterType>('all');
  const [selectedVersions, setSelectedVersions] = useState<string[]>([]);
  const [compareModalOpen, setCompareModalOpen] = useState(false);
  
  const { entries, isLoading } = useFileHistory(fileId);

  const filteredEntries = useMemo(() => {
    if (filter === 'all') return entries;
    return entries.filter((e) => e.source === filter);
  }, [entries, filter]);

  const groupedEntries = useMemo(() => {
    return groupByDate(filteredEntries);
  }, [filteredEntries]);

  const toggleSelection = (id: string) => {
    setSelectedVersions((prev) => {
      if (prev.includes(id)) {
        return prev.filter((v) => v !== id);
      }
      if (prev.length >= 2) {
        return [prev[1], id]; // Replace oldest selection
      }
      return [...prev, id];
    });
  };

  const handleCompare = () => {
    if (selectedVersions.length === 2) {
      setCompareModalOpen(true);
    }
  };

  if (isLoading) {
    return <HistoryPanelSkeleton />;
  }

  return (
    <div className="h-full flex flex-col bg-card border-l w-80">
      {/* Header */}
      <div className="flex items-center justify-between px-4 py-3 border-b">
        <div>
          <h2 className="text-sm font-semibold">History</h2>
          <p className="text-xs text-foreground-muted truncate max-w-[200px]">
            {filePath}
          </p>
        </div>
        <Button variant="ghost" size="icon" onClick={onClose}>
          <X className="h-4 w-4" />
        </Button>
      </div>

      {/* Filter */}
      <div className="px-4 py-2 border-b">
        <Select value={filter} onValueChange={(v) => setFilter(v as FilterType)}>
          <SelectTrigger className="h-8">
            <SelectValue placeholder="Filter" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All Sources</SelectItem>
            <SelectItem value="git">Git Commits Only</SelectItem>
            <SelectItem value="history">.history Only</SelectItem>
          </SelectContent>
        </Select>
      </div>

      {/* Timeline */}
      <ScrollArea className="flex-1">
        <div className="p-4 space-y-4">
          {Object.entries(groupedEntries).map(([dateLabel, items]) => (
            <div key={dateLabel}>
              <h3 className="text-xs font-medium text-foreground-muted mb-2">
                {dateLabel}
              </h3>
              <div className="space-y-1">
                {items.map((entry) => (
                  <HistoryEntryRow
                    key={entry.id}
                    entry={entry}
                    isSelected={selectedVersions.includes(entry.id)}
                    onSelect={() => toggleSelection(entry.id)}
                    onView={() => {/* Open preview modal */}}
                    onRestore={() => onRestore(entry.id)}
                  />
                ))}
              </div>
            </div>
          ))}
        </div>
      </ScrollArea>

      {/* Compare Footer */}
      <div className="px-4 py-3 border-t">
        <Button
          onClick={handleCompare}
          disabled={selectedVersions.length !== 2}
          className="w-full"
          variant="secondary"
        >
          Compare Selected ({selectedVersions.length}/2)
        </Button>
      </div>

      {/* Comparison Modal */}
      <ComparisonModal
        isOpen={compareModalOpen}
        onClose={() => setCompareModalOpen(false)}
        versionAId={selectedVersions[0]}
        versionBId={selectedVersions[1]}
        fileId={fileId}
        onRestore={onRestore}
      />
    </div>
  );
}

interface HistoryEntryRowProps {
  entry: HistoryEntry;
  isSelected: boolean;
  onSelect: () => void;
  onView: () => void;
  onRestore: () => void;
}

function HistoryEntryRow({ entry, isSelected, onSelect, onView, onRestore }: HistoryEntryRowProps) {
  return (
    <div
      className={cn(
        'flex items-center gap-2 p-2 rounded-lg cursor-pointer transition-colors',
        isSelected ? 'bg-primary/10 border border-primary/30' : 'hover:bg-muted/50'
      )}
      onClick={onSelect}
    >
      {/* Source Icon */}
      <div className="shrink-0">
        {entry.source === 'git' ? (
          <GitBranch className="h-4 w-4 text-primary" />
        ) : (
          <Clock className="h-4 w-4 text-foreground-muted" />
        )}
      </div>

      {/* Details */}
      <div className="flex-1 min-w-0">
        <div className="flex items-center gap-2">
          <span className="text-xs text-foreground-muted">
            {format(new Date(entry.timestamp), 'h:mm a')}
          </span>
          <Badge variant="outline" className="text-[10px] px-1">
            {entry.source === 'git' ? 'Git' : '.history'}
          </Badge>
        </div>
        <p className="text-sm truncate">
          {entry.message ?? (entry.source === 'history' ? 'Auto-save' : entry.commitHash?.slice(0, 7))}
        </p>
        {entry.source === 'git' && entry.author && (
          <p className="text-xs text-foreground-muted">by {entry.author}</p>
        )}
      </div>

      {/* Actions */}
      <div className="flex items-center gap-1" onClick={(e) => e.stopPropagation()}>
        <Button size="sm" variant="ghost" onClick={onView}>
          View
        </Button>
        <Button size="sm" variant="ghost" onClick={onRestore}>
          Restore
        </Button>
      </div>
    </div>
  );
}

function groupByDate(entries: HistoryEntry[]): Record<string, HistoryEntry[]> {
  const groups: Record<string, HistoryEntry[]> = {};
  
  entries.forEach((entry) => {
    const date = new Date(entry.timestamp);
    let label: string;
    
    if (isToday(date)) {
      label = 'Today';
    } else if (isYesterday(date)) {
      label = 'Yesterday';
    } else {
      label = format(date, 'MMM d, yyyy');
    }
    
    if (!groups[label]) {
      groups[label] = [];
    }
    groups[label].push(entry);
  });
  
  return groups;
}
```

---

## 13.5 Comparison Modal

```typescript
// components/history/ComparisonModal.tsx
import { useState, useMemo } from 'react';
import { format } from 'date-fns';
import { GitBranch, Clock, ArrowRight } from 'lucide-react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Label } from '@/components/ui/label';
import { ScrollArea } from '@/components/ui/scroll-area';
import { useFileVersionContent } from '@/hooks/useFileVersionContent';
import { DiffViewer } from './DiffViewer';

type ViewMode = 'side-by-side' | 'unified' | 'split-sync';

interface ComparisonModalProps {
  isOpen: boolean;
  onClose: () => void;
  versionAId: string;
  versionBId: string;
  fileId: string;
  onRestore: (versionId: string) => Promise<void>;
}

export function ComparisonModal({
  isOpen,
  onClose,
  versionAId,
  versionBId,
  fileId,
  onRestore,
}: ComparisonModalProps) {
  const [viewMode, setViewMode] = useState<ViewMode>('side-by-side');
  
  const { data: versionA, isLoading: loadingA } = useFileVersionContent(fileId, versionAId);
  const { data: versionB, isLoading: loadingB } = useFileVersionContent(fileId, versionBId);

  const isLoading = loadingA || loadingB;

  const diffStats = useMemo(() => {
    if (!versionA || !versionB) return null;
    return calculateDiffStats(versionA.content, versionB.content);
  }, [versionA, versionB]);

  const handleRestore = async (versionId: string) => {
    await onRestore(versionId);
    onClose();
  };

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="max-w-6xl h-[85vh] flex flex-col">
        <DialogHeader>
          <DialogTitle>Compare Versions</DialogTitle>
        </DialogHeader>

        {/* Version Headers */}
        <div className="flex items-center gap-4 py-2">
          <VersionBadge version={versionA} label="Version A (Older)" />
          <ArrowRight className="h-4 w-4 text-foreground-muted" />
          <VersionBadge version={versionB} label="Version B (Newer)" />
        </div>

        {/* View Mode Selector */}
        <div className="flex items-center gap-4 py-2 border-b">
          <span className="text-sm text-foreground-muted">View:</span>
          <RadioGroup
            value={viewMode}
            onValueChange={(v) => setViewMode(v as ViewMode)}
            className="flex gap-4"
          >
            <div className="flex items-center space-x-2">
              <RadioGroupItem value="side-by-side" id="side-by-side" />
              <Label htmlFor="side-by-side" className="text-sm">Side-by-Side</Label>
            </div>
            <div className="flex items-center space-x-2">
              <RadioGroupItem value="unified" id="unified" />
              <Label htmlFor="unified" className="text-sm">Unified</Label>
            </div>
            <div className="flex items-center space-x-2">
              <RadioGroupItem value="split-sync" id="split-sync" />
              <Label htmlFor="split-sync" className="text-sm">Split (Synced Scroll)</Label>
            </div>
          </RadioGroup>
        </div>

        {/* Diff Content */}
        <ScrollArea className="flex-1">
          {isLoading ? (
            <DiffSkeleton />
          ) : (
            <DiffViewer
              contentA={versionA?.content ?? ''}
              contentB={versionB?.content ?? ''}
              mode={viewMode}
            />
          )}
        </ScrollArea>

        {/* Summary & Actions */}
        <div className="flex items-center justify-between pt-4 border-t">
          <div className="text-sm text-foreground-muted">
            {diffStats && (
              <>
                <span className="text-green-600">+{diffStats.additions} lines</span>
                {', '}
                <span className="text-red-600">-{diffStats.deletions} lines</span>
                {', '}
                <span>{diffStats.changedSections} sections changed</span>
              </>
            )}
          </div>
          <div className="flex gap-2">
            <Button variant="outline" onClick={onClose}>
              Close
            </Button>
            <Button
              variant="secondary"
              onClick={() => handleRestore(versionAId)}
              disabled={!versionA}
            >
              Restore Version A
            </Button>
            <Button
              variant="secondary"
              onClick={() => handleRestore(versionBId)}
              disabled={!versionB}
            >
              Restore Version B
            </Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}

function VersionBadge({ version, label }: { version: any; label: string }) {
  if (!version) return null;
  
  return (
    <div className="flex items-center gap-2 p-2 rounded-lg bg-muted/50">
      {version.source === 'git' ? (
        <GitBranch className="h-4 w-4 text-primary" />
      ) : (
        <Clock className="h-4 w-4 text-foreground-muted" />
      )}
      <div>
        <div className="text-xs text-foreground-muted">{label}</div>
        <div className="text-sm font-medium">
          {format(new Date(version.timestamp), 'MMM d, h:mm a')}
        </div>
        {version.source === 'git' && (
          <Badge variant="outline" className="text-[10px]">
            {version.commitHash?.slice(0, 7)}
          </Badge>
        )}
      </div>
    </div>
  );
}

function calculateDiffStats(contentA: string, contentB: string) {
  const linesA = contentA.split('\n');
  const linesB = contentB.split('\n');
  
  // Simple line-based diff calculation
  let additions = 0;
  let deletions = 0;
  
  // Use LCS or similar algorithm for accurate diff
  // This is simplified for spec purposes
  const maxLen = Math.max(linesA.length, linesB.length);
  for (let i = 0; i < maxLen; i++) {
    if (linesA[i] !== linesB[i]) {
      if (i >= linesA.length) additions++;
      else if (i >= linesB.length) deletions++;
      else {
        additions++;
        deletions++;
      }
    }
  }
  
  return {
    additions,
    deletions,
    changedSections: Math.ceil((additions + deletions) / 5),
  };
}
```

---

## 13.6 Diff Viewer Component

```typescript
// components/history/DiffViewer.tsx
import { useMemo } from 'react';
import { diffLines, Change } from 'diff';
import { cn } from '@/lib/utils';

interface DiffViewerProps {
  contentA: string;
  contentB: string;
  mode: 'side-by-side' | 'unified' | 'split-sync';
}

export function DiffViewer({ contentA, contentB, mode }: DiffViewerProps) {
  const changes = useMemo(() => {
    return diffLines(contentA, contentB);
  }, [contentA, contentB]);

  if (mode === 'unified') {
    return <UnifiedDiff changes={changes} />;
  }

  if (mode === 'side-by-side') {
    return <SideBySideDiff changes={changes} />;
  }

  return <SplitSyncDiff contentA={contentA} contentB={contentB} changes={changes} />;
}

function UnifiedDiff({ changes }: { changes: Change[] }) {
  return (
    <div className="font-mono text-sm">
      {changes.map((change, index) => (
        <div
          key={index}
          className={cn(
            'px-4 py-0.5',
            change.added && 'bg-green-500/10 text-green-700 dark:text-green-400',
            change.removed && 'bg-red-500/10 text-red-700 dark:text-red-400'
          )}
        >
          {change.value.split('\n').filter(Boolean).map((line, i) => (
            <div key={i} className="flex">
              <span className="w-6 text-foreground-muted select-none">
                {change.added ? '+' : change.removed ? '-' : ' '}
              </span>
              <span className="flex-1">{line}</span>
            </div>
          ))}
        </div>
      ))}
    </div>
  );
}

function SideBySideDiff({ changes }: { changes: Change[] }) {
  const { leftLines, rightLines } = useMemo(() => {
    const left: DiffLine[] = [];
    const right: DiffLine[] = [];
    
    changes.forEach((change) => {
      const lines = change.value.split('\n').filter(Boolean);
      
      if (change.removed) {
        lines.forEach((line) => {
          left.push({ content: line, type: 'removed' });
          right.push({ content: '', type: 'empty' });
        });
      } else if (change.added) {
        lines.forEach((line) => {
          left.push({ content: '', type: 'empty' });
          right.push({ content: line, type: 'added' });
        });
      } else {
        lines.forEach((line) => {
          left.push({ content: line, type: 'unchanged' });
          right.push({ content: line, type: 'unchanged' });
        });
      }
    });
    
    return { leftLines: left, rightLines: right };
  }, [changes]);

  return (
    <div className="flex font-mono text-sm">
      <div className="flex-1 border-r">
        {leftLines.map((line, i) => (
          <DiffLine key={i} line={line} lineNumber={i + 1} />
        ))}
      </div>
      <div className="flex-1">
        {rightLines.map((line, i) => (
          <DiffLine key={i} line={line} lineNumber={i + 1} />
        ))}
      </div>
    </div>
  );
}

interface DiffLine {
  content: string;
  type: 'added' | 'removed' | 'unchanged' | 'empty';
}

function DiffLine({ line, lineNumber }: { line: DiffLine; lineNumber: number }) {
  return (
    <div
      className={cn(
        'flex px-2 py-0.5',
        line.type === 'added' && 'bg-green-500/10',
        line.type === 'removed' && 'bg-red-500/10',
        line.type === 'empty' && 'bg-muted/30'
      )}
    >
      <span className="w-8 text-foreground-muted text-right pr-2 select-none border-r mr-2">
        {line.type !== 'empty' ? lineNumber : ''}
      </span>
      <span
        className={cn(
          'flex-1',
          line.type === 'added' && 'text-green-700 dark:text-green-400',
          line.type === 'removed' && 'text-red-700 dark:text-red-400'
        )}
      >
        {line.content}
      </span>
    </div>
  );
}
```

---

## 13.7 Hooks

```typescript
// hooks/useFileHistory.ts
import { useQuery } from '@tanstack/react-query';
import { historyApi } from '@/api/history';

export function useFileHistory(fileId: string) {
  const { data: entries = [], isLoading, error } = useQuery({
    queryKey: ['fileHistory', fileId],
    queryFn: () => historyApi.getFileHistory(fileId),
    enabled: !!fileId,
  });

  return { entries, isLoading, error };
}

// hooks/useFileVersionContent.ts
import { useQuery } from '@tanstack/react-query';
import { historyApi } from '@/api/history';

export function useFileVersionContent(fileId: string, versionId: string) {
  return useQuery({
    queryKey: ['fileVersion', fileId, versionId],
    queryFn: () => historyApi.getVersionContent(fileId, versionId),
    enabled: !!fileId && !!versionId,
  });
}
```

---

## 13.8 Types

```typescript
// types/history.ts
export type HistorySource = 'git' | 'history';

export interface HistoryEntry {
  id: string;
  fileId: string;
  source: HistorySource;
  timestamp: string;
  message: string | null;
  
  // Git-specific
  commitHash?: string;
  author?: string;
  
  // .history-specific
  hash?: string;
  autoSave?: boolean;
}

export interface VersionContent {
  id: string;
  fileId: string;
  source: HistorySource;
  timestamp: string;
  content: string;
  commitHash?: string;
}

export interface DiffStats {
  additions: number;
  deletions: number;
  changedSections: number;
}
```

---

## 13.9 .history Directory Structure

```
project-root/
├── spec/
│   ├── 01-backend/
│   │   ├── 01-overview.md
│   │   └── 02-database-schema.md
│   └── 02-frontend/
│       └── 01-overview.md
└── .history/
    └── spec/
        ├── 01-backend/
        │   ├── 01-overview.md/
        │   │   ├── 1706380800_abc123.md
        │   │   ├── 1706384400_def456.md
        │   │   └── 1706388000_ghi789.md
        │   └── 02-database-schema.md/
        │       ├── 1706380800_jkl012.md
        │       └── 1706388000_mno345.md
        └── 02-frontend/
            └── 01-overview.md/
                └── 1706384400_pqr678.md
```

### File Naming Convention
- **Format:** `{unix_timestamp}_{content_hash}.md`
- **Timestamp:** Unix timestamp in seconds
- **Hash:** First 6 characters of SHA-256 of content

---

## 13.10 Acceptance Criteria

### History Panel

- [ ] Shows combined timeline from Git and .history
- [ ] Entries grouped by date (Today, Yesterday, older dates)
- [ ] Filter dropdown to show All/Git only/.history only
- [ ] Each entry shows time, source badge, message
- [ ] Git entries show commit hash and author
- [ ] .history entries show "Auto-save" message
- [ ] View button opens preview modal
- [ ] Restore button restores file content

### Version Selection

- [ ] Click to select/deselect versions
- [ ] Maximum 2 versions can be selected
- [ ] Selection highlighted with border
- [ ] Compare button enabled when 2 selected
- [ ] Selection persists during scroll

### Comparison Modal

- [ ] Shows version A and B metadata headers
- [ ] Three view modes: Side-by-Side, Unified, Split Sync
- [ ] View mode toggle persists during session
- [ ] Diff highlighting for additions (green)
- [ ] Diff highlighting for deletions (red)
- [ ] Line numbers displayed
- [ ] Summary stats (lines added/removed)
- [ ] Restore buttons for both versions

### Diff Viewer

- [ ] Side-by-side shows both versions aligned
- [ ] Unified shows single stream with +/- markers
- [ ] Split sync scrolls both panes together
- [ ] Empty lines shown for alignment in side-by-side
- [ ] Monospace font for code readability

### .history Integration

- [ ] Auto-save creates .history entry on file save
- [ ] .history files stored in correct directory structure
- [ ] Content hash prevents duplicate entries
- [ ] Old entries cleaned up per retention policy

---

## Related Specs

- [History UI](./03-history-ui.md)
- [History System](./02-history-system.md)
- [Git Integration](./01-git-integration.md)
