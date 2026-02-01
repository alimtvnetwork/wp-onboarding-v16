# 08. History Logger

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-29  
**Parent:** [Overview](./00-overview.md)

---

## Purpose

Define the filesystem operation history logging system that tracks every operation performed by generated Golang code. Provides complete audit trails with checksums, timestamps, and error tracking for debugging and rollback capabilities.

---

## Logging Requirements

Every filesystem operation MUST log:

| Field | Description | Required |
|-------|-------------|----------|
| Timestamp | Operation time with timezone | ✅ |
| Operation | Type (create, update, delete, rename, etc.) | ✅ |
| TaskId | Associated TempCodingTask | ✅ |
| OldPath | Original file path | When applicable |
| NewPath | New file path | When applicable |
| ChecksumBefore | SHA-256 hash before operation | When applicable |
| ChecksumAfter | SHA-256 hash after operation | When applicable |
| FileSizeBytes | File size in bytes | When applicable |
| Success | Operation success status | ✅ |
| ErrorMessage | Error details if failed | When failed |
| Metadata | Additional context (JSON) | Optional |

---

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    HISTORY LOGGER                            │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐  │
│  │   Generated  │───▶│   Logger     │───▶│   SQLite     │  │
│  │   Golang CLI │    │   API        │    │   Database   │  │
│  └──────────────┘    └──────────────┘    └──────────────┘  │
│                             │                               │
│                             ▼                               │
│                      ┌──────────────┐                       │
│                      │   JSON Log   │                       │
│                      │   File       │                       │
│                      └──────────────┘                       │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## Implementation

### History Logger Interface

```go
package history

import (
    "crypto/sha256"
    "database/sql"
    "encoding/json"
    "fmt"
    "io"
    "os"
    "time"
    
    "gorm.io/gorm"
)

type OperationType string

const (
    OpCreate OperationType = "create"
    OpRead   OperationType = "read"
    OpUpdate OperationType = "update"
    OpDelete OperationType = "delete"
    OpRename OperationType = "rename"
    OpMove   OperationType = "move"
    OpCopy   OperationType = "copy"
)

type LogEntry struct {
    TaskId         uint                   `json:"taskId"`
    Timestamp      time.Time              `json:"timestamp"`
    Operation      OperationType          `json:"operation"`
    OldPath        *string                `json:"oldPath,omitempty"`
    NewPath        *string                `json:"newPath,omitempty"`
    FileSizeBytes  *int64                 `json:"fileSizeBytes,omitempty"`
    ChecksumBefore *string                `json:"checksumBefore,omitempty"`
    ChecksumAfter  *string                `json:"checksumAfter,omitempty"`
    Success        bool                   `json:"success"`
    ErrorMessage   *string                `json:"errorMessage,omitempty"`
    Metadata       map[string]interface{} `json:"metadata,omitempty"`
}

type HistoryLogger struct {
    db           *gorm.DB
    taskId       uint
    entries      []LogEntry
    jsonLogPath  string
}

func NewHistoryLogger(db *gorm.DB, taskId uint, jsonLogPath string) *HistoryLogger {
    return &HistoryLogger{
        db:          db,
        taskId:      taskId,
        entries:     []LogEntry{},
        jsonLogPath: jsonLogPath,
    }
}
```

### Logging Operations

