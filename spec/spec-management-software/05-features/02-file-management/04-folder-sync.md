# Folder Sync UI

**Version:** 0.1.0  
**Status:** Draft  
**Updated:** 2026-01-27  

---

## Overview

The Folder Sync UI provides a post-login experience for detecting, importing, and reconciling spec projects from the filesystem with the database. It helps users onboard existing projects and keeps the system synchronized with external changes.

---

## 9.1 Sync Flow States

```
┌─────────────────────────────────────────────────────────────────────────┐
│                       FOLDER SYNC STATE MACHINE                         │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌──────────┐    Scan Complete     ┌──────────┐                        │
│  │ SCANNING │ ──────────────────→ │ PENDING  │                        │
│  │          │                      │ REVIEW   │                        │
│  └──────────┘                      └──────────┘                        │
│       │                                  │                              │
│       │ No Changes                       │ User Action                  │
│       ▼                                  ▼                              │
│  ┌──────────┐                      ┌──────────┐                        │
│  │ UP TO    │ ←────────────────── │IMPORTING │                        │
│  │ DATE     │    Import Complete   │          │                        │
│  └──────────┘                      └──────────┘                        │
│       │                                  │                              │
│       │ External Change Detected         │ Error                       │
│       ▼                                  ▼                              │
│  ┌──────────┐                      ┌──────────┐                        │
│  │ PENDING  │                      │  ERROR   │                        │
│  │ CHANGES  │                      │          │                        │
│  └──────────┘                      └──────────┘                        │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 9.2 Post-Login Sync Screen

### When Displayed

The sync screen appears after login when:
1. First login ever (no projects in database)
2. New folders detected in spec root that aren't in database
3. Projects in database missing from filesystem
4. Significant changes detected since last sync

### Full-Screen Layout

```
┌─────────────────────────────────────────────────────────────────────────┐
│  Header                                                    [Theme] [×]  │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│           ┌─────────────────────────────────────────────────┐          │
│           │  🔄  Folder Sync                                │          │
│           │                                                  │          │
│           │  We found changes in your spec folder.          │          │
│           │  Review and import the projects below.          │          │
│           └─────────────────────────────────────────────────┘          │
│                                                                         │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │  📁 New Projects Detected (3)                        [Import All] │  │
│  ├──────────────────────────────────────────────────────────────────┤  │
│  │  ┌────────────────────────────────────────────────────────────┐  │  │
│  │  │  ☐  📁 my-new-plugin                                       │  │  │
│  │  │      Path: spec/wp-plugins/my-new-plugin                   │  │  │
│  │  │      Files: 12 • Category: WordPress Plugins               │  │  │
│  │  │      [Import] [Ignore] [Inspect]                           │  │  │
│  │  └────────────────────────────────────────────────────────────┘  │  │
│  │  ┌────────────────────────────────────────────────────────────┐  │  │
│  │  │  ☐  📁 api-service-spec                                    │  │  │
│  │  │      Path: spec/backend/api-service-spec                   │  │  │
│  │  │      Files: 8 • Category: Backend                          │  │  │
│  │  │      [Import] [Ignore] [Inspect]                           │  │  │
│  │  └────────────────────────────────────────────────────────────┘  │  │
│  │  ┌────────────────────────────────────────────────────────────┐  │  │
│  │  │  ☐  📁 mobile-app                                          │  │  │
│  │  │      Path: spec/mobile/mobile-app                          │  │  │
│  │  │      Files: 15 • Category: Mobile                          │  │  │
│  │  │      [Import] [Ignore] [Inspect]                           │  │  │
│  │  └────────────────────────────────────────────────────────────┘  │  │
│  └──────────────────────────────────────────────────────────────────┘  │
│                                                                         │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │  ⚠️  Missing from Filesystem (1)                      [Remove All] │  │
│  ├──────────────────────────────────────────────────────────────────┤  │
│  │  ┌────────────────────────────────────────────────────────────┐  │  │
│  │  │  ⚠️  old-project                                           │  │  │
│  │  │      Was at: spec/archived/old-project                     │  │  │
│  │  │      Last seen: 2 days ago                                 │  │  │
│  │  │      [Keep in DB] [Remove]                                 │  │  │
│  │  └────────────────────────────────────────────────────────────┘  │  │
│  └──────────────────────────────────────────────────────────────────┘  │
│                                                                         │
│                                    [Skip for Now]  [Complete Sync]      │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 9.3 Sync Banner (Non-Blocking)

