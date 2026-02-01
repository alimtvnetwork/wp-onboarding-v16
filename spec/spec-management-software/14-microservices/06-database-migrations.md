# 06. Shared Database Migrations

## Overview
Centralized SQLite schema migrations across all microservices, implementing the four-tier database architecture with versioned migration scripts.

**Error Code Range**: 9xxx (Migration/Schema errors)

---

## 6.1 Four-Tier Database Architecture

### Database Hierarchy
```
data/
├── settings.db              # Tier 1: Global configuration
├── projects.db              # Tier 2: Project index & routing
├── chronicle.db             # Tier 2: Global audit logs
├── scout.db                 # Tier 2: Global search registry
└── projects/
    └── {project-id}/
        ├── project.db       # Tier 3: Project specifications
        ├── history.db       # Tier 3: Project version control
        ├── search.db        # Tier 3: Project search index
        └── conversations/
            └── {conv-id}.db # Tier 4: Conversation context
```

### Architectural Constraints
| Constraint | Enforcement |
|------------|-------------|
| No cross-database JOINs | Application-layer aggregation only |
| PascalCase naming | Tables and fields |
| UUID primary keys | `TEXT PRIMARY KEY` format |
| Timestamps as ISO8601 | `TEXT` with UTC timezone |
| JSON fields validated | Application-layer schema validation |

---

## 6.2 Migration System

### Migration Table Schema
Each database contains a migrations tracking table:

```sql
-- Applied to ALL databases
CREATE TABLE IF NOT EXISTS Migrations (
    Id              INTEGER PRIMARY KEY AUTOINCREMENT,
    Version         TEXT NOT NULL UNIQUE,
    Name            TEXT NOT NULL,
    AppliedAt       TEXT NOT NULL DEFAULT (datetime('now')),
    Checksum        TEXT NOT NULL,
    ExecutionTimeMs INTEGER NOT NULL
);

CREATE INDEX idx_migrations_version ON Migrations(Version);
```

### Migration File Convention
```
migrations/
├── settings/
│   ├── 001_initial_schema.sql
│   └── 002_add_ai_providers.sql
├── projects/
│   ├── 001_initial_schema.sql
│   └── 002_add_soft_delete.sql
├── project/
│   ├── 001_initial_schema.sql
│   ├── 002_add_cross_references.sql
│   └── 003_add_file_hash.sql
├── chronicle/
│   ├── 001_initial_schema.sql
│   └── 002_add_diff_cache.sql
├── history/
│   ├── 001_initial_schema.sql
│   └── 002_add_rollback_tracking.sql
├── scout/
│   ├── 001_initial_schema.sql
│   └── 002_add_embedding_providers.sql
├── search/
│   ├── 001_initial_schema.sql
│   └── 002_add_fts5_triggers.sql
└── conversation/
    └── 001_initial_schema.sql
```

### Migration Naming Convention
```
{version}_{description}.sql

Examples:
  001_initial_schema.sql
  002_add_user_preferences.sql
  003_migrate_legacy_paths.sql
```

---

## 6.3 Tier 1: Settings Database

### File: `settings.db`
Global application configuration with seedable defaults.

```sql
-- migrations/settings/001_initial_schema.sql
-- Version: 001
-- Description: Initial settings schema

CREATE TABLE IF NOT EXISTS Settings (
    Key         TEXT PRIMARY KEY,
    Value       TEXT NOT NULL,
    Type        TEXT NOT NULL CHECK (Type IN ('string', 'number', 'boolean', 'json')),
    Category    TEXT NOT NULL DEFAULT 'general',
    Description TEXT,
    IsSecret    INTEGER NOT NULL DEFAULT 0,
    CreatedAt   TEXT NOT NULL DEFAULT (datetime('now')),
    UpdatedAt   TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX idx_settings_category ON Settings(Category);

-- Default seed data
INSERT OR IGNORE INTO Settings (Key, Value, Type, Category, Description) VALUES
    ('app.version', '1.0.0', 'string', 'system', 'Application version'),
    ('app.dataRoot', './data', 'string', 'paths', 'Root data directory'),
    ('app.uploadRoot', './.upload', 'string', 'paths', 'Upload directory root'),
    ('gateway.port', '8080', 'number', 'network', 'Gateway HTTP port'),
    ('gateway.timeout', '30', 'number', 'network', 'Request timeout in seconds'),
    ('chronicle.autoCommit', 'true', 'boolean', 'versioning', 'Enable auto-commit on save'),
    ('chronicle.gitSync', 'false', 'boolean', 'versioning', 'Sync with local Git repository'),
    ('scout.ftsWeight', '0.3', 'number', 'search', 'FTS5 weight in hybrid search'),
    ('scout.vectorWeight', '0.7', 'number', 'search', 'Vector weight in hybrid search'),
    ('scout.chunkSize', '512', 'number', 'search', 'Default chunk size in tokens'),
    ('scout.chunkOverlap', '64', 'number', 'search', 'Chunk overlap in tokens');
```

