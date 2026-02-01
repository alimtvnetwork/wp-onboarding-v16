# Phase 6.2: Sync Mechanism

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  
**Parent:** [Cross-Project Memory](./06-cross-project-memory.md)

---

## Overview

Bi-directional synchronization for shared memories with conflict detection, resolution strategies, and offline-first capabilities.

---

## 1. Sync Architecture

### 1.1 Sync Flow

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           SYNC ARCHITECTURE                                 │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  SOURCE PROJECT                         TARGET PROJECT                      │
│  ┌─────────────┐                       ┌─────────────┐                     │
│  │   spec.md   │                       │   (ref)     │                     │
│  │  v3 (hash)  │                       │   v2        │                     │
│  └──────┬──────┘                       └──────┬──────┘                     │
│         │                                     │                            │
│         ▼                                     ▼                            │
│  ┌─────────────────────────────────────────────────────────┐              │
│  │                    SYNC SERVICE                         │              │
│  │  ┌───────────┐  ┌───────────┐  ┌───────────────────┐   │              │
│  │  │  Detect   │──│  Compare  │──│  Resolve/Apply    │   │              │
│  │  │  Changes  │  │  Versions │  │  Changes          │   │              │
│  │  └───────────┘  └───────────┘  └───────────────────┘   │              │
│  │                                                         │              │
│  │  Change Detection:      Conflict Resolution:            │              │
│  │  • Hash comparison      • Last-write-wins               │              │
│  │  • Version vectors      • Manual merge                  │              │
│  │  • File watchers        • Keep both                     │              │
│  │                         • Source priority               │              │
│  └─────────────────────────────────────────────────────────┘              │
│                                                                             │
│  SYNC MODES:                                                                │
│  • Push:      Source → Target (one-way)                                    │
│  • Pull:      Source ← Target (one-way)                                    │
│  • Bi-direct: Source ↔ Target (two-way with conflict handling)             │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 1.2 Sync State Model

```typescript
// types/sync.ts

export interface SyncState {
  shareId: string;
  
  // Version tracking
  sourceVersion: number;
  targetVersion: number;
  lastCommonVersion: number;
  
  // Hashes for change detection
  sourceHash: string;
  targetHash: string;
  lastSyncHash: string;
  
  // Timestamps
  lastSyncedAt: Date;
  sourceModifiedAt: Date;
  targetModifiedAt: Date;
  
  // State
  status: SyncStatus;
  direction: SyncDirection;
  
  // Conflict info
  hasConflict: boolean;
  conflict?: ConflictInfo;
}

export type SyncStatus =
  | 'synced'          // Both sides match
  | 'pending'         // Changes waiting to sync
  | 'syncing'         // Sync in progress
  | 'conflict'        // Conflict needs resolution
  | 'error';          // Sync failed

export type SyncDirection =
  | 'push'            // Source → Target
  | 'pull'            // Target → Source
  | 'bidirectional';  // Both ways

export interface ConflictInfo {
  type: ConflictType;
  sourceContent: string;
  targetContent: string;
  baseContent?: string; // Last synced version
  sourceModifiedBy: string;
  targetModifiedBy: string;
  sourceModifiedAt: Date;
  targetModifiedAt: Date;
}

export type ConflictType =
  | 'content'         // Both sides modified
  | 'delete_modify'   // One deleted, one modified
  | 'rename'          // Both renamed differently
  | 'type_change';    // Resource type changed

export type ConflictResolution =
  | 'use_source'      // Keep source version
  | 'use_target'      // Keep target version
  | 'use_both'        // Create copies of both
  | 'merge'           // Manual merge
  | 'skip';           // Don't sync this time

export interface SyncEvent {
  id: string;
  shareId: string;
  eventType: SyncEventType;
  direction: SyncDirection;
  beforeHash: string;
  afterHash: string;
  changesApplied: number;
  timestamp: Date;
  triggeredBy: 'manual' | 'auto' | 'webhook';
}

export type SyncEventType =
  | 'sync_started'
  | 'sync_completed'
  | 'conflict_detected'
  | 'conflict_resolved'
  | 'sync_failed';
```