```go
func (hl *HistoryLogger) LogOperation(entry LogEntry) error {
    entry.TaskId = hl.taskId
    entry.Timestamp = time.Now()
    
    // Add to in-memory list
    hl.entries = append(hl.entries, entry)
    
    // Persist to database
    dbEntry := FilesystemHistory{
        TaskId:         &hl.taskId,
        Timestamp:      entry.Timestamp,
        OperationType:  string(entry.Operation),
        OldPath:        entry.OldPath,
        NewPath:        entry.NewPath,
        FileSizeBytes:  entry.FileSizeBytes,
        ChecksumBefore: entry.ChecksumBefore,
        ChecksumAfter:  entry.ChecksumAfter,
        IsSuccess:      entry.Success,
        ErrorMessage:   entry.ErrorMessage,
    }
    
    if entry.Metadata != nil {
        metaJson, _ := json.Marshal(entry.Metadata)
        metaStr := string(metaJson)
        dbEntry.Metadata = &metaStr
    }
    
    return hl.db.Create(&dbEntry).Error
}

func (hl *HistoryLogger) LogCreate(path string, success bool, err error) error {
    entry := LogEntry{
        Operation: OpCreate,
        NewPath:   &path,
        Success:   success,
    }
    
    if success {
        if checksum, e := hl.CalculateChecksum(path); e == nil {
            entry.ChecksumAfter = &checksum
        }
        if info, e := os.Stat(path); e == nil {
            size := info.Size()
            entry.FileSizeBytes = &size
        }
    }
    
    if err != nil {
        errMsg := err.Error()
        entry.ErrorMessage = &errMsg
    }
    
    return hl.LogOperation(entry)
}

func (hl *HistoryLogger) LogUpdate(path string, success bool, err error) error {
    entry := LogEntry{
        Operation: OpUpdate,
        OldPath:   &path,
        NewPath:   &path,
        Success:   success,
    }
    
    if success {
        if checksum, e := hl.CalculateChecksum(path); e == nil {
            entry.ChecksumAfter = &checksum
        }
    }
    
    if err != nil {
        errMsg := err.Error()
        entry.ErrorMessage = &errMsg
    }
    
    return hl.LogOperation(entry)
}

func (hl *HistoryLogger) LogRename(oldPath, newPath string, success bool, err error) error {
    entry := LogEntry{
        Operation: OpRename,
        OldPath:   &oldPath,
        NewPath:   &newPath,
        Success:   success,
    }
    
    if success {
        if checksum, e := hl.CalculateChecksum(newPath); e == nil {
            entry.ChecksumAfter = &checksum
            entry.ChecksumBefore = &checksum // Same content, different name
        }
    }
    
    if err != nil {
        errMsg := err.Error()
        entry.ErrorMessage = &errMsg
    }
    
    return hl.LogOperation(entry)
}

func (hl *HistoryLogger) LogDelete(path string, checksumBefore string, success bool, err error) error {
    entry := LogEntry{
        Operation:      OpDelete,
        OldPath:        &path,
        ChecksumBefore: &checksumBefore,
        Success:        success,
    }
    
    if err != nil {
        errMsg := err.Error()
        entry.ErrorMessage = &errMsg
    }
    
    return hl.LogOperation(entry)
}
```

### Checksum Calculation

```go
func (hl *HistoryLogger) CalculateChecksum(filePath string) (string, error) {
    file, err := os.Open(filePath)
    if err != nil {
        return "", fmt.Errorf("failed to open file: %w", err)
    }
    defer file.Close()
    
    hash := sha256.New()
    if _, err := io.Copy(hash, file); err != nil {
        return "", fmt.Errorf("failed to calculate checksum: %w", err)
    }
    
    return fmt.Sprintf("%x", hash.Sum(nil)), nil
}

func (hl *HistoryLogger) CalculateChecksumWithSize(filePath string) (string, int64, error) {
    file, err := os.Open(filePath)
    if err != nil {
        return "", 0, err
    }
    defer file.Close()
    
    info, err := file.Stat()
    if err != nil {
        return "", 0, err
    }
    
    hash := sha256.New()
    if _, err := io.Copy(hash, file); err != nil {
        return "", 0, err
    }
    
    return fmt.Sprintf("%x", hash.Sum(nil)), info.Size(), nil
}
```

### History Queries

```go
func (hl *HistoryLogger) GetTaskHistory(taskId uint) ([]LogEntry, error) {
    var dbEntries []FilesystemHistory
    
    err := hl.db.
        Where("task_id = ?", taskId).
        Order("timestamp DESC").
        Find(&dbEntries).Error
    
    if err != nil {
        return nil, err
    }
    
    entries := make([]LogEntry, len(dbEntries))
    for i, db := range dbEntries {
        entries[i] = LogEntry{
            TaskId:         *db.TaskId,
            Timestamp:      db.Timestamp,
            Operation:      OperationType(db.OperationType),
            OldPath:        db.OldPath,
            NewPath:        db.NewPath,
            FileSizeBytes:  db.FileSizeBytes,
            ChecksumBefore: db.ChecksumBefore,
            ChecksumAfter:  db.ChecksumAfter,
            Success:        db.IsSuccess,
            ErrorMessage:   db.ErrorMessage,
        }
    }
    
    return entries, nil
}

func (hl *HistoryLogger) GetOperationsByPath(path string) ([]LogEntry, error) {
    var dbEntries []FilesystemHistory
    
    err := hl.db.
        Where("old_path = ? OR new_path = ?", path, path).
        Order("timestamp DESC").
        Find(&dbEntries).Error
    
    if err != nil {
        return nil, err
    }
    
    // Convert to LogEntry...
    return nil, nil
}

func (hl *HistoryLogger) GetFailedOperations(since time.Time) ([]LogEntry, error) {
    var dbEntries []FilesystemHistory
    
    err := hl.db.
        Where("is_success = ? AND timestamp > ?", false, since).
        Order("timestamp DESC").
        Find(&dbEntries).Error
    
    if err != nil {
        return nil, err
    }
    
    // Convert to LogEntry...
    return nil, nil
}
```