```sql
-- migrations/settings/002_add_ai_providers.sql
-- Version: 002
-- Description: AI provider configuration

CREATE TABLE IF NOT EXISTS AIProviders (
    Id          TEXT PRIMARY KEY,
    Name        TEXT NOT NULL,
    Type        TEXT NOT NULL CHECK (Type IN ('openai', 'anthropic', 'ollama', 'custom')),
    BaseUrl     TEXT NOT NULL,
    ApiKey      TEXT,  -- Encrypted at rest
    Models      TEXT NOT NULL DEFAULT '[]',  -- JSON array
    IsDefault   INTEGER NOT NULL DEFAULT 0,
    IsEnabled   INTEGER NOT NULL DEFAULT 1,
    CreatedAt   TEXT NOT NULL DEFAULT (datetime('now')),
    UpdatedAt   TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS EmbeddingModels (
    Id          TEXT PRIMARY KEY,
    ProviderId  TEXT NOT NULL REFERENCES AIProviders(Id) ON DELETE CASCADE,
    Name        TEXT NOT NULL,
    Dimensions  INTEGER NOT NULL,
    MaxTokens   INTEGER NOT NULL,
    IsDefault   INTEGER NOT NULL DEFAULT 0,
    CreatedAt   TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX idx_embedding_models_provider ON EmbeddingModels(ProviderId);
```

---

## 6.4 Tier 2: Global Databases

### File: `projects.db`
Project index and routing (SpecManager service).

```sql
-- migrations/projects/001_initial_schema.sql
-- Version: 001
-- Description: Initial projects index schema

CREATE TABLE IF NOT EXISTS Projects (
    Id          TEXT PRIMARY KEY,
    Name        TEXT NOT NULL,
    Slug        TEXT NOT NULL UNIQUE,
    Description TEXT,
    RootPath    TEXT NOT NULL,
    Status      TEXT NOT NULL DEFAULT 'active' CHECK (Status IN ('active', 'archived', 'deleted')),
    CreatedAt   TEXT NOT NULL DEFAULT (datetime('now')),
    UpdatedAt   TEXT NOT NULL DEFAULT (datetime('now')),
    DeletedAt   TEXT
);

CREATE INDEX idx_projects_slug ON Projects(Slug);
CREATE INDEX idx_projects_status ON Projects(Status);
CREATE INDEX idx_projects_deleted ON Projects(DeletedAt) WHERE DeletedAt IS NOT NULL;
```

```sql
-- migrations/projects/002_add_soft_delete.sql
-- Version: 002
-- Description: Soft delete with retention tracking

ALTER TABLE Projects ADD COLUMN RetentionDays INTEGER DEFAULT 32;
ALTER TABLE Projects ADD COLUMN PurgeAt TEXT;

CREATE INDEX idx_projects_purge ON Projects(PurgeAt) WHERE PurgeAt IS NOT NULL;
```

### File: `chronicle.db`
Global audit logs (Chronicle service).

