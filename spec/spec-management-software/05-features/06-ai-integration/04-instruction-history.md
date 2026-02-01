# Instruction History System

**Version:** 1.0.0  
**Status:** Draft  
**Last Updated:** 2026-01-27

---

## 1. Overview

The Instruction History System tracks the relationship between voice/text instructions and the file changes they produce. This enables:

- **Traceability**: See which instruction caused each file change
- **Rollback**: Undo all changes from a specific instruction
- **Audit**: Complete history of AI-driven modifications
- **Learning**: Analyze instruction patterns for improvement

---

## 2. Data Model

### 2.1 Core Entities

```
┌─────────────────┐      ┌──────────────────┐      ┌─────────────────┐
│   Instruction   │──1:N─│ InstructionTask  │──1:N─│  FileChange     │
└─────────────────┘      └──────────────────┘      └─────────────────┘
         │                        │                        │
         │                        │                        │
         ▼                        ▼                        ▼
    Voice/Text              Planned Task              Actual Change
    + AI Reasoning          (create/update)           (diff/snapshot)
```

### 2.2 FileChange Table

| Column | Type | Description |
|--------|------|-------------|
| `Id` | TEXT (UUID) | Primary key |
| `InstructionTaskId` | TEXT (UUID) | FK → InstructionTask |
| `FileId` | TEXT (UUID) | FK → File (nullable for created files) |
| `FilePath` | TEXT | Path at time of change |
| `ChangeType` | TEXT | `created`, `updated`, `deleted`, `renamed` |
| `BeforeHash` | TEXT | SHA-256 before change (null for create) |
| `AfterHash` | TEXT | SHA-256 after change (null for delete) |
| `DiffContent` | TEXT | Unified diff format (nullable) |
| `BeforeSnapshot` | TEXT | Full content before (for small files) |
| `AfterSnapshot` | TEXT | Full content after (for small files) |
| `BytesBefore` | INTEGER | File size before |
| `BytesAfter` | INTEGER | File size after |
| `CreatedAt` | TEXT | ISO8601 timestamp |

### 2.3 InstructionRollback Table

| Column | Type | Description |
|--------|------|-------------|
| `Id` | TEXT (UUID) | Primary key |
| `InstructionId` | TEXT (UUID) | FK → Instruction |
| `UserId` | TEXT (UUID) | FK → User who initiated |
| `Status` | TEXT | `pending`, `in_progress`, `completed`, `failed`, `partial` |
| `FilesReverted` | INTEGER | Count of successfully reverted files |
| `FilesSkipped` | INTEGER | Count of skipped (conflict) files |
| `Reason` | TEXT | User-provided reason (optional) |
| `ErrorDetails` | TEXT | JSON array of errors if partial/failed |
| `StartedAt` | TEXT | ISO8601 timestamp |
| `CompletedAt` | TEXT | ISO8601 timestamp |

---

## 3. Change Tracking Logic

### 3.1 Capture Strategy

```
When InstructionTask executes:
  1. Before modification:
     - Capture file hash (BeforeHash)
     - If file < 100KB, capture full content (BeforeSnapshot)
     
  2. After modification:
     - Capture file hash (AfterHash)
     - If file < 100KB, capture full content (AfterSnapshot)
     - Generate unified diff (DiffContent)
     
  3. Create FileChange record linking to InstructionTaskId
```

### 3.2 Snapshot Threshold

```go
const (
    SnapshotSizeThreshold = 100 * 1024  // 100KB
    DiffSizeThreshold     = 500 * 1024  // 500KB - skip diff for large files
)

type ChangeCapture struct {
    CaptureSnapshot bool  // Store full content
    CaptureDiff     bool  // Generate unified diff
}

func DetermineCaptureStrategy(fileSize int64) ChangeCapture {
    return ChangeCapture{
        CaptureSnapshot: fileSize < SnapshotSizeThreshold,
        CaptureDiff:     fileSize < DiffSizeThreshold,
    }
}
```

### 3.3 Change Type Detection

| Scenario | ChangeType |
|----------|------------|
| File didn't exist, now exists | `created` |
| File existed, content changed | `updated` |
| File existed, now doesn't exist | `deleted` |
| File path changed | `renamed` |

---

## 4. History Queries

### 4.1 Get Instruction Impact

```go
type InstructionImpact struct {
    InstructionId   string
    Transcription   string
    ExecutedAt      time.Time
    FilesCreated    int
    FilesUpdated    int
    FilesDeleted    int
    TotalBytesAdded int64
    TotalBytesRemoved int64
    Changes         []FileChange
}

type InstructionHistoryService interface {
    // Get impact summary for an instruction
    GetInstructionImpact(ctx context.Context, instructionId string) (*InstructionImpact, error)
    
    // Get all changes for a specific file
    GetFileHistory(ctx context.Context, fileId string, limit int) ([]FileChange, error)
    
    // Get changes within time range
    GetChangesByTimeRange(ctx context.Context, projectId string, from, to time.Time) ([]FileChange, error)
    
    // Get instruction that last modified a file
    GetLastModifyingInstruction(ctx context.Context, fileId string) (*Instruction, error)
}
```