For minor changes, show a dismissible banner instead of full screen:

```
┌─────────────────────────────────────────────────────────────────────────┐
│  🔄 3 new folders detected in your spec directory.                      │
│     [Review Now]  [Remind Later]  [Ignore]                        [×]  │
└─────────────────────────────────────────────────────────────────────────┘
```

### Banner Trigger Rules

| Condition | UI Treatment |
|-----------|--------------|
| First login + no projects | Full-screen sync wizard |
| ≥3 new folders detected | Full-screen sync |
| 1-2 new folders detected | Banner notification |
| Projects missing from FS | Full-screen sync |
| Minor metadata changes | Silent background sync |

---

## 9.4 Project Detection Rules

### Directory Classification

```go
type FolderClassification struct {
    Type        ClassificationType // "project" | "category" | "ignore"
    ProjectName string
    Category    string
    FileCount   int
    HasOverview bool
}

type ClassificationType string

const (
    TypeProject  ClassificationType = "project"
    TypeCategory ClassificationType = "category"
    TypeIgnore   ClassificationType = "ignore"
)
```

### Detection Algorithm

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    FOLDER CLASSIFICATION LOGIC                          │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  Is folder in ignore list?                                              │
│  (.git, .history, node_modules, etc.)                                   │
│      │                                                                  │
│      ├── YES → Classify as IGNORE                                      │
│      │                                                                  │
│      └── NO ↓                                                           │
│                                                                         │
│  Does folder contain 00-overview.md OR spec.project.json?               │
│      │                                                                  │
│      ├── YES → Classify as PROJECT                                     │
│      │         • Name from spec.project.json or folder name            │
│      │         • Category from parent folder path                      │
│      │                                                                  │
│      └── NO ↓                                                           │
│                                                                         │
│  Does folder contain only subdirectories (no .md files)?                │
│      │                                                                  │
│      ├── YES → Classify as CATEGORY                                    │
│      │         • Recursively scan children                             │
│      │                                                                  │
│      └── NO ↓                                                           │
│                                                                         │
│  Does folder contain any .md files?                                     │
│      │                                                                  │
│      ├── YES → Classify as PROJECT (implicit)                          │
│      │         • Create overview from folder name                      │
│      │                                                                  │
│      └── NO → Classify as IGNORE                                       │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 9.5 Import Actions

### Action Types

| Action | Description | Result |
|--------|-------------|--------|
| **Import** | Add project to database | Creates Project + File records |
| **Ignore** | Skip folder permanently | Adds to `.syncignore` file |
| **Ignore Once** | Skip this session only | Folder reappears next scan |
| **Inspect** | View folder contents | Opens preview modal |
| **Import All** | Batch import selected | Imports all checked items |

### Import Process

```typescript
interface ImportRequest {
  folderPath: string;
  projectName: string;      // User can edit
  category: string | null;  // Inferred or user-selected
  createMetadataFile: boolean;  // Generate spec.project.json
  guidelinePresetId: string | null;  // Optional preset
}

interface ImportResult {
  success: boolean;
  projectId: string;
  filesImported: number;
  warnings: string[];
}
```

---

## 9.6 Sync Item Component