```sql
-- migrations/chronicle/001_initial_schema.sql
-- Version: 001
-- Description: Initial global audit schema

CREATE TABLE IF NOT EXISTS AuditLog (
    Id          TEXT PRIMARY KEY,
    Timestamp   TEXT NOT NULL DEFAULT (datetime('now')),
    ServiceName TEXT NOT NULL,
    Action      TEXT NOT NULL,
    EntityType  TEXT NOT NULL,
    EntityId    TEXT NOT NULL,
    UserId      TEXT,
    Details     TEXT,  -- JSON
    IpAddress   TEXT,
    UserAgent   TEXT
);

CREATE INDEX idx_audit_timestamp ON AuditLog(Timestamp DESC);
CREATE INDEX idx_audit_service ON AuditLog(ServiceName, Action);
CREATE INDEX idx_audit_entity ON AuditLog(EntityType, EntityId);
CREATE INDEX idx_audit_user ON AuditLog(UserId) WHERE UserId IS NOT NULL;

CREATE TABLE IF NOT EXISTS ServiceEvents (
    Id          TEXT PRIMARY KEY,
    Timestamp   TEXT NOT NULL DEFAULT (datetime('now')),
    ServiceName TEXT NOT NULL,
    EventType   TEXT NOT NULL CHECK (EventType IN ('startup', 'shutdown', 'error', 'warning', 'info')),
    Message     TEXT NOT NULL,
    Metadata    TEXT,  -- JSON
    StackTrace  TEXT
);

CREATE INDEX idx_service_events_time ON ServiceEvents(Timestamp DESC);
CREATE INDEX idx_service_events_type ON ServiceEvents(ServiceName, EventType);
```

### File: `scout.db`
Global search registry (Scout service).

```sql
-- migrations/scout/001_initial_schema.sql
-- Version: 001
-- Description: Initial global search registry

CREATE TABLE IF NOT EXISTS ProjectRegistry (
    ProjectId       TEXT PRIMARY KEY,
    LastIndexedAt   TEXT,
    ChunkCount      INTEGER NOT NULL DEFAULT 0,
    EmbeddingModel  TEXT NOT NULL,
    IndexVersion    INTEGER NOT NULL DEFAULT 1,
    Status          TEXT NOT NULL DEFAULT 'pending' CHECK (Status IN ('pending', 'indexing', 'ready', 'error')),
    ErrorMessage    TEXT,
    CreatedAt       TEXT NOT NULL DEFAULT (datetime('now')),
    UpdatedAt       TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX idx_project_registry_status ON ProjectRegistry(Status);

CREATE TABLE IF NOT EXISTS ModelRegistry (
    Id          TEXT PRIMARY KEY,
    Provider    TEXT NOT NULL,
    ModelName   TEXT NOT NULL,
    Dimensions  INTEGER NOT NULL,
    MaxTokens   INTEGER NOT NULL,
    IsActive    INTEGER NOT NULL DEFAULT 1,
    CreatedAt   TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE UNIQUE INDEX idx_model_registry_unique ON ModelRegistry(Provider, ModelName);
```

---

## 6.5 Tier 3: Per-Project Databases

### File: `{project-id}/project.db`
Project specifications (SpecManager service).

