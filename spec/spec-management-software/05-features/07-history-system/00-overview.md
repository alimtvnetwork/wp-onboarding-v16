# Feature: History System

**Version:** 2.0.0  
**Status:** Complete  
**Updated:** 2026-01-31  

---

## Summary

Version control and snapshot system for tracking changes to specifications over time with Git integration. Provides full undo/redo, named snapshots, diff viewing, and restore capabilities.

---

## User Stories

- As a user, I want to see the history of changes to a file
- As a user, I want to compare different versions of a file
- As a user, I want to restore a previous version
- As a user, I want to create named snapshots at milestones
- As a user, I want to undo/redo my recent changes

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           History System                                     │
├─────────────────────────────────────────────────────────────────────────────┤
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────────────────┐  │
│  │  HistoryService │  │  SnapshotManager│  │     DiffEngine              │  │
│  │                 │  │                 │  │                             │  │
│  │ - Track changes │  │ - Create named  │  │ - Myers diff algorithm      │  │
│  │ - Undo/redo     │  │ - Restore point │  │ - Semantic diff             │  │
│  │ - Version list  │  │ - Export/import │  │ - Three-way merge           │  │
│  └────────┬────────┘  └────────┬────────┘  └─────────────┬───────────────┘  │
│           │                    │                         │                   │
│           └────────────────────┼─────────────────────────┘                   │
│                                │                                             │
│                    ┌───────────┴───────────┐                                 │
│                    │    GitAdapter         │                                 │
│                    │    (go-git)           │                                 │
│                    │                       │                                 │
│                    │ - Commit management   │                                 │
│                    │ - Branch operations   │                                 │
│                    │ - Blob storage        │                                 │
│                    └───────────┬───────────┘                                 │
└────────────────────────────────┼─────────────────────────────────────────────┘
                                 │
                    ┌────────────┴────────────┐
                    │    .git Repository      │
                    │                         │
                    │  - Objects (blobs)      │
                    │  - Refs (branches)      │
                    │  - Index (staging)      │
                    └─────────────────────────┘
```

---

## Components

| # | Component | Type | Description |
|---|-----------|------|-------------|
| 01 | [Git Integration](./01-git-integration.md) | Backend | Git version control with go-git |
| 02 | [History Service](./02-history-service.md) | Backend | Snapshots and version management |
| 03 | [Diff Engine](./03-diff-engine.md) | Backend | Diff algorithms and three-way merge |
| 04 | [History UI](./04-history-ui.md) | Frontend | Version history interface |
| 05 | [File Comparison](./05-file-comparison.md) | Frontend | Side-by-side diff viewer |

---

## TypeScript Interfaces

### Version

```typescript
interface Version {
  id: string;                    // Git commit SHA
  fileId: string;                // File UUID
  message: string;               // Commit message
  author: string;                // User ID
  timestamp: string;             // ISO 8601
  parentId: string | null;       // Parent commit SHA
  size: number;                  // Content size in bytes
  hash: string;                  // Content SHA-256
}

interface VersionListResponse {
  versions: Version[];
  totalCount: number;
  hasMore: boolean;
  cursor: string | null;
}
```

### Snapshot

```typescript
interface Snapshot {
  id: string;                    // UUID
  projectId: string;             // Project UUID
  name: string;                  // User-defined name
  description: string | null;    // Optional description
  tag: string;                   // Git tag (V{nn}-{YYYY-MM-DD})
  commitSha: string;             // Git commit SHA
  fileCount: number;             // Number of files
  totalSize: number;             // Total size in bytes
  createdAt: string;             // ISO 8601
  createdBy: string;             // User ID
  metadata: SnapshotMetadata;
}

interface SnapshotMetadata {
  trigger: SnapshotTrigger;
  previousSnapshotId: string | null;
  changesSinceLastSnapshot: number;
  autoExpireAt: string | null;
}

type SnapshotTrigger = 
  | 'manual' 
  | 'scheduled' 
  | 'before_restore' 
  | 'before_merge'
  | 'milestone';
```

### Diff

```typescript
interface DiffResult {
  fileId: string;
  fromVersion: string;           // Commit SHA or 'working'
  toVersion: string;             // Commit SHA or 'working'
  hunks: DiffHunk[];
  stats: DiffStats;
  binary: boolean;
}

interface DiffHunk {
  oldStart: number;
  oldLines: number;
  newStart: number;
  newLines: number;
  lines: DiffLine[];
}

