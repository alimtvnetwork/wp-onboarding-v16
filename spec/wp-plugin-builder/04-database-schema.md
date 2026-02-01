# Database Schema

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-02-01  

---

## Overview

Dual-database architecture: a root database for global metadata and per-project databases for RAG vectors and file tracking.

**Cross-References:**
- [Core Architecture](./01-core-architecture.md)
- [RAG System](./05-rag-system.md)
- [Project Management](./06-project-management.md)

---

## Architecture

```
~/.wpb/
├── wpb.sqlite              # Root database
├── projects/
│   ├── exam-manager.sqlite # Project database
│   ├── quiz-maker.sqlite   # Project database
│   └── form-builder.sqlite # Project database
└── backups/
    └── ...
```

---

## Root Database Schema

### projects

Tracks all created projects.

```sql
CREATE TABLE projects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    slug TEXT NOT NULL UNIQUE,
    author TEXT,
    author_email TEXT,
    website TEXT,
    description TEXT,
    text_domain TEXT,
    namespace TEXT,
    version TEXT DEFAULT '1.0.0',
    db_path TEXT NOT NULL,
    output_path TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_generated_at DATETIME,
    generation_count INTEGER DEFAULT 0
);

CREATE INDEX idx_projects_slug ON projects(slug);
CREATE INDEX idx_projects_created ON projects(created_at);
```

**GORM Model:**

```go
type Project struct {
    ID              uint      `gorm:"primaryKey"`
    Name            string    `gorm:"uniqueIndex;not null"`
    Slug            string    `gorm:"uniqueIndex;not null"`
    Author          string
    AuthorEmail     string
    Website         string
    Description     string
    TextDomain      string
    Namespace       string
    Version         string    `gorm:"default:'1.0.0'"`
    DBPath          string    `gorm:"not null"`
    OutputPath      string
    CreatedAt       time.Time
    UpdatedAt       time.Time
    LastGeneratedAt *time.Time
    GenerationCount int       `gorm:"default:0"`
}
```

---

### presets

Global learning presets.

```sql
CREATE TABLE presets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    category TEXT NOT NULL DEFAULT 'general',
    description TEXT,
    source_path TEXT,
    content_hash TEXT,
    chunk_count INTEGER DEFAULT 0,
    is_active BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_presets_category ON presets(category);
CREATE INDEX idx_presets_active ON presets(is_active);
```

**Categories:**
- `core` — Core WordPress plugin standards
- `admin` — Admin panel patterns
- `api` — REST API patterns
- `shortcode` — Shortcode implementations
- `block` — Gutenberg block patterns
- `general` — General best practices

---

### preset_vectors

Vector embeddings for presets (global knowledge).

```sql
CREATE TABLE preset_vectors (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    preset_id INTEGER NOT NULL REFERENCES presets(id) ON DELETE CASCADE,
    chunk_index INTEGER NOT NULL,
    content TEXT NOT NULL,
    embedding BLOB NOT NULL,
    metadata JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(preset_id, chunk_index)
);

CREATE INDEX idx_preset_vectors_preset ON preset_vectors(preset_id);
```

---

### settings

Global settings storage.

```sql
CREATE TABLE settings (
    key TEXT PRIMARY KEY,
    value TEXT,
    type TEXT DEFAULT 'string',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

---

### generation_log

Global generation history.

```sql
CREATE TABLE generation_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    started_at DATETIME NOT NULL,
    completed_at DATETIME,
    status TEXT NOT NULL DEFAULT 'running',
    spec_path TEXT,
    files_generated INTEGER DEFAULT 0,
    errors JSON,
    metadata JSON
);

