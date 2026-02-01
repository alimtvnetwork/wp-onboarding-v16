# Database Migrations

**Version:** 1.0.0  
**Status:** Active  
**Updated:** 2026-01-28  

---

## Overview

Migration patterns and strategies for the Spec Management Software database using GORM AutoMigrate.

**Cross-References:**
- [Schema Definition](./01-schema.md)
- [Conventions](./04-conventions.md)

---

## Migration Strategy

### ORM-Based Migrations

All schema changes are managed through GORM's `AutoMigrate` function. This ensures:

- **Type Safety**: Schema defined in Go structs
- **Automatic DDL**: GORM generates appropriate SQL
- **Idempotent Operations**: Safe to run multiple times
- **No Raw SQL**: Maintains ORM-only policy

```go
func RunMigrations(db *gorm.DB) error {
    return db.AutoMigrate(
        // Core entities (User & Auth: 2)
        &User{},
        &Session{},
        
        // Project & Organization: 3
        &Project{},
        &ProjectMetadata{},
        &VectorIndexMetadata{},
        
        // File & Snapshots: 2
        &File{},
        &Snapshot{},
        
        // Configuration: 2
        &Config{},
        &ConfigSeedEvent{},
        
        // LLM Models: 2
        &ModelRegistry{},
        &ModelSlot{},
        
        // Prompt System: 3
        &PromptPreset{},
        &PromptPresetVersion{},
        &UserPromptOverride{},
        
        // Instructions: 5
        &Instruction{},
        &InstructionTask{},
        &FileChange{},
        &InstructionSegment{},
        &MemoryEntry{},
        
        // Inconsistency Analysis: 5
        &InconsistencyReport{},
        &InconsistencyIssue{},
        &ClarificationQuestion{},
        &ClarificationAnswer{},
        &RegenerationEvent{},
        
        // RAG System: 6
        &Artifact{},
        &Chunk{},
        &Embedding{},
        &RetrievalSession{},
        &RetrievalSessionChunk{},
        &PromotionEvent{},
        
        // Consistency Checker: 2
        &ConsistencyLoop{},
        &ConsistencyLoopIteration{},
    )
}
```

---

## Migration Order

Tables must be migrated in dependency order to satisfy foreign key constraints:

```
Phase 1: Independent Tables
├── User
├── Config
├── ModelRegistry
└── PromptPreset

Phase 2: User-Dependent Tables
├── Session (→ User)
├── Project (→ User)
├── PromptPresetVersion (→ PromptPreset)
└── UserPromptOverride (→ User, PromptPreset)

Phase 3: Project-Dependent Tables
├── ProjectMetadata (→ Project)
├── File (→ Project, File)
├── Snapshot (→ Project, User)
├── Instruction (→ Project, User, ModelRegistry)
└── Artifact (→ Project)

Phase 4: Instruction-Dependent Tables
├── InstructionTask (→ Instruction)
├── InconsistencyReport (→ Instruction)
└── RegenerationEvent (→ Instruction)

Phase 5: Deep Dependencies
├── InconsistencyIssue (→ InconsistencyReport)
├── ClarificationQuestion (→ InconsistencyIssue)
├── ClarificationAnswer (→ ClarificationQuestion)
├── Chunk (→ Artifact)
├── Embedding (→ Chunk)
└── RetrievalSessionChunk (→ RetrievalSession, Chunk)
```

---

## Initial Setup

### First-Time Database Creation

```go
func InitializeDatabase(dbPath string) (*gorm.DB, error) {
    db, err := gorm.Open(sqlite.Open(dbPath), &gorm.Config{
        Logger: logger.Default.LogMode(logger.Info),
    })
    if err != nil {
        return nil, fmt.Errorf("failed to connect database: %w", err)
    }
    
    // Enable foreign keys for SQLite
    db.Exec("PRAGMA foreign_keys = ON")
    
    // Run migrations
    if err := RunMigrations(db); err != nil {
        return nil, fmt.Errorf("failed to run migrations: %w", err)
    }
    
    return db, nil
}
```

### SQLite Pragmas

Apply these pragmas after connection for optimal performance:

```go
func ConfigureSQLite(db *gorm.DB) error {
    pragmas := []string{
        "PRAGMA foreign_keys = ON",
        "PRAGMA journal_mode = WAL",
        "PRAGMA synchronous = NORMAL",
        "PRAGMA cache_size = -64000",  // 64MB cache
        "PRAGMA temp_store = MEMORY",
    }
    
    for _, pragma := range pragmas {
        if err := db.Exec(pragma).Error; err != nil {
            return fmt.Errorf("failed to set pragma: %w", err)
        }
    }
    return nil
}
```