### Finalization

```go
func (hl *HistoryLogger) Finalize() error {
    // Write JSON log file
    if hl.jsonLogPath != "" {
        data, err := json.MarshalIndent(hl.entries, "", "  ")
        if err != nil {
            return err
        }
        
        if err := os.WriteFile(hl.jsonLogPath, data, 0644); err != nil {
            return err
        }
    }
    
    return nil
}

func (hl *HistoryLogger) GetSummary() HistorySummary {
    summary := HistorySummary{
        TotalOperations: len(hl.entries),
    }
    
    for _, entry := range hl.entries {
        if entry.Success {
            summary.SuccessCount++
        } else {
            summary.FailureCount++
        }
        
        switch entry.Operation {
        case OpCreate:
            summary.Creates++
        case OpUpdate:
            summary.Updates++
        case OpDelete:
            summary.Deletes++
        case OpRename:
            summary.Renames++
        case OpMove:
            summary.Moves++
        case OpCopy:
            summary.Copies++
        }
    }
    
    return summary
}

type HistorySummary struct {
    TotalOperations int `json:"totalOperations"`
    SuccessCount    int `json:"successCount"`
    FailureCount    int `json:"failureCount"`
    Creates         int `json:"creates"`
    Updates         int `json:"updates"`
    Deletes         int `json:"deletes"`
    Renames         int `json:"renames"`
    Moves           int `json:"moves"`
    Copies          int `json:"copies"`
}
```

---

## JSON Log Format

```json
{
  "taskId": 1,
  "taskName": "lowercase-filenames",
  "executedAt": "2026-01-29T10:30:00Z",
  "summary": {
    "totalOperations": 5,
    "successCount": 5,
    "failureCount": 0
  },
  "operations": [
    {
      "timestamp": "2026-01-29T10:30:01Z",
      "operation": "rename",
      "oldPath": "spec/README.md",
      "newPath": "spec/readme.md",
      "checksumBefore": "abc123...",
      "checksumAfter": "abc123...",
      "success": true
    },
    {
      "timestamp": "2026-01-29T10:30:02Z",
      "operation": "rename",
      "oldPath": "spec/CHANGELOG.md",
      "newPath": "spec/changelog.md",
      "checksumBefore": "def456...",
      "checksumAfter": "def456...",
      "success": true
    }
  ]
}
```

---

## TypeScript Types

```typescript
enum OperationType {
  Create = "create",
  Read = "read",
  Update = "update",
  Delete = "delete",
  Rename = "rename",
  Move = "move",
  Copy = "copy",
}

interface LogEntry {
  readonly taskId: number;
  readonly timestamp: Date;
  readonly operation: OperationType;
  readonly oldPath: string | null;
  readonly newPath: string | null;
  readonly fileSizeBytes: number | null;
  readonly checksumBefore: string | null;
  readonly checksumAfter: string | null;
  readonly success: boolean;
  readonly errorMessage: string | null;
  readonly metadata: Record<string, unknown> | null;
}

interface HistorySummary {
  readonly totalOperations: number;
  readonly successCount: number;
  readonly failureCount: number;
  readonly creates: number;
  readonly updates: number;
  readonly deletes: number;
  readonly renames: number;
  readonly moves: number;
  readonly copies: number;
}

interface HistoryLogFile {
  readonly taskId: number;
  readonly taskName: string;
  readonly executedAt: Date;
  readonly summary: HistorySummary;
  readonly operations: readonly LogEntry[];
}
```

---

## Integration with Generated Code

All generated Golang code includes this history logging pattern:

```go
// In generated main.go
func processFile(path string, config Config, logger *HistoryLogger) error {
    // Calculate checksum before operation
    checksumBefore, size, _ := logger.CalculateChecksumWithSize(path)
    
    // Perform operation
    newPath := strings.ToLower(path)
    err := os.Rename(path, newPath)
    
    // Log the operation
    logger.LogRename(path, newPath, err == nil, err)
    
    return err
}
```

---

## Related Specs

- [11-database-schema.md](./11-database-schema.md) — FilesystemHistory table
- [07-approval-workflow.md](./07-approval-workflow.md) — Audit trail integration
- [15-history-browser.md](./15-history-browser.md) — UI for viewing history