CREATE INDEX idx_genlog_project ON generation_log(project_id);
CREATE INDEX idx_genlog_status ON generation_log(status);
```

---

## Project Database Schema

Each project has its own SQLite database (`{project-slug}.sqlite`).

### project_files

Tracks all files in the project.

```sql
CREATE TABLE project_files (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    path TEXT NOT NULL UNIQUE,
    relative_path TEXT NOT NULL,
    file_type TEXT NOT NULL,
    content_hash TEXT,
    size_bytes INTEGER,
    is_generated BOOLEAN DEFAULT 0,
    generation_id INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_files_path ON project_files(path);
CREATE INDEX idx_files_type ON project_files(file_type);
CREATE INDEX idx_files_generated ON project_files(is_generated);
```

**File Types:**
- `php` — PHP source files
- `css` — Stylesheets
- `js` — JavaScript files
- `json` — JSON configuration
- `md` — Documentation
- `txt` — Text files

---

### rag_vectors

Project-specific RAG vectors.

```sql
CREATE TABLE rag_vectors (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    source_type TEXT NOT NULL,
    source_id TEXT NOT NULL,
    chunk_index INTEGER NOT NULL,
    content TEXT NOT NULL,
    embedding BLOB NOT NULL,
    metadata JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(source_type, source_id, chunk_index)
);

CREATE INDEX idx_rag_source ON rag_vectors(source_type, source_id);
```

**Source Types:**
- `file` — From project file
- `spec` — From specification
- `preset` — Copied from global preset
- `generated` — From generated code

---

### specifications

Imported specifications.

```sql
CREATE TABLE specifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    path TEXT NOT NULL,
    content TEXT NOT NULL,
    content_hash TEXT,
    format TEXT DEFAULT 'markdown',
    is_active BOOLEAN DEFAULT 1,
    imported_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_specs_name ON specifications(name);
CREATE INDEX idx_specs_active ON specifications(is_active);
```

---

### generation_history

Detailed generation history for this project.

```sql
CREATE TABLE generation_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    spec_id INTEGER REFERENCES specifications(id),
    prompt TEXT NOT NULL,
    context_used JSON,
    output TEXT NOT NULL,
    model TEXT,
    tokens_in INTEGER,
    tokens_out INTEGER,
    duration_ms INTEGER,
    validation_result JSON,
    status TEXT DEFAULT 'success',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_genhist_spec ON generation_history(spec_id);
CREATE INDEX idx_genhist_status ON generation_history(status);
CREATE INDEX idx_genhist_created ON generation_history(created_at);
```

---

### file_generations

Links generated files to generation events.

```sql
CREATE TABLE file_generations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    file_id INTEGER NOT NULL REFERENCES project_files(id) ON DELETE CASCADE,
    history_id INTEGER NOT NULL REFERENCES generation_history(id) ON DELETE CASCADE,
    action TEXT NOT NULL,
    previous_hash TEXT,
    new_hash TEXT,
    diff TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_filegen_file ON file_generations(file_id);
CREATE INDEX idx_filegen_history ON file_generations(history_id);
```

**Actions:**
- `created` — New file
- `updated` — Modified existing
- `skipped` — No changes needed
- `backup` — Backed up before update

---

## Entity Relationship Diagram

```
ROOT DATABASE (wpb.sqlite)
┌─────────────┐     ┌─────────────┐     ┌─────────────────┐
│  projects   │     │   presets   │     │ preset_vectors  │
├─────────────┤     ├─────────────┤     ├─────────────────┤
│ id          │     │ id          │◄────│ preset_id       │
│ name        │     │ name        │     │ chunk_index     │
│ slug        │     │ category    │     │ embedding       │
│ db_path ────┼──┐  │ content_hash│     └─────────────────┘
└─────────────┘  │  └─────────────┘
                 │
                 │  ┌─────────────────────────────────────┐
                 │  │        PROJECT DATABASE              │
                 │  │    ({project-slug}.sqlite)           │
                 ▼  ├─────────────────────────────────────┤
┌────────────────┐  │  ┌──────────────┐  ┌─────────────┐  │
│ {slug}.sqlite  │──│  │project_files │  │ rag_vectors │  │
└────────────────┘  │  └──────────────┘  └─────────────┘  │
                    │  ┌──────────────┐  ┌─────────────┐  │
                    │  │specifications│  │gen_history  │  │
                    │  └──────────────┘  └─────────────┘  │
                    └─────────────────────────────────────┘
```

---

## Vector Storage

Using SQLite with vector extension (sqlite-vec or similar):

```go
type VectorStore struct {
    db *gorm.DB
}

func (v *VectorStore) Insert(embedding []float32, content string, meta map[string]any) error {
    // Serialize float32 slice to bytes
    blob := serializeVector(embedding)
    
    return v.db.Create(&RAGVector{
        Content:   content,
        Embedding: blob,
        Metadata:  meta,
    }).Error
}

func (v *VectorStore) Search(query []float32, topK int) ([]RAGResult, error) {
    // Use sqlite-vec for similarity search
    // SELECT * FROM rag_vectors 
    // ORDER BY vec_distance_cosine(embedding, ?) 
    // LIMIT ?
}
```

---

## Migrations

```go
func RunMigrations(db *gorm.DB, dbType string) error {
    switch dbType {
    case "root":
        return db.AutoMigrate(
            &Project{},
            &Preset{},
            &PresetVector{},
            &Setting{},
            &GenerationLog{},
        )
    case "project":
        return db.AutoMigrate(
            &ProjectFile{},
            &RAGVector{},
            &Specification{},
            &GenerationHistory{},
            &FileGeneration{},
        )
    }
    return errors.New(10201, "unknown database type")
}
```

---

## See Also

- [RAG System](./05-rag-system.md)
- [Project Management](./06-project-management.md)
- [Error Handling](./10-error-handling.md)
