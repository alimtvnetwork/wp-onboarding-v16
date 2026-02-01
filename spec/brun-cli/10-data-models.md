# Data Models

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

GORM entity definitions for optional run history persistence in SQLite.

**Cross-References:**
- [Core Architecture](./01-core-architecture.md)
- [Error Handling](./06-error-handling.md)
- [Database Design](../../07-database-design/00-overview.md)

---

## Entity Relationship Diagram

```
┌─────────────────────┐       ┌─────────────────────┐
│     BuildRun        │       │    BuildError       │
├─────────────────────┤       ├─────────────────────┤
│ ID                  │──1:N──│ ID                  │
│ RunID               │       │ BuildRunID          │
│ ProfileName         │       │ File                │
│ Runtime             │       │ Line                │
│ Command             │       │ Column              │
│ WorkDir             │       │ Message             │
│ ExitCode            │       │ Severity            │
│ Success             │       │ Code                │
│ StartTime           │       │ StackTrace          │
│ EndTime             │       │ CreatedAt           │
│ Duration            │       └─────────────────────┘
│ Port                │
│ LogPath             │
│ CreatedAt           │
└─────────────────────┘
         │
         │
         1:N
         │
         ▼
┌─────────────────────┐
│   AssetOperation    │
├─────────────────────┤
│ ID                  │
│ BuildRunID          │
│ Source              │
│ Destination         │
│ Mode                │
│ FilesCopied         │
│ BytesCopied         │
│ Duration            │
│ Success             │
│ CreatedAt           │
└─────────────────────┘
```

---

## GORM Models

### BuildRun

```go
type BuildRun struct {
    ID          uint           `gorm:"primaryKey"`
    RunID       string         `gorm:"uniqueIndex;size:50;not null"`
    ProfileName string         `gorm:"size:100;index"`
    Runtime     string         `gorm:"size:20;not null"` // powershell, nodejs, golang
    Command     string         `gorm:"size:500"`
    WorkDir     string         `gorm:"size:500"`
    ExitCode    int            `gorm:"not null;default:0"`
    Success     bool           `gorm:"not null;default:false"`
    Stdout      string         `gorm:"type:text"`
    Stderr      string         `gorm:"type:text"`
    StartTime   time.Time      `gorm:"not null"`
    EndTime     time.Time      `gorm:"not null"`
    Duration    int64          `gorm:"not null"` // milliseconds
    Port        int            `gorm:"default:0"`
    LogPath     string         `gorm:"size:500"`
    CreatedAt   time.Time      `gorm:"autoCreateTime"`
    
    // Relationships
    Errors     []BuildError     `gorm:"foreignKey:BuildRunID;constraint:OnDelete:CASCADE"`
    Assets     []AssetOperation `gorm:"foreignKey:BuildRunID;constraint:OnDelete:CASCADE"`
}

func (BuildRun) TableName() string {
    return "build_runs"
}
```

### BuildError

```go
type BuildError struct {
    ID          uint      `gorm:"primaryKey"`
    BuildRunID  uint      `gorm:"index;not null"`
    File        string    `gorm:"size:500"`
    Line        int       `gorm:"default:0"`
    Column      int       `gorm:"default:0"`
    Message     string    `gorm:"size:2000;not null"`
    Severity    string    `gorm:"size:20;not null"` // error, warning, info
    Code        string    `gorm:"size:50"`          // TS2304, ESLint rule, etc.
    StackTrace  string    `gorm:"type:text"`
    Context     string    `gorm:"size:500"`         // Source code context
    CreatedAt   time.Time `gorm:"autoCreateTime"`
    
    // Relationship
    BuildRun BuildRun `gorm:"foreignKey:BuildRunID"`
}

func (BuildError) TableName() string {
    return "build_errors"
}
```

### AssetOperation

```go
type AssetOperation struct {
    ID          uint      `gorm:"primaryKey"`
    BuildRunID  uint      `gorm:"index;not null"`
    Source      string    `gorm:"size:500;not null"`
    Destination string    `gorm:"size:500;not null"`
    Mode        string    `gorm:"size:20;not null"` // copy, clear-copy, override, skip-existing
    FilesCopied int       `gorm:"not null;default:0"`
    FilesSkipped int      `gorm:"not null;default:0"`
    BytesCopied int64     `gorm:"not null;default:0"`
    Duration    int64     `gorm:"not null;default:0"` // milliseconds
    Success     bool      `gorm:"not null;default:false"`
    ErrorMsg    string    `gorm:"size:500"`
    CreatedAt   time.Time `gorm:"autoCreateTime"`
    
    // Relationship
    BuildRun BuildRun `gorm:"foreignKey:BuildRunID"`
}

func (AssetOperation) TableName() string {
    return "asset_operations"
}
```

### PortCheck