interface DiffLine {
  type: 'context' | 'add' | 'delete';
  content: string;
  oldLineNumber: number | null;
  newLineNumber: number | null;
}

interface DiffStats {
  additions: number;
  deletions: number;
  changes: number;
}
```

### Undo/Redo

```typescript
interface UndoStack {
  canUndo: boolean;
  canRedo: boolean;
  undoDescription: string | null;
  redoDescription: string | null;
}

interface UndoOperation {
  id: string;
  type: OperationType;
  fileId: string;
  timestamp: string;
  description: string;
  patches: Patch[];
}

type OperationType = 
  | 'edit' 
  | 'create' 
  | 'delete' 
  | 'rename' 
  | 'move';

interface Patch {
  offset: number;
  length: number;
  text: string;
}
```

---

## Git Integration

### go-git Configuration

```go
type GitConfig struct {
    RepoPath       string        // Path to .git directory
    AuthorName     string        // Default commit author
    AuthorEmail    string        // Default commit email
    AutoCommit     bool          // Auto-commit on save
    CommitInterval time.Duration // Batch commit interval (default: 30s)
    MaxBlobSize    int64         // Max blob size (default: 10MB)
}

type GitAdapter interface {
    // Repository operations
    Init() error
    Clone(url string) error
    
    // Commit operations
    Commit(files []string, message string) (string, error)
    GetCommit(sha string) (*Commit, error)
    GetCommitHistory(path string, limit int) ([]*Commit, error)
    
    // Diff operations
    DiffCommits(from, to string) (*DiffResult, error)
    DiffWorking(path string) (*DiffResult, error)
    
    // Branch operations
    CreateBranch(name string) error
    SwitchBranch(name string) error
    MergeBranch(source, target string) (*MergeResult, error)
    
    // Blob operations
    GetBlob(sha string) ([]byte, error)
    GetBlobAtCommit(path, commitSha string) ([]byte, error)
    
    // Tag operations (for snapshots)
    CreateTag(name, commitSha, message string) error
    ListTags() ([]*Tag, error)
    GetTaggedCommit(tagName string) (string, error)
}
```

### Commit Strategy

```go
// BatchCommitter groups rapid changes into single commits
type BatchCommitter struct {
    interval    time.Duration  // 30 seconds default
    maxPending  int            // 100 files max
    pending     map[string]struct{}
    mu          sync.Mutex
    timer       *time.Timer
}

func (bc *BatchCommitter) QueueFile(path string) {
    bc.mu.Lock()
    defer bc.mu.Unlock()
    
    bc.pending[path] = struct{}{}
    
    if len(bc.pending) >= bc.maxPending {
        bc.flush()
        return
    }
    
    if bc.timer == nil {
        bc.timer = time.AfterFunc(bc.interval, bc.flush)
    }
}
```

---

## Diff Algorithms

### Myers Diff Algorithm

```go
// MyersDiff implements the Myers O(ND) difference algorithm
type MyersDiff struct {
    a, b []string  // Lines to compare
}

func (d *MyersDiff) Compute() []Edit {
    n, m := len(d.a), len(d.b)
    max := n + m
    
    v := make([]int, 2*max+1)
    trace := make([][]int, 0)
    
    for depth := 0; depth <= max; depth++ {
        // V array snapshots for backtracking
        trace = append(trace, append([]int{}, v...))
        
        for k := -depth; k <= depth; k += 2 {
            // Choose path: down or right
            var x int
            if k == -depth || (k != depth && v[k-1+max] < v[k+1+max]) {
                x = v[k+1+max]  // Move down
            } else {
                x = v[k-1+max] + 1  // Move right
            }
            
            y := x - k
            
            // Follow diagonal (matching lines)
            for x < n && y < m && d.a[x] == d.b[y] {
                x++
                y++
            }
            
            v[k+max] = x
            
            // Check if we reached the end
            if x >= n && y >= m {
                return d.backtrack(trace)
            }
        }
    }
    
    return nil
}
```

### Semantic Diff (Markdown-Aware)

```go
// SemanticDiff understands Markdown structure
type SemanticDiff struct {
    parser *MarkdownParser
}

type SemanticChange struct {
    Type     ChangeType
    Path     string     // e.g., "## Section > ### Subsection"
    OldValue string
    NewValue string
    Context  []string
}

type ChangeType int

const (
    HeadingChange ChangeType = iota
    ParagraphChange
    CodeBlockChange
    ListItemChange
    LinkChange
    MetadataChange
)