---

## 2. Backend Sync Service

### 2.1 Core Sync Service

```go
// internal/sharing/sync_service.go

package sharing

import (
	"context"
	"crypto/sha256"
	"encoding/hex"
	"fmt"
	"time"

	"specmgmt/internal/db"
	"specmgmt/internal/files"
)

type SyncService struct {
	db    *db.DB
	files *files.Service
}

func NewSyncService(db *db.DB, files *files.Service) *SyncService {
	return &SyncService{db: db, files: files}
}

type SyncResult struct {
	Status          string       `json:"status"`
	ChangesPushed   int          `json:"changes_pushed"`
	ChangesPulled   int          `json:"changes_pulled"`
	Conflicts       []ConflictInfo `json:"conflicts,omitempty"`
	SyncedAt        time.Time    `json:"synced_at"`
}

// SyncShare performs synchronization for a single share
func (s *SyncService) SyncShare(ctx context.Context, shareID string, direction string) (*SyncResult, error) {
	// Get share and sync state
	share, err := s.getShare(ctx, shareID)
	if err != nil {
		return nil, fmt.Errorf("share not found: %w", err)
	}
	
	state, err := s.getSyncState(ctx, shareID)
	if err != nil {
		// Initialize state if not exists
		state = &SyncState{
			ShareID:       shareID,
			SourceVersion: 0,
			TargetVersion: 0,
			Status:        "pending",
		}
	}
	
	// Detect changes
	sourceContent, sourceHash, err := s.getContentWithHash(ctx, share.SourceProjectID, share.ResourcePath)
	if err != nil {
		return nil, fmt.Errorf("failed to read source: %w", err)
	}
	
	targetContent, targetHash, err := s.getContentWithHash(ctx, share.TargetProjectID, share.ResourcePath)
	if err != nil && !s.isNotFound(err) {
		return nil, fmt.Errorf("failed to read target: %w", err)
	}
	
	// Check for changes
	sourceChanged := sourceHash != state.SourceHash
	targetChanged := targetHash != state.TargetHash
	
	result := &SyncResult{
		Status:   "synced",
		SyncedAt: time.Now(),
	}
	
	// Handle based on direction and changes
	switch {
	case !sourceChanged && !targetChanged:
		// No changes
		result.Status = "no_changes"
		
	case sourceChanged && !targetChanged:
		// Only source changed - push to target
		if direction != "pull" {
			if err := s.pushToTarget(ctx, share, sourceContent); err != nil {
				return nil, err
			}
			result.ChangesPushed = 1
		}
		
	case !sourceChanged && targetChanged:
		// Only target changed - pull to source (if bidirectional)
		if direction == "bidirectional" || direction == "pull" {
			if err := s.pullFromTarget(ctx, share, targetContent); err != nil {
				return nil, err
			}
			result.ChangesPulled = 1
		}
		
	case sourceChanged && targetChanged:
		// Both changed - conflict!
		conflict := &ConflictInfo{
			Type:          "content",
			SourceContent: sourceContent,
			TargetContent: targetContent,
			BaseContent:   state.LastSyncContent,
		}
		
		// Try auto-resolution based on strategy
		resolved, resolution := s.tryAutoResolve(conflict, share.ConflictStrategy)
		if resolved {
			if err := s.applyResolution(ctx, share, resolution, conflict); err != nil {
				return nil, err
			}
		} else {
			result.Status = "conflict"
			result.Conflicts = append(result.Conflicts, *conflict)
			
			// Save conflict state
			s.saveConflictState(ctx, shareID, conflict)
		}
	}
	
	// Update sync state
	s.updateSyncState(ctx, &SyncState{
		ShareID:         shareID,
		SourceHash:      sourceHash,
		TargetHash:      targetHash,
		LastSyncedAt:    time.Now(),
		Status:          result.Status,
		SourceVersion:   state.SourceVersion + result.ChangesPushed,
		TargetVersion:   state.TargetVersion + result.ChangesPulled,
		LastSyncContent: sourceContent,
	})
	
	return result, nil
}

// pushToTarget copies content from source to target
func (s *SyncService) pushToTarget(ctx context.Context, share *MemoryShare, content string) error {
	return s.files.WriteContent(ctx, share.TargetProjectID, share.ResourcePath, content)
}

// pullFromTarget copies content from target to source
func (s *SyncService) pullFromTarget(ctx context.Context, share *MemoryShare, content string) error {
	return s.files.WriteContent(ctx, share.SourceProjectID, share.ResourcePath, content)
}

// tryAutoResolve attempts automatic conflict resolution
func (s *SyncService) tryAutoResolve(conflict *ConflictInfo, strategy string) (bool, string) {
	switch strategy {
	case "source_wins":
		return true, "use_source"
		
	case "target_wins":
		return true, "use_target"
		
	case "last_write_wins":
		if conflict.SourceModifiedAt.After(conflict.TargetModifiedAt) {
			return true, "use_source"
		}
		return true, "use_target"
		
	case "manual":
		return false, ""
		
	default:
		return false, ""
	}
}

// ResolveConflict manually resolves a conflict
func (s *SyncService) ResolveConflict(
	ctx context.Context,
	shareID string,
	resolution string,
	mergedContent *string,
) error {
	state, err := s.getSyncState(ctx, shareID)
	if err != nil || !state.HasConflict {
		return fmt.Errorf("no conflict to resolve")
	}
	
	share, _ := s.getShare(ctx, shareID)
	
	switch resolution {
	case "use_source":
		content, _, _ := s.getContentWithHash(ctx, share.SourceProjectID, share.ResourcePath)
		return s.pushToTarget(ctx, share, content)
		
	case "use_target":
		content, _, _ := s.getContentWithHash(ctx, share.TargetProjectID, share.ResourcePath)
		return s.pullFromTarget(ctx, share, content)
		
	case "merge":
		if mergedContent == nil {
			return fmt.Errorf("merged content required for merge resolution")
		}
		// Write merged content to both
		s.files.WriteContent(ctx, share.SourceProjectID, share.ResourcePath, *mergedContent)
		s.files.WriteContent(ctx, share.TargetProjectID, share.ResourcePath, *mergedContent)
		
	case "use_both":
		// Create a copy in target with conflict suffix
		conflictPath := s.generateConflictPath(share.ResourcePath)
		targetContent, _, _ := s.getContentWithHash(ctx, share.TargetProjectID, share.ResourcePath)
		s.files.WriteContent(ctx, share.TargetProjectID, conflictPath, targetContent)
		
		// Then sync source to original path
		sourceContent, _, _ := s.getContentWithHash(ctx, share.SourceProjectID, share.ResourcePath)
		return s.pushToTarget(ctx, share, sourceContent)
	}
	
	// Clear conflict state
	return s.clearConflictState(ctx, shareID)
}

func (s *SyncService) getContentWithHash(ctx context.Context, projectID, path string) (string, string, error) {
	content, err := s.files.GetContent(ctx, projectID, path)
	if err != nil {
		return "", "", err
	}
	hash := sha256.Sum256([]byte(content))
	return content, hex.EncodeToString(hash[:]), nil
}

func (s *SyncService) generateConflictPath(path string) string {
	timestamp := time.Now().Format("20060102-150405")
	ext := filepath.Ext(path)
	base := strings.TrimSuffix(path, ext)
	return fmt.Sprintf("%s.conflict-%s%s", base, timestamp, ext)
}
```