```typescript
// components/sync/SyncItem.tsx
import { useState } from 'react';
import { Folder, FileText, ChevronDown, ChevronRight, Check, X, Eye } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Badge } from '@/components/ui/badge';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { cn } from '@/lib/utils';

interface SyncItemProps {
  item: DetectedFolder;
  selected: boolean;
  onSelect: (selected: boolean) => void;
  onImport: () => void;
  onIgnore: () => void;
  onInspect: () => void;
}

interface DetectedFolder {
  path: string;
  name: string;
  type: 'new' | 'missing' | 'changed';
  category: string | null;
  fileCount: number;
  lastSeen?: string;
}

export function SyncItem({
  item,
  selected,
  onSelect,
  onImport,
  onIgnore,
  onInspect,
}: SyncItemProps) {
  const [expanded, setExpanded] = useState(false);

  const typeStyles = {
    new: 'border-green-500/50 bg-green-50 dark:bg-green-950/20',
    missing: 'border-yellow-500/50 bg-yellow-50 dark:bg-yellow-950/20',
    changed: 'border-blue-500/50 bg-blue-50 dark:bg-blue-950/20',
  };

  const typeIcons = {
    new: '📁',
    missing: '⚠️',
    changed: '🔄',
  };

  return (
    <Card className={cn('transition-colors', typeStyles[item.type])}>
      <CardContent className="p-4">
        <div className="flex items-start gap-3">
          <Checkbox
            checked={selected}
            onCheckedChange={onSelect}
            className="mt-1"
          />
          
          <div className="flex-1 min-w-0">
            <div className="flex items-center gap-2 mb-1">
              <span className="text-lg">{typeIcons[item.type]}</span>
              <h4 className="font-medium truncate">{item.name}</h4>
            </div>
            
            <p className="text-sm text-foreground-muted truncate">
              {item.type === 'missing' ? 'Was at: ' : 'Path: '}
              {item.path}
            </p>
            
            <div className="flex items-center gap-3 mt-2 text-xs text-foreground-subtle">
              {item.type !== 'missing' && (
                <span className="flex items-center gap-1">
                  <FileText className="h-3 w-3" />
                  {item.fileCount} files
                </span>
              )}
              {item.category && (
                <Badge variant="secondary" className="text-xs">
                  {item.category}
                </Badge>
              )}
              {item.lastSeen && (
                <span>Last seen: {item.lastSeen}</span>
              )}
            </div>
          </div>

          <div className="flex items-center gap-2">
            {item.type === 'new' && (
              <>
                <Button size="sm" onClick={onImport}>
                  Import
                </Button>
                <Button size="sm" variant="outline" onClick={onIgnore}>
                  Ignore
                </Button>
                <Button size="sm" variant="ghost" onClick={onInspect}>
                  <Eye className="h-4 w-4" />
                </Button>
              </>
            )}
            {item.type === 'missing' && (
              <>
                <Button size="sm" variant="outline" onClick={onIgnore}>
                  Keep in DB
                </Button>
                <Button size="sm" variant="destructive" onClick={onImport}>
                  Remove
                </Button>
              </>
            )}
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
```

---

## 9.7 Sync Screen Page

