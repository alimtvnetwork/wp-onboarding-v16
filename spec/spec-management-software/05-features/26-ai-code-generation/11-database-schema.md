# 11. Database Schema

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-29  

---

## Purpose

Define the SQLite database schema for storing generated code, execution history, and reusability metadata using PascalCase naming convention per project standards.

---

## Technology

- **Database:** SQLite (local-first, consistent with gsearch/brun)
- **ORM:** GORM
- **Naming:** PascalCase for tables and columns

---

## Schema Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                     TempCodingTasks                          │
├─────────────────────────────────────────────────────────────┤
│ Id              INTEGER PRIMARY KEY                          │
│ TaskName        TEXT NOT NULL UNIQUE                         │
│ Description     TEXT                                         │
│ GolangCode      TEXT NOT NULL                                │
│ FilePath        TEXT NOT NULL                                │
│ ComplexityScore INTEGER NOT NULL                             │
│ IsReusable      BOOLEAN DEFAULT true                         │
│ CreatedAt       DATETIME DEFAULT CURRENT_TIMESTAMP           │
│ LastExecutedAt  DATETIME                                     │
│ ExecutionCount  INTEGER DEFAULT 0                            │
│ SuccessCount    INTEGER DEFAULT 0                            │
│ FailureCount    INTEGER DEFAULT 0                            │
│ AvgDurationMs   INTEGER                                      │
│ Metadata        TEXT (JSON)                                  │
└─────────────────────────────────────────────────────────────┘
                              │
                              │ 1:N
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                        TaskTags                              │
├─────────────────────────────────────────────────────────────┤
│ Id              INTEGER PRIMARY KEY                          │
│ TaskId          INTEGER REFERENCES TempCodingTasks(Id)       │
│ TagName         TEXT NOT NULL                                │
│ CreatedAt       DATETIME DEFAULT CURRENT_TIMESTAMP           │
└─────────────────────────────────────────────────────────────┘
                              │
                              │ 1:N
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                    FilesystemHistory                         │
├─────────────────────────────────────────────────────────────┤
│ Id              INTEGER PRIMARY KEY                          │
│ TaskId          INTEGER REFERENCES TempCodingTasks(Id)       │
│ Timestamp       DATETIME DEFAULT CURRENT_TIMESTAMP           │
│ OperationType   TEXT NOT NULL                                │
│ OldPath         TEXT                                         │
│ NewPath         TEXT                                         │
│ FileSizeBytes   INTEGER                                      │
│ ChecksumBefore  TEXT                                         │
│ ChecksumAfter   TEXT                                         │
│ IsSuccess       BOOLEAN NOT NULL                             │
│ ErrorMessage    TEXT                                         │
│ Metadata        TEXT (JSON)                                  │
└─────────────────────────────────────────────────────────────┘
                              │
                              │ N:1
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                    TaskExecutionLog                          │
├─────────────────────────────────────────────────────────────┤
│ Id              INTEGER PRIMARY KEY                          │
│ TaskId          INTEGER REFERENCES TempCodingTasks(Id)       │
│ StartedAt       DATETIME NOT NULL                            │
│ CompletedAt     DATETIME                                     │
│ ExitCode        INTEGER                                      │
│ Stdout          TEXT                                         │
│ Stderr          TEXT                                         │
│ DurationMs      INTEGER                                      │
│ FilesAffected   INTEGER                                      │
│ WasDryRun       BOOLEAN DEFAULT false                        │
│ ApprovedBy      TEXT                                         │
│ ApprovedAt      DATETIME                                     │
└─────────────────────────────────────────────────────────────┘
```

---

## Table Definitions

### TempCodingTasks

Stores all generated Golang code for reusability.

```sql
CREATE TABLE TempCodingTasks (
    Id              INTEGER PRIMARY KEY AUTOINCREMENT,
    TaskName        TEXT NOT NULL UNIQUE,
    Description     TEXT,
    GolangCode      TEXT NOT NULL,
    FilePath        TEXT NOT NULL,
    ComplexityScore INTEGER NOT NULL,
    IsReusable      BOOLEAN DEFAULT 1,
    CreatedAt       DATETIME DEFAULT CURRENT_TIMESTAMP,
    LastExecutedAt  DATETIME,
    ExecutionCount  INTEGER DEFAULT 0,
    SuccessCount    INTEGER DEFAULT 0,
    FailureCount    INTEGER DEFAULT 0,
    AvgDurationMs   INTEGER,
    Metadata        TEXT
);

CREATE INDEX IdxTempCodingTasksTaskName ON TempCodingTasks(TaskName);
CREATE INDEX IdxTempCodingTasksCreatedAt ON TempCodingTasks(CreatedAt DESC);
CREATE INDEX IdxTempCodingTasksIsReusable ON TempCodingTasks(IsReusable) WHERE IsReusable = 1;
```

### TaskTags

Tag associations for task categorization.

```sql
CREATE TABLE TaskTags (
    Id        INTEGER PRIMARY KEY AUTOINCREMENT,
    TaskId    INTEGER NOT NULL REFERENCES TempCodingTasks(Id) ON DELETE CASCADE,
    TagName   TEXT NOT NULL,
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(TaskId, TagName)
);