### 2.2 Auto-Sync Worker

```go
// internal/sharing/sync_worker.go

package sharing

import (
	"context"
	"fmt"
	"time"
)

type SyncWorker struct {
	syncService *SyncService
	db          *db.DB
	interval    time.Duration
	stopCh      chan struct{}
}

func NewSyncWorker(sync *SyncService, db *db.DB, interval time.Duration) *SyncWorker {
	return &SyncWorker{
		syncService: sync,
		db:          db,
		interval:    interval,
		stopCh:      make(chan struct{}),
	}
}

// Start begins the auto-sync worker
func (w *SyncWorker) Start(ctx context.Context) {
	ticker := time.NewTicker(w.interval)
	defer ticker.Stop()
	
	for {
		select {
		case <-ctx.Done():
			return
		case <-w.stopCh:
			return
		case <-ticker.C:
			w.runSyncCycle(ctx)
		}
	}
}

// Stop halts the worker
func (w *SyncWorker) Stop() {
	close(w.stopCh)
}

func (w *SyncWorker) runSyncCycle(ctx context.Context) {
	// Get all shares with sync permission
	shares, err := w.getAutoSyncShares(ctx)
	if err != nil {
		fmt.Printf("Failed to get sync shares: %v\n", err)
		return
	}
	
	for _, share := range shares {
		// Skip if recently synced
		if time.Since(share.LastSyncedAt) < w.interval/2 {
			continue
		}
		
		// Run sync
		result, err := w.syncService.SyncShare(ctx, share.ID, share.SyncDirection)
		if err != nil {
			fmt.Printf("Sync failed for %s: %v\n", share.ID, err)
			continue
		}
		
		// Log result
		if result.Status == "conflict" {
			w.notifyConflict(ctx, share, result.Conflicts)
		}
	}
}

func (w *SyncWorker) getAutoSyncShares(ctx context.Context) ([]ShareWithSyncInfo, error) {
	rows, err := w.db.QueryContext(ctx, `
		SELECT 
			ms.id, ms.source_project_id, ms.target_project_id, ms.resource_path,
			ss.last_synced_at, ms.sync_direction
		FROM memory_shares ms
		LEFT JOIN share_sync_state ss ON ms.id = ss.share_id
		WHERE ms.permissions = 'sync' 
		AND ms.status = 'active'
		AND (ss.has_conflict IS NULL OR ss.has_conflict = FALSE)
	`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	
	var shares []ShareWithSyncInfo
	for rows.Next() {
		var s ShareWithSyncInfo
		rows.Scan(&s.ID, &s.SourceProjectID, &s.TargetProjectID, 
			&s.ResourcePath, &s.LastSyncedAt, &s.SyncDirection)
		shares = append(shares, s)
	}
	
	return shares, nil
}

func (w *SyncWorker) notifyConflict(ctx context.Context, share ShareWithSyncInfo, conflicts []ConflictInfo) {
	// TODO: Send notification to share owner
	fmt.Printf("Conflict detected in share %s: %d conflicts\n", share.ID, len(conflicts))
}
```