```typescript
// pages/SyncPage.tsx
import { useState, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { RefreshCw, FolderPlus, AlertTriangle, Check } from 'lucide-react';
import { useFolderSync } from '@/hooks/useFolderSync';
import { SyncItem } from '@/components/sync/SyncItem';
import { InspectModal } from '@/components/sync/InspectModal';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { Skeleton } from '@/components/ui/skeleton';

export function SyncPage() {
  const navigate = useNavigate();
  const { 
    detectedFolders, 
    missingProjects, 
    isScanning, 
    isImporting,
    importProgress,
    importFolder, 
    ignoreFolder,
    removeProject,
    rescan,
    completeSync,
  } = useFolderSync();

  const [selectedNew, setSelectedNew] = useState<Set<string>>(new Set());
  const [selectedMissing, setSelectedMissing] = useState<Set<string>>(new Set());
  const [inspectingPath, setInspectingPath] = useState<string | null>(null);

  const newFolders = useMemo(() => 
    detectedFolders.filter(f => f.type === 'new'),
    [detectedFolders]
  );

  const handleSelectAllNew = () => {
    if (selectedNew.size === newFolders.length) {
      setSelectedNew(new Set());
    } else {
      setSelectedNew(new Set(newFolders.map(f => f.path)));
    }
  };

  const handleImportSelected = async () => {
    for (const path of selectedNew) {
      await importFolder(path);
    }
    setSelectedNew(new Set());
  };

  const handleComplete = async () => {
    await completeSync();
    navigate('/');
  };

  const handleSkip = () => {
    navigate('/');
  };

  if (isScanning) {
    return <SyncSkeleton />;
  }

  const hasChanges = newFolders.length > 0 || missingProjects.length > 0;

  if (!hasChanges) {
    return (
      <div className="flex flex-col items-center justify-center min-h-screen p-8">
        <Check className="h-16 w-16 text-green-500 mb-4" />
        <h1 className="text-2xl font-bold mb-2">All Synced!</h1>
        <p className="text-foreground-muted mb-6">
          Your projects are up to date with the filesystem.
        </p>
        <Button onClick={() => navigate('/')}>
          Go to Dashboard
        </Button>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-background">
      {/* Header */}
      <header className="border-b p-4">
        <div className="container mx-auto flex items-center justify-between">
          <div className="flex items-center gap-3">
            <RefreshCw className="h-6 w-6 text-primary" />
            <h1 className="text-xl font-bold">Folder Sync</h1>
          </div>
          <Button variant="ghost" onClick={rescan}>
            <RefreshCw className="h-4 w-4 mr-2" />
            Rescan
          </Button>
        </div>
      </header>

      <main className="container mx-auto py-8 px-4 max-w-4xl">
        {/* Intro */}
        <div className="text-center mb-8">
          <p className="text-foreground-muted">
            We found changes in your spec folder. Review and import the projects below.
          </p>
        </div>

        {/* Import Progress */}
        {isImporting && (
          <div className="mb-6 p-4 rounded-lg border bg-muted/50">
            <div className="flex items-center justify-between mb-2">
              <span className="text-sm font-medium">Importing...</span>
              <span className="text-sm text-foreground-muted">
                {importProgress.current} / {importProgress.total}
              </span>
            </div>
            <Progress value={(importProgress.current / importProgress.total) * 100} />
          </div>
        )}

        {/* New Projects Section */}
        {newFolders.length > 0 && (
          <section className="mb-8">
            <div className="flex items-center justify-between mb-4">
              <h2 className="flex items-center gap-2 text-lg font-medium">
                <FolderPlus className="h-5 w-5 text-green-500" />
                New Projects Detected ({newFolders.length})
              </h2>
              <div className="flex gap-2">
                <Button 
                  variant="outline" 
                  size="sm"
                  onClick={handleSelectAllNew}
                >
                  {selectedNew.size === newFolders.length ? 'Deselect All' : 'Select All'}
                </Button>
                <Button 
                  size="sm"
                  onClick={handleImportSelected}
                  disabled={selectedNew.size === 0 || isImporting}
                >
                  Import Selected ({selectedNew.size})
                </Button>
              </div>
            </div>

            <div className="space-y-3">
              {newFolders.map((folder) => (
                <SyncItem
                  key={folder.path}
                  item={folder}
                  selected={selectedNew.has(folder.path)}
                  onSelect={(checked) => {
                    const next = new Set(selectedNew);
                    if (checked) {
                      next.add(folder.path);
                    } else {
                      next.delete(folder.path);
                    }
                    setSelectedNew(next);
                  }}
                  onImport={() => importFolder(folder.path)}
                  onIgnore={() => ignoreFolder(folder.path)}
                  onInspect={() => setInspectingPath(folder.path)}
                />
              ))}
            </div>
          </section>
        )}

        {/* Missing Projects Section */}
        {missingProjects.length > 0 && (
          <section className="mb-8">
            <div className="flex items-center justify-between mb-4">
              <h2 className="flex items-center gap-2 text-lg font-medium">
                <AlertTriangle className="h-5 w-5 text-yellow-500" />
                Missing from Filesystem ({missingProjects.length})
              </h2>
            </div>

            <div className="space-y-3">
              {missingProjects.map((project) => (
                <SyncItem
                  key={project.path}
                  item={{
                    ...project,
                    type: 'missing',
                  }}
                  selected={selectedMissing.has(project.path)}
                  onSelect={(checked) => {
                    const next = new Set(selectedMissing);
                    if (checked) {
                      next.add(project.path);
                    } else {
                      next.delete(project.path);
                    }
                    setSelectedMissing(next);
                  }}
                  onImport={() => removeProject(project.id)}
                  onIgnore={() => {/* Keep in DB, do nothing */}}
                  onInspect={() => {}}
                />
              ))}
            </div>
          </section>
        )}

        {/* Actions */}
        <div className="flex justify-end gap-3 pt-4 border-t">
          <Button variant="outline" onClick={handleSkip}>
            Skip for Now
          </Button>
          <Button onClick={handleComplete} disabled={isImporting}>
            Complete Sync
          </Button>
        </div>
      </main>

      {/* Inspect Modal */}
      <InspectModal
        isOpen={!!inspectingPath}
        onClose={() => setInspectingPath(null)}
        folderPath={inspectingPath}
      />
    </div>
  );
}

function SyncSkeleton() {
  return (
    <div className="min-h-screen bg-background p-8">
      <div className="container mx-auto max-w-4xl">
        <Skeleton className="h-8 w-48 mb-8 mx-auto" />
        <Skeleton className="h-4 w-64 mb-8 mx-auto" />
        <div className="space-y-4">
          <Skeleton className="h-24 w-full" />
          <Skeleton className="h-24 w-full" />
          <Skeleton className="h-24 w-full" />
        </div>
      </div>
    </div>
  );
}
```