```sql
-- migrations/project/001_initial_schema.sql
-- Version: 001
-- Description: Initial project schema

CREATE TABLE IF NOT EXISTS Folders (
    Id          TEXT PRIMARY KEY,
    ParentId    TEXT REFERENCES Folders(Id) ON DELETE CASCADE,
    Name        TEXT NOT NULL,
    SortOrder   INTEGER NOT NULL DEFAULT 0,
    CreatedAt   TEXT NOT NULL DEFAULT (datetime('now')),
    UpdatedAt   TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX idx_folders_parent ON Folders(ParentId);

CREATE TABLE IF NOT EXISTS Specs (
    Id          TEXT PRIMARY KEY,
    FolderId    TEXT REFERENCES Folders(Id) ON DELETE SET NULL,
    Title       TEXT NOT NULL,
    FilePath    TEXT NOT NULL UNIQUE,  -- Relative path from project root
    FileHash    TEXT NOT NULL,         -- SHA256 of file content
    Status      TEXT NOT NULL DEFAULT 'draft' CHECK (Status IN ('draft', 'review', 'approved', 'archived')),
    SortOrder   INTEGER NOT NULL DEFAULT 0,
    CreatedAt   TEXT NOT NULL DEFAULT (datetime('now')),
    UpdatedAt   TEXT NOT NULL DEFAULT (datetime('now')),
    DeletedAt   TEXT
);

CREATE INDEX idx_specs_folder ON Specs(FolderId);
CREATE INDEX idx_specs_status ON Specs(Status);
CREATE INDEX idx_specs_path ON Specs(FilePath);
CREATE INDEX idx_specs_deleted ON Specs(DeletedAt) WHERE DeletedAt IS NOT NULL;

CREATE TABLE IF NOT EXISTS Tags (
    Id          TEXT PRIMARY KEY,
    Name        TEXT NOT NULL UNIQUE,
    Color       TEXT NOT NULL DEFAULT '#6366f1',
    CreatedAt   TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS SpecTags (
    SpecId      TEXT NOT NULL REFERENCES Specs(Id) ON DELETE CASCADE,
    TagId       TEXT NOT NULL REFERENCES Tags(Id) ON DELETE CASCADE,
    PRIMARY KEY (SpecId, TagId)
);

CREATE INDEX idx_spec_tags_tag ON SpecTags(TagId);
```

```sql
-- migrations/project/002_add_cross_references.sql
-- Version: 002
-- Description: Cross-reference tracking between specs

CREATE TABLE IF NOT EXISTS CrossReferences (
    Id          TEXT PRIMARY KEY,
    SourceId    TEXT NOT NULL REFERENCES Specs(Id) ON DELETE CASCADE,
    TargetId    TEXT NOT NULL REFERENCES Specs(Id) ON DELETE CASCADE,
    LinkText    TEXT NOT NULL,
    LineNumber  INTEGER NOT NULL,
    IsValid     INTEGER NOT NULL DEFAULT 1,
    CreatedAt   TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE(SourceId, TargetId, LineNumber)
);

CREATE INDEX idx_crossref_source ON CrossReferences(SourceId);
CREATE INDEX idx_crossref_target ON CrossReferences(TargetId);
CREATE INDEX idx_crossref_valid ON CrossReferences(IsValid) WHERE IsValid = 0;
```

```sql
-- migrations/project/003_add_file_hash.sql
-- Version: 003
-- Description: Add file size and metadata tracking

ALTER TABLE Specs ADD COLUMN FileSize INTEGER NOT NULL DEFAULT 0;
ALTER TABLE Specs ADD COLUMN Metadata TEXT;  -- JSON for frontmatter

CREATE TABLE IF NOT EXISTS FileRegistry (
    FilePath    TEXT PRIMARY KEY,
    FileHash    TEXT NOT NULL,
    FileSize    INTEGER NOT NULL,
    MimeType    TEXT NOT NULL DEFAULT 'text/markdown',
    LastScanned TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX idx_file_registry_hash ON FileRegistry(FileHash);
```

### File: `{project-id}/history.db`
Version control history (Chronicle service).

```sql
-- migrations/history/001_initial_schema.sql
-- Version: 001
-- Description: Initial version history schema

CREATE TABLE IF NOT EXISTS Commits (
    Id          TEXT PRIMARY KEY,
    ParentId    TEXT REFERENCES Commits(Id),
    Message     TEXT NOT NULL,
    Author      TEXT NOT NULL DEFAULT 'system',
    Timestamp   TEXT NOT NULL DEFAULT (datetime('now')),
    IsAutoCommit INTEGER NOT NULL DEFAULT 0,
    Metadata    TEXT  -- JSON
);

CREATE INDEX idx_commits_parent ON Commits(ParentId);
CREATE INDEX idx_commits_time ON Commits(Timestamp DESC);
CREATE INDEX idx_commits_auto ON Commits(IsAutoCommit);

CREATE TABLE IF NOT EXISTS FileVersions (
    Id          TEXT PRIMARY KEY,
    CommitId    TEXT NOT NULL REFERENCES Commits(Id) ON DELETE CASCADE,
    FilePath    TEXT NOT NULL,
    Operation   TEXT NOT NULL CHECK (Operation IN ('create', 'modify', 'delete', 'rename')),
    OldPath     TEXT,  -- For rename operations
    ContentHash TEXT NOT NULL,
    Snapshot    TEXT,  -- Full content for restoration
    CreatedAt   TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX idx_file_versions_commit ON FileVersions(CommitId);
CREATE INDEX idx_file_versions_path ON FileVersions(FilePath);
CREATE INDEX idx_file_versions_hash ON FileVersions(ContentHash);

CREATE TABLE IF NOT EXISTS Branches (
    Id          TEXT PRIMARY KEY,
    Name        TEXT NOT NULL UNIQUE,
    HeadCommit  TEXT REFERENCES Commits(Id),
    IsDefault   INTEGER NOT NULL DEFAULT 0,
    CreatedAt   TEXT NOT NULL DEFAULT (datetime('now')),
    UpdatedAt   TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Default main branch
INSERT OR IGNORE INTO Branches (Id, Name, IsDefault) VALUES ('br_main', 'main', 1);
```