---

## 3. Frontend Components

### 3.1 Sync Status Indicator

```typescript
// components/sharing/SyncStatusIndicator.tsx

import { useState, useEffect } from 'react';
import { RefreshCw, Check, AlertTriangle, Clock, Loader2 } from 'lucide-react';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { formatDistanceToNow } from 'date-fns';
import { cn } from '@/lib/utils';
import type { SyncState, SyncStatus } from '@/types/sync';

interface SyncStatusIndicatorProps {
  shareId: string;
  syncState: SyncState;
  onSync: () => Promise<void>;
  onResolveConflict: () => void;
}

export function SyncStatusIndicator({
  shareId,
  syncState,
  onSync,
  onResolveConflict,
}: SyncStatusIndicatorProps) {
  const [isSyncing, setIsSyncing] = useState(false);
  
  const handleSync = async () => {
    setIsSyncing(true);
    try {
      await onSync();
    } finally {
      setIsSyncing(false);
    }
  };
  
  const statusConfig: Record<SyncStatus, {
    icon: React.ReactNode;
    label: string;
    color: string;
  }> = {
    synced: {
      icon: <Check className="h-4 w-4" />,
      label: 'Synced',
      color: 'text-green-500',
    },
    pending: {
      icon: <Clock className="h-4 w-4" />,
      label: 'Changes pending',
      color: 'text-yellow-500',
    },
    syncing: {
      icon: <Loader2 className="h-4 w-4 animate-spin" />,
      label: 'Syncing...',
      color: 'text-blue-500',
    },
    conflict: {
      icon: <AlertTriangle className="h-4 w-4" />,
      label: 'Conflict',
      color: 'text-destructive',
    },
    error: {
      icon: <AlertTriangle className="h-4 w-4" />,
      label: 'Sync error',
      color: 'text-destructive',
    },
  };
  
  const config = statusConfig[isSyncing ? 'syncing' : syncState.status];
  
  return (
    <div className="flex items-center gap-2">
      <Tooltip>
        <TooltipTrigger asChild>
          <div className={cn('flex items-center gap-1.5', config.color)}>
            {config.icon}
            <span className="text-sm">{config.label}</span>
          </div>
        </TooltipTrigger>
        <TooltipContent>
          <div className="space-y-1 text-xs">
            <p>Last synced: {formatDistanceToNow(syncState.lastSyncedAt)} ago</p>
            <p>Source version: {syncState.sourceVersion}</p>
            <p>Target version: {syncState.targetVersion}</p>
          </div>
        </TooltipContent>
      </Tooltip>
      
      {syncState.status === 'conflict' ? (
        <Button
          variant="destructive"
          size="sm"
          onClick={onResolveConflict}
        >
          Resolve
        </Button>
      ) : (
        <Button
          variant="ghost"
          size="icon"
          onClick={handleSync}
          disabled={isSyncing}
          className="h-8 w-8"
        >
          <RefreshCw className={cn('h-4 w-4', isSyncing && 'animate-spin')} />
        </Button>
      )}
    </div>
  );
}
```