func (sd *SemanticDiff) Compare(oldDoc, newDoc string) []SemanticChange {
    oldAST := sd.parser.Parse(oldDoc)
    newAST := sd.parser.Parse(newDoc)
    
    return sd.diffAST(oldAST, newAST)
}
```

### Three-Way Merge

```go
// ThreeWayMerge handles concurrent edits
type ThreeWayMerge struct {
    base   []string  // Common ancestor
    ours   []string  // Our changes
    theirs []string  // Their changes
}

type MergeResult struct {
    Content   []string
    Conflicts []MergeConflict
    Success   bool
}

type MergeConflict struct {
    BaseStart   int
    BaseEnd     int
    OursStart   int
    OursEnd     int
    TheirsStart int
    TheirsEnd   int
    BaseLines   []string
    OurLines    []string
    TheirLines  []string
}

func (m *ThreeWayMerge) Merge() (*MergeResult, error) {
    // Compute diffs from base to each version
    oursEdits := MyersDiff{m.base, m.ours}.Compute()
    theirsEdits := MyersDiff{m.base, m.theirs}.Compute()
    
    // Identify conflicting regions
    conflicts := m.findConflicts(oursEdits, theirsEdits)
    
    if len(conflicts) == 0 {
        // Clean merge: apply both sets of edits
        merged := m.applyEdits(oursEdits, theirsEdits)
        return &MergeResult{Content: merged, Success: true}, nil
    }
    
    // Return with conflict markers
    return m.generateConflictMarkers(conflicts), nil
}
```

---

## Key Features

| Feature | Description | Implementation |
|---------|-------------|----------------|
| **Git Backend** | Full version control | go-git library |
| **Auto-Commit** | Batch commits on save | 30s interval, 100 file max |
| **Snapshots** | Named point-in-time captures | Git tags (V{nn}-{YYYY-MM-DD}) |
| **Diff Viewer** | Side-by-side comparison | Myers algorithm |
| **Semantic Diff** | Markdown-aware changes | AST comparison |
| **Three-Way Merge** | Concurrent edit handling | Conflict markers |
| **Undo/Redo** | Local edit history | Patch-based stack |
| **Restore** | Revert to any version | Git checkout + commit |

---

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/files/{id}/history` | List file versions |
| GET | `/api/v1/files/{id}/versions/{sha}` | Get specific version content |
| GET | `/api/v1/files/{id}/diff` | Diff two versions |
| POST | `/api/v1/files/{id}/restore` | Restore to version |
| GET | `/api/v1/projects/{id}/snapshots` | List snapshots |
| POST | `/api/v1/projects/{id}/snapshots` | Create snapshot |
| POST | `/api/v1/projects/{id}/snapshots/{id}/restore` | Restore snapshot |
| POST | `/api/v1/files/{id}/undo` | Undo last change |
| POST | `/api/v1/files/{id}/redo` | Redo undone change |

---

## Error Codes

| Code | Constant | Description |
|------|----------|-------------|
| 7001 | `ERR_HISTORY_NOT_FOUND` | Version not found |
| 7002 | `ERR_HISTORY_CORRUPT` | Git repository corrupted |
| 7003 | `ERR_SNAPSHOT_NOT_FOUND` | Snapshot not found |
| 7004 | `ERR_SNAPSHOT_CREATE_FAILED` | Failed to create snapshot |
| 7005 | `ERR_RESTORE_FAILED` | Restore operation failed |
| 7006 | `ERR_MERGE_CONFLICT` | Merge has conflicts |
| 7007 | `ERR_DIFF_FAILED` | Diff computation failed |
| 7008 | `ERR_UNDO_UNAVAILABLE` | Nothing to undo |
| 7009 | `ERR_REDO_UNAVAILABLE` | Nothing to redo |

---

## Dependencies

- [File Management](../02-file-management/00-overview.md)
- [Database Design](../../07-database-design/00-overview.md)

---

## E2E Tests

| # | Test | Priority |
|---|------|----------|
| 01 | [Version History](./tests/01-version-history-e2e.md) | High |
| 02 | [Diff Comparison](./tests/02-diff-comparison-e2e.md) | High |
| 03 | [Restore Version](./tests/03-restore-version-e2e.md) | High |
| 04 | [Snapshot Management](./tests/04-snapshot-e2e.md) | High |
| 05 | [Undo/Redo](./tests/05-undo-redo-e2e.md) | Medium |

---

## Related Specs

- [Git Integration](./01-git-integration.md)
- [File Management](../02-file-management/00-overview.md)