---

## 9.8 useFolderSync Hook

```typescript
// hooks/useFolderSync.ts
import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { syncApi } from '@/api/sync';
import { toast } from 'sonner';

interface DetectedFolder {
  path: string;
  name: string;
  type: 'new' | 'missing' | 'changed';
  category: string | null;
  fileCount: number;
  hasMetadataFile: boolean;
}

interface MissingProject {
  id: string;
  path: string;
  name: string;
  lastSeen: string;
}

interface ImportProgress {
  current: number;
  total: number;
  currentPath: string;
}

export function useFolderSync() {
  const queryClient = useQueryClient();
  const [importProgress, setImportProgress] = useState<ImportProgress>({
    current: 0,
    total: 0,
    currentPath: '',
  });

  // Scan for changes
  const { data: scanResult, isLoading: isScanning, refetch: rescan } = useQuery({
    queryKey: ['folder-sync', 'scan'],
    queryFn: syncApi.scanFolders,
  });

  // Import folder mutation
  const importMutation = useMutation({
    mutationFn: syncApi.importFolder,
    onSuccess: (result) => {
      queryClient.invalidateQueries({ queryKey: ['folder-sync'] });
      queryClient.invalidateQueries({ queryKey: ['projects'] });
      toast.success(`Imported ${result.projectName} (${result.filesImported} files)`);
    },
    onError: (error: Error) => {
      toast.error(`Import failed: ${error.message}`);
    },
  });

  // Ignore folder mutation
  const ignoreMutation = useMutation({
    mutationFn: syncApi.ignoreFolder,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['folder-sync'] });
      toast.success('Folder added to ignore list');
    },
  });

  // Remove missing project mutation
  const removeMutation = useMutation({
    mutationFn: syncApi.removeProject,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['folder-sync'] });
      queryClient.invalidateQueries({ queryKey: ['projects'] });
      toast.success('Project removed from database');
    },
  });

  // Complete sync mutation
  const completeSyncMutation = useMutation({
    mutationFn: syncApi.completeSync,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['folder-sync'] });
      toast.success('Sync completed');
    },
  });

  return {
    detectedFolders: scanResult?.detected ?? [],
    missingProjects: scanResult?.missing ?? [],
    isScanning,
    isImporting: importMutation.isPending,
    importProgress,
    importFolder: importMutation.mutate,
    ignoreFolder: ignoreMutation.mutate,
    removeProject: removeMutation.mutate,
    rescan,
    completeSync: completeSyncMutation.mutate,
  };
}
```

---

## 9.9 API Endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/v1/sync/scan` | Scan filesystem for changes |
| POST | `/api/v1/sync/import` | Import a detected folder |
| POST | `/api/v1/sync/ignore` | Add folder to ignore list |
| DELETE | `/api/v1/sync/projects/:id` | Remove project from DB |
| POST | `/api/v1/sync/complete` | Mark sync as complete |
| GET | `/api/v1/sync/status` | Get current sync status |

### Scan Response

```typescript
interface ScanResponse {
  success: boolean;
  data: {
    detected: DetectedFolder[];
    missing: MissingProject[];
    unchanged: number;
    lastScanAt: string;
  };
}
```

### Import Request/Response

```typescript
interface ImportRequest {
  folderPath: string;
  projectName?: string;      // Override detected name
  categoryId?: string;       // Assign to category
  presetId?: string;         // Apply preset guidelines
}

interface ImportResponse {
  success: boolean;
  data: {
    projectId: string;
    projectName: string;
    filesImported: number;
    metadataCreated: boolean;
    warnings: string[];
  };
}
```