### 4.2 Timeline View Data

```typescript
interface InstructionTimelineEntry {
  instructionId: string;
  transcription: string;         // Truncated to 100 chars
  scope: InstructionScope;
  executedAt: string;            // ISO8601
  status: 'completed' | 'partial' | 'rolled_back';
  impact: {
    filesCreated: number;
    filesUpdated: number;
    filesDeleted: number;
    netBytesChange: number;      // Positive = added, negative = removed
  };
  isRollbackable: boolean;       // False if subsequent changes conflict
}
```

---

## 5. Rollback System

### 5.1 Rollback Process

```
User initiates rollback for Instruction X:

1. VALIDATION
   - Check instruction exists and has changes
   - For each FileChange:
     - If ChangeType = 'created': Check file still exists with same hash
     - If ChangeType = 'updated': Check current hash matches AfterHash
     - If ChangeType = 'deleted': Check file still doesn't exist
     - If ChangeType = 'renamed': Check both paths status

2. CONFLICT DETECTION
   - If current state doesn't match expected AfterHash:
     → File was modified by subsequent instruction or external edit
     → Mark as conflict, skip or force (user choice)

3. EXECUTION (per FileChange, reverse order)
   - created → Delete file
   - updated → Restore BeforeSnapshot (or apply reverse diff)
   - deleted → Restore BeforeSnapshot
   - renamed → Rename back to original path

4. RECORD
   - Create InstructionRollback record
   - Update Instruction.Status to 'rolled_back'
```

### 5.2 Rollback Modes

```go
type RollbackMode string

const (
    RollbackSafe  RollbackMode = "safe"   // Skip conflicts, partial rollback
    RollbackForce RollbackMode = "force"  // Overwrite conflicts
    RollbackDry   RollbackMode = "dry"    // Preview only, no changes
)

type RollbackRequest struct {
    InstructionId string
    Mode          RollbackMode
    Reason        string  // Optional user note
}

type RollbackResult struct {
    Success       bool
    FilesReverted []string
    FilesSkipped  []FileConflict
    Errors        []RollbackError
}

type FileConflict struct {
    FilePath       string
    ExpectedHash   string  // What we expected (AfterHash from instruction)
    CurrentHash    string  // What's actually there now
    ConflictReason string  // "modified_externally", "modified_by_instruction", etc.
}
```

### 5.3 Cascade Rollback

When rolling back instruction X, check if subsequent instructions depend on X's changes:

```go
type CascadeAnalysis struct {
    DirectChanges      []FileChange  // Changes from instruction X
    DependentChanges   []FileChange  // Changes from later instructions to same files
    RequiresCascade    bool          // True if dependent changes exist
    CascadeInstructions []string     // IDs of instructions that would also need rollback
}

func AnalyzeCascade(ctx context.Context, instructionId string) (*CascadeAnalysis, error)
```

---

## 6. API Endpoints

### 6.1 History Queries

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/instructions/{id}/impact` | Get instruction impact summary |
| GET | `/api/v1/instructions/{id}/changes` | Get all file changes from instruction |
| GET | `/api/v1/files/{id}/history` | Get change history for a file |
| GET | `/api/v1/projects/{id}/history/timeline` | Get instruction timeline for project |
| GET | `/api/v1/projects/{id}/history/stats` | Get aggregate change statistics |

### 6.2 Rollback Operations

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/instructions/{id}/rollback/preview` | Dry-run rollback analysis |
| POST | `/api/v1/instructions/{id}/rollback` | Execute rollback |
| GET | `/api/v1/rollbacks/{id}` | Get rollback status |
| GET | `/api/v1/projects/{id}/rollbacks` | List rollbacks for project |

### 6.3 Request/Response Schemas

#### GET `/api/v1/instructions/{id}/impact`

**Response:**
```json
{
  "success": true,
  "data": {
    "instructionId": "uuid",
    "transcription": "Add a footer component to all frontend pages",
    "scope": "frontend",
    "executedAt": "2026-01-27T14:30:00Z",
    "duration": 2.5,
    "impact": {
      "filesCreated": 1,
      "filesUpdated": 3,
      "filesDeleted": 0,
      "totalLinesAdded": 45,
      "totalLinesRemoved": 2,
      "bytesAdded": 1250,
      "bytesRemoved": 48
    },
    "changes": [
      {
        "id": "uuid",
        "filePath": "02-frontend/components/Footer.md",
        "changeType": "created",
        "linesAdded": 28,
        "linesRemoved": 0
      }
    ],
    "isRollbackable": true,
    "rollbackConflicts": []
  }
}
```

#### POST `/api/v1/instructions/{id}/rollback`

**Request:**
```json
{
  "mode": "safe",
  "reason": "Instruction produced incorrect output"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "rollbackId": "uuid",
    "status": "completed",
    "filesReverted": 4,
    "filesSkipped": 0,
    "revertedPaths": [
      "02-frontend/components/Footer.md",
      "02-frontend/01-overview.md"
    ],
    "skippedPaths": [],
    "completedAt": "2026-01-27T14:35:00Z"
  }
}
```