### 3.2 Conflict Resolution Dialog

```typescript
// components/sharing/ConflictResolutionDialog.tsx

import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Badge } from '@/components/ui/badge';
import { Textarea } from '@/components/ui/textarea';
import { AlertTriangle, ArrowLeft, ArrowRight, GitMerge, Copy } from 'lucide-react';
import { DiffViewer } from './DiffViewer';
import { useToast } from '@/hooks/use-toast';
import type { ConflictInfo, ConflictResolution } from '@/types/sync';

interface ConflictResolutionDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  shareId: string;
  conflict: ConflictInfo;
}

export function ConflictResolutionDialog({
  open,
  onOpenChange,
  shareId,
  conflict,
}: ConflictResolutionDialogProps) {
  const [resolution, setResolution] = useState<ConflictResolution>('use_source');
  const [mergedContent, setMergedContent] = useState<string>('');
  const [activeTab, setActiveTab] = useState<'compare' | 'merge'>('compare');
  
  const queryClient = useQueryClient();
  const { toast } = useToast();
  
  // Initialize merged content when switching to merge tab
  const handleTabChange = (tab: string) => {
    setActiveTab(tab as 'compare' | 'merge');
    if (tab === 'merge' && !mergedContent) {
      // Start with source content as base
      setMergedContent(conflict.sourceContent);
    }
  };
  
  const resolveMutation = useMutation({
    mutationFn: async () => {
      const body: any = {
        resolution,
      };
      
      if (resolution === 'merge') {
        body.merged_content = mergedContent;
      }
      
      const res = await fetch(`/api/v1/sharing/shares/${shareId}/resolve`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      });
      
      if (!res.ok) throw new Error('Failed to resolve conflict');
      return res.json();
    },
    onSuccess: () => {
      toast({ title: 'Conflict resolved successfully' });
      queryClient.invalidateQueries({ queryKey: ['sync-state', shareId] });
      onOpenChange(false);
    },
    onError: () => {
      toast({ variant: 'destructive', title: 'Failed to resolve conflict' });
    },
  });
  
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-4xl max-h-[90vh]">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <AlertTriangle className="h-5 w-5 text-destructive" />
            Resolve Conflict
          </DialogTitle>
          <DialogDescription>
            Both source and target have been modified. Choose how to resolve.
          </DialogDescription>
        </DialogHeader>
        
        {/* Conflict info */}
        <div className="flex gap-4 p-3 bg-muted rounded-lg text-sm">
          <div className="flex-1">
            <Badge variant="outline">Source</Badge>
            <p className="mt-1 text-muted-foreground">
              Modified by {conflict.sourceModifiedBy}
            </p>
          </div>
          <div className="flex-1">
            <Badge variant="outline">Target</Badge>
            <p className="mt-1 text-muted-foreground">
              Modified by {conflict.targetModifiedBy}
            </p>
          </div>
        </div>
        
        <Tabs value={activeTab} onValueChange={handleTabChange}>
          <TabsList>
            <TabsTrigger value="compare">Compare</TabsTrigger>
            <TabsTrigger value="merge">Manual Merge</TabsTrigger>
          </TabsList>
          
          <TabsContent value="compare" className="mt-4">
            <ScrollArea className="h-[400px] border rounded-lg">
              <DiffViewer
                oldContent={conflict.baseContent || ''}
                newSourceContent={conflict.sourceContent}
                newTargetContent={conflict.targetContent}
              />
            </ScrollArea>
            
            {/* Resolution options */}
            <div className="grid grid-cols-2 gap-4 mt-4">
              <Button
                variant={resolution === 'use_source' ? 'default' : 'outline'}
                className="h-auto py-4 flex-col gap-2"
                onClick={() => setResolution('use_source')}
              >
                <ArrowRight className="h-5 w-5" />
                <span>Use Source</span>
                <span className="text-xs text-muted-foreground font-normal">
                  Keep the source version
                </span>
              </Button>
              
              <Button
                variant={resolution === 'use_target' ? 'default' : 'outline'}
                className="h-auto py-4 flex-col gap-2"
                onClick={() => setResolution('use_target')}
              >
                <ArrowLeft className="h-5 w-5" />
                <span>Use Target</span>
                <span className="text-xs text-muted-foreground font-normal">
                  Keep the target version
                </span>
              </Button>
              
              <Button
                variant={resolution === 'use_both' ? 'default' : 'outline'}
                className="h-auto py-4 flex-col gap-2"
                onClick={() => setResolution('use_both')}
              >
                <Copy className="h-5 w-5" />
                <span>Keep Both</span>
                <span className="text-xs text-muted-foreground font-normal">
                  Create a conflict copy
                </span>
              </Button>
              
              <Button
                variant={resolution === 'merge' ? 'default' : 'outline'}
                className="h-auto py-4 flex-col gap-2"
                onClick={() => {
                  setResolution('merge');
                  handleTabChange('merge');
                }}
              >
                <GitMerge className="h-5 w-5" />
                <span>Manual Merge</span>
                <span className="text-xs text-muted-foreground font-normal">
                  Edit and combine changes
                </span>
              </Button>
            </div>
          </TabsContent>
          
          <TabsContent value="merge" className="mt-4">
            <div className="space-y-2">
              <p className="text-sm text-muted-foreground">
                Edit the content below to create a merged version:
              </p>
              <Textarea
                value={mergedContent}
                onChange={(e) => setMergedContent(e.target.value)}
                className="h-[400px] font-mono text-sm"
              />
            </div>
          </TabsContent>
        </Tabs>
        
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button
            onClick={() => resolveMutation.mutate()}
            disabled={resolveMutation.isPending}
          >
            Apply Resolution
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
```