CREATE INDEX IdxTaskTagsTaskId ON TaskTags(TaskId);
CREATE INDEX IdxTaskTagsTagName ON TaskTags(TagName);
```

### FilesystemHistory

Tracks every filesystem operation for audit trail.

```sql
CREATE TABLE FilesystemHistory (
    Id              INTEGER PRIMARY KEY AUTOINCREMENT,
    TaskId          INTEGER REFERENCES TempCodingTasks(Id) ON DELETE SET NULL,
    Timestamp       DATETIME DEFAULT CURRENT_TIMESTAMP,
    OperationType   TEXT NOT NULL,
    OldPath         TEXT,
    NewPath         TEXT,
    FileSizeBytes   INTEGER,
    ChecksumBefore  TEXT,
    ChecksumAfter   TEXT,
    IsSuccess       BOOLEAN NOT NULL,
    ErrorMessage    TEXT,
    Metadata        TEXT
);

CREATE INDEX IdxFilesystemHistoryTimestamp ON FilesystemHistory(Timestamp DESC);
CREATE INDEX IdxFilesystemHistoryTaskId ON FilesystemHistory(TaskId);
CREATE INDEX IdxFilesystemHistoryOperationType ON FilesystemHistory(OperationType);
```

### TaskExecutionLog

Detailed execution logs for debugging.

```sql
CREATE TABLE TaskExecutionLog (
    Id            INTEGER PRIMARY KEY AUTOINCREMENT,
    TaskId        INTEGER NOT NULL REFERENCES TempCodingTasks(Id) ON DELETE CASCADE,
    StartedAt     DATETIME NOT NULL,
    CompletedAt   DATETIME,
    ExitCode      INTEGER,
    Stdout        TEXT,
    Stderr        TEXT,
    DurationMs    INTEGER,
    FilesAffected INTEGER,
    WasDryRun     BOOLEAN DEFAULT 0,
    ApprovedBy    TEXT,
    ApprovedAt    DATETIME
);

CREATE INDEX IdxTaskExecutionLogTaskId ON TaskExecutionLog(TaskId);
CREATE INDEX IdxTaskExecutionLogStartedAt ON TaskExecutionLog(StartedAt DESC);
```

---

## GORM Models

```go
package models

import (
    "time"
    "gorm.io/gorm"
)

type TempCodingTask struct {
    Id              uint           `gorm:"primaryKey;column:Id"`
    TaskName        string         `gorm:"column:TaskName;uniqueIndex;not null"`
    Description     *string        `gorm:"column:Description"`
    GolangCode      string         `gorm:"column:GolangCode;not null"`
    FilePath        string         `gorm:"column:FilePath;not null"`
    ComplexityScore int            `gorm:"column:ComplexityScore;not null"`
    IsReusable      bool           `gorm:"column:IsReusable;default:true"`
    CreatedAt       time.Time      `gorm:"column:CreatedAt;autoCreateTime"`
    LastExecutedAt  *time.Time     `gorm:"column:LastExecutedAt"`
    ExecutionCount  int            `gorm:"column:ExecutionCount;default:0"`
    SuccessCount    int            `gorm:"column:SuccessCount;default:0"`
    FailureCount    int            `gorm:"column:FailureCount;default:0"`
    AvgDurationMs   *int           `gorm:"column:AvgDurationMs"`
    Metadata        *string        `gorm:"column:Metadata"`
    
    Tags            []TaskTag      `gorm:"foreignKey:TaskId"`
    History         []FilesystemHistory `gorm:"foreignKey:TaskId"`
    ExecutionLogs   []TaskExecutionLog  `gorm:"foreignKey:TaskId"`
}

func (TempCodingTask) TableName() string {
    return "TempCodingTasks"
}

type TaskTag struct {
    Id        uint      `gorm:"primaryKey;column:Id"`
    TaskId    uint      `gorm:"column:TaskId;not null"`
    TagName   string    `gorm:"column:TagName;not null"`
    CreatedAt time.Time `gorm:"column:CreatedAt;autoCreateTime"`
}

func (TaskTag) TableName() string {
    return "TaskTags"
}

type FilesystemHistory struct {
    Id              uint       `gorm:"primaryKey;column:Id"`
    TaskId          *uint      `gorm:"column:TaskId"`
    Timestamp       time.Time  `gorm:"column:Timestamp;autoCreateTime"`
    OperationType   string     `gorm:"column:OperationType;not null"`
    OldPath         *string    `gorm:"column:OldPath"`
    NewPath         *string    `gorm:"column:NewPath"`
    FileSizeBytes   *int64     `gorm:"column:FileSizeBytes"`
    ChecksumBefore  *string    `gorm:"column:ChecksumBefore"`
    ChecksumAfter   *string    `gorm:"column:ChecksumAfter"`
    IsSuccess       bool       `gorm:"column:IsSuccess;not null"`
    ErrorMessage    *string    `gorm:"column:ErrorMessage"`
    Metadata        *string    `gorm:"column:Metadata"`
}