```sql
-- migrations/history/002_add_diff_cache.sql
-- Version: 002
-- Description: Cached diff results for performance

CREATE TABLE IF NOT EXISTS DiffCache (
    Id              TEXT PRIMARY KEY,
    FileVersionId   TEXT NOT NULL REFERENCES FileVersions(Id) ON DELETE CASCADE,
    PreviousHash    TEXT NOT NULL,
    CurrentHash     TEXT NOT NULL,
    UnifiedDiff     TEXT NOT NULL,
    AddedLines      INTEGER NOT NULL DEFAULT 0,
    RemovedLines    INTEGER NOT NULL DEFAULT 0,
    CreatedAt       TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE UNIQUE INDEX idx_diff_cache_hashes ON DiffCache(PreviousHash, CurrentHash);
CREATE INDEX idx_diff_cache_version ON DiffCache(FileVersionId);

CREATE TABLE IF NOT EXISTS RollbackLog (
    Id              TEXT PRIMARY KEY,
    TargetCommitId  TEXT NOT NULL REFERENCES Commits(Id),
    RolledBackAt    TEXT NOT NULL DEFAULT (datetime('now')),
    Reason          TEXT,
    RestoredFiles   TEXT NOT NULL,  -- JSON array of file paths
    CreatedBy       TEXT NOT NULL DEFAULT 'system'
);

CREATE INDEX idx_rollback_time ON RollbackLog(RolledBackAt DESC);
```

### File: `{project-id}/search.db`
Search index (Scout service).

```sql
-- migrations/search/001_initial_schema.sql
-- Version: 001
-- Description: Initial search index schema

-- FTS5 virtual table for full-text search
CREATE VIRTUAL TABLE IF NOT EXISTS ChunksFTS USING fts5(
    ChunkId,
    Content,
    FilePath,
    Headings,
    tokenize='porter unicode61'
);

CREATE TABLE IF NOT EXISTS Chunks (
    Id          TEXT PRIMARY KEY,
    FilePath    TEXT NOT NULL,
    FileHash    TEXT NOT NULL,
    ChunkIndex  INTEGER NOT NULL,
    StartLine   INTEGER NOT NULL,
    EndLine     INTEGER NOT NULL,
    Content     TEXT NOT NULL,
    Headings    TEXT,  -- JSON array of ancestor headings
    TokenCount  INTEGER NOT NULL,
    CreatedAt   TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE(FilePath, ChunkIndex)
);

CREATE INDEX idx_chunks_file ON Chunks(FilePath);
CREATE INDEX idx_chunks_hash ON Chunks(FileHash);

CREATE TABLE IF NOT EXISTS Embeddings (
    ChunkId     TEXT PRIMARY KEY REFERENCES Chunks(Id) ON DELETE CASCADE,
    ModelId     TEXT NOT NULL,
    Vector      BLOB NOT NULL,  -- Float32 array
    Dimensions  INTEGER NOT NULL,
    CreatedAt   TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX idx_embeddings_model ON Embeddings(ModelId);
```