```go
type PortCheck struct {
    ID          uint      `gorm:"primaryKey"`
    BuildRunID  uint      `gorm:"index"`
    Port        int       `gorm:"not null"`
    Available   bool      `gorm:"not null"`
    Reason      string    `gorm:"size:200"`
    ProcessName string    `gorm:"size:100"`
    ProcessPID  int       `gorm:"default:0"`
    CheckedAt   time.Time `gorm:"not null"`
    CreatedAt   time.Time `gorm:"autoCreateTime"`
}

func (PortCheck) TableName() string {
    return "port_checks"
}
```

---

## Database Repository

```go
type BuildRunRepository struct {
    db *gorm.DB
}

func NewBuildRunRepository(db *gorm.DB) *BuildRunRepository {
    return &BuildRunRepository{db: db}
}

func (r *BuildRunRepository) Create(run *BuildRun) error {
    return r.db.Create(run).Error
}

func (r *BuildRunRepository) GetByRunID(runID string) (*BuildRun, error) {
    var run BuildRun
    err := r.db.Preload("Errors").Preload("Assets").
        Where("run_id = ?", runID).
        First(&run).Error
    if err != nil {
        return nil, err
    }
    return &run, nil
}

func (r *BuildRunRepository) GetRecent(limit int) ([]BuildRun, error) {
    var runs []BuildRun
    err := r.db.Preload("Errors").
        Order("created_at DESC").
        Limit(limit).
        Find(&runs).Error
    return runs, err
}

func (r *BuildRunRepository) GetByProfile(profileName string, limit int) ([]BuildRun, error) {
    var runs []BuildRun
    err := r.db.Where("profile_name = ?", profileName).
        Order("created_at DESC").
        Limit(limit).
        Find(&runs).Error
    return runs, err
}

func (r *BuildRunRepository) GetFailedRuns(since time.Time) ([]BuildRun, error) {
    var runs []BuildRun
    err := r.db.Preload("Errors").
        Where("success = ? AND created_at > ?", false, since).
        Order("created_at DESC").
        Find(&runs).Error
    return runs, err
}

func (r *BuildRunRepository) DeleteOldRuns(keepCount int) error {
    // Get IDs to keep
    var keepIDs []uint
    r.db.Model(&BuildRun{}).
        Order("created_at DESC").
        Limit(keepCount).
        Pluck("id", &keepIDs)
    
    // Delete older runs (cascade deletes errors and assets)
    return r.db.Where("id NOT IN ?", keepIDs).Delete(&BuildRun{}).Error
}

func (r *BuildRunRepository) GetStatistics(since time.Time) (*BuildStatistics, error) {
    var stats BuildStatistics
    
    r.db.Model(&BuildRun{}).
        Where("created_at > ?", since).
        Count(&stats.TotalRuns)
    
    r.db.Model(&BuildRun{}).
        Where("created_at > ? AND success = ?", since, true).
        Count(&stats.SuccessfulRuns)
    
    r.db.Model(&BuildRun{}).
        Where("created_at > ? AND success = ?", since, false).
        Count(&stats.FailedRuns)
    
    r.db.Model(&BuildRun{}).
        Where("created_at > ?", since).
        Select("AVG(duration)").
        Scan(&stats.AvgDuration)
    
    return &stats, nil
}

type BuildStatistics struct {
    TotalRuns      int64   `json:"totalRuns"`
    SuccessfulRuns int64   `json:"successfulRuns"`
    FailedRuns     int64   `json:"failedRuns"`
    AvgDuration    float64 `json:"avgDurationMs"`
}
```

---

## Database Initialization

```go
func InitDatabase(dbPath string) (*gorm.DB, error) {
    db, err := gorm.Open(sqlite.Open(dbPath), &gorm.Config{
        Logger: logger.Default.LogMode(logger.Warn),
    })
    if err != nil {
        return nil, fmt.Errorf("failed to open database: %w", err)
    }
    
    // Auto migrate
    err = db.AutoMigrate(
        &BuildRun{},
        &BuildError{},
        &AssetOperation{},
        &PortCheck{},
    )
    if err != nil {
        return nil, fmt.Errorf("failed to migrate database: %w", err)
    }
    
    // Create indexes
    db.Exec("CREATE INDEX IF NOT EXISTS idx_build_runs_profile ON build_runs(profile_name)")
    db.Exec("CREATE INDEX IF NOT EXISTS idx_build_runs_success ON build_runs(success)")
    db.Exec("CREATE INDEX IF NOT EXISTS idx_build_errors_file ON build_errors(file)")
    
    return db, nil
}
```

---

## Configuration

```json
{
  "database": {
    "enabled": true,
    "path": "./brun.db",
    "keepRuns": 100,
    "vacuumInterval": "24h"
  }
}
```

---

## See Also

- [Core Architecture](./01-core-architecture.md)
- [Error Handling](./06-error-handling.md)
- [Build Profiles](./07-build-profiles.md)