---

## 9.10 Inspect Modal

```typescript
// components/sync/InspectModal.tsx
import { useQuery } from '@tanstack/react-query';
import { syncApi } from '@/api/sync';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Folder, FileText, ChevronRight } from 'lucide-react';

interface InspectModalProps {
  isOpen: boolean;
  onClose: () => void;
  folderPath: string | null;
}

export function InspectModal({ isOpen, onClose, folderPath }: InspectModalProps) {
  const { data: contents, isLoading } = useQuery({
    queryKey: ['folder-contents', folderPath],
    queryFn: () => folderPath ? syncApi.getFolderContents(folderPath) : null,
    enabled: isOpen && !!folderPath,
  });

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Folder className="h-5 w-5" />
            {folderPath?.split('/').pop()}
          </DialogTitle>
        </DialogHeader>

        <ScrollArea className="max-h-96">
          {isLoading ? (
            <div className="p-4 text-center text-foreground-muted">Loading...</div>
          ) : contents ? (
            <div className="space-y-1 p-2">
              {contents.items.map((item) => (
                <div
                  key={item.path}
                  className="flex items-center gap-2 py-1 px-2 rounded hover:bg-muted"
                >
                  {item.type === 'folder' ? (
                    <Folder className="h-4 w-4 text-primary" />
                  ) : (
                    <FileText className="h-4 w-4 text-foreground-muted" />
                  )}
                  <span className="text-sm">{item.name}</span>
                  {item.type === 'folder' && (
                    <ChevronRight className="h-3 w-3 ml-auto text-foreground-subtle" />
                  )}
                </div>
              ))}
            </div>
          ) : (
            <div className="p-4 text-center text-foreground-muted">
              Unable to load folder contents
            </div>
          )}
        </ScrollArea>
      </DialogContent>
    </Dialog>
  );
}
```

---

## 9.11 Types

```typescript
// types/sync.ts
export interface DetectedFolder {
  path: string;
  name: string;
  type: 'new' | 'missing' | 'changed';
  category: string | null;
  fileCount: number;
  hasMetadataFile: boolean;
  hasOverview: boolean;
  inferredLanguage: string | null;
}

export interface MissingProject {
  id: string;
  path: string;
  name: string;
  lastSeen: string;
  fileCountWas: number;
}

export interface SyncStatus {
  state: 'idle' | 'scanning' | 'pending' | 'importing' | 'complete' | 'error';
  pendingCount: number;
  lastSyncAt: string | null;
  lastError: string | null;
}

export interface FolderContents {
  path: string;
  items: FolderItem[];
}

export interface FolderItem {
  name: string;
  path: string;
  type: 'folder' | 'file';
  size?: number;
}
```

---

## 9.12 .syncignore File

Users can permanently ignore folders by adding them to `.syncignore`:

```
# Folders to ignore during sync
old-projects/
drafts/
temp/
*.bak/
```

### Ignore Rules

- One pattern per line
- Supports glob patterns (`*`, `**`)
- Lines starting with `#` are comments
- Relative to spec root directory

---

## 9.13 Acceptance Criteria

### Sync Detection

- [ ] Detects new folders in spec root not in database
- [ ] Detects projects in database missing from filesystem
- [ ] Correctly classifies folders as project vs category
- [ ] Respects `.syncignore` patterns
- [ ] Identifies folders with spec.project.json as projects

### UI Behavior

- [ ] Full-screen sync shown on first login
- [ ] Banner shown for 1-2 new folders
- [ ] Full-screen shown for 3+ changes
- [ ] Import action creates project and file records
- [ ] Ignore action adds to `.syncignore`
- [ ] Inspect modal shows folder contents
- [ ] Bulk select/import works correctly
- [ ] Progress indicator shown during import

### Navigation

- [ ] "Skip for Now" navigates to dashboard
- [ ] "Complete Sync" marks sync done and navigates
- [ ] Banner can be dismissed
- [ ] User can rescan manually

### Error Handling

- [ ] Failed imports show error toast
- [ ] Partial imports report which failed
- [ ] Network errors allow retry

---

## Related Specs

- [File Operations](./01-file-operations.md)
- [Path Manager](./02-path-manager.md)
- [Folder Tree](./03-folder-tree.md)