```sql
-- migrations/search/002_add_fts5_triggers.sql
-- Version: 002
-- Description: FTS5 synchronization triggers

-- Trigger: Insert into FTS on chunk creation
CREATE TRIGGER IF NOT EXISTS chunks_fts_insert
AFTER INSERT ON Chunks
BEGIN
    INSERT INTO ChunksFTS (ChunkId, Content, FilePath, Headings)
    VALUES (NEW.Id, NEW.Content, NEW.FilePath, NEW.Headings);
END;

-- Trigger: Update FTS on chunk modification
CREATE TRIGGER IF NOT EXISTS chunks_fts_update
AFTER UPDATE ON Chunks
BEGIN
    DELETE FROM ChunksFTS WHERE ChunkId = OLD.Id;
    INSERT INTO ChunksFTS (ChunkId, Content, FilePath, Headings)
    VALUES (NEW.Id, NEW.Content, NEW.FilePath, NEW.Headings);
END;

-- Trigger: Delete from FTS on chunk removal
CREATE TRIGGER IF NOT EXISTS chunks_fts_delete
AFTER DELETE ON Chunks
BEGIN
    DELETE FROM ChunksFTS WHERE ChunkId = OLD.Id;
END;

-- Search statistics table
CREATE TABLE IF NOT EXISTS SearchStats (
    Id          TEXT PRIMARY KEY,
    Query       TEXT NOT NULL,
    ResultCount INTEGER NOT NULL,
    DurationMs  INTEGER NOT NULL,
    SearchType  TEXT NOT NULL CHECK (SearchType IN ('fts', 'vector', 'hybrid')),
    CreatedAt   TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX idx_search_stats_time ON SearchStats(CreatedAt DESC);
```

---

## 6.6 Tier 4: Conversation Databases

### File: `{project-id}/conversations/{conv-id}.db`
Isolated conversation context (AI-Bridge service).

```sql
-- migrations/conversation/001_initial_schema.sql
-- Version: 001
-- Description: Conversation history and RAG context

CREATE TABLE IF NOT EXISTS Messages (
    Id          TEXT PRIMARY KEY,
    Role        TEXT NOT NULL CHECK (Role IN ('user', 'assistant', 'system')),
    Content     TEXT NOT NULL,
    TokenCount  INTEGER NOT NULL DEFAULT 0,
    Metadata    TEXT,  -- JSON (model, temperature, etc.)
    CreatedAt   TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX idx_messages_time ON Messages(CreatedAt);

CREATE TABLE IF NOT EXISTS RAGContext (
    Id          TEXT PRIMARY KEY,
    MessageId   TEXT NOT NULL REFERENCES Messages(Id) ON DELETE CASCADE,
    ChunkId     TEXT NOT NULL,
    FilePath    TEXT NOT NULL,
    Content     TEXT NOT NULL,
    Score       REAL NOT NULL,
    SearchType  TEXT NOT NULL CHECK (SearchType IN ('fts', 'vector', 'hybrid')),
    CreatedAt   TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX idx_rag_context_message ON RAGContext(MessageId);
CREATE INDEX idx_rag_context_chunk ON RAGContext(ChunkId);

CREATE TABLE IF NOT EXISTS ConversationMeta (
    Key         TEXT PRIMARY KEY,
    Value       TEXT NOT NULL,
    UpdatedAt   TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Seed conversation metadata
INSERT OR IGNORE INTO ConversationMeta (Key, Value) VALUES
    ('title', 'New Conversation'),
    ('model', 'gpt-4'),
    ('temperature', '0.7'),
    ('maxTokens', '4096'),
    ('systemPrompt', '');
```

---

## 6.7 Migration Runner

### MigrationRunner Interface
```go
type MigrationRunner interface {
    // Run all pending migrations
    Migrate(ctx context.Context, db *sql.DB, dbType string) error
    
    // Rollback last N migrations
    Rollback(ctx context.Context, db *sql.DB, count int) error
    
    // Get current version
    Version(ctx context.Context, db *sql.DB) (string, error)
    
    // List pending migrations
    Pending(ctx context.Context, db *sql.DB, dbType string) ([]Migration, error)
}

type Migration struct {
    Version     string
    Name        string
    Checksum    string
    SQL         string
    AppliedAt   *time.Time
}
```