---

## Schema Evolution

### Adding New Columns

GORM AutoMigrate automatically adds new columns:

```go
// Before: User struct without Avatar
type User struct {
    BaseModel
    Username string `gorm:"type:text;not null"`
    Email    string `gorm:"type:text;not null"`
}

// After: Add Avatar column - just update struct and run AutoMigrate
type User struct {
    BaseModel
    Username  string  `gorm:"type:text;not null"`
    Email     string  `gorm:"type:text;not null"`
    AvatarUrl *string `gorm:"type:text"`  // New column
}
```

### Adding New Tables

Simply add the new model to the AutoMigrate call:

```go
// New table definition
type AuditLog struct {
    TimestampModel
    UserId    string `gorm:"type:text;index"`
    Action    string `gorm:"type:text;not null"`
    Resource  string `gorm:"type:text;not null"`
    Details   string `gorm:"type:text"`
}

// Add to migrations
db.AutoMigrate(&AuditLog{})
```

### Adding Indexes

Define indexes in GORM tags:

```go
type File struct {
    // Composite index
    ProjectId string `gorm:"type:text;index:idx_file_project_path"`
    Path      string `gorm:"type:text;index:idx_file_project_path"`
    
    // Single column index
    Name string `gorm:"type:text;index:idx_file_name"`
    
    // Unique index
    Slug string `gorm:"type:text;uniqueIndex:idx_file_slug"`
}
```

---

## Data Migrations

### Seeding Initial Data

Use GORM operations, never raw SQL:

```go
func SeedDefaultConfig(db *gorm.DB) error {
    defaults := []Config{
        {Key: "llama.server.path", Value: "/usr/local/bin/llama-server", Source: ConfigSourceSeed},
        {Key: "llama.server.port", Value: "8080", Source: ConfigSourceSeed},
        {Key: "app.theme.default", Value: "light", Source: ConfigSourceSeed},
    }
    
    for _, config := range defaults {
        // Only insert if not exists
        result := db.Where("key = ?", config.Key).FirstOrCreate(&config)
        if result.Error != nil {
            return result.Error
        }
    }
    return nil
}
```

### Data Transformations

For complex data migrations, use transactions:

```go
func MigrateProjectVisibility(db *gorm.DB) error {
    return db.Transaction(func(tx *gorm.DB) error {
        // Update all projects without visibility to 'user'
        return tx.Model(&Project{}).
            Where("visibility IS NULL OR visibility = ''").
            Update("visibility", VisibilityUser).Error
    })
}
```

---

## Rollback Strategy

### Pre-Migration Backup

Always backup before migrations in production:

```go
func BackupDatabase(dbPath string) (string, error) {
    backupPath := fmt.Sprintf("%s.backup.%d", dbPath, time.Now().Unix())
    
    src, err := os.Open(dbPath)
    if err != nil {
        return "", err
    }
    defer src.Close()
    
    dst, err := os.Create(backupPath)
    if err != nil {
        return "", err
    }
    defer dst.Close()
    
    _, err = io.Copy(dst, src)
    return backupPath, err
}
```

### Manual Rollback

For critical failures, restore from backup:

```bash
# Stop application
# Replace database with backup
cp spec-manager.db.backup.1706400000 spec-manager.db
# Restart application
```

---

## FTS5 Virtual Tables

The only exception to ORM-only policy. Required for full-text search:

```go
func CreateFTS5Tables(db *gorm.DB) error {
    // FTS5 requires raw SQL as GORM doesn't support virtual tables
    ftsSQL := `
        CREATE VIRTUAL TABLE IF NOT EXISTS file_content_fts USING fts5(
            file_id,
            content,
            tokenize='porter unicode61'
        );
    `
    return db.Exec(ftsSQL).Error
}
```

---

## Version Tracking

Track migration versions for debugging:

```go
type MigrationLog struct {
    Id        string    `gorm:"type:text;primaryKey"`
    Version   string    `gorm:"type:text;not null"`
    AppliedAt time.Time `gorm:"not null"`
    Duration  int64     `gorm:"not null"` // milliseconds
}

func LogMigration(db *gorm.DB, version string, duration time.Duration) error {
    return db.Create(&MigrationLog{
        Id:        uuid.New().String(),
        Version:   version,
        AppliedAt: time.Now(),
        Duration:  duration.Milliseconds(),
    }).Error
}
```

---

## Related Specs

- [Schema Definition](./01-schema.md) — Complete GORM models
- [Relationships](./03-relationships.md) — Foreign key constraints
- [Conventions](./04-conventions.md) — Naming standards