func (FilesystemHistory) TableName() string {
    return "FilesystemHistory"
}

type TaskExecutionLog struct {
    Id            uint       `gorm:"primaryKey;column:Id"`
    TaskId        uint       `gorm:"column:TaskId;not null"`
    StartedAt     time.Time  `gorm:"column:StartedAt;not null"`
    CompletedAt   *time.Time `gorm:"column:CompletedAt"`
    ExitCode      *int       `gorm:"column:ExitCode"`
    Stdout        *string    `gorm:"column:Stdout"`
    Stderr        *string    `gorm:"column:Stderr"`
    DurationMs    *int       `gorm:"column:DurationMs"`
    FilesAffected *int       `gorm:"column:FilesAffected"`
    WasDryRun     bool       `gorm:"column:WasDryRun;default:false"`
    ApprovedBy    *string    `gorm:"column:ApprovedBy"`
    ApprovedAt    *time.Time `gorm:"column:ApprovedAt"`
}

func (TaskExecutionLog) TableName() string {
    return "TaskExecutionLog"
}
```

---

## TypeScript Types

```typescript
interface TempCodingTask {
  readonly id: number;
  readonly taskName: string;
  readonly description: string | null;
  readonly golangCode: string;
  readonly filePath: string;
  readonly complexityScore: number;
  readonly isReusable: boolean;
  readonly createdAt: Date;
  readonly lastExecutedAt: Date | null;
  readonly executionCount: number;
  readonly successCount: number;
  readonly failureCount: number;
  readonly avgDurationMs: number | null;
  readonly metadata: Record<string, unknown> | null;
  readonly tags: readonly TaskTag[];
}

interface TaskTag {
  readonly id: number;
  readonly taskId: number;
  readonly tagName: string;
  readonly createdAt: Date;
}

enum OperationType {
  Create = "create",
  Read = "read",
  Update = "update",
  Delete = "delete",
  Rename = "rename",
  Move = "move",
  Copy = "copy",
}

interface FilesystemHistoryEntry {
  readonly id: number;
  readonly taskId: number | null;
  readonly timestamp: Date;
  readonly operationType: OperationType;
  readonly oldPath: string | null;
  readonly newPath: string | null;
  readonly fileSizeBytes: number | null;
  readonly checksumBefore: string | null;
  readonly checksumAfter: string | null;
  readonly isSuccess: boolean;
  readonly errorMessage: string | null;
  readonly metadata: Record<string, unknown> | null;
}

interface TaskExecutionLogEntry {
  readonly id: number;
  readonly taskId: number;
  readonly startedAt: Date;
  readonly completedAt: Date | null;
  readonly exitCode: number | null;
  readonly stdout: string | null;
  readonly stderr: string | null;
  readonly durationMs: number | null;
  readonly filesAffected: number | null;
  readonly wasDryRun: boolean;
  readonly approvedBy: string | null;
  readonly approvedAt: Date | null;
}
```

---

## Query Examples

### Find Reusable Code by Tags

```sql
SELECT t.Id, t.TaskName, t.GolangCode,
       COUNT(tt.TagName) AS TagOverlap
FROM TempCodingTasks t
JOIN TaskTags tt ON t.Id = tt.TaskId
WHERE tt.TagName IN ('filesystem', 'rename', 'batch-operation')
  AND t.IsReusable = 1
GROUP BY t.Id
ORDER BY TagOverlap DESC, t.SuccessCount DESC
LIMIT 5;
```

### Get Task History with Operations

```sql
SELECT t.TaskName, 
       COUNT(h.Id) AS OperationCount,
       SUM(CASE WHEN h.IsSuccess THEN 1 ELSE 0 END) AS SuccessCount
FROM TempCodingTasks t
LEFT JOIN FilesystemHistory h ON t.Id = h.TaskId
GROUP BY t.Id
ORDER BY t.LastExecutedAt DESC;
```

### Recent Executions with Details

```sql
SELECT t.TaskName, 
       e.StartedAt, 
       e.DurationMs,
       e.ExitCode,
       e.FilesAffected,
       e.WasDryRun
FROM TaskExecutionLog e
JOIN TempCodingTasks t ON e.TaskId = t.Id
ORDER BY e.StartedAt DESC
LIMIT 20;
```

---

## Migration

```go
func Migrate(db *gorm.DB) error {
    return db.AutoMigrate(
        &TempCodingTask{},
        &TaskTag{},
        &FilesystemHistory{},
        &TaskExecutionLog{},
    )
}
```

---

## Related Specs

- [12-tag-system.md](./12-tag-system.md) — Tag taxonomy
- [08-history-logger.md](./08-history-logger.md) — Operation logging
- [05-task-matcher.md](./05-task-matcher.md) — Reusability queries