### 3.3 Diff Viewer Component

```typescript
// components/sharing/DiffViewer.tsx

import { useMemo } from 'react';
import { diffLines, Change } from 'diff';
import { cn } from '@/lib/utils';

interface DiffViewerProps {
  oldContent: string;
  newSourceContent: string;
  newTargetContent?: string;
  mode?: 'unified' | 'split';
}

export function DiffViewer({
  oldContent,
  newSourceContent,
  newTargetContent,
  mode = 'split',
}: DiffViewerProps) {
  const sourceDiff = useMemo(() => 
    diffLines(oldContent, newSourceContent),
    [oldContent, newSourceContent]
  );
  
  const targetDiff = useMemo(() => 
    newTargetContent ? diffLines(oldContent, newTargetContent) : [],
    [oldContent, newTargetContent]
  );
  
  if (mode === 'split' && newTargetContent) {
    return (
      <div className="grid grid-cols-2 divide-x">
        <div className="p-4">
          <h4 className="text-sm font-medium mb-2 text-muted-foreground">Source Changes</h4>
          <DiffPanel changes={sourceDiff} />
        </div>
        <div className="p-4">
          <h4 className="text-sm font-medium mb-2 text-muted-foreground">Target Changes</h4>
          <DiffPanel changes={targetDiff} />
        </div>
      </div>
    );
  }
  
  return (
    <div className="p-4">
      <DiffPanel changes={sourceDiff} />
    </div>
  );
}

function DiffPanel({ changes }: { changes: Change[] }) {
  let lineNumber = 0;
  
  return (
    <div className="font-mono text-sm">
      {changes.map((change, i) => {
        const lines = change.value.split('\n').filter(Boolean);
        
        return lines.map((line, j) => {
          if (!change.added) lineNumber++;
          
          return (
            <div
              key={`${i}-${j}`}
              className={cn(
                'flex',
                change.added && 'bg-green-500/10 text-green-700 dark:text-green-400',
                change.removed && 'bg-red-500/10 text-red-700 dark:text-red-400'
              )}
            >
              <span className="w-10 text-right pr-2 text-muted-foreground select-none border-r">
                {!change.added ? lineNumber : ''}
              </span>
              <span className="w-6 text-center text-muted-foreground select-none">
                {change.added ? '+' : change.removed ? '-' : ' '}
              </span>
              <span className="flex-1 px-2">{line}</span>
            </div>
          );
        });
      })}
    </div>
  );
}
```

