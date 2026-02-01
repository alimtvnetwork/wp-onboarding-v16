# File Modification Display System

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

Real-time file modification display system (Bolt/Lovable-style) that shows which files are being created, modified, or deleted during AI operations. Includes diff visualization, file tree highlighting, and modification history.

**Cross-References:**
- [AI Chat Interface](./20-ai-chat-interface.md) - Parent interface
- [Project Editor UI](./15-project-editor-ui.md) - File tree integration
- [Long Chain Events](./24-long-chain-events.md) - Event streaming

---

## Display Modes

### 1. Inline Chat Display

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│  🤖 AI                                                            10:30 AM     │
│                                                                                  │
│  I'll create the authentication module with the following files:               │
│                                                                                  │
│  ┌─────────────────────────────────────────────────────────────────────────────┐│
│  │ 📁 File Changes (4 files)                                    [Expand All]  ││
│  ├─────────────────────────────────────────────────────────────────────────────┤│
│  │                                                                              ││
│  │  ➕ internal/auth/handler.go                          +156 lines   [View]  ││
│  │  ➕ internal/auth/service.go                          +89 lines    [View]  ││
│  │  ✏️ internal/router/routes.go                        +12 -3 lines  [Diff]  ││
│  │  ✏️ cmd/server/main.go                               +4 -0 lines   [Diff]  ││
│  │                                                                              ││
│  └─────────────────────────────────────────────────────────────────────────────┘│
│                                                                                  │
│  Authentication endpoints have been added to `/api/v1/auth/*`                  │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### 2. Expanded Diff View

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│  ✏️ internal/router/routes.go                           +12 -3 lines           │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  @@ -45,6 +45,18 @@ func SetupRoutes(r *mux.Router) {                          │
│                                                                                  │
│       // Existing routes                                                        │
│       api.HandleFunc("/users", userHandler.List).Methods("GET")                │
│  -    api.HandleFunc("/users/{id}", userHandler.Get).Methods("GET")            │
│  +                                                                              │
│  +    // Authentication routes                                                  │
│  +    auth := api.PathPrefix("/auth").Subrouter()                              │
│  +    auth.HandleFunc("/login", authHandler.Login).Methods("POST")             │
│  +    auth.HandleFunc("/logout", authHandler.Logout).Methods("POST")           │
│  +    auth.HandleFunc("/refresh", authHandler.Refresh).Methods("POST")         │
│  +    auth.HandleFunc("/register", authHandler.Register).Methods("POST")       │
│  +                                                                              │
│  +    // Protected routes                                                       │
│  +    protected := api.PathPrefix("/protected").Subrouter()                    │
│  +    protected.Use(authMiddleware.Verify)                                     │
│  +    api.HandleFunc("/users/{id}", userHandler.Get).Methods("GET")            │
│                                                                                  │
│                                               [Copy] [Apply] [Revert] [Close]  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## File Status Icons

| Icon | Status | Color | Description |
|------|--------|-------|-------------|
| ➕ | Created | `--color-success` (green) | New file added |
| ✏️ | Modified | `--color-warning` (amber) | Existing file changed |
| 🗑️ | Deleted | `--color-destructive` (red) | File removed |
| 📋 | Copied | `--color-info` (blue) | File duplicated |
| 📁 | Renamed | `--color-muted` (gray) | File moved/renamed |
| ⏳ | Pending | `--color-muted` (gray) | Awaiting processing |
| ✅ | Applied | `--color-success` (green) | Change committed |
| ❌ | Failed | `--color-destructive` (red) | Change failed |
| ⚠️ | Conflict | `--color-warning` (amber) | Merge conflict detected |

---

## Data Structures

### TypeScript Interfaces

```typescript
type FileChangeStatus = 
  | 'pending'       // Queued for processing
  | 'in_progress'   // Currently being modified
  | 'applied'       // Successfully applied
  | 'failed'        // Failed to apply
  | 'reverted'      // Change was undone
  | 'conflict';     // Merge conflict

type FileChangeType = 
  | 'create'        // New file
  | 'modify'        // Edit existing
  | 'delete'        // Remove file
  | 'rename'        // Move/rename
  | 'copy';         // Duplicate

interface FileChange {
  id: string;
  sessionId: string;
  messageId: string;
  
  // File info
  filePath: string;
  changeType: FileChangeType;
  status: FileChangeStatus;
  
  // For renames/copies
  oldPath?: string;
  
  // Change details
  linesAdded: number;
  linesRemoved: number;
  
  // Content
  originalContent?: string;
  newContent?: string;
  diffPatch?: string;           // Unified diff format
  
  // Timing
  createdAt: Date;
  appliedAt?: Date;
  
  // Error handling
  errorMessage?: string;
}

interface FileChangeGroup {
  id: string;
  messageId: string;
  title: string;
  description?: string;
  changes: FileChange[];
  
  // Aggregate stats
  totalFiles: number;
  filesCreated: number;
  filesModified: number;
  filesDeleted: number;
  totalLinesAdded: number;
  totalLinesRemoved: number;
  
  // State
  isExpanded: boolean;
  allApplied: boolean;
  hasErrors: boolean;
}
```

### Go Backend Models

```go
type FileChange struct {
    ID          string    `gorm:"primaryKey" json:"id"`
    SessionID   string    `gorm:"index;not null" json:"sessionId"`
    MessageID   string    `gorm:"index;not null" json:"messageId"`
    
    FilePath    string    `gorm:"not null" json:"filePath"`
    ChangeType  string    `gorm:"not null" json:"changeType"` // create, modify, delete, rename, copy
    Status      string    `gorm:"default:pending" json:"status"`
    
    OldPath     *string   `json:"oldPath,omitempty"`
    
    LinesAdded   int      `gorm:"default:0" json:"linesAdded"`
    LinesRemoved int      `gorm:"default:0" json:"linesRemoved"`
    
    OriginalContent *string `gorm:"type:text" json:"-"`
    NewContent      *string `gorm:"type:text" json:"-"`
    DiffPatch       *string `gorm:"type:text" json:"diffPatch,omitempty"`
    
    CreatedAt time.Time  `json:"createdAt"`
    AppliedAt *time.Time `json:"appliedAt,omitempty"`
    
    ErrorMessage *string `json:"errorMessage,omitempty"`
}

type FileChangeGroup struct {
    ID          string       `gorm:"primaryKey" json:"id"`
    MessageID   string       `gorm:"unique;not null" json:"messageId"`
    Title       string       `json:"title"`
    Description *string      `json:"description,omitempty"`
    
    TotalFiles       int     `json:"totalFiles"`
    FilesCreated     int     `json:"filesCreated"`
    FilesModified    int     `json:"filesModified"`
    FilesDeleted     int     `json:"filesDeleted"`
    TotalLinesAdded  int     `json:"totalLinesAdded"`
    TotalLinesRemoved int    `json:"totalLinesRemoved"`
    
    IsExpanded  bool         `gorm:"default:true" json:"isExpanded"`
    AllApplied  bool         `gorm:"default:false" json:"allApplied"`
    HasErrors   bool         `gorm:"default:false" json:"hasErrors"`
    
    CreatedAt   time.Time    `json:"createdAt"`
    
    Changes []FileChange     `gorm:"foreignKey:MessageID;references:MessageID" json:"changes"`
}
```

---

## Component Structure

```
FileModification/
├── components/
│   ├── FileChangePanel.tsx         # Main panel container
│   ├── FileChangeGroup.tsx         # Grouped changes header
│   ├── FileChangeItem.tsx          # Individual file row
│   ├── FileChangeIcon.tsx          # Status/type icons
│   ├── DiffViewer.tsx              # Syntax-highlighted diff
│   ├── FilePreview.tsx             # Full file preview
│   ├── ChangeStats.tsx             # +/- line counts
│   ├── ChangeActions.tsx           # Apply/Revert/Copy buttons
│   └── ConflictResolver.tsx        # Merge conflict UI
│
├── hooks/
│   ├── useFileChanges.ts           # Change state management
│   ├── useDiffGenerator.ts         # Generate unified diffs
│   ├── useChangeApplication.ts     # Apply/revert logic
│   └── useConflictDetection.ts     # Detect merge conflicts
│
└── utils/
    ├── diffParser.ts               # Parse unified diff format
    ├── syntaxHighlighter.ts        # Highlight diff syntax
    └── changeCalculator.ts         # Calculate line changes
```

---

## WebSocket Events

### File Change Events

```typescript
// Server → Client: File change started
interface FileChangeStartedEvent {
  type: 'file:change:started';
  payload: {
    sessionId: string;
    messageId: string;
    changeId: string;
    filePath: string;
    changeType: FileChangeType;
  };
}

// Server → Client: File change progress
interface FileChangeProgressEvent {
  type: 'file:change:progress';
  payload: {
    changeId: string;
    status: 'generating' | 'writing' | 'validating';
    progress: number;        // 0-100
    currentLine?: number;
    totalLines?: number;
  };
}

// Server → Client: File change completed
interface FileChangeCompletedEvent {
  type: 'file:change:completed';
  payload: {
    changeId: string;
    status: 'applied' | 'failed';
    linesAdded: number;
    linesRemoved: number;
    diffPatch?: string;
    errorMessage?: string;
  };
}

// Server → Client: File group summary
interface FileGroupSummaryEvent {
  type: 'file:group:summary';
  payload: {
    messageId: string;
    groupId: string;
    totalFiles: number;
    filesCreated: number;
    filesModified: number;
    filesDeleted: number;
    totalLinesAdded: number;
    totalLinesRemoved: number;
    allApplied: boolean;
  };
}

// Client → Server: Request file diff
interface RequestFileDiffEvent {
  type: 'file:diff:request';
  payload: {
    changeId: string;
  };
}

// Server → Client: File diff response
interface FileDiffResponseEvent {
  type: 'file:diff:response';
  payload: {
    changeId: string;
    originalContent?: string;
    newContent?: string;
    diffPatch: string;
  };
}

// Client → Server: Apply/Revert change
interface FileChangeActionEvent {
  type: 'file:change:action';
  payload: {
    changeId: string;
    action: 'apply' | 'revert' | 'apply_all' | 'revert_all';
  };
}
```

---

## Diff Generation

### Go Diff Library Integration

```go
import (
    "github.com/sergi/go-diff/diffmatchpatch"
)

type DiffGenerator struct {
    dmp *diffmatchpatch.DiffMatchPatch
}

func NewDiffGenerator() *DiffGenerator {
    return &DiffGenerator{
        dmp: diffmatchpatch.New(),
    }
}

// GenerateUnifiedDiff creates a unified diff format string
func (d *DiffGenerator) GenerateUnifiedDiff(
    oldContent, newContent string,
    oldPath, newPath string,
) string {
    diffs := d.dmp.DiffMain(oldContent, newContent, true)
    patches := d.dmp.PatchMake(oldContent, diffs)
    return d.dmp.PatchToText(patches)
}

// CalculateLineChanges counts added/removed lines
func (d *DiffGenerator) CalculateLineChanges(oldContent, newContent string) (added, removed int) {
    oldLines := strings.Split(oldContent, "\n")
    newLines := strings.Split(newContent, "\n")
    
    oldSet := make(map[string]bool)
    for _, line := range oldLines {
        oldSet[line] = true
    }
    
    newSet := make(map[string]bool)
    for _, line := range newLines {
        newSet[line] = true
    }
    
    for _, line := range newLines {
        if !oldSet[line] {
            added++
        }
    }
    
    for _, line := range oldLines {
        if !newSet[line] {
            removed++
        }
    }
    
    return added, removed
}
```

---

## File Tree Integration

### Tree Highlighting

When files are being modified, the file tree shows visual indicators:

```
┌─────────────────────────┐
│ 📁 project-name         │
│ ├─ 📁 internal          │
│ │  ├─ 📁 auth        🔵│  ← Currently modifying
│ │  │  ├─ handler.go  ✨│  ← Newly created (pulse animation)
│ │  │  └─ service.go  ✨│
│ │  └─ 📁 router         │
│ │     └─ routes.go   ⚡│  ← Modified (highlight)
│ └─ 📁 cmd               │
│    └─ 📁 server         │
│       └─ main.go     ⚡│
└─────────────────────────┘
```

### Visual States

| State | Visual Effect |
|-------|--------------|
| Creating | Pulse animation, success color border |
| Modifying | Highlight background, warning color |
| Deleting | Strikethrough, fade animation |
| In Progress | Spinner icon, muted color |
| Completed | Brief flash, then normal |
| Error | Red background, error icon |

---

## React Component Examples

### FileChangePanel

```tsx
interface FileChangePanelProps {
  messageId: string;
  changes: FileChange[];
  isStreaming: boolean;
}

export const FileChangePanel: React.FC<FileChangePanelProps> = ({
  messageId,
  changes,
  isStreaming
}) => {
  const [expandedChanges, setExpandedChanges] = useState<Set<string>>(new Set());
  const [viewMode, setViewMode] = useState<'list' | 'unified'>('list');
  
  const stats = useMemo(() => ({
    total: changes.length,
    created: changes.filter(c => c.changeType === 'create').length,
    modified: changes.filter(c => c.changeType === 'modify').length,
    deleted: changes.filter(c => c.changeType === 'delete').length,
    linesAdded: changes.reduce((sum, c) => sum + c.linesAdded, 0),
    linesRemoved: changes.reduce((sum, c) => sum + c.linesRemoved, 0),
  }), [changes]);

  return (
    <div className="rounded-lg border border-border bg-card">
      {/* Header */}
      <div className="flex items-center justify-between p-3 border-b border-border">
        <div className="flex items-center gap-2">
          <FolderIcon className="h-4 w-4 text-muted-foreground" />
          <span className="font-medium">
            File Changes ({stats.total} files)
          </span>
          {isStreaming && <Spinner className="h-4 w-4" />}
        </div>
        
        <div className="flex items-center gap-2">
          <span className="text-sm text-success">+{stats.linesAdded}</span>
          <span className="text-sm text-destructive">-{stats.linesRemoved}</span>
          <Button variant="ghost" size="sm" onClick={() => setViewMode(
            viewMode === 'list' ? 'unified' : 'list'
          )}>
            {viewMode === 'list' ? <DiffIcon /> : <ListIcon />}
          </Button>
        </div>
      </div>
      
      {/* File List */}
      <div className="divide-y divide-border">
        {changes.map(change => (
          <FileChangeItem
            key={change.id}
            change={change}
            isExpanded={expandedChanges.has(change.id)}
            onToggle={() => toggleExpanded(change.id)}
            viewMode={viewMode}
          />
        ))}
      </div>
      
      {/* Actions */}
      <div className="flex justify-end gap-2 p-3 border-t border-border">
        <Button variant="outline" size="sm" onClick={handleRevertAll}>
          Revert All
        </Button>
        <Button variant="default" size="sm" onClick={handleApplyAll}>
          Apply All
        </Button>
      </div>
    </div>
  );
};
```

### FileChangeItem

```tsx
interface FileChangeItemProps {
  change: FileChange;
  isExpanded: boolean;
  onToggle: () => void;
  viewMode: 'list' | 'unified';
}

export const FileChangeItem: React.FC<FileChangeItemProps> = ({
  change,
  isExpanded,
  onToggle,
  viewMode
}) => {
  const icon = useMemo(() => {
    switch (change.changeType) {
      case 'create': return <PlusCircle className="h-4 w-4 text-success" />;
      case 'modify': return <Edit2 className="h-4 w-4 text-warning" />;
      case 'delete': return <Trash2 className="h-4 w-4 text-destructive" />;
      case 'rename': return <ArrowRight className="h-4 w-4 text-muted-foreground" />;
      default: return <File className="h-4 w-4" />;
    }
  }, [change.changeType]);

  return (
    <div className="group">
      {/* Summary row */}
      <div 
        className="flex items-center justify-between p-2 hover:bg-muted/50 cursor-pointer"
        onClick={onToggle}
      >
        <div className="flex items-center gap-2">
          {icon}
          <code className="text-sm font-mono">{change.filePath}</code>
          {change.oldPath && (
            <span className="text-xs text-muted-foreground">
              ← {change.oldPath}
            </span>
          )}
        </div>
        
        <div className="flex items-center gap-2">
          {change.linesAdded > 0 && (
            <span className="text-xs text-success">+{change.linesAdded}</span>
          )}
          {change.linesRemoved > 0 && (
            <span className="text-xs text-destructive">-{change.linesRemoved}</span>
          )}
          <StatusBadge status={change.status} />
          <ChevronDown className={cn(
            "h-4 w-4 transition-transform",
            isExpanded && "rotate-180"
          )} />
        </div>
      </div>
      
      {/* Expanded diff view */}
      {isExpanded && change.diffPatch && (
        <div className="border-t border-border">
          <DiffViewer 
            diff={change.diffPatch}
            language={getLanguageFromPath(change.filePath)}
          />
        </div>
      )}
    </div>
  );
};
```

---

## API Endpoints

### GET /api/v1/sessions/{sessionId}/file-changes

List all file changes for a session.

**Response:**
```json
{
  "success": true,
  "data": {
    "changes": [
      {
        "id": "fc_abc123",
        "messageId": "msg_xyz789",
        "filePath": "internal/auth/handler.go",
        "changeType": "create",
        "status": "applied",
        "linesAdded": 156,
        "linesRemoved": 0,
        "createdAt": "2026-01-29T10:30:00Z",
        "appliedAt": "2026-01-29T10:30:05Z"
      }
    ],
    "summary": {
      "totalFiles": 4,
      "filesCreated": 2,
      "filesModified": 2,
      "filesDeleted": 0,
      "totalLinesAdded": 261,
      "totalLinesRemoved": 3
    }
  }
}
```

### GET /api/v1/file-changes/{changeId}/diff

Get full diff for a specific change.

**Response:**
```json
{
  "success": true,
  "data": {
    "changeId": "fc_abc123",
    "filePath": "internal/router/routes.go",
    "originalContent": "package router\n\nfunc SetupRoutes...",
    "newContent": "package router\n\nimport \"auth\"\n\nfunc SetupRoutes...",
    "diffPatch": "@@ -1,5 +1,7 @@\n package router\n+\n+import \"auth\"..."
  }
}
```

### POST /api/v1/file-changes/{changeId}/revert

Revert a specific file change.

**Request:**
```json
{
  "reason": "User requested revert"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "changeId": "fc_abc123",
    "status": "reverted",
    "revertedAt": "2026-01-29T10:35:00Z"
  }
}
```

---

## Error Handling

| Code | Description |
|------|-------------|
| 12800 | File change not found |
| 12801 | Cannot revert: file already modified |
| 12802 | Diff generation failed |
| 12803 | File write permission denied |
| 12804 | Conflict detected during apply |
| 12805 | Original content not available for revert |
| 12806 | Invalid change type |
| 12807 | File path outside project scope |