### Migration Execution
```go
func (r *Runner) Migrate(ctx context.Context, db *sql.DB, dbType string) error {
    migrations, err := r.loadMigrations(dbType)
    if err != nil {
        return errors.Wrap(err, 9001, "failed to load migrations")
    }
    
    applied, err := r.getAppliedMigrations(ctx, db)
    if err != nil {
        return errors.Wrap(err, 9002, "failed to get applied migrations")
    }
    
    for _, m := range migrations {
        if applied[m.Version] {
            continue
        }
        
        start := time.Now()
        if err := r.executeMigration(ctx, db, m); err != nil {
            return errors.Wrap(err, 9003, "migration failed: "+m.Version)
        }
        
        duration := time.Since(start).Milliseconds()
        if err := r.recordMigration(ctx, db, m, duration); err != nil {
            return errors.Wrap(err, 9004, "failed to record migration")
        }
        
        log.Info("migration applied",
            "version", m.Version,
            "name", m.Name,
            "duration_ms", duration)
    }
    
    return nil
}
```

---

## 6.8 Database Initialization

### InitializeProjectDatabases
```go
func InitializeProjectDatabases(projectId, rootPath string) error {
    projectDir := filepath.Join(rootPath, "projects", projectId)
    
    // Create directory structure
    dirs := []string{
        projectDir,
        filepath.Join(projectDir, "conversations"),
    }
    for _, dir := range dirs {
        if err := os.MkdirAll(dir, 0755); err != nil {
            return errors.Wrap(err, 9010, "failed to create directory: "+dir)
        }
    }
    
    // Initialize per-project databases
    databases := map[string]string{
        "project": filepath.Join(projectDir, "project.db"),
        "history": filepath.Join(projectDir, "history.db"),
        "search":  filepath.Join(projectDir, "search.db"),
    }
    
    runner := NewMigrationRunner()
    for dbType, dbPath := range databases {
        db, err := sql.Open("sqlite3", dbPath+"?_journal_mode=WAL")
        if err != nil {
            return errors.Wrap(err, 9011, "failed to open "+dbType)
        }
        defer db.Close()
        
        if err := runner.Migrate(context.Background(), db, dbType); err != nil {
            return errors.Wrap(err, 9012, "failed to migrate "+dbType)
        }
    }
    
    return nil
}
```

---

## 6.9 Error Codes

| Code | Error | Description |
|------|-------|-------------|
| 9001 | MIGRATION_LOAD_FAILED | Failed to load migration files |
| 9002 | MIGRATION_HISTORY_FAILED | Failed to read migration history |
| 9003 | MIGRATION_EXEC_FAILED | Migration SQL execution failed |
| 9004 | MIGRATION_RECORD_FAILED | Failed to record applied migration |
| 9005 | MIGRATION_CHECKSUM_MISMATCH | Migration file modified after application |
| 9010 | DB_DIRECTORY_FAILED | Failed to create database directory |
| 9011 | DB_OPEN_FAILED | Failed to open database connection |
| 9012 | DB_INIT_FAILED | Failed to initialize database |
| 9020 | ROLLBACK_FAILED | Migration rollback failed |
| 9021 | ROLLBACK_NOT_SUPPORTED | Migration does not support rollback |

---

## 6.10 Acceptance Criteria

### Migration System
- [ ] All databases use versioned migrations
- [ ] Checksums prevent modified migrations from re-running
- [ ] Rollback supported for reversible migrations
- [ ] Migration history tracked per database

### Schema Enforcement
- [ ] PascalCase enforced for tables and columns
- [ ] UUID primary keys for all entities
- [ ] ISO8601 timestamps with UTC timezone
- [ ] Foreign key constraints enabled

### Initialization
- [ ] Project databases created atomically
- [ ] Directory structure validated before creation
- [ ] WAL mode enabled for all databases
- [ ] Indexes created for common query patterns

### Cross-Service Consistency
- [ ] Shared types use identical schema definitions
- [ ] Error codes don't overlap between services
- [ ] No cross-database JOINs in any service