---

## 4. Sync Triggers

### 4.1 File Watcher Integration

```go
// internal/sharing/file_watcher.go

package sharing

import (
	"context"
	"path/filepath"

	"github.com/fsnotify/fsnotify"
)

type FileWatcher struct {
	watcher     *fsnotify.Watcher
	syncService *SyncService
	db          *db.DB
	watchedDirs map[string]string // path -> projectID
}

func NewFileWatcher(sync *SyncService, db *db.DB) (*FileWatcher, error) {
	watcher, err := fsnotify.NewWatcher()
	if err != nil {
		return nil, err
	}
	
	return &FileWatcher{
		watcher:     watcher,
		syncService: sync,
		db:          db,
		watchedDirs: make(map[string]string),
	}, nil
}

func (w *FileWatcher) Start(ctx context.Context) {
	go func() {
		for {
			select {
			case <-ctx.Done():
				return
				
			case event, ok := <-w.watcher.Events:
				if !ok {
					return
				}
				
				if event.Op&(fsnotify.Write|fsnotify.Create) != 0 {
					w.handleFileChange(ctx, event.Name)
				}
				
			case err, ok := <-w.watcher.Errors:
				if !ok {
					return
				}
				fmt.Printf("Watcher error: %v\n", err)
			}
		}
	}()
}

func (w *FileWatcher) handleFileChange(ctx context.Context, path string) {
	// Find project for this path
	projectID, found := w.findProject(path)
	if !found {
		return
	}
	
	// Get shares that include this file
	shares, err := w.getSharesForFile(ctx, projectID, path)
	if err != nil {
		return
	}
	
	// Trigger sync for each share
	for _, share := range shares {
		if share.Permissions == "sync" {
			go w.syncService.SyncShare(ctx, share.ID, share.SyncDirection)
		}
	}
}

func (w *FileWatcher) WatchProject(projectID, basePath string) error {
	w.watchedDirs[basePath] = projectID
	return w.watcher.Add(basePath)
}
```

---

## 5. Testing Requirements

| Test | Description | Priority |
|------|-------------|----------|
| Basic sync | Content syncs between projects | Critical |
| Conflict detection | Both-modified detected as conflict | Critical |
| Conflict resolution | All resolution types work | Critical |
| Auto-sync worker | Periodic sync executes | High |
| Hash comparison | Changes detected accurately | High |
| Version tracking | Versions increment correctly | Medium |
| File watcher | File changes trigger sync | Medium |

---

## Related Specs

- [Sharing Architecture](./06-01-sharing-architecture.md)
- [RAG Integration](./06-03-rag-integration.md)
- [UI Components](./06-04-sharing-ui.md)