---

## 7. Diff Generation

### 7.1 Unified Diff Format

```go
import "github.com/sergi/go-diff/diffmatchpatch"

func GenerateUnifiedDiff(before, after, filePath string) string {
    dmp := diffmatchpatch.New()
    diffs := dmp.DiffMain(before, after, true)
    patches := dmp.PatchMake(before, diffs)
    return dmp.PatchToText(patches)
}
```

### 7.2 Diff Storage Rules

| File Size | Storage Strategy |
|-----------|------------------|
| < 100KB | Full before/after snapshots + diff |
| 100KB - 500KB | Diff only |
| > 500KB | Hashes only (no diff) |

### 7.3 Applying Reverse Diff

```go
func ApplyReverseDiff(currentContent, diff string) (string, error) {
    dmp := diffmatchpatch.New()
    patches, err := dmp.PatchFromText(diff)
    if err != nil {
        return "", err
    }
    
    // Reverse the patches
    reversed := ReversePatch(patches)
    
    result, applied := dmp.PatchApply(reversed, currentContent)
    if !allTrue(applied) {
        return "", ErrPatchFailed
    }
    return result, nil
}
```

---

## 8. Error Codes

| Code | Constant | Description |
|------|----------|-------------|
| 5070 | `ERR_INSTRUCTION_NOT_FOUND` | Instruction ID doesn't exist |
| 5071 | `ERR_NO_CHANGES_RECORDED` | Instruction has no file changes |
| 5072 | `ERR_ROLLBACK_CONFLICT` | File modified since instruction |
| 5073 | `ERR_ROLLBACK_IN_PROGRESS` | Another rollback already running |
| 5074 | `ERR_SNAPSHOT_MISSING` | Required snapshot not stored (large file) |
| 5075 | `ERR_CASCADE_REQUIRED` | Rollback requires cascading to dependent instructions |
| 5076 | `ERR_DIFF_APPLY_FAILED` | Failed to apply reverse diff |
| 5077 | `ERR_FILE_STATE_CHANGED` | File state doesn't match expected |

---

## 9. Retention & Cleanup

### 9.1 Retention Policy

```go
type HistoryRetentionConfig struct {
    MaxAgeInDays         int   // Default: 90 days
    MaxEntriesPerProject int   // Default: 1000
    KeepSnapshotsForDays int   // Default: 30 days (then only keep hashes)
    KeepDiffsForDays     int   // Default: 60 days
}
```

### 9.2 Cleanup Job

```go
// Runs daily via cron
func CleanupOldHistory(ctx context.Context) error {
    config := GetRetentionConfig()
    
    // 1. Delete snapshots older than threshold
    DeleteOldSnapshots(ctx, config.KeepSnapshotsForDays)
    
    // 2. Delete diffs older than threshold  
    DeleteOldDiffs(ctx, config.KeepDiffsForDays)
    
    // 3. Delete change records older than max age
    DeleteOldChanges(ctx, config.MaxAgeInDays)
    
    // 4. If over max entries, delete oldest
    TrimExcessEntries(ctx, config.MaxEntriesPerProject)
    
    return nil
}
```

---

## 10. Frontend Integration

### 10.1 History Panel Component

See `02-frontend/06-history-ui.md` for full UI specification.

Key integration points:

```typescript
// Hook for instruction history
function useInstructionHistory(projectId: string) {
  return useQuery({
    queryKey: ['instruction-history', projectId],
    queryFn: () => api.get(`/projects/${projectId}/history/timeline`),
  });
}

// Hook for rollback preview
function useRollbackPreview(instructionId: string) {
  return useMutation({
    mutationFn: () => api.post(`/instructions/${instructionId}/rollback/preview`),
  });
}

// Hook for executing rollback
function useRollback() {
  return useMutation({
    mutationFn: (params: { instructionId: string; mode: RollbackMode; reason?: string }) =>
      api.post(`/instructions/${params.instructionId}/rollback`, params),
  });
}
```

### 10.2 Change Visualization

```typescript
interface FileChangeView {
  filePath: string;
  changeType: 'created' | 'updated' | 'deleted' | 'renamed';
  linesAdded: number;
  linesRemoved: number;
  hasDiff: boolean;      // Can show inline diff
  hasSnapshot: boolean;  // Can show full before/after
}

// Diff viewer component receives:
interface DiffViewerProps {
  before: string | null;
  after: string | null;
  diff: string | null;
  filePath: string;
  language: string;  // For syntax highlighting
}
```

---

## 11. Cross-References

- **Database Schema:** [01-schema.md](../../07-database-design/01-schema.md)
- **Instruction System:** [03-instruction-system.md](./03-instruction-system.md)
- **History UI:** [03-history-ui.md](../07-history-system/03-history-ui.md)
- **AI Integration:** [01-ai-integration.md](./01-ai-integration.md)
